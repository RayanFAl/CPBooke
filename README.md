# CPBooke

Admin Control Panel foundation for the Booke platform.

## Stack

- Laravel 13
- Vue 3
- Inertia.js
- MySQL
- Laravel Breeze for admin authentication foundation

## Installation Commands

```powershell
composer install
npm install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

## Packages Installed

- `laravel/breeze`
- `inertiajs/inertia-laravel`
- `@inertiajs/vue3`
- `tightenco/ziggy`

## Current Foundation

- Login-only authentication flow for administrators
- `is_admin` flag on users table
- `EnsureUserIsAdmin` middleware guarding `/admin/*`
- Modular backend controllers under `app/Modules/Admin/*`
- Modular frontend pages under `resources/js/modules/admin/*`
- Admin layout with a simple sidebar and placeholder module pages
- MySQL-first environment defaults in `.env.example`

## Folder Structure

```text
app/
	Http/Middleware/
	Modules/Admin/
		Dashboard/
		Users/
		Orders/
		Finance/
		Support/
		Settings/
database/
	migrations/
	seeders/
resources/js/
	modules/admin/
		components/
		config/
		layouts/
		dashboard/pages/
		users/pages/
		orders/pages/
		finance/pages/
		support/pages/
		settings/pages/
routes/
	admin.php
	admin/
```

## First Admin Account

The initial admin user is created by `php artisan migrate --seed` using the following environment variables:

- `ADMIN_NAME`
- `ADMIN_EMAIL`
- `ADMIN_PASSWORD`

Update them in `.env` before seeding in non-local environments.
