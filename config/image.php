<?php

return [
    'disk' => env('IMAGE_DISK', 'public'),
    'originals_disk' => env('IMAGE_ORIGINALS_DISK', 'local'),
    'format' => env('IMAGE_FORMAT', 'webp'),
    'quality' => (int) env('IMAGE_QUALITY', 80),

    'paths' => [
        'restaurants' => 'restaurants',
        'menu_items' => 'menu-items',
        'qrcodes' => 'qrcodes',
        'originals' => 'originals',
    ],

    'main' => ['width' => 800],
    'thumb' => ['width' => 400],

    'preprocess' => [
        'max_width' => (int) env('IMAGE_PREPROCESS_WIDTH', 2400),
        'format' => 'webp',
        'quality' => 85,
    ],

    'preflight' => [
        'enabled' => (bool) env('IMAGE_PREFLIGHT_ENABLED', true),
        'model' => env('IMAGE_PREFLIGHT_MODEL', 'gemini-2.5-flash-lite'),
        'max_dim' => (int) env('IMAGE_PREFLIGHT_MAX_DIM', 384),
        'timeout' => (int) env('IMAGE_PREFLIGHT_TIMEOUT', 15),
    ],
];
