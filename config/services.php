<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // apitxt.com OTP text messages (same gateway and .env keys as taksh-admin).
    'apitxt' => [
        'base_url' => env('APITXT_BASE_URL', 'https://apitxt.com/api'),
        'authkey' => env('APITXT_AUTHKEY'),
        'country_code' => env('APITXT_COUNTRY_CODE', '91'),
        'timeout' => (int) env('APITXT_TIMEOUT', 36),
    ],

    // Place search / reverse lookup for the address map (OpenStreetMap Nominatim). Keep the contact e-mail set: their usage policy asks for it.
    'nominatim' => [
        'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),
        'contact' => env('NOMINATIM_CONTACT', 'care@takshallinone.com'),
        'country' => env('NOMINATIM_COUNTRY', 'in'),
        'timeout' => (int) env('NOMINATIM_TIMEOUT', 8),
    ],

    // Razorpay (same keys as taksh-admin). There is no mock mode on the website: a signature is always really verified.
    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'timeout' => (int) env('RAZORPAY_TIMEOUT', 20),
        // Refund a paid online order through Razorpay when the customer cancels it. Off by default: until it is switched on a
        // cancelled paid order keeps its `paid` status and is flagged for a manual refund.
        'refund_on_cancel' => env('RAZORPAY_REFUND_ON_CANCEL', false),
    ],

];
