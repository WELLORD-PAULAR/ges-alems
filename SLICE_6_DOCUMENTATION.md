# Slice 6 — Subjects
## Development and Verification Report

**Project:** GES-ALEMS
**Repository:** `WELLORD-PAULAR/ges-alems`
**Branch:** `slice-6-subjects` (built on `slice-2-auth-roles`)
**Lane:** C
**Date:** November 25, 2026
**Status:** Development and automated testing complete. Parity check against the old app pending.

---

## 1. Overview

Slice 6 gives administrators a page to manage academic subjects: add, edit, list and delete them, and choose which term each subject is offered in.

It replaces the old `subject_management` page. It adds no database migration, because the `subjects` table was created in Slice 1. It depends on Slice 2 for the `auth` and `role:admin` middleware.

The slice also adds a reusable `Subject::offeredIn()` query, which Slice 8 (teacher assignments) will use to offer only the subjects taught in a given term.

## 2. Implemented Features

### Subject management (admin only)
- Added the page `/admin/subjects`, with one form for adding and editing and a table of subjects.
- Subjects are listed alphabetically by name.
- Each subject has a code, a name and an optional offered term.
- A blank term means the subject is offered in every term, shown as "All terms (year-round)".
- Term labels are read from `config/school.php`, so they are defined in one place.
- Editing a subject reuses the same form. Cancel returns to the add form.
- Delete asks for confirmation in the browser.
- A success or error toast is shown after each action.

### Validation rules
- `code`: required, text, up to 30 characters, unique.
- `name`: required, text, up to 100 characters.
- `offered_term`: optional, must be 1, 2 or 3. Summer (4) is rejected.
- Subject codes are trimmed and stored in upper case, so `eng` and `ENG` cannot both exist, whatever the database collation.
- Blank terms are treated as "all terms" on both create and update.

### Deleting subjects
- A subject that is assigned to a teacher cannot be deleted.
- The Delete button is disabled for such subjects, with a tooltip explaining why.
- The server enforces the same rule, so it still holds if the button is bypassed.

### Access control
- All four routes sit inside the admin route group, so they require a logged-in user with the Admin role.
- Guests are redirected to login. Teachers receive HTTP 403.
- The form request also checks that the user is an admin, as a second layer.
- The sidebar shows the "Subjects" link to admins only.

### Reusable model additions (`app/Models/Subject.php`)
- `scopeOfferedIn(Term|int $term)`: subjects offered in that term plus those offered in all terms.
- `isOfferedEveryTerm()`: true when `offered_term` is null.
- `termLabel()`: the human label, read from config.

## 3. Routes

All routes are defined in `routes/admin.php`, which is required from inside the `auth` + `role:admin` group in `routes/web.php`. That group supplies the `/admin` prefix and the `admin.` name prefix.

| Method | URI | Name | Action |
|---|---|---|---|
| GET | `/admin/subjects` | `admin.subjects.index` | List subjects and show the form |
| POST | `/admin/subjects` | `admin.subjects.store` | Create a subject |
| PUT/PATCH | `/admin/subjects/{subject}` | `admin.subjects.update` | Update a subject |
| DELETE | `/admin/subjects/{subject}` | `admin.subjects.destroy` | Delete a subject |

## 4. Differences From the Old App

Behaviour that matches the old app: admin only, sorted by name, delete asks for confirmation, term labels use the same wording.

These behaviours are intentionally different:

| Situation | Old app | New app | Reason |
|---|---|---|---|
| Add a code that already exists | Allowed | Rejected | The checklist requires unique codes |
| Add `eng` when `ENG` exists | Allowed | Rejected; codes saved upper-case | Case-safe uniqueness |
| Edit a subject and set the term to blank | Term stayed unchanged | Switches to "all terms" | The old `isset` check ignored blank values |
| Delete a subject that is assigned | Deleted | Blocked | The checklist rule |
| Non-admin opens the page | Redirected away | HTTP 403 | Slice 2 role middleware |

## 5. Database

No migration was added. The slice uses the Slice 1 tables:

- `subjects`: `id`, `code` (string 30, unique), `name` (string 100), `offered_term` (nullable tiny integer), `legacy_key` (nullable, unique), timestamps.
- `teacher_assignments.subject_id` references `subjects.id` with `cascadeOnDelete`.

**Important:** the cascade means a delete made outside this page (for example from Tinker or SQL) would silently remove the subject's assignments. The "cannot delete" rule is therefore enforced in the controller, not by the database. Changing the foreign key to `restrictOnDelete` in a new migration would add a second layer of protection. This is a decision for Lane A.

## 6. Important Files

### New
- `app/Http/Controllers/Admin/SubjectController.php`
- `app/Http/Requests/Admin/SubjectRequest.php` (used for both create and update)
- `routes/admin.php`
- `resources/js/pages/admin/subjects/Index.vue`
- `tests/Feature/Admin/SubjectManagementTest.php`

### Modified
- `app/Models/Subject.php` (scope and label helpers)
- `routes/web.php` (one line: `require __DIR__.'/admin.php';` inside the admin group)
- `resources/js/components/AppSidebar.vue` (admin-only "Subjects" link; the navigation items became a computed list)

### Page data
The controller passes the Inertia page `admin/subjects/Index` these props:

- `subjects`: a list of `{ id, code, name, offered_term, term_label, assignments_count }`.
- `terms`: the assignable terms as `{ value, label }` (terms 1 to 3).

## 7. Tests

Test file: `tests/Feature/Admin/SubjectManagementTest.php` (14 tests, which run as 26 cases because three of them use datasets).

| Area | What is checked |
|---|---|
| Access | Guests are redirected to login. Teachers get 403 on every route and nothing is changed. |
| Listing | Subjects are sorted by name. Term labels come from config. Summer is not offered as a choice. |
| Create | Code is trimmed and upper-cased. A blank term creates an all-terms subject. Code and name are required. |
| Unique code | `MATH`, `math` and ` Math ` are all rejected when `MATH` exists. |
| Terms | 1, 2, 3, null and blank are accepted. Summer (4), 0, 5 and text are rejected. |
| Update | Fields change. A subject keeps its own code. A subject can be switched back to all terms. Another subject's code is rejected. |
| Delete | An unassigned subject is deleted. A subject with an assignment is kept, and its assignment is untouched. |
| Model | `offeredIn()` returns the given term plus all-term subjects, for both an integer and a `Term` value. Labels follow config changes. |

**Result after applying the slice:** full test suite 162 passed, 454 assertions, 0 failures (136 passed before the slice).

## 8. Setup and Troubleshooting Notes

These came up while verifying the slice on a Windows machine with Laravel Herd and XAMPP.

### 8.1 PHPStan stopped at the default memory limit
**Symptom:** `PHPStan process crashed because it reached configured PHP memory limit: 128M`.
**Fix:** run `vendor\bin\phpstan analyse --memory-limit=1G`. No project files need to change.

### 8.2 Eleven page tests returned HTTP 500
**Symptom:** tests that render a page (login, settings, home) failed with `500 is identical to 200`.
**Cause:** the frontend assets were not built, so the Vite manifest was missing.
**Fix:** run `npm install` and `npm run build`, then rerun the tests.

### 8.3 Pint and PHPStan errors that belong to the base branch
Pint reported 5 style issues and PHPStan reported 10 errors. All of them were in Slice 2 files (`EnsureUserHasRole.php`, `User.php`, `DevUserSeeder.php`, `routes/web.php`), none in Slice 6 files. They were left alone to avoid conflicts when Slice 2 merges, and were reported to the Slice 2 author.

### 8.4 Herd and XAMPP both want port 80
**Symptom:** XAMPP's Apache refused to start because Herd's nginx held port 80.
**Fix:** move Apache to port 8080 and the SSL port to 4443, and update the Control Panel's port settings.

### 8.5 The old database has no subjects table
The old app created `subjects`, `sections`, `teachers` and `teacher_class_roster` at runtime, and they are missing from the database dump. The old subjects page therefore fails against the dump unless the table is created by hand. There is also no legacy subject data to import. Subjects start from the seeder.

## 9. Open Items

- **Parity check.** Compare the new page with the old `subject_management` page, using the table in section 4. The old page needs a hand-created `subjects` table to run.
- **Seeded subjects.** `ReferenceDataSeeder` creates a standard subject list that is an assumption, not legacy data. Confirm it with the school.
- **Database-level delete protection** (section 5).
- **Checklist.** Tick the five Slice 6 boxes in the slice checklist when the PR is merged.
- **Shared files.** `AppSidebar.vue` and `routes/web.php` are shared with other lanes, so expect small merge conflicts there.

## 10. Final Status

Slice 6's implementation and automated testing are complete. The following have been verified:

- Admin-only access, with guests redirected and teachers refused.
- Create, edit and delete.
- Unique, case-insensitive subject codes.
- Term validation, including rejection of Summer.
- Switching a subject back to all terms.
- Deletion blocked for assigned subjects.
- The `offeredIn()` term filter and config-based term labels.
- Full test suite passing.

**Remaining delivery steps:** parity check, pull request (stacked on `slice-2-auth-roles` until Slice 2 merges), review and merge.

---

*End of Slice 6 documentation report.*
