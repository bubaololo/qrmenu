<?php

namespace Database\Seeders;

use App\Models\Locale;
use Illuminate\Database\Seeder;

class LocaleSeeder extends Seeder
{
    public function run(): void
    {
        $locales = [
            // East & Southeast Asia
            ['code' => 'vi', 'name' => 'Vietnamese'],
            ['code' => 'th', 'name' => 'Thai'],
            ['code' => 'ko', 'name' => 'Korean'],
            ['code' => 'ja', 'name' => 'Japanese'],
            ['code' => 'zh', 'name' => 'Chinese'],
            ['code' => 'id', 'name' => 'Indonesian'],
            ['code' => 'ms', 'name' => 'Malay'],
            ['code' => 'tl', 'name' => 'Filipino'],
            ['code' => 'km', 'name' => 'Khmer'],
            ['code' => 'lo', 'name' => 'Lao'],
            ['code' => 'my', 'name' => 'Burmese'],
            // South Asia
            ['code' => 'hi', 'name' => 'Hindi'],
            ['code' => 'bn', 'name' => 'Bengali'],
            ['code' => 'ur', 'name' => 'Urdu'],
            ['code' => 'ta', 'name' => 'Tamil'],
            ['code' => 'te', 'name' => 'Telugu'],
            ['code' => 'ml', 'name' => 'Malayalam'],
            ['code' => 'si', 'name' => 'Sinhala'],
            ['code' => 'ne', 'name' => 'Nepali'],
            // Middle East & Central Asia
            ['code' => 'ar', 'name' => 'Arabic'],
            ['code' => 'fa', 'name' => 'Persian'],
            ['code' => 'tr', 'name' => 'Turkish'],
            ['code' => 'he', 'name' => 'Hebrew'],
            ['code' => 'kk', 'name' => 'Kazakh'],
            ['code' => 'uz', 'name' => 'Uzbek'],
            ['code' => 'az', 'name' => 'Azerbaijani'],
            ['code' => 'ka', 'name' => 'Georgian'],
            ['code' => 'hy', 'name' => 'Armenian'],
            // Europe — Western
            ['code' => 'en', 'name' => 'English'],
            ['code' => 'fr', 'name' => 'French'],
            ['code' => 'de', 'name' => 'German'],
            ['code' => 'es', 'name' => 'Spanish'],
            ['code' => 'pt', 'name' => 'Portuguese'],
            ['code' => 'it', 'name' => 'Italian'],
            ['code' => 'nl', 'name' => 'Dutch'],
            ['code' => 'sv', 'name' => 'Swedish'],
            ['code' => 'da', 'name' => 'Danish'],
            ['code' => 'no', 'name' => 'Norwegian'],
            ['code' => 'fi', 'name' => 'Finnish'],
            ['code' => 'is', 'name' => 'Icelandic'],
            // Europe — Eastern & Central
            ['code' => 'ru', 'name' => 'Russian'],
            ['code' => 'uk', 'name' => 'Ukrainian'],
            ['code' => 'pl', 'name' => 'Polish'],
            ['code' => 'cs', 'name' => 'Czech'],
            ['code' => 'sk', 'name' => 'Slovak'],
            ['code' => 'ro', 'name' => 'Romanian'],
            ['code' => 'hu', 'name' => 'Hungarian'],
            ['code' => 'bg', 'name' => 'Bulgarian'],
            ['code' => 'sr', 'name' => 'Serbian'],
            ['code' => 'hr', 'name' => 'Croatian'],
            ['code' => 'bs', 'name' => 'Bosnian'],
            ['code' => 'sl', 'name' => 'Slovenian'],
            ['code' => 'mk', 'name' => 'Macedonian'],
            ['code' => 'sq', 'name' => 'Albanian'],
            ['code' => 'el', 'name' => 'Greek'],
            ['code' => 'lt', 'name' => 'Lithuanian'],
            ['code' => 'lv', 'name' => 'Latvian'],
            ['code' => 'et', 'name' => 'Estonian'],
            // Africa
            ['code' => 'sw', 'name' => 'Swahili'],
            ['code' => 'am', 'name' => 'Amharic'],
            ['code' => 'so', 'name' => 'Somali'],
            ['code' => 'ha', 'name' => 'Hausa'],
            ['code' => 'yo', 'name' => 'Yoruba'],
            ['code' => 'ig', 'name' => 'Igbo'],
            // Americas
            ['code' => 'ht', 'name' => 'Haitian Creole'],
        ];

        foreach ($locales as $locale) {
            Locale::firstOrCreate(['code' => $locale['code']], ['name' => $locale['name']]);
        }
    }
}
