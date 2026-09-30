<?php

return [

    /*
    | Base URL of the Next.js frontend. OAuth callbacks redirect here.
    | The first entry is used when FRONTEND_URL contains several origins.
    */
    'frontend_url' => rtrim(trim(explode(',', (string) env('FRONTEND_URL', 'http://localhost:3000'))[0]), '/'),

    /*
    | Comma-separated list of Google account emails that are platform admins.
    */
    'admin_emails' => array_values(array_filter(array_map(
        fn (string $email) => strtolower(trim($email)),
        explode(',', (string) env('ADMIN_EMAILS', ''))
    ))),

    /*
    | Lifetime (seconds) of the single-use login code handed to the frontend.
    */
    'login_code_ttl' => (int) env('LOGIN_CODE_TTL', 60),

    /*
    | Version of the candidate document-consent text currently in force.
    | Stored alongside the consent timestamp so we know which text was agreed to.
    */
    'consent_version' => (string) env('CONSENT_VERSION', '1.0'),

    /*
    | Path to the qpdf binary used to inspect uploaded PDFs (encryption
    | status and page count). Defaults to the binary on the system PATH.
    */
    'qpdf_path' => env('QPDF_PATH', 'qpdf'),

    /*
    | qpdf binary used by the merger and inspector. Defaults to QPDF_BINARY,
    | falling back to the legacy QPDF_PATH so existing deployments keep
    | working. On shared hosting (cPanel) qpdf is often a home-directory
    | build that lives outside the system PATH: point QPDF_BINARY at that
    | absolute path (e.g. /home/USER/bin/qpdf10/bin/qpdf).
    */
    'qpdf_binary' => env('QPDF_BINARY', env('QPDF_PATH', 'qpdf')),

    /*
    | Optional shared-library path for a home-directory qpdf build. When set
    | it is exported as LD_LIBRARY_PATH on the qpdf child process only, so a
    | self-compiled qpdf can find its own libqpdf .so files without touching
    | the system loader configuration. Leave null to use the system loader.
    */
    'qpdf_library_path' => env('QPDF_LD_LIBRARY_PATH', null),

    /*
    | Delay (seconds) before a queued candidate-pack rebuild runs after a
    | profile or document change. A small delay lets rapid successive edits
    | settle so the pack is usually ready before anyone asks for it.
    */
    'pack_rebuild_delay' => (int) env('PACK_REBUILD_DELAY', 10),

    /*
    | Admin bulk ZIP export limits. `max_candidates` caps how many candidates
    | a single export may include (enforced server-side in the request and
    | mirrored by the UI batching). `ttl_hours` is how long a finished ZIP
    | stays downloadable before the scheduled purge removes it. Both values
    | are single-sourced from here.
    */
    'bulk_export' => [
        'max_candidates' => (int) env('BULK_EXPORT_MAX', 50),
        'ttl_hours' => (int) env('BULK_EXPORT_TTL_HOURS', 24),
    ],

];
