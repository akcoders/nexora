# Nexora Backend

Laravel 13 API and Blade admin panel for the Nexora HVAC ERP.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan storage:link
php artisan serve --host=0.0.0.0 --port=8000
```

Admin login: `admin@nexora.test` / `password`

Technician API login: `technician@nexora.test` / `password`

## Quality checks

```bash
vendor/bin/pint --test
php artisan test
npm run build
```

API routes are versioned under `/api/v1`. Sanctum bearer tokens secure all technician endpoints after login.
