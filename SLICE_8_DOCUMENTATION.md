# Slice 8 — Teacher Assignments
## Development and Verification Report

**Project:** GES-ALEMS
**Repository:** `WELLORD-PAULAR/ges-alems`
**Branch:** `slice-8-assignments` (built on `slice-5-sections`)
**Lane:** C
**Date:** November 26, 2026
**Status:** Development, automated testing and the manual click-through are complete. Parity check pending.

---

## 1. Overview

Slice 8 gives administrators a page to manage teacher assignments: which teacher teaches which subject to which section, in which term of which school year. They can add, edit, filter and delete assignments, including deleting all of one teacher's or one section's assignments at once.

It replaces the old `teacher_assignments` page. It adds no database migration, because the `teacher_assignments` table and its unique index were created in Slice 1. It brings together the work of Slices 5, 6 and 7: an assignment points at a section, a subject and a teacher. It depends on Slice 2 for the `auth` and `role:admin` middleware, and shares `routes/admin.php` and the sidebar with the other Lane C slices.

The slice also unblocks Lane B's teacher dashboard (Slice 9), which needs real assignments to show a teacher only their own sections.

## 2. Implemented Features

### Assignment management (admin only)
- The page `/admin/assignments` has one form for adding and editing and a table of assignments, with filters above it.
- Each assignment has a teacher, a subject, a section, a school year and a term.
- The form's **Term** choice narrows the **Subject** list to the subjects offered in that term.
- The form's **School year** choice narrows the **Section** list to that year's sections.
- Editing reuses the same form and replaces all of the assignment's details.
- Delete asks for confirmation in the browser, and a toast is shown after each action.

### List and filters
- The list shows one school year at a time, defaulting to the current school year from `config/school.php`.
- It can be narrowed by term, teacher, section and subject. The filters combine.
- An unknown school year falls back to the current one. An unknown term, including Summer, is ignored.
- Assignments are ordered by term, then Kindergarten up to Grade 6, then section, teacher and subject.
- The section filter lists only the sections of the chosen school year.

### Validation rules
- `teacher_id`, `subject_id` and `section_id`: required, and each must exist.
- `school_year`: required, `YYYY-YYYY` with two consecutive years (`2026-2027` is accepted; `2026`, `2026-27` and `2026-2028` are rejected).
- `term`: required and must be 1, 2 or 3. Summer (4) is rejected.

Once those pass, three more rules are checked:
- **The section must belong to the assignment's school year.** (Added by this slice.)
- **The subject must be offered in the assignment's term.** A subject offered in every term fits any term.
- **The same five-part combination cannot exist twice** (teacher, subject, section, school year, term). The message is: "That teacher already has this subject in this section for that school year and term." When editing, an assignment is not counted as a duplicate of itself.

If two people save the same assignment at the same moment, the database's unique index stops the second one and it gets the same friendly message.

### Deleting assignments
There are three ways, which replace the old broken bulk deletes:
- **One assignment:** the Delete button on its row.
- **All of one teacher's assignments in one school year:** choose a teacher in the filters, then use the delete-all button. The school year is always sent, so a click cannot erase a teacher's earlier years.
- **All of one section's assignments:** choose a section in the filters, then use the delete-all button. A section belongs to a single school year, so that is the scope.

The delete-all buttons show how many assignments will go, ask for confirmation, and are disabled when there is nothing to delete. A toast reports how many were deleted.

Clearing a teacher's or a section's assignments also lets that teacher or section be deleted afterwards (Slices 7 and 5 refuse to delete them while assignments exist).

### Access control
- All routes sit inside the admin route group, so they require a logged-in user with the Admin role.
- Guests are redirected to login. Teachers receive HTTP 403.
- The form request also checks that the user is an admin, as a second layer.
- The sidebar shows the "Assignments" link to admins only.

### Reusable additions
- `TeacherAssignment` scopes that port the old list queries and combine freely: `forSchoolYear()`, `forTerm()`, `forTeacher()`, `forSubject()` and `forSection()`.
- `App\Rules\SchoolYear`: a validation rule for a `YYYY-YYYY` school year with consecutive years. The Sections form request has its own copy of this check, which could use the rule later.

## 3. Routes

All routes are defined in `routes/admin.php`, inside the `auth` + `role:admin` group (prefix `/admin`, names starting `admin.`).

| Method | URI | Name | Action |
|---|---|---|---|
| GET | `/admin/assignments` | `admin.assignments.index` | List with filters, and the form |
| POST | `/admin/assignments` | `admin.assignments.store` | Create an assignment |
| PUT/PATCH | `/admin/assignments/{assignment}` | `admin.assignments.update` | Update an assignment |
| DELETE | `/admin/assignments/{assignment}` | `admin.assignments.destroy` | Delete one assignment |
| DELETE | `/admin/teachers/{teacher}/assignments` | `admin.teachers.assignments.destroy` | Delete a teacher's assignments for one school year (`school_year` is required) |
| DELETE | `/admin/sections/{section}/assignments` | `admin.sections.assignments.destroy` | Delete all of a section's assignments |

The list accepts the query parameters `school_year`, `term`, `teacher_id`, `section_id` and `subject_id`.

## 4. Differences From the Old App

Behaviour that matches the old app: admin only, the same five fields, the same duplicate rule, term 1 to 3, the `YYYY-YYYY` school year format, and delete asks for confirmation.

These behaviours are intentionally different:

| Situation | Old app | New app | Reason |
|---|---|---|---|
| Which school year the list shows | Asked for `date('Y')` (for example `2026`) while assignments store `2026-2027`, so nothing matched | The current school year from config, with a year filter | The checklist warns about this bug |
| Filters | None on the page (the handlers had by-teacher, by-section, by-subject, by-year and by-year-and-term queries) | Year, term, teacher, section and subject filters | Ports the old queries |
| Checks when editing | Only checked the term, and only for fields that were sent | All rules apply on edit, including duplicates, section year and school year format | The old edit skipped them |
| Delete all of a teacher's assignments | Broken (used an undefined `$deletedCount` and never committed) | Works, limited to one school year, with a count message | The checklist asks to fix it |
| Delete all of a section's assignments | Broken in the same way | Works, with a count message | The checklist asks to fix it |
| Section from another school year | Allowed | Rejected | The checklist adds this rule |
| Subject offered in a different term | Allowed | Rejected | The subject's offered term is now enforced |
| Non-admin opens the page | Redirected away | HTTP 403 | Slice 2 role middleware |

## 5. Database

No migration was added. The slice uses the Slice 1 table `teacher_assignments`:

- `id`, `teacher_id`, `subject_id`, `section_id`, `school_year` (9 characters), `term` (tiny integer), `legacy_key` (nullable, unique), timestamps.
- The unique index `teacher_assignments_unique` covers `(teacher_id, subject_id, section_id, school_year, term)`. It is the rule the old handler checked by hand.
- An index on `(school_year, term)` supports the list filters.
- The foreign keys to teachers, subjects and sections all use `cascadeOnDelete`.

**Important:** the cascades mean a delete made outside the admin pages (for example from Tinker or SQL) would silently remove assignments along with the teacher, subject or section. Slices 5, 6 and 7 guard against that in their controllers. Changing these foreign keys to `restrictOnDelete` in a new migration would add a second layer of protection. This is a decision for Lane A.

## 6. Important Files

### New
- `app/Http/Controllers/Admin/AssignmentController.php`
- `app/Http/Requests/Admin/AssignmentRequest.php` (used for both create and update)
- `app/Rules/SchoolYear.php`
- `resources/js/pages/admin/assignments/Index.vue`
- `tests/Feature/Admin/AssignmentManagementTest.php`

### Modified
- `app/Models/TeacherAssignment.php` (the list scopes)
- `routes/admin.php` (added the assignment routes and the two bulk-delete routes)
- `resources/js/components/AppSidebar.vue` (admin-only "Assignments" link)

### Page data
The controller passes the Inertia page `admin/assignments/Index` these props:

- `assignments`: a list of `{ id, teacher_id, subject_id, section_id, teacher_name, subject_code, subject_name, section_name, grade_label, school_year, term, term_label }`.
- `filters`: the active `school_year`, `term`, `teacher_id`, `section_id` and `subject_id`.
- `schoolYears`, `terms` (terms 1 to 3 with their labels), `teachers`, `subjects` (with their offered term) and `sections` (every year, narrowed in the browser).
- `teacherTotal`: how many assignments the delete-all-for-teacher button would remove (the chosen teacher, the chosen school year, all terms), or null when no teacher is chosen.
- `sectionTotal`: how many assignments the delete-all-for-section button would remove, or null when no section is chosen.

## 7. Tests

Test file: `tests/Feature/Admin/AssignmentManagementTest.php` (28 tests, which run as 39 cases because four of them use datasets).

| Area | What is checked |
|---|---|
| Access | Guests are redirected to login. Teachers get 403 on every route, including both bulk deletes, and nothing is changed. |
| Listing | Current year by default. Ordered by term, grade, section and teacher. Filters by year, term, teacher, section and subject, alone and combined. Unknown years and terms fall back. Terms 1 to 3 only. The teacher and section totals are correct. |
| Create | An assignment is created. Every field is required. The teacher, subject and section must exist. |
| Terms and years | Terms 1, 2 and 3 are accepted. Summer, 0, 5 and text are rejected. Invalid school years are rejected. |
| Section year | A section from another school year is rejected, but accepted for its own year. |
| Subject term | A subject offered only in one term is rejected in other terms and accepted in its own. A subject offered in every term fits any term. |
| Duplicates | The same assignment cannot be created twice, with the friendly message. Changing any one part (term, teacher, subject, section or year) makes it a different assignment. The database's unique index refuses a duplicate even if the form check is skipped. |
| Update | An assignment can be changed. Saving it unchanged is not a duplicate of itself. Changing it into another assignment is rejected. The section-year and subject-term rules still apply. |
| Delete one | The assignment is removed and its teacher, subject and section remain. |
| Delete by teacher | Only that teacher's assignments in that school year are removed. Earlier years and other teachers are untouched. A missing or invalid school year is rejected. Nothing to delete is harmless. |
| Delete by section | Only that section's assignments are removed. Other sections and the section itself remain. |
| Integration | Clearing a teacher's and a section's assignments lets the teacher and the section be deleted (they were blocked before). |
| Model | The list scopes combine like the old queries. |

## 8. Verification Results

| Check | Result |
|---|---|
| Slice 8 tests | 39 passed, 0 failed |
| Full test suite | 271 passed (1072 assertions), 0 failed |
| `npm run build` | Passed |
| `npm run types:check` | Passed (no errors) |
| PHPStan (Slice 8 files) | No errors |
| Pint (19 files) | Passed |

### Manual click-through (`/admin/assignments`, as admin)
- Choosing a term narrows the Subject list: confirmed.
- Changing the School year in the form changes the available sections: confirmed.
- Adding the same assignment twice shows the friendly duplicate message: confirmed.
- Choosing a teacher or a section in the filters and using the delete-all button works: confirmed.
- A teacher account is refused with 403 on `/admin/assignments`: confirmed (also covered by an automated test).

## 9. Design Decisions

- **The subject must be offered in the term.** This is not in the checklist, but it is what a subject's offered term means. The seeded subjects are offered in every term, so it blocks nothing today. It is one check in `AssignmentRequest` if the school prefers to drop it.
- **Delete by teacher is limited to one school year,** unlike the old code, which erased every year. This protects a teacher's history from a single click.
- **Section choices follow the school year, and subject choices follow the term,** in the browser, so the form guides people to valid combinations. The server still checks every rule.
- **No pagination.** One school year's list is at most a few hundred rows.
- **`SchoolYear` is a reusable rule.** The Sections form request still has its own copy of the same check, which can switch to it in a later clean-up.

## 10. Open Items

- **Parity check.** Compare with the old `teacher_assignments` page using the table in section 4. The old database dump has no `teacher_class_roster` table, so compare against the old code's behaviour. The old bulk deletes never worked, so only the single delete and the duplicate rule have old behaviour to match.
- **Legacy data.** There are no legacy assignments to import. Assignments are entered by hand at launch, after teachers, subjects and sections exist.
- **Database-level protection** (section 5).
- **Checklist.** Tick the Slice 8 boxes when the PR is merged.
- **Shared files.** `AppSidebar.vue` and `routes/admin.php` are shared with the other slices, so expect small merge conflicts.

## 11. Final Status

Slice 8's implementation, automated testing and manual click-through are complete. The following have been verified:

- Admin-only access, with guests redirected and teachers refused.
- Create, edit and delete of assignments.
- The year, term, teacher, section and subject filters.
- Term 1 to 3 and school year format validation.
- The section-year, subject-term and duplicate rules, with a friendly duplicate message.
- Deleting by assignment, by teacher (one school year) and by section.
- A full test suite of 271 passing tests, a clean build, type check, PHPStan and Pint.

**Remaining delivery steps:** parity check, pull request (stacked on `slice-5-sections`), review and merge.

---

*End of Slice 8 documentation report.*
