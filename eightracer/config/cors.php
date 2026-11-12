<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', env(
        'FRONTEND_URLS',
        env('APP_ENV') === 'production' ? '' : 'http://localhost:5173,http://127.0.0.1:5173,http://localhost:3000,http://127.0.0.1:3000'
    ))))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept'],
    'exposed_headers' => [],
    'max_age' => 600,
    // Next.js BFF forwards Bearer tokens; cross-origin Laravel cookies are not used.
    'supports_credentials' => false,
];
