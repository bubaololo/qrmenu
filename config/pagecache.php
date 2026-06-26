<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Static full-page caching of public menus
    |--------------------------------------------------------------------------
    |
    | Master switch for silber/page-cache on the public menu routes. When on,
    | rendered menu pages are written to public/page-cache/*.html and served by
    | nginx (via try_files) with zero PHP on a hit; an edit purges the affected
    | restaurant's files. Off by default so local dev serves live PHP (no stale
    | .html, no clutter). Enable in staging/production where nginx is configured
    | to serve the page-cache directory.
    |
    */

    'enabled' => env('PAGE_CACHE_ENABLED', false),
];
