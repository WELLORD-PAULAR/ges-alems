# Slice 7 — Teachers
## Development and Verification Report

**Project:** GES-ALEMS
**Repository:** `WELLORD-PAULAR/ges-alems`
**Branch:** `slice-7-teachers` (built on `slice-6-subjects`)
**Lane:** C
**Date:** November 25, 2026
**Status:** Implementation written. Test run and parity check pending (see section 9).

---

## 1. Overview

Slice 7 gives administrators a page to manage teachers: add, edit, list and delete them, and connect each one to a login account with the Teacher role.

It replaces the old `teacher_management` page. It adds no database migration, because the `teachers` table was created in Slice 1. It depends on Slice 2 for the `auth` and `role:admin` middleware and on Slice 6 for the shared `routes/admin.php` file and the admin sidebar items.

The old app could only link a teacher to an account that already existed, and it had no screen to create one. This slice keeps that option and adds the ability to create the login in the same form.

## 2. Implemented Features

### Teacher management (admin only)
- Added the page `/admin/teachers`, with one form for adding and editing and a table of teachers.
- Each teacher has a first name, an optional middle name, a last name and an optional employee number.
- Teachers are listed by last name, then first name.
- The table shows the full name, employee number and the login email.
- Editing a teacher reuses the same form and shows the linked login email as plain text.
- Delete asks for confirmation in the browser.
- A success or error toast is shown after each action.

### Choosing a login account (when adding a teacher)
The form offers two options:

1. **Link an existing teacher account.** The dropdown lists only accounts that are active, have the Teacher role and are not already linked to a teacher.
2. **Create a login now.** The admin enters an email and a temporary password.

When creating a login:
- The login and the teacher are saved in one database transaction, so either both exist or neither does.
- The new account has the Teacher role, is active, and is marked as email-verified, because the admin vouches for the address.
- The account name is the teacher's full name.
- The email is trimmed and stored in lower case.
- The password follows the application's default password rules.

### Validation rules
- `first_name` and `last_name`: required, up to 50 characters.
- `middle_name`: optional, up to 50 characters.
- `employee_no`: optional, up to 30 characters, unique among teachers when present. Several teachers may have no employee number.
- `account_mode`: required when adding (`existing` or `new`).
- When linking: `user_id` is required, must exist, must have the Teacher role and must not already be linked.
- When creating a login: `email` is required, valid and unused, and `password` is required.

### Updating teachers
- Names and employee number can be changed.
- Blank values are real values: a middle name or employee number can be cleared.
- The linked account cannot be changed by editing. Any `user_id` sent with an update is ignored.

### Deleting teachers
- A teacher who still has assignments cannot be deleted. The Delete button is disabled with a tooltip, and the server enforces the same rule.
- Deleting a teacher removes only the teacher record. The login account stays, so it can be linked again.

### Access control
- All routes sit inside the admin route group. Guests are redirected to login and teachers receive HTTP 403.
- The form request also checks that the user is an admin.
- The sidebar shows the "Teachers" link to admins only.

### Reusable model additions (`app/Models/Teacher.php`)
- `fullName()` now joins first, middle and last name with single spaces and skips blank parts. Before, it left out the middle name.
- `scopeOrderedByName()` sorts by last name, then first name. Slice 8 and the teacher screens can reuse it.

## 3. Routes

All routes are defined in `routes/admin.php`, inside the `auth` + `role:admin` group (prefix `/admin`, names starting `admin.`).

| Method | URI | Name | Action |
|---|---|---|---|
| GET | `/admin/teachers` | `admin.teachers.index` | List teachers and show the form |
| POST | `/admin/teachers` | `admin.teachers.store` | Create a teacher (link or create a login) |
| PUT/PATCH | `/admin/teachers/{teacher}` | `admin.teachers.update` | Update a teacher |
| DELETE | `/admin/teachers/{teacher}` | `admin.teachers.destroy` | Delete a teacher |

## 4. Differences From the Old App

Behaviour that matches the old app: admin only, linking to an existing Teacher-role account, lists sorted by last name then first name, delete asks for confirmation, the linked account is not changed when editing.

These behaviours are intentionally different:

| Situation | Old app | New app | Reason |
|---|---|---|---|
| Create the login account | No screen for it | Optional, in the same form | The checklist allows it |
| Duplicate employee number | Allowed | Rejected when present | The checklist requires it to be unique |
| Clear a middle name or employee number | Ignored | Cleared | The old `isset` check ignored blank values |
| Full name | Included the middle name | Includes the middle name, skipping blanks | Matches the checklist (the first Laravel version left it out) |
| Delete a teacher with assignments | Deleted | Blocked | The checklist rule |
| Non-admin opens the page | Redirected away | HTTP 403 | Slice 2 role middleware |

## 5. Database

No migration was added. The slice uses these Slice 1 and Slice 2 tables:

- `teachers`: `id`, `user_id` (unique, foreign key to `users`, cascade on delete), `employee_no` (nullable, unique, 30 characters), `first_name` (50), `last_name` (50), `middle_name` (nullable, 50), `legacy_key` (nullable, unique), timestamps. Indexed on `(last_name, first_name)`.
- `users`: `name`, `email` (unique), `password`, `role`, `is_active`, `email_verified_at`.
- `teacher_assignments.teacher_id` references `teachers.id` with `cascadeOnDelete`.

**Important:** as with subjects, the cascade means a delete made outside this page would silently remove a teacher's assignments. The "cannot delete" rule is enforced in the controller, not by the database. Changing the foreign key to `restrictOnDelete` in a new migration would add a second layer. This is a decision for Lane A.

## 6. Important Files

### New
- `app/Http/Controllers/Admin/TeacherController.php`
- `app/Http/Requests/Admin/TeacherRequest.php` (used for both create and update)
- `resources/js/pages/admin/teachers/Index.vue`
- `tests/Feature/Admin/TeacherManagementTest.php`

### Modified
- `app/Models/Teacher.php` (`fullName()` fix and `scopeOrderedByName()`)
- `routes/admin.php` (added the teachers routes)
- `resources/js/components/AppSidebar.vue` (admin-only "Teachers" link)

### Page data
The controller passes the Inertia page `admin/teachers/Index` these props:

- `teachers`: a list of `{ id, full_name, first_name, middle_name, last_name, employee_no, email, assignments_count }`.
- `accounts`: the linkable accounts as `{ id, name, email }`.

## 7. Tests

Test file: `tests/Feature/Admin/TeacherManagementTest.php` (22 tests).

| Area | What is checked |
|---|---|
| Access | Guests are redirected to login. Teachers get 403 on every route and nothing is changed. |
| Listing | Sorted by last name then first name, with the full name. Only active, unlinked, Teacher-role accounts are offered. |
| Full name | Blank and empty middle names are skipped; a middle name is included. |
| Link an account | A teacher is created for a free Teacher-role account. An admin account, an already-linked account and a missing account are rejected. |
| Create a login | The user and teacher are created together, with the right role, active flag, verified email, name and a hashed password. The email is lower-cased. |
| Rollback | No login is created when the teacher details are invalid. |
| Login validation | A used email, a short password, an invalid email and a missing account option are all rejected. |
| Shared rules | First and last name are required. A duplicate employee number is rejected. Several teachers may have none. |
| Update | Fields change and a teacher keeps their own number. Middle name and employee number can be cleared. Another teacher's number is rejected. The linked account never changes. |
| Delete | A teacher without assignments is deleted and the account remains. A teacher with an assignment is kept, and the assignment is untouched. |

## 8. Design Decisions

- **Temporary password.** The admin types the password and tells the teacher. There is no email invitation, because mail delivery is not set up. The teacher can change the password in Settings.
- **Account name is not kept in sync.** The user's display name is set from the teacher's name when the login is created, but later edits to the teacher do not change it.
- **Only active accounts are offered for linking.** An inactive account cannot log in, so it is not listed.
- **No way to change the link when editing**, matching the old app. To change it, delete the teacher (if there are no assignments) and add them again.

## 9. Open Items

- **Run the checks.** This slice was written without being run in the development environment. Before merging, run `npm run build`, `npm run types:check`, `vendor\bin\phpstan analyse --memory-limit=1G`, `vendor\bin\pest` and `vendor\bin\pint --test` on the changed files, and record the results here.

  | Check | Result |
  |---|---|
  | Full test suite | _not yet recorded_ |
  | PHPStan (Slice 7 files) | _not yet recorded_ |
  | Pint (Slice 7 files) | _not yet recorded_ |
  | Type check and build | _not yet recorded_ |

- **Parity check.** Compare with the old `teacher_management` page using the table in section 4. The old database dump has no `teachers` table, so it must be created by hand or the comparison done from the old code.
- **Legacy data.** There are no legacy teachers to import. Teachers are entered by hand at launch, and each needs a login account.
- **Database-level delete protection** (section 5).
- **Checklist.** Tick the five Slice 7 boxes when the PR is merged.
- **Shared files.** `AppSidebar.vue` and `routes/admin.php` are shared with other slices, so expect small merge conflicts.

## 10. Final Status

Slice 7's implementation is written and covered by 22 automated tests. Once the checks in section 9 pass, the following will be verified:

- Admin-only access.
- Create, edit and delete.
- Linking an existing Teacher-role account.
- Creating a login and a teacher in one step.
- Unique employee numbers.
- Clearing optional fields.
- Deletion blocked for teachers with assignments.
- Correct full-name formatting.

**Remaining delivery steps:** run the checks, parity check, pull request (stacked on `slice-6-subjects`), review and merge.

---

*End of Slice 7 documentation report.*
