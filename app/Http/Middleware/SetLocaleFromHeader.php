<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Menus\Concerns\ResolvesLocale;
use Closure;
use Illuminate\Http\Request;
use Matriphe\ISO639\ISO639;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the request locale from headers.
 *
 * Looks at the custom X-Locale header first — the frontend uses it to signal
 * "user explicitly picked this language in the editor". Falls back to
 * Accept-Language only when X-Locale is absent, for public read-only pages
 * where browser language is a reasonable default.
 *
 * Browser-default Accept-Language (e.g. Symfony test client's
 * "en-us,en;q=0.5") is NOT used for write-path validation —
 * {@see ResolvesLocale} only consumes
 * X-Locale via `locale_from_header`. The Accept-Language fallback is
 * informational for read paths.
 */
class SetLocaleFromHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $explicit = $request->header('X-Locale');

        if ($explicit) {
            $code = strtolower(substr(trim($explicit), 0, 2));
            $iso = new ISO639;
            if ($iso->languageByCode1($code) !== '') {
                app()->setLocale($code);
                $request->attributes->set('locale_from_header', $code);

                return $next($request);
            }
        }

        // Fallback to Accept-Language only for app()->setLocale (read-path hint).
        // Do NOT populate locale_from_header — that attribute is the write-path
        // signal and must come from an explicit user choice.
        $browser = $request->header('Accept-Language');
        if ($browser) {
            $code = strtolower(substr(trim(explode(',', $browser)[0]), 0, 2));
            $iso = new ISO639;
            if ($iso->languageByCode1($code) !== '') {
                app()->setLocale($code);
            }
        }

        return $next($request);
    }
}
