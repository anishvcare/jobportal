# Nexus Flow — production deployment status (nexusflowservices.com)

Read this alongside `project-decisions.md` before touching deployment. This
file exists so work can hand off cleanly between sessions/accounts. Update it
whenever deployment state changes — it should always reflect reality, not a
plan.

## Server facts

- **Host:** cPanel shared hosting, CloudLinux EL8 (`glibc 2.28`), account
  `egwdlagf` on `s803.bom1.mysecurecloudhost.com`.
- **Domain:** `nexusflowservices.com`, DNS/subdomains managed in this cPanel.
- **PHP 8.4 binary:** `/opt/cpanel/ea-php84/root/usr/bin/php` (the CLI default
  `php` is 8.3 — always use the explicit 8.4 path, or `alias php84=...`).
- **Database:** MariaDB 10.6, database `egwdlagf_amarizzjob`, user
  `egwdlagf_amarizzjob` with ALL PRIVILEGES on it. Password is in the
  server's `backend/.env` only — not recorded here.
- **qpdf:** no system package (no root/sudo). A self-contained qpdf 10.6.3
  build lives at `/home/egwdlagf/bin/qpdf10/` (the *only* qpdf line that
  runs on this host's glibc — 12.x needs glibc 2.34+ and fails). Configured
  in `backend/.env`:
  ```
  QPDF_BINARY=/home/egwdlagf/bin/qpdf10/bin/qpdf
  QPDF_LD_LIBRARY_PATH=/home/egwdlagf/bin/qpdf10/bin
  ```
  Verified working: merges PDFs and detects encryption correctly (see
  `QPDF_BINARY`/`QPDF_LD_LIBRARY_PATH` support added in PR #8, kept through
  the Laravel 13 restore in PR #10).
- **Node:** managed per-app by cPanel "Setup Node.js App" (CloudLinux Node
  selector), not a system Node on PATH. Node 22 venv for the (old, about to
  be replaced — see Frontend below) app is at
  `/home/egwdlagf/nodevenv/amarizzjob-api/frontend/22/`.

## Repo layout on the server

- **Backend app:** cloned at `~/amarizzjob-api` (this GitHub repo,
  `main` branch). This is a normal `git clone`, not the deploy mechanism —
  to update, `git pull` and re-run composer/artisan steps below.
- **Frontend bundle:** cloned at `~/nexusflow-web`, but from the
  **`deploy-frontend`** branch, not `main` (see "Why the frontend is built
  on GitHub" below).

## Backend (API) — LIVE and verified working

`api.nexusflowservices.com` document root is a symlink to
`~/amarizzjob-api/backend/public` (with `.well-known` copied back in for
SSL). Confirmed via curl: `/up` → 200 "Application up",
`/api/public/lookups` → real JSON (countries, trades, etc.).

Done:
- `composer install --no-dev --optimize-autoloader` (PHP 8.4)
- `.env` configured: DB, `SESSION_DOMAIN=.nexusflowservices.com`,
  `SESSION_SECURE_COOKIE=true`, `SANCTUM_STATEFUL_DOMAINS`, `FRONTEND_URL`,
  qpdf vars (above)
- `php artisan key:generate`, `migrate --force --seed` (all 14 migrations +
  `LookupSeeder` ran clean), `storage:link`, `config:cache`, `route:cache`,
  `view:cache`
- Queue worker: cron `* * * * * .../artisan queue:work --stop-when-empty
  --max-time=50 >> storage/logs/queue.log 2>&1` — confirmed firing every
  minute in the log.

**Remember:** any `.env` edit requires `php artisan config:cache` again
(config is cached) — this bites people, always re-run it after editing `.env`.

**Not yet done (blocks real login):**
- `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` in `.env` are still
  placeholders. Redirect URI to register in Google Cloud Console:
  `https://api.nexusflowservices.com/auth/google/callback`.
- `ADMIN_EMAILS` in `.env` is still a placeholder — set it to the Google
  account that should be admin.
- After setting both, `php artisan config:cache` again, then test a real
  Google sign-in end to end.

## Frontend — bundle exists, cPanel Node app NOT yet repointed

### Why the frontend is built on GitHub, not on the server

Building Next.js directly on this host **fails**, for two independent
reasons specific to this host (not a code bug):
1. Next's native compiler (SWC/Turbopack) needs glibc ≥ 2.29; this host has
   2.28. It falls back to a slow WASM compiler.
2. CloudLinux's per-account process limits refuse the build's worker
   processes (`spawn ... node EAGAIN`), crashing the build outright.

The fix (merged in PR #11, `.github/workflows/deploy-frontend.yml`): every
push to `main` touching `frontend/**` builds the site on GitHub Actions
(`output: "standalone"` in `next.config.ts`) and **force-pushes a ready
to run bundle to the `deploy-frontend` branch** — `app.js` (Passenger
startup shim) + `bundle/` (Next's standalone server + its own trimmed
`node_modules`, ~26 MB) + `DEPLOY_INFO.txt`. `NEXT_PUBLIC_API_URL` /
`NEXT_PUBLIC_SITE_URL` are baked in at build time (defaults point at
`nexusflowservices.com`; override via repo Settings → Secrets and
variables → Actions → **Variables** if the domain ever changes).

**The server never runs `npm install` or `npm run build`.** Updating the
live site is always: merge to `main` → wait for the Action → on the server,
`git fetch origin deploy-frontend && git reset --hard
origin/deploy-frontend` in `~/nexusflow-web` → restart the Node app in
cPanel.

### Current state (as of this handoff)

- PR #11 merged, the Action ran successfully, `deploy-frontend` branch
  exists with the expected files (verified: `DEPLOY_INFO.txt` shows the
  right commit + `api: https://api.nexusflowservices.com` /
  `site: https://nexusflowservices.com`).
- `~/nexusflow-web` cloned on the server from that branch — confirmed same
  commit/URLs.
- A manual smoke test (`node app.js` on a scratch port, borrowing the old
  Node 22 venv at `~/nodevenv/amarizzjob-api/frontend/22/` just to get a
  `node` binary) was **started but not confirmed** — the user was mid-test
  (about to curl `/`, `/jobs`, `/sw.js` expecting 200) when this handoff
  happened. This exact bundle was already verified end-to-end in the dev
  sandbox (all routes 200, manifest/SW/icons served, API URL correctly
  baked into the client JS) before merging PR #11, so it is expected to work
  — but the on-server curl check was never pasted back. **Re-run or confirm
  that smoke test first**, then move on.

### Immediate next step — repoint the cPanel Node.js App

The cPanel **Setup Node.js App** entry still points at the **old** location
from before the standalone-bundle approach:
- Application root: `amarizzjob-api/frontend` ← **stale, wrong now**
- Startup file: `server.js` ← **stale, wrong now**

It needs to be changed to:
- **Application root:** `nexusflow-web`
- **Startup file:** `app.js`
- Node version: `22`, mode: `Production` (unchanged)
- Env vars `NEXT_PUBLIC_API_URL` / `NEXT_PUBLIC_SITE_URL` can stay (harmless
  — already baked into the bundle too)
- **Do not** click "Run NPM Install" — the bundle carries its own
  `node_modules` inside `bundle/`.

After saving, click **Restart**, then load `https://nexusflowservices.com`
and confirm the home page renders.

Once that's confirmed live, the old `~/amarizzjob-api/frontend` folder and
its `nodevenv` are no longer needed for the live site (the backend repo
clone `~/amarizzjob-api` itself is still needed — that's the API).

## Git / PR conventions used throughout (keep following these)

- One branch + PR per change, **squash merge only** (repo settings enforce
  this — only "Squash and merge" is enabled).
- CI (`.github/workflows/ci.yml`) runs backend tests on SQLite **and**
  MySQL 8, plus Pint, and frontend typecheck/lint/build. Wait for all green
  before merging.
- Never commit directly to `main`.
- `main` is currently Laravel 13 / PHP 8.4 (PR #10 restored this after a
  brief, since-reverted PHP 8.2 / Laravel 11 detour in PR #8 — that detour
  is historical only, do not repeat it; PHP 8.4 is confirmed available and
  in use on this host).

## Suggested order for the next session

1. Confirm the frontend smoke test (curl 200s on `/`, `/jobs`, `/sw.js`)
   from `~/nexusflow-web`, or just proceed — it was already verified
   pre-merge.
2. Repoint + restart the cPanel Node.js App (above). Load the live site.
3. Set real Google OAuth credentials + `ADMIN_EMAILS` in the backend
   `.env`, `config:cache`, test a real sign-in.
4. Do a light smoke pass over production: home/jobs pages render, sign-in
   works, candidate can reach the profile wizard, admin dashboard loads.
5. Fill in the placeholder About/Contact/Privacy/Terms copy with real
   company details before public launch.
