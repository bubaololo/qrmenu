<?php

namespace Database\Seeders;

use App\Models\Prompt;
use App\Models\PromptType;
use Illuminate\Database\Seeder;

class PromptSeeder extends Seeder
{
    public function run(): void
    {
        $type = PromptType::firstOrCreate(
            ['slug' => 'menu_analyzer'],
            ['name' => 'Menu analyzer'],
        );

        $systemPrompt = <<<'SYSTEM'
You are a precise menu digitization assistant. Your only job is to extract structured data from restaurant menu images and return it as valid JSON. Follow the schema exactly — no explanations, no markdown, no prose. Return only the JSON object.
SYSTEM;

        $userPrompt = <<<'PROMPT'
Extract structured data from the menu image(s) following these rules.

=== FIELD RULES ===

**Text fields** — exact original text as printed on the menu. No translations. null if absent.

**primary_language** — ISO 639-1 code of the menu text language. Use "mixed" only when multiple languages are present with no single dominant one.

**currency** — ISO 4217 three-letter code. Infer from symbols and regional context.

**opening_hours** — always fill raw_text with the exact text from the menu. Represent periods as objects: days (mon/tue/wed/thu/fri/sat/sun), open/close in HH:MM 24h format. For split shifts add two period objects with the same days. Convert day names from any language to standard codes. null if not shown.

**image_bbox** — [x1, y1, x2, y2] normalized coordinates 0.0–1.0 of the dish/drink photo including plate/glass with a small margin. null if no photo.

**price** — value: numeric price, null if not fixed. min/max: for price ranges, null otherwise. unit: unit of sale exactly as printed, null if not shown. original_text: exact price text from the menu — always fill.

**variations** — mutually exclusive choices the customer must or may pick ONE from. Typical groups: portion size, protein type, base (rice/noodles/bread), cooking method, spice level, or any other choice that changes the essence or price of the dish. type: free-form English label for the group. Pictograms that carry specific meaning in context (snowflake = cold version, chili icons = spice levels) should be interpreted as variation options and stored as text. Distinguish meaningful pictograms from purely decorative star/highlight marks.

**options** — optional add-ons/toppings the customer may select MULTIPLE from. Extras like sauce, cheese, toppings, additional protein.

**Options/variations scope resolution** — before assigning options or variations to items, determine their scope:
1. Block at the start/end of the menu or on a dedicated page with no adjacent section → GLOBAL scope: apply to every item in the menu.
2. Block directly before, after, or inside a specific section → SECTION scope: apply to every item in that section.
3. Never leave a standalone options/variations block unassigned. Always propagate it to every item within its determined scope.

**starred** — true if the item is explicitly highlighted (star mark, "popular", "bestseller", "chef's choice" or similar label).

=== JSON SCHEMA ===

{
  "restaurant": {
    "name": string | null,
    "address": string | null,
    "city": string | null,
    "country": string | null,
    "phone": string | null,
    "opening_hours": {
      "raw_text": string | null,
      "is_24_7": boolean,
      "periods": [
        {
          "days": ["mon"|"tue"|"wed"|"thu"|"fri"|"sat"|"sun"],
          "open": "HH:MM",
          "close": "HH:MM"
        }
      ]
    } | null,
    "currency": string,
    "primary_language": string
  },
  "menu_version": {
    "detected_date": "YYYY-MM-DD" | null,
    "source_images_count": integer
  },
  "sections": [
    {
      "category_name": string | null,
      "items": [
        {
          "name": string | null,
          "description": string | null,
          "starred": boolean,
          "price": {
            "value": number | null,
            "min": number | null,
            "max": number | null,
            "unit": string | null,
            "original_text": string
          },
          "image_bbox": [number, number, number, number] | null,
          "variations": [
            {
              "type": string | null,
              "name": string | null,
              "required": boolean,
              "allow_multiple": boolean,
              "options": [
                {
                  "name": string | null,
                  "price_adjust": number,
                  "is_default": boolean
                }
              ]
            }
          ],
          "options": [
            {
              "group_name": string | null,
              "min_select": integer,
              "max_select": integer | null,
              "options": [
                {
                  "name": string | null,
                  "price_adjust": number
                }
              ]
            }
          ]
        }
      ]
    }
  ]
}
PROMPT;

        Prompt::updateOrCreate(
            [
                'prompt_type_id' => $type->id,
                'name' => 'Default menu analyzer',
            ],
            [
                'system_prompt' => $systemPrompt,
                'user_prompt' => $userPrompt,
                'is_active' => true,
            ],
        );

        Prompt::where('prompt_type_id', $type->id)
            ->where('name', '!=', 'Default menu analyzer')
            ->update(['is_active' => false]);

        // ── Menu translator prompt ──────────────────────────────────────────

        $translatorType = PromptType::firstOrCreate(
            ['slug' => 'menu_translator'],
            ['name' => 'Menu translator'],
        );

        $translatorSystem = <<<'SYSTEM'
You are a professional restaurant menu translator. You receive pipe-delimited lines and return the same format with translated text. No explanations, no markdown, no extra output — only the translated lines.
SYSTEM;

        $translatorUser = <<<'PROMPT'
Translate the restaurant menu below from {source_locale} to {target_locale}.

Context: {restaurant_name}, {city}, {country}

Format — each line is TYPE|ID(s)|TEXT or TYPE|ID|NAME|DESCRIPTION:
- S|id|section name
- I|id|item name|item description (empty if none)
- V|id(s)|variation option name (comma-separated IDs share same text)
- G|id(s)|option group name
- O|id(s)|option name
- R|field|value (restaurant field)

Rules:
- Return EXACTLY the same lines with same IDs, only translate the text parts
- Preserve brand names and proper nouns as-is
- Use natural food terminology for {target_locale}
- Keep empty descriptions empty
- No extra lines, no markdown fences, no explanations

Lines to translate:
PROMPT;

        Prompt::updateOrCreate(
            [
                'prompt_type_id' => $translatorType->id,
                'name' => 'Default menu translator',
            ],
            [
                'system_prompt' => $translatorSystem,
                'user_prompt' => $translatorUser,
                'is_active' => true,
            ],
        );
    }
}
