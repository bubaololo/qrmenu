<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Matriphe\ISO639\ISO639;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Accept-Language');

        if ($header) {
            // Take only the primary language tag, strip region subtags (e.g. "en-US" → "en")
            $code = strtolower(substr(trim(explode(',', $header)[0]), 0, 2));

            $iso = new ISO639;
            if ($iso->languageByCode1($code) !== '') {
                app()->setLocale($code);
                $request->attributes->set('locale_from_header', $code);
            }
        }

        return $next($request);
    }
}
