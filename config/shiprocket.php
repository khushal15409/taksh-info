<?php
return [
    'base_url' => env('SHIPROCKET_BASE_URL', 'https://apiv2.shiprocket.in/v1/external'),
    'email' => env('SHIPROCKET_EMAIL'),
    'password' => env('SHIPROCKET_PASSWORD'),
    // Seconds.
    'timeout' => (int) env('SHIPROCKET_TIMEOUT', 30),
    'connect_timeout' => (int) env('SHIPROCKET_CONNECT_TIMEOUT', 10),
    'token_cache_key' => env('SHIPROCKET_TOKEN_CACHE_KEY', 'shiprocket:auth:token'),
    'token_ttl_minutes' => (int) env('SHIPROCKET_TOKEN_TTL_MINUTES', 60 * 24 * 9),
    'token_safety_buffer_minutes' => (int) env('SHIPROCKET_TOKEN_SAFETY_BUFFER_MINUTES', 60),
    'platform_charge' => env('SHIPROCKET_PLATFORM_CHARGE'),
    'pickup_location' => env('SHIPROCKET_PICKUP_LOCATION'),
    'default_declared_value' => env('SHIPROCKET_DEFAULT_DECLARED_VALUE'),
    'default_item_name' => env('SHIPROCKET_DEFAULT_ITEM_NAME', 'Courier parcel'),
    'webhook_secret' => env('SHIPROCKET_WEBHOOK_SECRET'),
    'tracking_sync_interval_minutes' => (int) env('SHIPROCKET_TRACKING_SYNC_INTERVAL_MINUTES', 25),
    'tracking_sync_limit' => (int) env('SHIPROCKET_TRACKING_SYNC_LIMIT', 200),
    'refund_policy' => env('SHIPROCKET_REFUND_POLICY'),
];
