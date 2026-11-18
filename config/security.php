<?php

return [
    /*
    | Content-Security-Policy mode: "enforce" (default), "report-only" (the browser only logs what it would block - handy to test a
    | new third party without breaking pages) or "off".
    */
    'csp' => env('SECURITY_CSP', 'enforce'),

    // Strict-Transport-Security is only sent on https requests.
    'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
];
