# GEMS-AMES

Enrollment and academic learning management for GEMS-AMES, rebuilt in Laravel
from the old PHP application. This repository currently covers the enrollment
side; the work is split into vertical slices so three people can build in
parallel.

## Stack

| Layer           | Choice                         |
| --------------- | ------------------------------ |
| Framework       | Laravel 13 (PHP 8.3+)          |
| Frontend        | Inertia 3 + Vue 3 + TypeScript |
| Styling         | Tailwind CSS 4 (Vite)          |
| Auth            | Fortify                        |
| Tests           | Pest                           |
| Style           | Pint                           |
| Static analysis | Larastan / PHPStan level 7     |

## Requirements

- PHP 8.3 or newer (`php -v`)
- Composer 2
- Node 22 or newer and npm (CI uses 22)
- SQLite (default) or MySQL for the legacy import
- [Herd](https://herd.laravel.com/) or any local web server

## Local setup

1. `composer install`
2. `cp .env.example .env` (PowerShell: `Copy-Item .env.example .env`)
3. `php artisan key:generate`
4. `php artisan migrate`
5. `npm install`
6. `npm run build` (or `npm run dev` while working)
7. Point your host at `public/` — with Herd the site is `http://ges-alems.test/`
8. Log in with a seeded development account (Slice 2)
9. Run `composer test` before opening a PR

## Common commands

| Command               | What it does                   |
| --------------------- | ------------------------------ |
| `composer test`       | Pint check, PHPStan, then Pest |
| `composer lint`       | Auto-fix code style with Pint  |
| `npm run dev`         | Vite dev server                |
| `npm run types:check` | `vue-tsc` type check           |
| `php artisan migrate` | Run migrations                 |

## School configuration

School year and terms live in `config/school.php`, driven by `.env`:

```dotenv
SCHOOL_NAME="GEMS-AMES"
SCHOOL_YEAR=2026-2027
```

Terms are `1st` (June - August), `2nd` (September - November),
`3rd` (December - April), plus the Summer label. The canonical enums live in
`app/Enums/`:

- `VerificationStatus` — the seven legacy statuses
- `GradeLevel` — `K`, `1`..`6`, with `fromLegacy()` for old spellings
- `Role` — Admin and Teacher only (D5)
- `Term` — 1..3 plus Summer

## Working in slices

Each slice is one branch and one PR. The slice checklist is the source of
truth; a slice merges only when every box is ticked, including tests and the
parity check against the old app.

Conventions agreed for the rebuild:

- Tables are plural `snake_case`; models are singular (`students`,
  `enrollments`, `App\Models\Student`).
- Admin routes live under `/admin/...`; teacher routes under `/teacher/...`.
- `students` holds identity; `enrollments` holds one row per student per school
  year (D1).
- Status and section live on the enrollment, not the student.
- No real student data in the repository, ever — seed with factories.

### Suggested lanes (3 people)

- **Lane A:** Slice 0 (foundations), Slice 1 (schema + import), Slice 11 (PDF),
  Slice 12 (hardening + cutover)
- **Lane B:** Slice 2 (auth), Slice 3 (public form), Slice 9 (dashboards),
  Slice 10 (masterlist)
- **Lane C:** Slices 4–8 (verification, sections, subjects, teachers,
  assignments)

Slice 0 and Slice 1 must merge before lanes B and C start.

## Legacy application

The old app and its database dump live in `legacy/` for reference only:

```
legacy/ams/                 old PHP application
legacy/ams/gem_db (1).sql   phpMyAdmin export
```

`legacy/` and `*.sql` are git-ignored. **Never commit real student data**,
including medical and special-needs records. Seed demo data with factories.
