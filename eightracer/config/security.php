<?php
return [
    'require_https' => (bool) env('API_REQUIRE_HTTPS', false),
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),
    'mail_capture_only' => (bool) env('MAIL_CAPTURE_ONLY', false),
    'password_reset_enabled' => (bool) env('PASSWORD_RESET_ENABLED', false),
    'password_reset_url' => env('PASSWORD_RESET_FRONTEND_URL'),
    'clamav_endpoint' => env('CLAMAV_ENDPOINT', 'tcp://127.0.0.1:3310'),
    'clamav_timeout' => 10,
    'upload_bytes' => 5 * 1024 * 1024,
    'import_max_rows' => 2000,
];
