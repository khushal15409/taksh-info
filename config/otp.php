<?php

// Same keys and defaults as the Taksh API (taksh-admin) so a code behaves identically on web and mobile.
return [

    'length' => (int) env('AUTH_OTP_LENGTH', 4),

    'expires_in_minutes' => (int) env('AUTH_OTP_EXPIRES_MINUTES', 5),

    // Throttles (per mobile number). `send` mirrors the API; `verify` guards the short code against guessing.
    'send_attempts_per_minute' => (int) env('AUTH_OTP_SEND_PER_MINUTE', 5),
    'verify_attempts_per_minute' => (int) env('AUTH_OTP_VERIFY_PER_MINUTE', 5),

];
