<?php

return [
    'disk' => env('IMAGE_DISK', 'public'),
    'originals_disk' => env('IMAGE_ORIGINALS_DISK', 'local'),
    'format' => env('IMAGE_FORMAT', 'webp'),
    'quality' => (int) env('IMAGE_QUALITY', 80),

    'paths' => [
        'restaurants' => 'restaurants',
        'logos' => 'logos',
        'zones' => 'zones',
        'menu_items' => 'menu-items',
        'qrcodes' => 'qrcodes',
        'originals' => 'originals',
    ],

    /*
     * Size profiles for ImageProcessor::processAndStore().
     * Thumbs fit within the box (aspect preserved, no crop).
     */
    'profiles' => [
        // main 1024 matches the admin cropper's output exactly (no server-side
        // re-resize) and gives ~2x density on the full-width bottom-sheet photo.
        'default' => ['main' => 1024, 'thumb' => 400],  // menu items, zones
        'banner' => ['main' => 1600, 'thumb' => 800],   // restaurant cover, full-width
        'logo' => ['main' => 320, 'thumb' => 160],      // restaurant logo
    ],

    'preprocess' => [
        'max_width' => (int) env('IMAGE_PREPROCESS_WIDTH', 2400),
        'format' => 'webp',
        'quality' => 85,
    ],

    'preflight' => [
        'enabled' => (bool) env('IMAGE_PREFLIGHT_ENABLED', true),
        'model' => env('IMAGE_PREFLIGHT_MODEL', 'gemini-2.5-flash-lite'),
        'max_dim' => (int) env('IMAGE_PREFLIGHT_MAX_DIM', 768),
        'timeout' => (int) env('IMAGE_PREFLIGHT_TIMEOUT', 15),
    ],
];
