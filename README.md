# Nexus Flow

Mobile-first job portal PWA: Laravel REST API (`backend/`) + Next.js frontend (`frontend/`).

> Work in progress. The full README (production deployment, queue worker, PWA install testing) is written in the final milestone.

## Prerequisites

- PHP 8.3+ with `gd`, `intl`, `pdo_mysql`, `zip`, and Composer 2
- Node.js 20+ and npm
- MySQL 8
- `qpdf` (needed from the Candidate Pack milestone onward)

## Local setup

```bash
# API → http://localhost:8000
cd backend
cp .env.example .env         # fill in DB_*, GOOGLE_*, ADMIN_EMAILS
composer install
php artisan key:generate
php artisan migrate --seed   # creates tables and seeds lookup lists
php artisan serve

# Frontend → http://localhost:3000
cd frontend
cp .env.example .env.local
npm install
npm run dev
```

## Google OAuth

1. In Google Cloud Console, go to **APIs & Services → Credentials** and create an **OAuth client ID** of type *Web application*.
2. Add this **Authorized redirect URI**: `http://localhost:8000/auth/google/callback` (production: `https://api.example.com/auth/google/callback`).
3. Put the client ID and secret into `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` in `backend/.env`.
4. Add your own email to `ADMIN_EMAILS` to become an admin.

How sign-in works: the frontend sends the browser to `GET /auth/google/redirect`. The API's callback then redirects to `FRONTEND_URL/auth/callback?code=…` with a single-use code (valid for 60 s). The frontend exchanges that code with `POST /api/auth/exchange` to create the Sanctum session cookie. Because the frontend's own request creates the cookie, sign-in also works inside installed PWAs.

## Checks

```bash
cd backend  && php artisan test && ./vendor/bin/pint --test
cd frontend && npm run typecheck && npm run lint && npm run build
```
