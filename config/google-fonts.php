<?php

return [

    /*
     * Here you can register fonts to call from the @googlefonts Blade directive.
     * The google-fonts:fetch command will prefetch these fonts.
     */
    'fonts' => [
        // Static-weight URL (matches what was on prod) — Google's variable font
        // version of Geist appears to include Regional Indicator glyphs that
        // break flag emoji rendering on Windows. Static weights don't.
        'default' => 'https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Unbounded:wght@400;500;600;700&display=swap',
    ],

    /*
     * This disk will be used to store local Google Fonts. The public disk
     * is the default because it can be served over HTTP with storage:link.
     */
    'disk' => 'public',

    /*
     * Prepend all files that are written to the selected disk with this path.
     * This allows separating the fonts from other data in the public disk.
     */
    'path' => 'fonts',

    /*
     * By default, CSS will be inlined to reduce the amount of round trips
     * browsers need to make in order to load the requested font files.
     */
    'inline' => false,

    /*
     * When preload is set to true, preload meta tags will be generated
     * in the HTML output to instruct the browser to start fetching the
     * font files as early as possible, even before the CSS is fully parsed.
     */
    // Off — preload would eagerly fetch ALL 10 subsets (Geist+Unbounded × 5 subsets each).
    // unicode-range in inline @font-face declarations lets the browser fetch only the
    // subsets actually needed for the page's text (typically 2-4 of 10).
    'preload' => false,

    /*
     * When something goes wrong fonts are loaded directly from Google.
     * With fallback disabled, this package will throw an exception.
     */
    'fallback' => true,

    /*
     * This user agent will be used to request the stylesheet from Google Fonts.
     * This is the Safari 14 user agent that only targets modern browsers. If
     * you want to target older browsers, use different user agent string.
     */
    'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.3 Safari/605.1.15',

];
