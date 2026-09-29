# Nexus Flow – approved decisions

- Monorepo: `backend/` (Laravel, Pest, Pint) and `frontend/` (Next.js App Router, TS, Tailwind).
- Job postings table is `job_posts` (model `JobPost`); `jobs` is Laravel's queue table.
- Auth: Google only. OAuth callback redirects to the frontend with a single-use, 60s code; the frontend calls `POST /api/auth/exchange` to create the Sanctum session (works inside installed PWAs).
- Admins are designated by `ADMIN_EMAILS`; role is re-synced on every login.

## Who sees what
- Employers see ONLY applicants to their own jobs: profile + CV. No documents, no Profile PDF, no candidate search.
- The "customer" feature (employers allowed to search all candidates / download packs) is REMOVED. Only admins search all candidates and download documents, Profile PDFs and bulk ZIPs.

## Categories
- Two levels: groups **Skilled** and **Unskilled** (no Semi-skilled), each containing trades (Electrician, Welder, …). `job_categories.parent_id` null = group.
- Candidates pick up to 3 trades; jobs use one trade. Admin search shows counts per trade. Admin can edit the list.

## Profile PDF (Option B)
- A candidate provides the Profile PDF EITHER by uploading one ready-made PDF (max 50 MB) OR by uploading documents one by one, in which case the system builds it (cover → CV → Aadhaar → SSLC → education → skill → experience certs → all passport pages; DomPDF + qpdf).
- Show this instruction next to the upload button: "Upload a profile PDF that includes your CV, photo, Aadhaar card, all pages of your passport (30 or 60), SSLC book, other qualification certificates and experience certificates."
- Photo is always uploaded separately (shown in search results).
- CV means BOTH: the auto-generated CV from profile data and an optional CV file uploaded by the candidate.
- If a stale generated pack is requested, build it during the request (Option A); observers also rebuild in the background after changes.

## Bulk download (admin only)
- Up to 50 candidates per ZIP; larger selections are split into several ZIPs. Queued job; UI polls status.
- ZIP layout: one folder per candidate with `Profile.pdf` and `CV.pdf` (plus uploaded CV if any), and a spreadsheet (CSV, Excel-compatible) listing the selected applicants: name, trade, phone, passport number and expiry, experience, district.
- Exports expire after 24h. Every download is audit-logged.

## Other
- HEIC: converted client-side; server converts only if Imagick with HEIC support exists, otherwise a friendly rejection.
- Account deletion: immediate hard delete of profile, files, applications and packs; audit rows keep only the candidate ID.
- Locations: structured states/districts for India and structured regions for Russia; free-text city for everything else.
- Jobs from approved employers go live immediately (admin can hide). Up to 3 preferred countries per candidate.
- Tests run on SQLite in-memory; FULLTEXT is MySQL-only with a LIKE fallback. CI runs against MySQL 8.
- Never cache API responses, documents or personal data in the service worker.

## Git workflow
- One branch + PR per milestone (`milestone/N-name`) into `main`; squash merge; never commit directly to `main`.
