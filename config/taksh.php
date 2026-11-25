<?php

return [

    // Search / filter terms never show in the address bar: a form POSTs them and the page URL carries only `?f=<opaque token>`
    // (App\Support\FilterToken: encrypted + tamper-proof, no session needed, so a copied URL still works). `scopes` lists, per page, the
    // keys that are packed into the token; any other query key (quick=1, tab=orders, page=2) stays visible. Off = plain ?q=... again.
    'hide_filters' => [
        'enabled' => (bool) env('TAKSH_HIDE_FILTERS', true),
        'scopes' => [
            'listing' => ['q', 'category', 'max', 'pincode', 'location'],
            'shops' => ['q'],
            'shop' => ['q', 'category', 'min', 'max'],
            'orders' => ['status', 'mode', 'from', 'to'],
            'notifications' => ['filter'],
            'services' => ['category'],
            'payresult' => ['status', 'reason'],
        ],
        // kept visible when a form is POSTed (the page's mode/tab, not a search term)
        'carry' => ['quick', 'tab'],
    ],

    // Opaque page URLs: ids in page URLs (/shops/relish-k3x9..., /tracking?id=...) are keyed codes, not row numbers. `enabled` off = plain
    // numeric ids again (instant rollback). `legacy_numeric` = old numeric links keep working (a GET is redirected to the opaque URL;
    // ownership checks still apply). Turn legacy off once old links are no longer needed.
    'opaque_urls' => [
        'enabled' => (bool) env('TAKSH_OPAQUE_URLS', true),
        'legacy_numeric' => (bool) env('TAKSH_LEGACY_NUMERIC_URLS', true),
    ],

    // Where the shared product/banner/category images live. taksh-admin stores a path like
    // "banner/xyz.jpg" in the database; the site builds <media_url>/<path>. Full URLs are used as they are.
    'media_url' => env('TAKSH_MEDIA_URL', 'https://taksh-admin.takshallinone.in/storage'),

    // Seconds the standard dashboard is kept before it is read from the database again (0 = never cache).
    'dashboard_cache_seconds' => (int) env('TAKSH_DASHBOARD_CACHE', 60),

    // Where files a customer uploads (return photos) are stored. Point it at the SAME disk/bucket taksh-admin reads
    // (e.g. a shared s3/gcs disk); the default local "public" disk only works when admin reads that same folder.
    'media_disk' => env('TAKSH_MEDIA_DISK', 'public'),

    // taksh-admin's base URL; the invoice PDF is rendered by taksh-admin (the template and its tax rules live there).
    // (an empty TAKSH_ADMIN_URL in .env falls back to the live admin instead of an unusable blank address)
    'admin_url' => env('TAKSH_ADMIN_URL') ?: 'https://taksh-admin.takshallinone.in',
    // shared secret the website sends (X-Site-Key) when it calls taksh-admin for a signed-in customer; the same value is in taksh-admin's .env
    'site_api_key' => env('TAKSH_SITE_API_KEY'),

    // Customer/vendor push for new orders is sent by taksh-admin's queue; the website does not send it (see docs). Set to true only
    // after pointing QUEUE_CONNECTION at the queue taksh-admin's worker reads, so the SendPushNotification job can be handed over.
    'order_push' => (bool) env('TAKSH_ORDER_PUSH', false),

    // Shown in the language & theme settings (the app's "app version" line).
    'version' => env('TAKSH_WEB_VERSION', '1.0.0'),

    // Where customers reach Taksh (the help links on order pages, the footer). Fixed here on purpose, not read from .env:
    // a stale TAKSH_SUPPORT_* variable in the server's environment overrides .env and kept showing the old details.
    // The app's "Live Chat" opens WhatsApp on this number (country code + number, no +)
    'live_chat_whatsapp' => '918982893690',
    'support_phone' => '+91 89828 93690',
    'support_address' => 'Sendhwa, Madhya Pradesh, India',
    'support_email' => 'customersupport@takshallinone.in',
];
