<?php

return [
    'disk'    => env('IMAGE_DISK', 'public'),
    'format'  => env('IMAGE_FORMAT', 'webp'),
    'quality' => (int) env('IMAGE_QUALITY', 80),
    'main'    => ['width' => 800, 'height' => 800],
    'thumb'   => ['width' => 200, 'height' => 200],
];
