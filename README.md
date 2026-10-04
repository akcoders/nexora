# Nexora HVAC ERP

Phase 1 monorepo for the Nexora web admin panel, API, and Android technician app.

## Applications

- `backend/` — Laravel 13 API and Blade admin panel
- `technician-app/` — Flutter Android application
- `docs/Requirement.md` — product requirements

## Backend setup

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve --host=0.0.0.0 --port=8000
```

Development admin: `admin@nexora.test` / `password`

## Technician app setup

```bash
cd technician-app
flutter pub get
flutter run --dart-define=NEXORA_API_URL=http://10.0.2.2:8000/api/v1/
```

Release builds always default to `https://nexora.webignitors.in/api/v1/`.

