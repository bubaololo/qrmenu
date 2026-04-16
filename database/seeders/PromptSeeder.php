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
Extract structured data from the menu image(s) and return ONLY the JSON object matching the schema below. No markdown, no prose, no explanations.

=== GENERAL RULES ===

**Text fields** — exact original text as printed on the menu. Do NOT translate anything. Use null when a field is absent.

**Bilingual menus** — if a single name/description appears in multiple languages side by side (separated by "/", "-", parentheses, or a new line), keep ONLY the text in `primary_language` and drop the other language. Example: menu line "Cà phê đen / Black coffee" with primary_language=vi → name = "Cà phê đen".

**primary_language** — ISO 639-1 code of the dominant menu text language. Use "mixed" only when two or more languages are equally present with no single dominant one.

**currency** — ISO 4217 three-letter code. Infer from currency symbols, price formatting, and regional context (e.g., Vietnam → VND).

**opening_hours** — always fill raw_text with the exact text from the menu. Represent periods as objects: days (mon/tue/wed/thu/fri/sat/sun), open/close in HH:MM 24h format. For split shifts add two period objects with the same days. Convert day names from any language to standard codes. Set to null if opening hours are not shown.

**starred** — true ONLY if the item is explicitly highlighted with a star/heart/fire/flame icon, or with an explicit label such as "popular", "bestseller", "chef's choice", "recommended". Decorative corner ornaments do NOT count.

**sort_order** — 0-based integer reflecting the visual order of sections on the menu (top-to-bottom, left-to-right for multi-column layouts). Within each section, items also get 0-based sort_order following the same rule.

**category_name** — the visible section heading exactly as printed. If a group of items has no visible heading AND appears in the middle of the menu right after another section, attach those items to the PREVIOUS section instead of creating a new one. Only create a section with category_name=null when the very first block of the menu has no heading.

=== PRICE ===

Fill price for every item. Rules by case:

- **Fixed price** ("45.000"): value=45000, min=null, max=null.
- **Range** ("30.000 – 50.000", "30–50k"): value=null, min=30000, max=50000.
- **"From X"** ("từ 50.000", "from $5"): value=null, min=50000, max=null.
- **Unspecified / market price / "по запросу"**: value=null, min=null, max=null.

`original_text` is ALWAYS filled with the exact price text from the menu, including currency symbols and units if present. `unit` is the unit of sale exactly as printed ("con", "quả", "100g", "/kg"), or null.

=== IMAGE BBOX ===

For every item that has a photo on the menu, fill image_bbox as:

```
{ "image_index": <int>, "coords": [x1, y1, x2, y2] }
```

- `image_index` — 0-based index of the source image in the order they were supplied in the request. When only one image is supplied, this is always 0.
- `coords` — normalized 0.0–1.0 bounding box covering the whole plate/glass/dish photo with ~3–5% margin.

Set image_bbox to null when the item has no photo.

=== VARIATIONS vs OPTIONS ===

**variations** — MUTUALLY EXCLUSIVE groups where the customer picks EXACTLY ONE per group. Use variations for choices that change the identity, essence or price of the dish:

- portion size (S / M / L)
- temperature (hot / cold, often marked with a snowflake icon for cold)
- spice level (1–3 chili icons)
- sauce choice when only one must be picked
- base (rice / noodles / bread)
- protein (chicken / beef / tofu)
- cooking method (fried / grilled / steamed)
- number of pieces in one order ("1C", "6V")

Each variation group MUST have `required=true` when the customer must pick one to order; `required=false` only when the variant is a modifier with a default (e.g. default is hot, snowflake-priced cold version is optional). `allow_multiple` is always false for variations.

**options** — ADDITIVE extras where the customer picks 0..N items. Use options for add-ons that do not replace a required choice:

- extra toppings (extra cheese, extra shot, extra egg)
- additional sauce as an add-on, not a choice
- side extras

=== CLOSED LIST FOR variations[].type ===

Use EXACTLY one of these values for variations[].type. Do NOT invent new types:

"portion" | "size" | "spice_level" | "sauce" | "base" | "protein" | "temperature" | "cooking_method" | "flavor" | "unit" | "other"

=== SCOPE OF EXTRAS BLOCKS ===

Before attaching any options or variations block to items, determine its scope:

1. The block is placed at the start/end of the menu or on a dedicated page/column with no adjacent section → **GLOBAL scope**: apply to every item in the whole menu.
2. The block is placed directly before, after, or inside a specific section → **SECTION scope**: apply to every item in that section only.
3. Never leave a standalone block unassigned — always propagate it to every item within the determined scope.

Example: an "ADD ON" block in the bottom-right corner of a coffee menu, with no adjacent section, is GLOBAL — attach those options to every coffee item on the menu.

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
      "sort_order": integer,
      "items": [
        {
          "name": string | null,
          "description": string | null,
          "starred": boolean,
          "sort_order": integer,
          "price": {
            "value": number | null,
            "min": number | null,
            "max": number | null,
            "unit": string | null,
            "original_text": string
          },
          "image_bbox": {
            "image_index": integer,
            "coords": [number, number, number, number]
          } | null,
          "variations": [
            {
              "type": "portion"|"size"|"spice_level"|"sauce"|"base"|"protein"|"temperature"|"cooking_method"|"flavor"|"unit"|"other",
              "name": string | null,
              "required": boolean,
              "allow_multiple": false,
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
