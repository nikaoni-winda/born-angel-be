# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Born Angel API — a Laravel 12 REST API for a beauty/makeup services booking platform. PHP 8.2+, Sanctum token auth, Midtrans payments, Cloudinary image uploads.

## Commands

```bash
# Full setup (install deps, env, key, migrate, build frontend)
composer setup

# Development (runs server, queue, log streaming, Vite concurrently)
composer dev

# Run tests (clears config cache, then runs PHPUnit with in-memory SQLite)
composer test

# Run a single test file
php artisan test --filter=ExampleTest

# Lint with Laravel Pint
./vendor/bin/pint

# Database
php artisan migrate
php artisan db:seed
php artisan migrate:fresh --seed   # Reset and reseed

# Interactive debugging
php artisan tinker
```

## Architecture

### Authentication & RBAC

- **Sanctum** API token auth. All protected routes use `auth:sanctum` middleware.
- **Custom role middleware** `EnsureUserHasRole` (`app/Http/Middleware/EnsureUserHasRole.php`) — used as `role:admin,super_admin` on admin routes.
- **4 roles** with strict hierarchy: `super_admin` > `admin` > `instructor` > `user`
- Master Account (user ID 1) is immutable and protected from modification/deletion.
- See `CONTROLLER_RBAC.md` for the full access control matrix.

### Context-Aware Controllers

Several endpoints return different data depending on the authenticated user's role without separate routes:
- `ScheduleController@index` — public sees upcoming only; instructors see own schedules; admins see all
- `ReviewController@index` — instructors see only reviews for their classes; others see all
- `BookingController@index` — users see own bookings; admins see all

### Key Patterns

- **Pessimistic locking** on `Schedule::lockForUpdate()` inside `DB::transaction()` in `BookingController@store` to prevent race conditions on slot availability.
- **Soft deletes** on User, Service, Instructor, Schedule, Booking models.
- **Cloudinary uploads** for service images (`services/` folder) and instructor photos (`instructors/` folder), max 5MB, formats: jpeg/png/jpg/gif.
- **Midtrans Snap** integration for payments — `PaymentController@getSnapToken` generates tokens, `PaymentController@callback` handles the webhook.

### Route Structure (routes/api.php)

Three layers:
1. **Public** — register, login, read-only services/instructors/schedules/reviews, Midtrans callback
2. **Authenticated** (`auth:sanctum`) — logout, profile CRUD, bookings, payments, reviews
3. **Admin** (`auth:sanctum` + `role:admin,super_admin`) — user management, service/instructor/schedule CRUD, dashboard stats, reports

### Models & Relationships

```
User → hasMany Bookings, Reviews; hasOne Instructor (if role=instructor)
Service → hasMany Schedules, Instructors
Instructor → belongsTo User, Service; hasMany Schedules
Schedule → belongsTo Service, Instructor; hasMany Bookings
Booking → belongsTo User, Schedule; hasOne Review, Payment
```

### Database

- **Production:** MySQL (`born_angel_db`)
- **Testing:** SQLite in-memory (configured in `phpunit.xml`)
- 10 migration files in `database/migrations/`

### External Services

- **Midtrans** (payment gateway) — sandbox mode, config in `config/midtrans.php`, env vars: `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, `MIDTRANS_MERCHANT_ID`
- **Cloudinary** (image storage) — config in `config/cloudinary.php`, env var: `CLOUDINARY_URL`

### Testing

- PHPUnit 11.5 with SQLite `:memory:` database
- Test suites: `tests/Unit/` and `tests/Feature/`
- Postman collection available in `postman/` directory with pre-configured requests and auto token management
