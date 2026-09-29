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

];
