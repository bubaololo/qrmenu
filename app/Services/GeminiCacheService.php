<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Providers\Gemini\Gemini;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Throwable;

class GeminiCacheService
{
    private const CACHE_TTL_SECONDS = 3600;

    private const CACHE_REFRESH_BUFFER = 300;

    /**
     * Return a Gemini cachedContent name for the given system messages, creating
     * or reusing an existing cached context. Returns null on any failure so the
     * caller can fall back to sending the system prompt normally.
     *
     * @param  SystemMessage[]  $systemMessages
     */
    public function resolve(string $model, array $systemMessages): ?string
    {
        if (empty($systemMessages)) {
            return null;
        }

        $content = implode('', array_map(fn (SystemMessage $m) => $m->content, $systemMessages));
        $cacheKey = 'gemini_ctx:'.$model.':'.md5($content);

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            /** @var Gemini $gemini */
            $gemini = Prism::resolve(Provider::Gemini);
            $cachedObject = $gemini->cache($model, [], $systemMessages, self::CACHE_TTL_SECONDS);

            $name = $cachedObject->name;
            Cache::put($cacheKey, $name, self::CACHE_TTL_SECONDS - self::CACHE_REFRESH_BUFFER);

            Log::channel('llm')->info('Gemini context cache created', [
                'model' => $model,
                'cache_name' => $name,
                'tokens' => $cachedObject->tokens,
                'expires_at' => $cachedObject->expiresAt->toIso8601String(),
            ]);

            return $name;
        } catch (Throwable $e) {
            Log::channel('llm')->warning('Gemini context cache creation failed, proceeding without cache', [
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
