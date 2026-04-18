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

=== ITEM CONFIDENCE ===

**item_confidence** — float 0.0–1.0. How clearly the item's name, description, and price are legible in the menu image. Use 1.0 when the text is sharp and unambiguous. Lower the value when glare, blur, low contrast, partial occlusion, or unusual font makes the text hard to read. This helps flag items that may have transcription errors.

=== IMAGE BBOX ===

For every item, decide whether it has a dedicated photograph on the menu. Fill image_bbox when a photo is paired with this item; set it to null otherwise.

Schema when filled:

```
{ "image_index": <int>, "coords": [x1, y1, x2, y2], "confidence": <float> }
```

**KEEP image_bbox (fill it) when ANY of these layout cues are present:**

- The photo sits inside the same visual card, row, or bordered block as the item text.
- A leader line (dashed, dotted, or solid) connects the item text to the photo.
- The photo is immediately adjacent to the item text (to its left/right, or directly above/below) with no other item between them, AND the photo's subject visibly matches the item's description (e.g. "Grilled Beef Bread" item next to a photo of a bread roll with beef).

When layout clearly pairs a photo with an item, DO fill the bbox — the user needs these pairings.

**DROP image_bbox (set to null) when:**

- The graphic is a category icon or section-heading art (e.g. a cartoon fries drawing next to the "FRENCH FRIES" heading, a cartoon sausage next to the "SAUSAGE" heading, a menu-title ornament).
- The graphic is a stock-art illustration of a raw ingredient or whole object used as decoration (coconut, lemon slice, crab silhouette, squid outline, fruits in a corner, chef hat, beer mug next to the "BEERS" heading).
- The photo is in the page margin, border, corner, or header/footer area with no obvious pairing to any single item.
- A photo is visually equidistant from two or more items with no leader line and no shared container — you cannot confidently pick one owner. Set null for ALL candidate items (do not guess and do not attach it to every candidate).
- Brand logos, drink/beer logos, restaurant logo, "MENU" / "FOOD MENU" wordmark art.

**coords:**

- Four decimal numbers between 0.0 and 1.0, representing fractions of the image dimensions: `[left, top, right, bottom]`.
- Example: for a dish photo spanning from 10% to 40% horizontally and 20% to 50% vertically, coords = `[0.10, 0.20, 0.40, 0.50]`.
- Do NOT use pixel values. Do NOT use values greater than 1. If you are tempted to return 200 or 0.85 × 1600, stop and convert to a fraction between 0 and 1.
- Frame ONLY the food/drink photo itself. Do NOT include the item name text, the price, leader-line dots, or neighboring items.
- Use a tight crop with ~3–5% margin.

**image_index:**

- 0-based index of the source image in the order supplied. When only one image is supplied, this is always 0.

**confidence:**

- Float 0.0–1.0. Your certainty that this bbox correctly frames a dedicated photo of THIS item.
- Use 1.0 for a clear isolated dish photo in an obvious item card or row.
- Lower to 0.6–0.8 when the frame is tight but pairing has minor ambiguity.
- If confidence would be below 0.5, prefer null over a guess.

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

=== CATEGORY ICON ===

For each section, pick ONE icon name from this fixed list that best represents the section's content. Choose based on the category_name and the typical items it contains.

Allowed icons (pick exactly one, or null if nothing reasonably fits):

steak, chicken-thighs, hamburger-01, hotdog, sausage, bbq-grill, fry, pot-01, noodles, rice-bowl-01, fish-food, crab, prawn, shellfish, octopus, snail, sushi-01, dim-sum-01, mochi, taco-01, french-fries-01, pizza-01, popcorn, spaghetti, bread-01, croissant, pie, apple-pie, birthday-cake, cheese-cake-01, cupcake-01, doughnut, cookie, biscuit, cinnamon-roll, ice-cream-01, chocolate, lollipop, cotton-candy, coffee-01, tea, bubble-tea-01, soft-drink-01, soda-can, drink, milk-bottle, milk-coconut, milk-oat, yogurt, eggs, cheese, mushroom, broccoli, carrot, corn, pumpkin, avocado, vegetarian-food, organic-food, natural-food, honey-01, nut, apple, apricot, banana, cherry, grapes, orange, watermelon, chef-hat, dish-01, plate, fork, spoon, kitchen-utensils

Rules:
- Use EXACTLY one name from the list, spelled as shown. DO NOT invent new names.
- Match the section's dominant content: soups/stews → pot-01, rice dishes → rice-bowl-01, noodles/pasta → noodles or spaghetti, coffee drinks → coffee-01, tea drinks → tea, desserts → cupcake-01, seafood → fish-food, breakfast/eggs → eggs, salads/vegetarian → vegetarian-food, BBQ/grilled meat → bbq-grill or steak, pizza → pizza-01, beverages (general) → drink.
- Prefer specific over generic (use noodles for a noodle section, not dish-01).
- Set category_icon to null if the section is generic "Food" / "Our menu" or nothing fits.

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
      "category_icon": string | null,
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
          "item_confidence": number,
          "image_bbox": {
            "image_index": integer,
            "coords": [number, number, number, number],
            "confidence": number
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
