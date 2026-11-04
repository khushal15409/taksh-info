<?php

return [

    // Where the shared product/banner/category images live. taksh-admin stores a path like
    // "banner/xyz.jpg" in the database; the site builds <media_url>/<path>. Full URLs are used as they are.
    'media_url' => env('TAKSH_MEDIA_URL', 'https://taksh-admin.takshallinone.in/storage'),

    // Seconds the standard dashboard is kept before it is read from the database again (0 = never cache).
    'dashboard_cache_seconds' => (int) env('TAKSH_DASHBOARD_CACHE', 60),

    // Where files a customer uploads (return photos) are stored. Point it at the SAME disk/bucket taksh-admin reads
    // (e.g. a shared s3/gcs disk); the default local "public" disk only works when admin reads that same folder.
    'media_disk' => env('TAKSH_MEDIA_DISK', 'public'),

    // taksh-admin's base URL; the invoice PDF is rendered by taksh-admin (the template and its tax rules live there).
    'admin_url' => env('TAKSH_ADMIN_URL', 'https://taksh-admin.takshallinone.in'),

    // Customer/vendor push for new orders is sent by taksh-admin's queue; the website does not send it (see docs). Set to true only
    // after pointing QUEUE_CONNECTION at the queue taksh-admin's worker reads, so the SendPushNotification job can be handed over.
    'order_push' => (bool) env('TAKSH_ORDER_PUSH', false),

    // Shown in the language & theme settings (the app's "app version" line).
    'version' => env('TAKSH_WEB_VERSION', '1.0.0'),

    // Where customers reach Taksh (the help links on order pages, the footer).
    // Hide a quick product the admin switched off (Express 30 status = inactive). Off: it is listed, as the Taksh app lists it.
    'quick_require_active' => (bool) env('TAKSH_QUICK_REQUIRE_ACTIVE', false),
    'support_phone' => env('TAKSH_SUPPORT_PHONE', '+91 99999 12345'),
    'support_email' => env('TAKSH_SUPPORT_EMAIL', 'care@takshallinone.com'),
];
