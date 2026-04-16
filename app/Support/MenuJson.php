<?php

namespace App\Support;

final class MenuJson
{
    private const JSON_DECODE_FLAGS = JSON_INVALID_UTF8_SUBSTITUTE | JSON_BIGINT_AS_STRING;

    private const JSON_DECODE_DEPTH = 2048;

    /**
     * Parse JSON from an LLM reply: strip markdown fences, then decode the first top-level object or array.
     * Extra prose before/after the JSON does not break parsing (unlike json_decode on the full string).
     *
     * @return array<string, mixed>
     */
    public static function decodeMenuFromLlmText(string $raw): array
    {
        $text = trim($raw);
        if ($text === '') {
            return [];
        }
        if (str_starts_with($text, "\xEF\xBB\xBF")) {
            $text = substr($text, 3);
        }
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```\s*$/', '', $text) ?? $text;
        // Strip single-line // comments that some models (e.g. GPT-4.1) inject into JSON
        $text = preg_replace('/\/\/[^\n]*/', '', $text) ?? $text;
        $text = trim($text);

        $normalizeListRoot = static function (mixed $decoded): array {
            if (! is_array($decoded)) {
                return [];
            }
            if ($decoded === []) {
                return [];
            }
            if (array_is_list($decoded)) {
                return [
                    'sections' => [
                        [
                            'category_name' => '',
                            'sort_order' => 0,
                            'items' => $decoded,
                        ],
                    ],
                ];
            }

            return $decoded;
        };

        $decoded = json_decode($text, true, self::JSON_DECODE_DEPTH, self::JSON_DECODE_FLAGS);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $normalizeListRoot($decoded);
        }

        $slice = self::extractBalanced($text, '{', '}');
        if ($slice !== null) {
            $decoded = json_decode($slice, true, self::JSON_DECODE_DEPTH, self::JSON_DECODE_FLAGS);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $normalizeListRoot($decoded);
            }
        }

        $slice = self::extractBalanced($text, '[', ']');
        if ($slice !== null) {
            $decoded = json_decode($slice, true, self::JSON_DECODE_DEPTH, self::JSON_DECODE_FLAGS);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $normalizeListRoot($decoded);
            }
        }

        info('Menu JSON decode failed', [
            'json_last_error' => json_last_error_msg(),
            'text_length' => strlen($text),
            'head' => mb_substr($text, 0, 240),
            'tail' => mb_substr($text, max(0, mb_strlen($text) - 240)),
        ]);

        return [];
    }

    /**
     * @param  array<string, mixed>  $menu
     * @return list<array<string, mixed>>
     */
    public static function sections(array $menu): array
    {
        $sections = $menu['sections'] ?? $menu['categories'] ?? [];

        return is_array($sections) ? array_values($sections) : [];
    }

    /**
     * @param  array<string, mixed>  $menu
     */
    public static function dishCount(array $menu): int
    {
        $n = 0;
        foreach (self::sections($menu) as $sec) {
            $items = $sec['items'] ?? [];
            $n += is_array($items) ? count($items) : 0;
        }

        return $n;
    }

    /**
     * Extract a plain string from a field that may be a string or legacy bilingual array.
     * Returns null if the value is empty or not extractable.
     */
    public static function extractText(mixed $field): ?string
    {
        if ($field === null) {
            return null;
        }
        if (is_string($field)) {
            $s = trim($field);

            return $s !== '' ? $s : null;
        }
        if (! is_array($field)) {
            return null;
        }
        // Legacy bilingual format: {"local": "...", "en": "..."} or {"vi": "...", "en": "..."}
        $local = $field['local'] ?? $field['vi'] ?? $field['th'] ?? $field['ko'] ?? $field['ja'] ?? $field['zh'] ?? $field['id'] ?? null;
        $en = $field['en'] ?? null;
        $value = $local ?? $en;
        if (is_string($value)) {
            $v = trim($value);

            return $v !== '' ? $v : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $price
     */
    public static function formatPriceDisplay(array $price, ?string $defaultCurrency = null): string
    {
        $currency = isset($price['currency']) && $price['currency'] !== ''
            ? (string) $price['currency']
            : ($defaultCurrency ?? '');
        $amount = '';
        if (($price['type'] ?? '') === 'range' && isset($price['min'], $price['max'])) {
            $amount = (string) $price['min'].'–'.(string) $price['max'];
        } elseif (isset($price['value'])) {
            $amount = (string) $price['value'];
        }
        $parts = [];
        if ($amount !== '') {
            $parts[] = $amount;
        }
        if ($currency !== '') {
            $parts[] = $currency;
        }
        $line = implode(' ', $parts);
        if (! empty($price['original_text'])) {
            $line .= ($line !== '' ? ' ' : '').'('.(string) $price['original_text'].')';
        }
        $unit = $price['unit'] ?? '';
        if ($unit !== '') {
            $line .= ($line !== '' ? ' ' : '').(string) $unit;
        }

        return trim($line);
    }

    private static function extractBalanced(string $text, string $open, string $close): ?string
    {
        $start = strpos($text, $open);
        if ($start === false) {
            return null;
        }
        $len = strlen($text);
        $depth = 0;
        $inString = false;
        $escape = false;
        for ($i = $start; $i < $len; $i++) {
            $c = $text[$i];
            if ($inString) {
                if ($escape) {
                    $escape = false;

                    continue;
                }
                if ($c === '\\') {
                    $escape = true;

                    continue;
                }
                if ($c === '"') {
                    $inString = false;
                }

                continue;
            }
            if ($c === '"') {
                $inString = true;

                continue;
            }
            if ($c === $open) {
                $depth++;
            } elseif ($c === $close) {
                $depth--;
                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }
}
