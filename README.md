# Nexus Flow

Mobile-first job portal PWA for blue-collar and skilled-trade hiring. It pairs a
Laravel REST API with a Next.js Progressive Web App and is designed to work well
on low-end Android phones and slow networks.

This repository is a monorepo:

| Path        | Stack                                                                 |
|-------------|-----------------------------------------------------------------------|
| `backend/`  | Laravel 13 REST API (PHP 8.4, Sanctum cookie SPA auth, Socialite Google, queued jobs). |
| `frontend/` | Next.js 16 PWA (App Router, TypeScript, Tailwind, SWR, service worker). |

> Naming note: job postings live in the `job_posts` table. The `jobs` table is
> Laravel's own queue table (`QUEUE_CONNECTION=database`), not a business table.

## Prerequisites

- **PHP 8.4+** with the `gd`, `intl`, `pdo_mysql`, and `zip` extensions, plus **Composer 2**.
- **Node.js 22** and **npm**.
- **MySQL 8** (dev and production; the test suite uses in-memory SQLite).
- **qpdf**. This is **required**, not optional:
  - It generates the Candidate Pack PDFs (`BuildCandidatePack`, `BuildBulkPackZip`).
  - It backs the encrypted-PDF check in the document upload validator. The validator
    **fails closed**: if qpdf is missing or a PDF cannot be verified, the upload is
    **rejected** rather than accepted. A production host without qpdf will reject
    every PDF upload.

## Repository layout

```
backend/
  app/Http/Controllers/Api/{Auth,Candidate,Employer,Admin}
  app/Jobs/{BuildCandidatePack,BuildBulkPackZip}.php   # queued pack builders
  app/Services/Documents/{UploadValidator,PdfInspector}.php
  app/Providers/AppServiceProvider.php                 # rate limiting + prod session guard
  config/{session,filesystems,sanctum}.php
  database/migrations, database/seeders/{DatabaseSeeder,LookupSeeder}.php
  database/data/{countries.json,regions.php}
  deploy/php.ini.sample                                # production body-size overrides
  routes/{api.php,web.php}
frontend/
  src/app/    # App Router: (public)/, admin/, candidate/, employer/, auth/
  src/components/ui, src/lib   # shared UI + SWR data hooks
  public/sw.js                 # service worker
```

## Environment variables

### Backend (`backend/.env`, see `backend/.env.example`)

| Variable | Purpose |
|----------|---------|
| `APP_URL` | Public URL of the API. Dev: `http://localhost:8000`. Prod: `https://api.example.com`. |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | MySQL connection. |
| `FRONTEND_URL` | Frontend origin(s), comma-separated. Used for CORS and OAuth redirects. Prod: `https://app.example.com`. |
| `SANCTUM_STATEFUL_DOMAINS` | Host(s) (no scheme) that receive Sanctum session cookies. Dev: `localhost:3000`. Prod: `app.example.com`. |
| `SESSION_DRIVER` | `database`. |
| `SESSION_LIFETIME` | Session lifetime in minutes (default `720`). |
| `SESSION_ENCRYPT` | `true` (session payloads are encrypted). |
| `SESSION_DOMAIN` | Cookie domain. Dev: `null`. Prod: `.example.com` (leading dot shares the cookie across sibling subdomains). |
| `SESSION_SECURE_COOKIE` | `false` in dev, **`true` in production**. |
| `SESSION_SAME_SITE` | `lax`. |
| `ADMIN_EMAILS` | Comma-separated Google account emails that become platform admins. The role is re-synced on every login. |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Google OAuth Web application credentials. |
| `GOOGLE_REDIRECT_URI` | OAuth callback. Defaults to `${APP_URL}/auth/google/callback`. |
| `QUEUE_CONNECTION` | `database`. Drives the queued pack builders. |
| `FILESYSTEM_DISK` | `local` in dev. Use S3-compatible storage for private documents in production. |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT` | S3-compatible private document storage (production). |

> **Production session guard.** When `APP_ENV=production`, the app aborts on boot
> unless the session/cookie configuration is safe. `AppServiceProvider::boot()`
> throws a `RuntimeException` if any of the following is not set:
> `SESSION_SECURE_COOKIE` is not `true`, `SESSION_DOMAIN` is empty, or
> `SANCTUM_STATEFUL_DOMAINS` is empty. Misconfiguration therefore fails fast at
> deploy time rather than silently breaking cookie auth.

### Frontend (`frontend/.env.local`, see `frontend/.env.example`)

| Variable | Purpose |
|----------|---------|
| `NEXT_PUBLIC_API_URL` | Laravel API base URL, no trailing slash. Dev: `http://localhost:8000`. Prod: `https://api.example.com`. |
| `NEXT_PUBLIC_SITE_URL` | This frontend's public URL. Dev: `http://localhost:3000`. Prod: `https://app.example.com`. |

## Google OAuth setup

Sign-in is Google-only.

1. In the [Google Cloud Console](https://console.cloud.google.com), open
   **APIs & Services -> Credentials** and create an **OAuth client ID** of type
   **Web application**.
2. Add an **Authorized redirect URI** that exactly matches `GOOGLE_REDIRECT_URI`,
   which defaults to `${APP_URL}/auth/google/callback`:
   - Dev: `http://localhost:8000/auth/google/callback`
   - Prod: `https://api.example.com/auth/google/callback`
3. Copy the client ID and secret into `GOOGLE_CLIENT_ID` and
   `GOOGLE_CLIENT_SECRET` in `backend/.env`.
4. Add your Google account email to `ADMIN_EMAILS` to become an admin. The admin
   role is re-synced from `ADMIN_EMAILS` on every login.

## Database: migrate and seed

```bash
cd backend
php artisan migrate --seed
```

The `--seed` flag runs `DatabaseSeeder`, which calls `LookupSeeder`. The seeder is
idempotent (safe to re-run in production) and populates the lookup lists:

- **Countries** (from `database/data/countries.json`), with India and Russia pinned
  to the top of pickers.
- **Regions** for India and Russia (states/regions), and **districts for Kerala**
  (from `database/data/regions.php`).
- **Job categories / trades** in two groups: **Skilled** (Electrician, Welder,
  Carpenter, Plumber, Mason, and more) and **Unskilled** (General Labourer, Helper,
  Packer, Cleaner, and more).
- **Education levels** (Below 10th through Doctorate).
- **Languages** (English, Malayalam, Hindi, Tamil, and other Indian languages plus
  Arabic, Russian, French, German).

## Queue worker

Candidate Pack rebuilds (`BuildCandidatePack`) and bulk ZIP exports
(`BuildBulkPackZip`) run as queued jobs on the `database` queue connection. Start a
worker so these are processed:

```bash
cd backend
php artisan queue:work
```

In production, run the worker under **Supervisor** so it stays alive and restarts
on failure.

## Running both apps locally

### Backend API (http://localhost:8000)

```bash
cd backend
cp .env.example .env          # fill in DB_*, GOOGLE_*, ADMIN_EMAILS
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve             # serves the API on :8000
```

Run the queue worker in a second terminal so pack builds are processed:

```bash
cd backend
php artisan queue:work
```

### Frontend (http://localhost:3000)

The frontend requires Node 22. Set the PATH before any npm command:

```bash
cd frontend
export PATH=/root/.nvm/versions/node/v22.23.3/bin:$PATH
cp .env.example .env.local    # NEXT_PUBLIC_API_URL, NEXT_PUBLIC_SITE_URL
npm install
npm run dev                   # serves the PWA on :3000
```

## Authentication model

Sign-in is **Google-only** and uses a single-use-code exchange so it works inside
installed PWAs:

1. The frontend sends the browser to `GET /auth/google/redirect` on the API.
2. Google redirects back to `GET /auth/google/callback`
   (`GoogleAuthController`), which resolves the account, syncs the admin role from
   `ADMIN_EMAILS`, mints a **single-use code valid for 60 seconds**, and redirects
   to the frontend.
3. The frontend calls `POST /api/auth/exchange` with that code to create the
   **Sanctum session cookie**.

Because the cookie is set by the frontend's own request to the API (rather than by
a cross-site redirect), the session survives inside an installed PWA, where
third-party redirect cookies are unreliable. This is why the exchange step exists.

## Production deployment

### Backend on a Linux VPS

Run Laravel behind **Nginx + PHP-FPM** with **MySQL 8**, a **Supervisor**-managed
queue worker, and **qpdf** installed on the host.

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Configure the private document disk to S3-compatible storage via the `AWS_*`
  variables and keep `FILESYSTEM_DISK` pointed at it.
- Start the queue worker under Supervisor: `php artisan queue:work`.

### Frontend on Vercel

Deploy `frontend/` to Vercel and set `NEXT_PUBLIC_API_URL` and
`NEXT_PUBLIC_SITE_URL` to the production URLs.

### Alternative: cPanel shared hosting (CloudLinux)

Both apps can run on one cPanel account, e.g. `api.example.com` (Laravel) and
`example.com` (Next.js). Shared hosts often have an old glibc and per-account
process limits, so **the frontend is never built on the server**: GitHub
Actions builds it and the host only runs the result.

**API (Laravel).** Clone the repo into your home directory (not a web root), then
from `backend/` using the host's PHP 8.4 binary (e.g.
`/opt/cpanel/ea-php84/root/usr/bin/php`):

```bash
php /usr/local/bin/composer install --no-dev --optimize-autoloader
cp .env.example .env   # fill in DB_*, FRONTEND_URL, SESSION_*, GOOGLE_*, ADMIN_EMAILS
php artisan key:generate
php artisan migrate --force --seed
php artisan storage:link && php artisan config:cache && php artisan route:cache
```

Point the API subdomain's document root at `backend/public` (a symlink from the
cPanel docroot works). Run the queue from a once-per-minute cron job:
`php artisan queue:work --stop-when-empty --max-time=50`. If qpdf is a
home-directory build, set `QPDF_BINARY` and `QPDF_LD_LIBRARY_PATH`. After any
`.env` change, run `php artisan config:cache` again.

**Frontend (Next.js).** Every push to `main` that touches `frontend/` runs
`.github/workflows/deploy-frontend.yml`, which builds a standalone bundle and
force-pushes it to the `deploy-frontend` branch. `NEXT_PUBLIC_API_URL` and
`NEXT_PUBLIC_SITE_URL` are baked in at build time; set them as repository
**Variables** (Settings -> Secrets and variables -> Actions) to override the
defaults in the workflow.

On the host:

```bash
git clone --branch deploy-frontend --single-branch https://github.com/OWNER/REPO.git ~/nexusflow-web
```

In **Setup Node.js App** create an app with Node 22, mode Production,
application root `nexusflow-web`, your domain as the URL, and startup file
`app.js`. Do not run "NPM Install": the bundle ships its own trimmed
`node_modules` inside `bundle/`.

To update the site: `cd ~/nexusflow-web && git fetch origin deploy-frontend && git reset --hard origin/deploy-frontend`,
then click **Restart** on the Node.js app.

### Sibling subdomains are required for cookie auth

The frontend and API **must** be served from sibling subdomains of the same parent
domain, for example `app.example.com` (frontend) and `api.example.com` (API). The
Sanctum session cookie is shared across them via a parent-scoped cookie domain.
Configure the API accordingly:

- `SESSION_DOMAIN=.example.com` (leading dot shares the cookie across subdomains)
- `SESSION_SECURE_COOKIE=true`
- `SANCTUM_STATEFUL_DOMAINS=app.example.com`
- `FRONTEND_URL=https://app.example.com` (drives CORS and OAuth redirects)

> **Production session guard.** If `APP_ENV=production` and any of
> `SESSION_SECURE_COOKIE`, `SESSION_DOMAIN`, or `SANCTUM_STATEFUL_DOMAINS` is
> missing or insecure, the app throws on boot. This is intentional: a misconfigured
> deploy fails fast instead of silently breaking authentication.

### Body-size limits for 50 MB uploads

The largest accepted upload is a 50 MB Profile PDF, so every layer must accept a
body at least that large:

- **PHP** (`php.ini` or a `conf.d/*.ini` include): `upload_max_filesize >= 50M` and
  `post_max_size >= 50M` (`post_max_size` must exceed `upload_max_filesize` to leave
  headroom for other fields). See `backend/deploy/php.ini.sample` for ready-to-copy
  values.
- **Nginx**: `client_max_body_size 50m;` in the server or location block.

## PWA install testing

Nexus Flow is an installable PWA. Verify install on real devices:

- **Android (Chrome):** open the frontend, then either tap the in-app **Install app**
  button on the home page or use the browser menu (three dots -> **Install app**).
- **iOS (Safari):** tap the **Share** icon, then choose **Add to Home Screen**.

To confirm installability during development, open **Chrome DevTools ->
Application -> Manifest** and check that the manifest loads without errors and the
service worker is registered. See `frontend/README.md` for the same install notes.

## Verification checks

All five checks must pass. Set the Node 22 PATH before the frontend commands:

```bash
# Backend
cd backend
php artisan test
./vendor/bin/pint --test

# Frontend (Node 22)
cd frontend
export PATH=/root/.nvm/versions/node/v22.23.3/bin:$PATH
npm run typecheck
npm run lint
npm run build
```
