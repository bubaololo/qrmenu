<?php

namespace App\Support;

use Matriphe\ISO639\ISO639;

class LanguageOptions
{
    /**
     * ISO639-1 language options keyed by code, labelled with the native name
     * (falling back to the English name), sorted alphabetically by label.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        $iso = new ISO639;
        $options = [];

        foreach ($iso->allLanguages() as $lang) {
            if ($lang[0] !== '') {
                $options[$lang[0]] = $lang[5] !== '' ? $lang[5] : $lang[4];
            }
        }

        asort($options);

        return $options;
    }
}
