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
You are a menu digitization assistant. Extract structured data from restaurant menu images and return only the JSON object specified — no explanations, no markdown.
SYSTEM;

        $userPrompt = <<<'PROMPT'
Return ONLY the JSON object below. No markdown, no prose.

=== TEXT ===
All `name`, `description`, `category_name`, `restaurant.address|phone|city|country`: exact original text from the menu, do NOT translate. Use null when absent.
`restaurant.name`: in primary_language.
Bilingual lines (one item rendered in 2+ languages, separated by /, -, parens, newline): keep ONLY the primary_language version. Example with primary_language=vi: "Cà phê đen / Black coffee" → "Cà phê đen".

=== LANGUAGE & CURRENCY ===
`primary_language`: ISO 639-1 of dominant text. Use "mixed" when (a) 2+ languages are roughly equal across the menu, OR (b) different fields consistently use different languages (e.g. names in vi but descriptions in en) even if one dominates by char count.
`currency`: ISO 4217. Infer from symbols/format/region (Vietnam → VND).

=== OPENING HOURS ===
Always fill `raw_text` verbatim. `periods[]`: `days` ⊂ {mon,tue,wed,thu,fri,sat,sun}, `open`/`close` = HH:MM 24h. Split shifts = two period objects with the same days. Translate any day-name language to these codes. Set the whole object to null if hours are not shown.

=== STARRED & SORT ===
`starred=true` ONLY for an explicit star/heart/fire icon OR a label like "popular", "bestseller", "chef's choice", "recommended". Decorative ornaments do NOT count.
`sort_order`: 0-based, top-to-bottom, left-to-right (for multi-column). Same rule for items within a section.

=== CATEGORY GROUPING ===
`category_name` = visible section heading, exact text. If a group has no heading AND appears mid-menu after another section, attach its items to the PREVIOUS section. Only emit `category_name=null` when the VERY FIRST block of the menu has no heading.

=== PRICE ===
Always fill `price.original_text` with the exact price text (with currency symbols and units).
- "45.000"                             → value=45000, min=null,  max=null
- "30.000–50.000", "30–50k"           → value=null,  min=30000, max=50000
- "từ 50.000", "from $5"               → value=null,  min=50000, max=null
- market price / по запросу / blank    → value=null,  min=null,  max=null
`unit` = exact unit-of-sale string ("con", "quả", "100g", "/kg") or null.

=== ITEM CONFIDENCE ===
`item_confidence` ∈ [0.0,1.0] = how legible the item's name+description+price are. 1.0 = sharp; lower for glare, blur, low contrast, occlusion, unusual font.

=== IMAGE BBOX ===
For each item decide whether a dedicated photo is paired with it.

FILL `image_bbox` when ANY:
- The photo sits inside the same visual card / row / bordered block as the item text.
- A leader line (dashed, dotted, solid) connects the text to the photo.
- The photo is immediately adjacent to the text (left/right/above/below) with no other item between, AND the photo's subject visibly matches the item.

SET null when:
- The graphic is a category icon, section-heading art, or menu-title ornament.
- The graphic is decorative stock art of a raw ingredient or object next to a heading (coconut, lemon slice, crab silhouette, fruits in a corner, chef hat, beer mug next to "BEERS").
- The photo sits in a page margin, border, corner, header or footer with no obvious owner.
- The photo is visually equidistant from 2+ items with no leader line and no shared container — set null for ALL candidates, do NOT guess and do NOT attach it to every candidate.
- Brand logos, drink/beer logos, restaurant logo, "MENU" / "FOOD MENU" wordmark art.

`coords` = `[left, top, right, bottom]`, four FRACTIONS ∈ [0.0,1.0] of image dimensions, NEVER pixels. Example: photo at 10–40% horizontally, 20–50% vertically → `[0.10, 0.20, 0.40, 0.50]`. Tight crop with ~3–5% margin around the food/drink photo only — exclude item text, price, leader-line dots, neighboring items.
`image_index` = 0-based index of the source image (0 if only one is supplied).
`confidence` ∈ [0.0,1.0]: 1.0 for a clear isolated photo in an obvious card/row; 0.6–0.8 when pairing has minor ambiguity; if it would be below 0.5, prefer null over a guess.

=== VARIATIONS vs OPTIONS ===
`variations` = MUTUALLY EXCLUSIVE groups; customer picks EXACTLY ONE per group. Use for choices that change the identity, essence or price of the dish: portion size (S/M/L), temperature (hot/cold, often a snowflake icon for cold), spice level (1–3 chili icons), sauce choice (one-of), base (rice/noodles/bread), protein (chicken/beef/tofu), cooking method (fried/grilled/steamed), pieces per order ("1C", "6V").
- `required=true` when customer must pick one to order.
- `required=false` only when there is a default and the variant is a modifier (e.g. default hot, snowflake-priced cold is optional).
- `allow_multiple` is always false.

`options` = ADDITIVE extras; customer picks 0..N. Use for extra toppings (extra cheese, extra shot, extra egg), additional sauce as an add-on (not a choice), side extras.

`variations[].type` MUST be EXACTLY one of: portion, size, spice_level, sauce, base, protein, temperature, cooking_method, flavor, unit, other. Do NOT invent.

=== EXTRAS SCOPE ===
Before attaching any extras block, determine its scope:
- Block at menu start/end OR on a dedicated page/column with no adjacent section → GLOBAL: apply to every item in the menu.
- Block placed directly before/after/inside a specific section → SECTION: apply to every item in that section only.
Never leave a standalone block unassigned. Example: an "ADD ON" block in the bottom-right corner of a coffee menu with no adjacent section is GLOBAL → attach those options to every coffee item.

=== CATEGORY ICON ===
For each section pick exactly ONE icon name from the closed list below, OR null if nothing reasonably fits. DO NOT invent names; use the spelling shown.

Closed list ({icon_count}):
{icon_list}

=== JSON SCHEMA ===
{
  "restaurant": {
    "name": string|null,
    "address": string|null,
    "city": string|null,
    "country": string|null,
    "phone": string|null,
    "opening_hours": {
      "raw_text": string|null,
      "is_24_7": boolean,
      "periods": [{"days":["mon|tue|wed|thu|fri|sat|sun"],"open":"HH:MM","close":"HH:MM"}]
    } | null,
    "currency": string,
    "primary_language": string
  },
  "menu_version": {"detected_date":"YYYY-MM-DD"|null,"source_images_count":integer},
  "sections": [
    {
      "category_name": string|null,
      "category_icon": string|null,
      "sort_order": integer,
      "items": [
        {
          "name": string|null,
          "description": string|null,
          "starred": boolean,
          "sort_order": integer,
          "price": {"value":number|null,"min":number|null,"max":number|null,"unit":string|null,"original_text":string},
          "item_confidence": number,
          "image_bbox": {"image_index":integer,"coords":[number,number,number,number],"confidence":number} | null,
          "variations": [
            {
              "type": "portion|size|spice_level|sauce|base|protein|temperature|cooking_method|flavor|unit|other",
              "name": string|null,
              "required": boolean,
              "allow_multiple": false,
              "options": [{"name":string|null,"price_adjust":number,"is_default":boolean}]
            }
          ],
          "options": [
            {
              "group_name": string|null,
              "min_select": integer,
              "max_select": integer|null,
              "options": [{"name":string|null,"price_adjust":number}]
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

=== TRANSLATION RULES ===

1. Return EXACTLY the same lines with same IDs, only translate the text parts. No extra lines, no markdown, no explanations.

2. Use natural, meaningful {target_locale} food terminology. DO NOT transliterate generic culinary terms letter-by-letter into the target alphabet.

   Generic terms to translate (NOT transliterate): potato, rice, noodles, beef, chicken, pork, seafood, soup, salad, fried, grilled, steamed, sweet, spicy, iced, hot, milk, sugar, coffee, tea, etc. and their combinations.

3. Preserve iconic/signature dish names in their recognizable form — do not translate or describe them. These are dishes with no direct translation that are known globally by their original name:
   - Vietnamese: Phở, Bánh mì, Bún bò Huế, Bún chả, Gỏi cuốn, Chả giò, Cao lầu, Mì Quảng, Cơm tấm, Bánh xèo
   - Thai: Pad Thai, Tom Yum, Tom Kha, Som Tam, Massaman, Khao Pad
   - Japanese: Sushi, Ramen, Udon, Tempura, Teriyaki, Onigiri
   - Korean: Kimchi, Bibimbap, Bulgogi, Tteokbokki
   - Chinese: Dim Sum, Wonton, Kung Pao, Mapo Tofu

   For iconic names when target is Cyrillic, use the standard adapted spelling (e.g., "Pho" → "Фо", "Banh mi" → "Бань ми", "Pad Thai" → "Пад-тай").

4. For anything that is NOT in the iconic list, prefer descriptive translation over transliteration. If you are tempted to transliterate, stop and translate the meaning instead.

5. Descriptions: translate naturally, keep the meaning, do not pad. Keep empty descriptions empty.

6. Preserve brand names, geographic names, and proper nouns as-is.

=== EXAMPLES ===

Source Vietnamese → Russian (ru):
- "Khoai tây chiên" → "Картофель фри"   (generic: fried potato)
- "Hủ tiếu bò kho" → "Рисовая лапша с тушёной говядиной"   (generic dish, describe it)
- "Cà phê sữa đá" → "Кофе со сгущёнкой со льдом"   (generic: iced milk coffee)
- "Mì xào hải sản" → "Жареная лапша с морепродуктами"
- "Cơm chiên trứng" → "Жареный рис с яйцом"
- "Bánh mì bò nướng" → "Бань ми с говядиной на гриле"   (iconic Bánh mì + describe filling)
- "Phở bò" → "Фо бо"   (iconic, preserve)

Source Vietnamese → English (en):
- "Khoai tây chiên" → "French fries"
- "Hủ tiếu bò kho" → "Rice noodles with beef stew"
- "Cà phê sữa đá" → "Iced coffee with condensed milk"
- "Phở bò" → "Phở bò"   (iconic, preserve)

Anti-pattern (DO NOT DO THIS):
- "Khoai tây chiên" → "Кхоай тяй чиен"   ❌ transliteration loses meaning
- "Hủ tiếu bò kho" → "Ху тьеу бо кхо"   ❌ transliteration loses meaning

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
