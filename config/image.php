<?php

return [
    'disk'          => env('IMAGE_DISK', 'public'),
    'originals_disk' => env('IMAGE_ORIGINALS_DISK', 'local'),
    'format'        => env('IMAGE_FORMAT', 'webp'),
    'quality'       => (int) env('IMAGE_QUALITY', 80),

    'paths' => [
        'restaurants' => 'restaurants',
        'menu_items'  => 'menu-items',
        'qrcodes'     => 'qrcodes',
        'originals'   => 'originals',
    ],

    'main'  => ['width' => 800],
    'thumb' => ['width' => 200],
];
