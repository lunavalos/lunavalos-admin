# AGENTS

This repository is a Laravel 12 admin application with Inertia/Vue 3 front-end and Laravel Breeze-like authentication.

## Core facts
- Backend: PHP 8.4, Laravel 12.
- Frontend: Inertia + Vue 3 + Vite + Tailwind.
- Auth: login, password reset, email verification, two-factor support, and Spatie roles/permissions.
- There is no public registration route in `routes/auth.php`.

## Key files
- `routes/auth.php` — login, password reset, email verification, logout.
- `routes/web.php` — dashboard and all protected resources use `auth` and `verified` middleware.
- `database/seeders/DatabaseSeeder.php` — seeds the default test user.
- `app/Models/User.php` — has Spatie `HasRoles`, 2FA support, email verification, and password hashing.
- `database/seeders/ReportPermissionsSeeder.php` — local-only seeder for report permissions via `/run-report-permissions-seeder`.

## Startup and local onboarding
Use the repo scripts first:
- `composer setup` — installs PHP and JS dependencies, creates `.env`, generates app key, migrates the database, and builds assets.
- `npm run dev` — starts Vite for local frontend development.
- `php artisan serve` — serves the application.
- `php artisan migrate --force` — run migrations.
- `php artisan db:seed` — seed default data.

## Initial user guidance
- The repository already seeds a default user in `database/seeders/DatabaseSeeder.php`:
  - name: `Test User`
  - email: `test@example.com`
  - password: `password`
- Because the main app routes are protected by `verified`, a seeded user may need `email_verified_at` set or an email verification step before accessing `/dashboard`.
- If creating a manual initial admin user, prefer seeding a verified admin user and assigning an admin role via Spatie roles.

## Agent behavior
- Prefer making minimal, Laravel-conventional changes.
- Avoid adding unrelated auth/register features unless explicitly requested.
- Do not expose or modify `.env` secrets.
- Link to Laravel docs when behavior is standard and not repo-specific.

## Useful commands
- `composer setup`
- `composer test`
- `npm run dev`
- `npm run build`
- `php artisan migrate`
- `php artisan db:seed`
- `php artisan tinker`
