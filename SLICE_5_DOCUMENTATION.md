# Slice 5 — Sections and Sectioning
## Development and Verification Report

**Project:** GES-ALEMS
**Repository:** `WELLORD-PAULAR/ges-alems`
**Branch:** `slice-5-sections` (built on `slice-7-teachers`)
**Lane:** C
**Date:** November 26, 2026
**Status:** Development and automated testing complete. Static checks and the manual click-through are pending (see section 9).

---

## 1. Overview

Slice 5 gives administrators two screens:

1. **Sections** (`/admin/sections`): create, edit, list and delete sections. A section has a name, a grade level and a school year.
2. **Sectioning** (`/admin/sectioning`): place students into sections, one student at a time or many at once.

It replaces the old `section_management` and `sectioning` pages. It adds no database migration, because the `sections` table and the `enrollments.section_id` column were created in Slice 1. It depends on Slice 2 for the `auth` and `role:admin` middleware, and shares `routes/admin.php` and the sidebar with Slices 6 and 7.

The slice also adds `SectionAssigner`, the single class that decides whether a student may be placed in a section. Slice 8 (teacher assignments) and Slice 10 (masterlist) rely on the sections and placements it manages.

## 2. Implemented Features

### Section management (admin only)
- The page `/admin/sections` has one form for adding and editing and a table of sections.
- A section has a name, a grade level (Kindergarten, then Grade 1 to Grade 6) and a school year in `YYYY-YYYY` form.
- The school year dropdown offers last year through two years ahead, taken from `config/school.php`.
- The list is filtered by school year, defaulting to the current one, with an "All years" option.
- Sections are ordered newest school year first, then Kindergarten up to Grade 6, then by name.
- Each row shows how many students and teacher assignments use the section.
- Delete asks for confirmation in the browser, and a toast is shown after each action.

### Section validation rules
- `name`: required, up to 50 characters, unique within its school year (the same name may be used in another year).
- `grade_level`: required, one of `K`, `1`, `2`, `3`, `4`, `5`, `6`.
- `school_year`: required, `YYYY-YYYY` with two consecutive years. `2026-2027` is accepted. `2026`, `2026-27`, `2026-2028` and `2027-2026` are rejected.

### Protecting sections that are in use
- A section that has students or teacher assignments cannot be deleted. The Delete button is disabled with a tooltip, and the server enforces the same rule.
- While students or teacher assignments use a section, its grade level and school year cannot change. Moving it would leave those students and assignments pointing at the wrong grade or year. Renaming is still allowed.

### Sectioning screen (admin only)
- Grade tabs run from Kindergarten to Grade 6, each showing how many students are enrolled in that grade for the selected school year.
- The school year defaults to the current one and can be changed.
- The status filter offers all students or any one of the seven verification statuses.
- Search by name or LRN runs in the browser, so unsaved changes are not lost while searching.
- Students are sorted by last name, then first name.
- Each student row has a section dropdown listing only the sections for that grade and school year, plus "Unsectioned".
- Changes are kept as unsaved until saved. They survive switching grade tab, status filter, or school year, so one **Save all** can cover several grades.
- A row with an unsaved change shows a **Save** button that saves just that student.
- **Save all (N)** saves every unsaved change in one request. **Discard** drops them.
- Rows that could not be saved are marked in red with the reason, and the other rows are saved.

### Assignment rules (`SectionAssigner`)
Both the single and the bulk path use the same rules:
- The enrollment must exist.
- The section must exist, unless the section is null, which removes the student from their section.
- The section's grade level must match the student's grade level.
- The section's school year must match the enrollment's school year.
- Any verification status may be sectioned, as in the old app.

A rejected row says why, for example: "4-Charity is a Grade 4 section, but this student is in Grade 3."

### Access control
- All routes sit inside the admin route group, so they require a logged-in user with the Admin role.
- Guests are redirected to login. Teachers receive HTTP 403, including on the bulk JSON endpoint.
- The form requests also check that the user is an admin, as a second layer.
- The sidebar shows the "Sections" and "Sectioning" links to admins only.

### Reusable additions
- `Section::accepts(Enrollment)`: true when the section matches the enrollment's grade level and school year.
- `App\Services\SectionAssigner`: `assign()` for one student and `assignMany()` for a list.

## 3. Routes

All routes are defined in `routes/admin.php`, inside the `auth` + `role:admin` group (prefix `/admin`, names starting `admin.`).

| Method | URI | Name | Action |
|---|---|---|---|
| GET | `/admin/sections` | `admin.sections.index` | List sections and show the form |
| POST | `/admin/sections` | `admin.sections.store` | Create a section |
| PUT/PATCH | `/admin/sections/{section}` | `admin.sections.update` | Update a section |
| DELETE | `/admin/sections/{section}` | `admin.sections.destroy` | Delete a section |
| GET | `/admin/sectioning` | `admin.sectioning.index` | Sectioning screen |
| PUT | `/admin/sectioning/{enrollment}` | `admin.sectioning.assign` | Assign one student |
| POST | `/admin/sectioning/bulk` | `admin.sectioning.bulk` | Assign many students |

The sectioning screen accepts the query parameters `school_year`, `grade` and `status`. An unknown value falls back to the default (the current school year, Kindergarten, and no status filter).

## 4. Bulk Assignment Endpoint

`POST /admin/sectioning/bulk` takes JSON and returns JSON.

**Request**
```json
{
  "assignments": [
    { "enrollment_id": 12, "section_id": 3 },
    { "enrollment_id": 13, "section_id": null }
  ]
}
```
- `assignments` is required, with 1 to 500 rows.
- Each row needs `enrollment_id` (an integer, not repeated) and `section_id` (an integer or null, and the key must be present, so a missing key is never read as "remove").
- `null` removes the student from their section.

**Response (HTTP 200)**
```json
{
  "saved": 1,
  "failed": 1,
  "results": [
    { "enrollment_id": 12, "ok": true, "message": null },
    { "enrollment_id": 13, "ok": false, "message": "That student enrollment was not found." }
  ]
}
```
- There is one result per row, in the order sent.
- The response is HTTP 200 even when some or all rows fail. Check `failed`.
- HTTP 422 is returned only when the request itself is malformed (see the rules above).
- The rows are applied in one database transaction, but a failed row never undoes the others.

## 5. Differences From the Old App

Behaviour that matches the old app: admin only, grade tabs from K to 6, students sorted Kindergarten first then by grade and last name, search by name or LRN, a status filter, and "Save all" for bulk changes.

These behaviours are intentionally different:

| Situation | Old app | New app | Reason |
|---|---|---|---|
| Assign a section of the wrong grade | Allowed (only checked that the section name existed) | Rejected | The checklist rule |
| Assign a section from another school year | No school year at all | Rejected | Sections and enrollments now carry a school year |
| Bulk assign with an invalid row | Invalid rows ignored; always reported success | A result for every row, with the reason | The checklist asks for a result per row |
| Where the sections come from | A hardcoded list in the code | Real records you can add, rename and delete | Section CRUD |
| How a student's section is stored | A name string on the enrollment | `section_id` linking to the section | Slice 1 schema |
| Grade level spellings | Three different forms in different screens | One set (`K`, `1` to `6`) | Slice 1 `GradeLevel` enum |
| Remove a student from a section | Not possible | Allowed (choose "Unsectioned") | Added for convenience |
| Delete a section in use | Deleted | Blocked | The checklist rule |
| Change grade or year of a section in use | Allowed | Blocked | Avoids students in the wrong grade |
| Non-admin opens the page | Redirected away | HTTP 403 | Slice 2 role middleware |

## 6. Database

No migration was added. The slice uses these Slice 1 tables:

- `sections`: `id`, `name`, `grade_level` (2 characters), `school_year` (9 characters), `legacy_key`, timestamps. The pair `(name, school_year)` is unique.
- `enrollments`: includes `school_year`, `grade_level`, `section_id` (nullable), `verification` and `lrn`. Indexed on `(school_year, grade_level)` and `(school_year, section_id)`.
- `teacher_assignments.section_id` references `sections.id` with `cascadeOnDelete`.
- `enrollments.section_id` references `sections.id` with `nullOnDelete`.

**Important:** these foreign keys mean a delete made outside this page would silently remove a section's teacher assignments and silently un-section its students. The "cannot delete" and "cannot change grade or year" rules are therefore enforced in the controller, not by the database. Changing both foreign keys to `restrictOnDelete` in a new migration would add a second layer of protection. This is a decision for Lane A.

## 7. Important Files

### New
- `app/Services/SectionAssigner.php`
- `app/Http/Controllers/Admin/SectionController.php`
- `app/Http/Controllers/Admin/SectioningController.php`
- `app/Http/Requests/Admin/SectionRequest.php` (used for both create and update)
- `app/Http/Requests/Admin/AssignSectionRequest.php`
- `app/Http/Requests/Admin/BulkAssignSectionsRequest.php`
- `resources/js/pages/admin/sections/Index.vue`
- `resources/js/pages/admin/sectioning/Index.vue`
- `tests/Feature/Admin/SectionManagementTest.php`
- `tests/Feature/Admin/SectioningTest.php`

### Modified
- `app/Models/Section.php` (`accepts()`)
- `routes/admin.php` (added the sections and sectioning routes)
- `resources/js/components/AppSidebar.vue` (admin-only "Sections" and "Sectioning" links)

### Page data
- `admin/sections/Index`: `sections` (`id`, `name`, `grade_level`, `grade_label`, `school_year`, `enrollments_count`, `assignments_count`), `grades`, `schoolYears`, `currentSchoolYear`, `filter`.
- `admin/sectioning/Index`: `schoolYear`, `schoolYears`, `grade`, `status`, `grades`, `statuses`, `students` (`enrollment_id`, `full_name`, `lrn`, `verification`, `section_id`), `sections` (`id`, `name`) and `gradeCounts`.

### How the screen talks to the server
- Single assignment and the other actions use normal Inertia requests and redirects.
- Save all uses Inertia's `useHttp`, which sends JSON to the bulk endpoint and returns the per-row results without reloading the page. The page then reloads only the students and grade counts.

## 8. Tests

Two test files: 32 test functions, which run as 48 cases because four of them use datasets.

**`SectionManagementTest.php` (19 tests, 32 cases)**

| Area | What is checked |
|---|---|
| Access | Guests are redirected to login. Teachers get 403 on every section and sectioning route, and nothing is changed. |
| Listing | Current year by default, Kindergarten first then by name. "All years" lists newest first. Student and assignment counts are shown. |
| Create | A section is created. Name, grade and year are required. A name is unique within its year only. Unknown grades are rejected. Invalid school years are rejected. Every grade from K to 6 is accepted. |
| Update | A section can be renamed and keep its own name. Another section's name is rejected. Grade and year can change while unused. They are locked while students or teacher assignments use the section, while renaming still works. |
| Delete | An unused section is deleted. A section with students is kept, and its students keep their section. A section with teacher assignments is kept. |

**`SectioningTest.php` (13 tests, 16 cases)**

| Area | What is checked |
|---|---|
| Screen | Opens on Kindergarten, sorted by last then first name, current year only. A grade tab shows only that grade, and an unknown grade falls back to Kindergarten. The status filter works and an unknown status is ignored. Only the sections for that grade and year are offered. Tab counts are correct per school year. |
| Assign one | A matching section is accepted. A section of another grade or school year is rejected and nothing changes. A student can be removed from a section. A missing section, or a missing `section_id`, is rejected. |
| Bulk | All rows are saved and reported. With one bad row (wrong grade), a missing enrollment and a removal in the same request, the good rows are saved, the bad rows are reported with reasons, and results come back in order. Malformed requests (no rows, no key, missing section id, a repeated student) return 422. |

**Result:** both files pass: 32 passed in `SectionManagementTest.php` and 16 passed in `SectioningTest.php`, 48 in total, 0 failures.

## 9. Verification Status

| Check | Result |
|---|---|
| Slice 5 tests | 48 passed, 0 failed |
| `npm run types:check` | Passed (no errors) |
| Full test suite | _not yet recorded_ (expected 232 with Slices 2, 6 and 7 applied) |
| Pint (Slice 5 files) | _re-run needed after fixing the two request files (see 10.1)_ |
| PHPStan (Slice 5 files) | _not yet recorded_ |
| Manual click-through of both pages | _not yet recorded_ |

## 10. Setup and Troubleshooting Notes

### 10.1 Pint style failure in two request files
**Symptom:** Pint reported `fully_qualified_strict_types, ordered_imports` for `AssignSectionRequest.php` and `BulkAssignSectionsRequest.php`.
**Cause:** the class docblocks referred to `\App\Services\SectionAssigner` with a full name instead of an import.
**Fix:** run `vendor\bin\pint` (without `--test`) on those two files. It adds the `use` line and shortens the reference. Behaviour is unchanged.

### 10.2 A PHP crash (`zend_mm_heap corrupted`) was seen once
It was reported once while checking the slice. The cause was not identified, and the Slice 5 tests pass. If it returns, rerun with the JIT turned off (`php -d opcache.jit=off vendor\bin\pest`) and note which file was running.

### 10.3 The old database cannot show real section data
The old app expected a `sections` table and an `enrollment2.section` column that are missing from the database dump (see the Slice 6 notes). There is no legacy section data to import. The default sections come from the Slice 1 seeder, which was taken from the old hardcoded list.

## 11. Design Decisions

- **Unassigning is allowed.** Choosing "Unsectioned" removes a student from their section. The old app could not do this.
- **Search runs in the browser,** while grade, status and school year are applied by the server. This keeps unsaved changes while searching.
- **Unsaved changes survive tab and filter changes,** so one "Save all" can cover several grades.
- **Any verification status can be sectioned,** matching the old app. Restricting it to verified students is a policy decision.
- **The school year is checked for format and consecutive years,** not against the dropdown's list, so a valid year outside the list (for example a past year) can still be entered.
- **Bulk requests are capped at 500 rows,** which is more than the school's total enrollment.

## 12. Open Items

- **Finish the checks** in section 9: the Pint re-run, PHPStan on the Slice 5 files, the full suite, and a click-through. In the click-through, add a section, assign one student with Save, change two students and use Save all, and provoke one bad row.
- **Parity check.** Compare with the old sectioning screen using section 5. The old screen cannot run on the current dump, so compare against the old code's behaviour.
- **Database-level protection** (section 6).
- **Checklist.** Tick the Slice 5 boxes when the PR is merged.
- **Shared files.** `AppSidebar.vue` and `routes/admin.php` are shared with Slices 6 and 7, so expect small merge conflicts.

## 13. Final Status

Slice 5's implementation and automated testing are complete. The following have been verified:

- Admin-only access, on both the screens and the bulk endpoint.
- Section create, edit and delete, with unique names per school year.
- School year format and consecutive-year validation.
- Sections locked against deletion and grade or year changes while in use.
- Sectioning by grade, status, school year and search.
- Assigning, changing and removing a student's section.
- Grade and school year mismatches rejected.
- Bulk assignment with a result for every row, and bad rows not blocking good ones.
- The Slice 5 test files passing and the TypeScript type check passing.

**Remaining delivery steps:** the checks in section 9, parity check, pull request (stacked on `slice-7-teachers`), review and merge.

---

*End of Slice 5 documentation report.*
