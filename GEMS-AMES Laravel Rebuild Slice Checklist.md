# GEMS-AMES Laravel Rebuild: Slice Checklist

One checklist per vertical slice. A slice is done only when every box in it is ticked, including tests and the parity check against the old app. Each slice = one branch = one PR.

## How the team works

- [ ] Repo created, branch protection on `main`, PRs need 1 review
- [x] Naming agreed: tables plural snake\_case, models singular, routes `/admin/...`
- [x] Rule agreed: no real student data in the repo, ever (seed with factories)
- [x] Rule agreed: a PR is merged only when its slice checklist is fully ticked
- [ ] Old app kept running read-only as the reference for parity checks

**Suggested lanes (3 people):**

- **A**: Slices 0, 1, 11, 12 (foundation, schema, import, PDF, cutover)
- **B**: Slices 2, 3, 9, 10 (auth, public enrollment form, dashboards, masterlist)
- **C**: Slices 4, 5, 6, 7, 8 (verification, sections, subjects, teachers, assignments)
- **Hand-off:** A moves to Slice 11 (PDF) once the enrollment model is stable. If A is still on the import, B takes the PDF.

**Order:** Slice 0 then Slice 1 must merge first. After that, lanes B and C can run in parallel.

## Decisions to settle before coding

- [x] D1: Split into `students` (identity) and `enrollments` (per school year)
- [x] D2: Laravel 13 (needs PHP 8.3 or newer on all 3 machines)
- [x] D3: Tailwind (the old Bootstrap pages are restyled, not reused)
- [x] D4: Teachers only see students in their assigned sections
- [x] D5: Drop the `PARENT` role (Admin and Teacher only)
- [x] D6: Resolved. The live local database (phpMyAdmin export, Nov 21) has only 9 tables and no `sections`, `subjects`, `teachers` or `teacher_class_roster`. Those modules never had tables, so design them new from the model code and seed sections from the old hardcoded list. Nothing to import for them.
- [x] D7: All improvements are in v1 (status transitions, audit log, encrypted medical and special-needs fields)

### What these decisions change

- **Slice 0:** install PHP 8.3 or newer first. Use Tailwind through Vite. Breeze no longer gets updates, so use Fortify with your own Blade views, or the current Livewire starter kit.
- **Slice 1 (proposed split, confirm):** `students` holds name, birth date, sex, LRN, PSA number, place of birth, mother tongue, religion, indigenous group, 4Ps, and the address, parent, medical and special-needs records. `enrollments` holds school year, grade, section, status, learning modality, last school attended and the created date. The import creates one student per old row plus one 2026-2027 enrollment.
- **Slice 1:** match students by LRN first, then name plus birth date, and log every duplicate. Drop `user_account_id` from enrollments.
- **Slice 2:** two roles only, Admin and Teacher. The redirect-loop item is now trivial.
- **Slice 3:** the form creates a student and an enrollment. If the LRN already exists, it creates only a new enrollment for that student.
- **Slices 4, 5, 9:** status and section live on the enrollment. A teacher sees enrollments in their assigned sections for the current school year, so Slice 8 data is needed to demo Slice 9.
- **D7 effects:** encrypted columns can't be searched or filtered in SQL, so the masterlist and PDF decrypt in PHP and medical fields are not searchable. Status transitions need an agreed rule table before Slice 4 starts.
- **Dec 4:** the split, Tailwind and D7 together add roughly 6 to 10 person-days over the trimmed plan. Schedule the audit log and encryption last, and let them be the first to slip if you fall behind.

## Slice 0: Foundations (Lane A)

- [x] Laravel project created with the chosen version, PHP version confirmed locally for all 3 devs
- [x] `.env.example` committed, DB credentials not hardcoded
- [x] Vite and CSS framework set up, base layout with nav and flash messages
- [x] Pest installed, one passing example test, CI runs tests on every PR
- [x] Code style tool set up (Pint) and enforced in CI
- [x] `config/school.php` holds current school year and the 3 terms
- [x] Enums created: `VerificationStatus` (7 values), `Role`, `Term`, `GradeLevel`
- [x] README explains local setup in under 10 steps

### Slice 0 status (21 Nov 2026)

**Done in code**

- Laravel 13.43 on this machine with PHP 8.5.13; `composer.json` requires PHP `^8.3`. The other two devs still need to run `php -v` and confirm 8.3+.
- `.env.example` covers the school config, the legacy DB connection (local only) and dev-account passwords. No credentials are committed.
- Vite 7 + Tailwind 4 are wired through `@vite`. The starter layout is kept and `flash.toast` / `school` are now shared to Inertia, so `vue-sonner` toasts work.
- Pest is in place with the starter example test plus new enum and school-config tests: **65 tests, 186 assertions**.
- CI (`.github/workflows/tests.yml`) runs `composer ci:check` on every PR; Pint is enforced through `composer lint:check`.
- `config/school.php` reads `SCHOOL_YEAR` (default `2026-2027`) and exposes last year through two years ahead plus the three terms and Summer.
- Enums: `App\Enums\VerificationStatus` (7 legacy values, blank/`PENDING` normalizes to `PROCESSING`), `GradeLevel` (`K`, `1`..`6`, `fromLegacy()` for old spellings), `Role` (Admin/Teacher), `Term` (1–3 + Summer).
- README has the 9-step setup, stack, conventions, lanes and the legacy-data rule. PR template added at `.github/pull_request_template.md`.

**Still manual**

- Turn on branch protection for `main` and require 1 review.
- Have the other two devs confirm PHP 8.3+ (and Node 22+).
- Keep the old app running read-only for parity checks.
- Push the Slice 0 branch and open the PR.

### Prep notes

- **Read first:** `ams/config/config.php` (school year and term constants). Also `ams/style/style.css` and `auth.css` if you keep the current look.
- **Terms:** 1st is June to August, 2nd is September to November, 3rd is December to April, plus a Summer label. Current school year is 2026-2027.
- **Grade values clash:** enrollments store kindergarten as `Kindergart` and grades as `1` to `6`. The sectioning screen uses `K`. Section CRUD uses `KINDERGARTEN` and `GRADE 1` to `GRADE 6`. Pick one canonical enum (K, 1 to 6) with display labels.
- **Statuses:** PROCESSING, VERIFIED, REJECTED, WITHDRAWN, TRANSFERRED\_IN, TRANSFERRED\_OUT, DROPPED. The old dashboards also treat blank or PENDING as pending.
- **Team setup:** PR template containing the slice checklist, branch names like `slice-3-enrollment-form`, seeded dev accounts read from `.env`.
- **Test with:** the app boots, and each enum round-trips to its stored value.

## Slice 1: Schema and legacy import (Lane A)

**Schema**

- [x] Migrations for users, lookups (mother tongue, religion, indigenous group)
- [x] Migrations for students/enrollments (per D1), address, parents, medical, special needs
- [x] Migrations for sections, subjects, teachers, teacher assignments
- [x] Surrogate ids everywhere, `legacy_key` column kept for traceability
- [x] `set` columns stored as JSON arrays with casts (modality, medical conditions, special needs)
- [x] Unique index on assignment (teacher, subject, section, school year, term)
- [x] Typo'd columns renamed (`ca_provice`, `pi__attended_...`) with a mapping table documented
- [x] Models and relationships written, factories and seeders for fake data

**Import**

- [x] Old DB loaded locally and wired as a `legacy` connection
- [x] `ams:import-legacy` command created, safe to re-run (idempotent via `legacy_key`)
- [x] `--dry-run` option works
- [x] Cleaning rules done: `Kindergart` to K, school year formats, `0000-00-00` dates to null
- [x] Old `section` text converted to `section_id`, sections seeded from the old constant
- [x] Old `user_account` imported into users (bcrypt hashes kept as-is)
- [x] Reject report written to a file listing every row that failed and why
- [x] Row counts reconciled per table, 20 records spot-checked by hand
- [x] Tests cover the cleaning rules

### Slice 1 status (22 Nov 2026)

**Done in code**

- Six migrations add lookups, legacy identity on users, sections/subjects/teachers, students/enrollments (D1), four enrollment detail tables and the teacher-assignment unique index. Surrogate ids are used everywhere; `legacy_key` is retained on imported records.
- Typo mappings are handled in `LegacyRowCleaner`/`LegacyImporter`: `ca_provice` → `current_province`, `pi__attended_early_learning_program_name` → `attended_early_learning_program_name`, `pa_zip_code` → `permanent_zip_code`. MySQL `set` columns are stored as JSON arrays with casts.
- Models, relationships, factories and seeders added. `ReferenceDataSeeder` seeds sections from `config('school.sections')` plus the standard subject list; `DemoDataSeeder` creates 30 fake students (no real data in the repo).
- `ams:load-legacy-dump` parses the MySQL dump into the local SQLite `legacy` connection; MySQL users can point `LEGACY_DB_CONNECTION=mysql` instead. All nine old tables loaded.
- `ams:import-legacy` imports lookups, users (bcrypt hashes preserved via query builder), sections, students/enrollments and child records. It is idempotent via `legacy_key`, keeps the newest child row on duplicates (matching the old `ORDER BY id DESC`), and supports `--dry-run`, `--dump=` and `--report=`.
- Cleaning rules: `Kindergart` → K, dirty school years normalized (including `2026 - 202` → `2026-2027`), `0000-00-00` dates → null, junk PSA/4Ps/LRN values dropped, zip codes zero-padded. The two rows with a zero birth date are imported with `birth_date = null` and a warning.
- Sections seeded from the old hardcoded constant for every school year present in the dump plus the current year (34 rows), and the two rows with an old `section` value are linked by `section_id`.
- Every rejected or questionable row is written to a JSON report in `storage/app/imports/`. On the real dump the final run had **0 rejects and 8 warnings** (2 zero birth dates plus 6 expected dirty-value warnings).
- Reconciled counts: 427 students, 427 enrollments, 374 medical, 427 each of addresses/parents/special needs, 199 mother tongues, 11 religions, 177 indigenous groups, 2 users, 34 sections. A second run produced identical counts (idempotent), and `--dry-run` left the database unchanged. 20 records were spot-checked by hand across grades, statuses, sections, dates, modalities, lookups and child tables.
- Verification: `composer test` — **135 tests, 356 assertions** (Pint, PHPStan level 7, Pest all green); `npm run types:check`, `npm run check`, `npm run build` all pass.

**Still manual**

- Confirm the provisional status-transition table in `VerificationStatus` before Slice 4 begins (decision D7).
- Point the legacy connection at the real MySQL database on a dev machine with the old app running read-only, then re-run the import and compare counts.
- Push the Slice 1 branch and open the PR; merge Slice 0 and Slice 1 before Lanes B/C start.

### Prep notes

- **Read first:** `gem_db (1).sql`. For the four missing tables, read `classes/models/Section.php`, `Subject.php`, `Teacher.php` and `TeacherAssignment.php`.
- **Record groups:** enrollment (27 columns), address (current `ca_` and permanent `pa_`, 8 fields each), parents (father `fi_`, mother `mi_`, guardian `gi_`, plus `ec_to_contact`), medical, special needs.
- **Lookups:** about 212 mother tongues, 11 religions and 205 indigenous groups. Import them with their original ids so enrollment foreign keys map 1:1.
- **Dirty values to clean:** grade `Kindergart`; school years like `2026 _2027`, `2026 - 202` and `2025_2026`; dates of `0000-00-00`; free-text 4Ps numbers (`#553`, `NO`, `N/A`); junk PSA numbers; empty string versus NULL.
- **Duplicate child rows:** the child tables have no unique constraint on the student key. The old PDF code takes the newest row by `id DESC`. Do the same and log every duplicate.
- **Import order:** lookups, users, sections, students and enrollments, child records, teachers, subjects, assignments.
- **Test with:** table-driven cleaning cases, and running the import twice gives identical row counts.

## Slice 2: Auth and roles (Lane B)

- [X] Username login (Fortify or starter kit), logout
- [X] Inactive accounts (`is_active = 0`) cannot log in
- [X] Login rate limiting on
- [X] Role middleware and Policies for Admin and Teacher
- [X] Post-login redirect by role (admin dashboard / teacher dashboard)
- [X] D5 handled so no role can get into a redirect loop
- [X] Seeded admin and teacher accounts for dev only, passwords from `.env`
- [X] Tests: login success/fail, inactive user, each role blocked from the other's pages
- [X] Parity check: same pages accessible to same roles as the old app

### Prep notes

- **Read first:** `login/SessionManager.php`, `login/process.php`, `login/index.php`.
- **Rules to carry over:** username plus password, an `is_active` check, redirect by role (admin to the admin dashboard, everyone else to the teacher dashboard), and a 24-hour session (`JWT_EXPIRY` is 86400 seconds).
- **Dead code:** the login token is random hex and `JWT_SECRET` is never used. Don't rebuild either.
- **Watch out:** a `PARENT` account bounces between the two dashboards forever. Decision D5 settles this.
- **Test with:** the access matrix below, as a data-driven test.

| Area                                                                               | Admin               | Teacher | Guest |
| ---------------------------------------------------------------------------------- | ------------------- | ------- | ----- |
| Enrollment form                                                                    | yes                 | yes     | yes   |
| Verification list and edit                                                         | yes                 | yes     | no    |
| PDF download                                                                       | yes                 | yes     | no    |
| Teacher dashboard                                                                  | redirected to admin | yes     | no    |
| Admin dashboard, sectioning, sections, subjects, teachers, assignments, masterlist | yes                 | no      | no    |

## Slice 3: Public enrollment form (Lane B)

- [ ] Route and controller for the public form, plus success page
- [ ] `StoreEnrollmentRequest` validates every field server-side (LRN, dates, enums, required fields)
- [ ] Section 1 Enrollment details (grade, LRN, school year, last school)
- [ ] Section 2 Personal information (name, birth date, sex, mother tongue, religion, indigenous group, 4Ps)
- [ ] Section 3 Address (current and permanent, copy-address button)
- [ ] Section 4 Medical (allergies, conditions, surgery, treatment, comorbidities)
- [ ] Section 5 Parents/guardian (father, mother, guardian, emergency contact)
- [ ] Section 6 Special needs (diagnosis, sub-types, manifestations, PWD ID)
- [ ] Uppercase-on-input and searchable selects working
- [ ] Lookup endpoint (`/lookups/{type}`) authenticated or throttled, no stack traces in errors
- [ ] All 5 records saved in one DB transaction, new enrollments start as `PROCESSING`
- [ ] Throttling and honeypot on the form
- [ ] "Fill Dummy Data" removed (or only in local env)
- [ ] Tests: valid submit, each validation rule, rollback if a child record fails
- [ ] Parity check: submit the same sample on old and new app, compare saved data

### Prep notes

- **Read first:** `forms/enrollment_form/index.php`, `process.php`, the six files in `sections/`, `enrollment.js` and `handlers/EnrollmentHandler.php`.
- **Defaults the old handler applied:** mother tongue and religion default to id 1, country to Philippines, address status to Owned, zip to 0. Empty strings are dropped. The address is saved only if at least one address field is filled.
- **Multi-select fields (old: comma lists):** learning modality, medical conditions, comorbidities, special-needs diagnosis, manifestations. Use arrays with JSON casts.
- **JS behavior to port:** uppercase on input, searchable selects for the three lookups, copy current address to permanent, reset, client-side validation.
- **Required fields:** the old schema marks most name, birth, address and parent fields NOT NULL, yet the handler filled blanks. Decide the real required list before writing the Form Request.
- **Watch out:** set status to PROCESSING on the server, not from the hidden field. Warn on a duplicate LRN or the same name plus birth date. Names contain `'`, `Ñ` and hyphens.
- **Test with:** a full valid payload, each required rule, names with an apostrophe and Ñ, and a failure in one child record rolling everything back.

## Slice 4: Verification workflow (Lane C)

- [ ] List page with status tabs and counts (all 7 statuses)
- [ ] Filters: grade level, school year, search by name/LRN
- [ ] Sorting on columns, pagination
- [ ] Edit form loads all 5 record groups for one student
- [ ] Update saves all 5 record groups in one transaction
- [ ] Status change allowed only to valid next statuses (rules written down and agreed)
- [ ] Blank/unknown status shown as pending, same as old app
- [ ] Audit log records who changed status and when
- [ ] Only Teacher and Admin can access
- [ ] Tests: status transitions, update persistence, access control
- [ ] Parity check: edit the same record on both apps, compare results

### Prep notes

- **Read first:** `forms/verify/index.php`, `verify.js` (666 lines), `get_enrollment.php`, `process_verify.php`.
- **List:** name, sex, birth date, grade, school year, LRN and status. Status tabs with counts, grade and school-year filters, sortable columns. Grade sorts numerically with kindergarten first.
- **Edit form payload:** enrollment, address, medical, parents and special needs together. Multi-select fields come back as arrays.
- **Old save rules:** 23 enrollment fields are whitelisted. Child records are updated if they exist and inserted if not. An invalid status is silently ignored.
- **Watch out:** the old code skips empty values, so a field can never be cleared. Decide to allow clearing. Return a validation error for an invalid status instead of ignoring it.
- **Access:** Teacher and Admin.
- **Test with:** edit then reload shows all five groups, clearing a field persists, an invalid status is rejected, and saving creates a missing child record.

## Slice 5: Sections and sectioning (Lane C)

- [ ] Section CRUD (name, grade level, school year) with validation
- [ ] Cannot delete a section that still has students or assignments
- [ ] Sectioning screen with grade tabs and student list
- [ ] Assign one student to a section
- [ ] Bulk assign and save all
- [ ] Section must match the student's grade level
- [ ] Filter by verification status and search by name
- [ ] Admin only
- [ ] Tests: CRUD, bulk assign, grade mismatch rejected
- [ ] Parity check: same students and sections as the old sectioning screen

### Prep notes

- **Read first:** `handlers/SectionAssignmentHandler.php` (the hardcoded list), `dashboard/admin_dashboard/sectioning/` (`assign_sections.php`, `.js`, `section_assignment_handler.php`), `section_management/index.php`, `classes/models/Section.php`, `handlers/SectionHandler.php`.
- **Seed these sections** (old hardcoded list): K: Obedience, Kindness, Joy. 1: Care, Love, Hope. 2: Integrity, Patience, Unity. 3: Peace, Faith. 4: Charity, Generosity. 5: Loyalty, Honesty. 6: Humility, Purity. Old names look like `K-Obedience` and `1-Care`.
- **Section fields:** name, grade level, school year in `YYYY-YYYY` format. The year dropdown offers last year through two years ahead.
- **Old sectioning endpoints:** get students (by grade or status), get sections, assign one, bulk assign. Students sort kindergarten first, then grade number, then last name.
- **Watch out:** the old bulk assign ignores invalid rows silently. Return a result per row. Section must belong to the student's grade and school year.
- **Test with:** CRUD, bulk assign with one bad row, grade mismatch rejected, deleting a section that has students is blocked.

## Slice 6: Subjects (Lane C)

- [ ] Subject CRUD (code, name, offered term or all terms)
- [ ] Unique subject code
- [ ] Cannot delete a subject that is assigned to a teacher
- [ ] Term labels come from `config/school.php`
- [ ] Admin only, tests for CRUD and uniqueness

### Prep notes

- **Read first:** `subject_management/index.php`, `classes/models/Subject.php`, `handlers/SubjectHandler.php`.
- **Fields:** `subject_code`, `subject_name`, `offered_term` (1 to 3, or empty meaning every term).
- **Rule to keep:** listing subjects for a term returns that term's subjects plus the ones offered in every term.
- **Watch out:** the old code does not enforce a unique code, though it looks subjects up by code. Make it unique.
- **Test with:** duplicate code rejected, term filter includes all-term subjects, delete blocked when assigned.

## Slice 7: Teachers (Lane C)

- [ ] Teacher CRUD (first, middle, last name, employee number)
- [ ] Link each teacher to a user account with the Teacher role
- [ ] Option to create the login account at the same time
- [ ] Cannot delete a teacher that still has assignments
- [ ] Admin only, tests for CRUD and linking

### Prep notes

- **Read first:** `teacher_management/index.php`, `classes/models/Teacher.php`, `handlers/TeacherHandler.php`.
- **Fields:** linked user, first, middle and last name, employee number (optional). Lists sort by last name, then first name. Full name joins first, middle and last, skipping blanks.
- **Old behavior:** the admin picks an existing user account with the TEACHER role. The old app has no screen to create that account.
- **Decide:** keep that, or create the login in the same form (the checklist assumes the latter is optional).
- **Watch out:** employee number is looked up but never enforced unique. Make it unique when present.
- **Test with:** linking, duplicate employee number, delete blocked when assignments exist.

## Slice 8: Teacher assignments (Lane C)

- [ ] Assignment CRUD (teacher, subject, section, school year, term)
- [ ] Duplicate assignment rejected (unique index plus friendly message)
- [ ] Term must be 1 to 3, school year format validated
- [ ] List filters by school year and term, default is the current school year from config
- [ ] Delete by assignment, by teacher, and by section (fix the old broken bulk deletes)
- [ ] Admin only, tests for all of the above

### Prep notes

- **Read first:** `teacher_assignments/index.php`, `classes/models/TeacherAssignment.php`, `handlers/TeacherAssignmentHandler.php`.
- **Old validation:** term is 1 to 3, school year matches `YYYY-YYYY`, teacher, subject and section must exist, and the same five-part combination can't repeat.
- **List queries to port:** by teacher, by section, by subject, by year, by year and term. Each joins teacher, subject and section names.
- **Watch out:** the old list passes a plain year (`date('Y')`) where assignments store `YYYY-YYYY`. Use the school year from config. The old bulk deletes by teacher and by section reference an undefined `$deletedCount` and never commit.
- **Add:** the section's school year must match the assignment's school year.
- **Test with:** duplicate rejected, bad term rejected, year and term filters, all three delete variants.

## Slice 9: Dashboards (Lane B)

- [ ] Admin dashboard: total, pending, verified, rejected counts
- [ ] Admin dashboard links to every admin module
- [ ] Teacher dashboard: students grouped by Verified / Pending / Rejected, sorted by grade
- [ ] Teacher dashboard filters (grade, status)
- [ ] D4 applied (all students or only assigned sections)
- [ ] Tests for counts and grouping

### Prep notes

- **Read first:** `dashboard/admin_dashboard/index.php`, `dashboard/teacher_dashboard/index.php`, `teacher_dashboard.js`, `download_enrollment_pdf.php`.
- **Admin cards:** total, pending, verified, rejected. Pending counts PROCESSING, PENDING and blank.
- **Watch out:** WITHDRAWN, TRANSFERRED and DROPPED count toward the total but appear in no card, so the cards don't add up. Decide whether to add cards or a note.
- **Teacher dashboard:** students grouped as Verified, Pending, Rejected, sorted by the number in the grade, then by grade text. The JS provides grade and status filters.
- **Per student:** a link to the verify page and a PDF download.
- **Test with:** counts against a fixture with every status, grouping and sort order, and the D4 decision (all students or only assigned sections).

## Slice 10: Masterlist export (Lane B)

- [ ] Column picker grouped by category (personal, learning, status, address, parents, medical, special needs)
- [ ] Column whitelist enforced server-side (no raw column names from the request)
- [ ] Section filter
- [ ] Lookup ids exported as names (mother tongue, religion, indigenous group)
- [ ] Summary totals: 4Ps beneficiaries
- [ ] Summary totals: indigenous group, mother tongue, religion, each split male/female
- [ ] CSV streamed (no memory blowup), filename with timestamp
- [ ] Enrollment date uses a real created-at column, not an auto-updating one
- [ ] Admin only
- [ ] Tests with a small fixture and known expected counts
- [ ] Parity check: same filters on old and new app give the same CSV totals

### Prep notes

- **Read first:** the data dictionary PDF from phpMyAdmin and `gem_db (1).sql`. The four missing tables don't exist in the live DB, so design `sections`, `subjects`, `teachers` and assignments new, using `classes/models/Section.php`, `Subject.php`, `Teacher.php` and `TeacherAssignment.php` as the spec.
- **Real row counts:** 427 enrollments, 427 address, parent and special-needs rows, 374 medical rows (53 students have none), 2 users. Medical must be optional in the new model.
- **Record groups:** enrollment (27 columns), address (current `ca_` and permanent `pa_`, 8 fields each), parents (father `fi_`, mother `mi_`, guardian `gi_`, plus `ec_to_contact`), medical, special needs.
- **Lookups:** 199 mother tongues, 11 religions and 177 indigenous groups. Ids have gaps (the next auto-increment values are 213, 12 and 206), so import with the original ids and enrollment foreign keys map 1:1.
- **Dirty values to clean:** 5 student keys end in `_` or contain `0000`, and 2 birth dates are `0000-00-00`; grade `Kindergart`; school years like `2026 _2027`, `2026 - 202` and `2025_2026`; free-text 4Ps numbers (`#553`, `NO`, `N/A`); junk PSA numbers; empty string versus NULL. Also `created-at` auto-updates on every edit, so it is really a last-modified time. Use `enrollments.created_at` only for new records and treat old values as approximate.
- **Child rows:** checked in the dump. Each child table has at most one row per student and no orphans, so import 1:1. Still assert this in the command and fail loudly if it ever breaks.
- **Import order:** lookups, users, students, enrollments, child records. Then seed sections from the old list.
- **Test with:** table-driven cleaning cases, and running the import twice gives identical row counts.

## Slice 11: PDF generation (Lane A)

- [ ] TCPDF installed via composer in the Laravel app
- [ ] Generator moved into `App\Services` and fed from the Eloquent model
- [ ] Download route for Teacher and Admin
- [ ] Pages 1 and 2 (enrollment form) match the old output
- [ ] Page 3 (medical and consent) matches the old output
- [ ] Handles missing address, medical or parent records without errors
- [ ] Filename format kept
- [ ] Side-by-side visual check against old PDFs for at least 5 students, including edge cases
- [ ] Snapshot or smoke test so a layout change gets noticed

### Prep notes

- **Read first:** `generation/pdf/GeneratePDF.php` (1,041 lines), `generation/pdf/pdf.php`, `dashboard/teacher_dashboard/download_enrollment_pdf.php`. The two PDFs in `templates/` are visual references only.
- **Structure:** page 1 and 2 are the DepEd enrollment form, page 3 is the medical history and consent form. Methods: `renderEnrollmentPage1`, `renderEnrollmentPage2`, `renderMedicalPage`, plus helpers.
- **Input contract:** one flat array merging all five tables, plus `mother_tongue_name`, `religion_name` and `indigenous_group_name`. Build an adapter that produces exactly this array, and leave the generator untouched.
- **Watch out:** the generator expects multi-select fields as comma strings. If you store arrays, convert them back in the adapter. Drop the old `?student_id=` lookup.
- **Output modes:** download (default) or save to a folder. Keep the filename format from `buildFilename`.
- **Test with:** about 5 students side by side with old output, including one with no medical record, one kindergarten, one long name, one with Ñ.

## Slice 12: Hardening and cutover (Lane A)

- [ ] Medical and special-needs fields encrypted or access restricted
- [ ] Audit log covers medical data views
- [ ] Custom error pages, debug off in production
- [ ] Debug files, `composer.phar`, committed `vendor/` not carried over
- [ ] Backups scheduled and a restore tested
- [ ] Parity checklist from the old app fully reviewed (every screen and action)
- [ ] Staff walkthrough and sign-off
- [ ] Old app frozen read-only
- [ ] Final import run, counts reconciled
- [ ] DNS or URL switched, old app archived

### Prep notes

- **Before the final import:** freeze the old app (read-only), take a backup of the old database and of the new one, run `--dry-run` and read the reject report.
- **After the import:** reconcile counts per table and per status, compare masterlist totals on both systems, and spot-check about 20 students.
- **Security clean-up:** rotate the two seeded passwords (admin and teacher), delete `debug_status.php` and `inspect_status_values.php`, set `APP_DEBUG=false`, keep real data out of the repo.
- **Privacy:** the data includes minors' medical and special-needs records. Restrict who can open those fields and check the old git remote is private.
- **Rollback plan:** keep the old app available read-only for an agreed number of days, and write down who decides to switch back.
- **Test with:** a full dress rehearsal on a copy of production data at least one day before cutover.
