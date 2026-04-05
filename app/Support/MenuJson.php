<?php

namespace App\Support;

final class MenuJson
{
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
                            'category_name' => ['vi' => '', 'en' => ''],
                            'sort_order' => 0,
                            'items' => $decoded,
                        ],
                    ],
                ];
            }

            return $decoded;
        };

        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $normalizeListRoot($decoded);
        }

        $slice = self::extractBalanced($text, '{', '}');
        if ($slice !== null) {
            $decoded = json_decode($slice, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $normalizeListRoot($decoded);
            }
        }

        $slice = self::extractBalanced($text, '[', ']');
        if ($slice !== null) {
            $decoded = json_decode($slice, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $normalizeListRoot($decoded);
            }
        }

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
     * @return array{primary: string, secondary: string|null}
     */
    public static function bilingualPair(mixed $field): array
    {
        if ($field === null) {
            return ['primary' => '', 'secondary' => null];
        }
        if (is_string($field)) {
            return ['primary' => $field, 'secondary' => null];
        }
        if (! is_array($field)) {
            return ['primary' => (string) $field, 'secondary' => null];
        }
        $en = isset($field['en']) ? (string) $field['en'] : '';
        $vi = isset($field['vi']) ? (string) $field['vi'] : '';
        if ($en !== '' && $vi !== '' && $en !== $vi) {
            return ['primary' => $en, 'secondary' => $vi];
        }
        $primary = $en !== '' ? $en : $vi;

        return ['primary' => $primary, 'secondary' => null];
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
        $unit = $price['unit_en'] ?? $price['unit'] ?? '';
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
