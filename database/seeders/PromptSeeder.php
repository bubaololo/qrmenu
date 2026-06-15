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
`name`, `description`, `category_name`: render in `primary_language`. The menu is the same information across languages, so if a field appears on the menu ONLY in another language, output it translated into `primary_language` (a faithful translation). Use null when absent.
`restaurant.address|phone|city|country`: exact original text from the menu, do NOT translate. Use null when absent.
`restaurant.name`: in primary_language.
Bilingual lines (one item rendered in 2+ languages, separated by /, -, parens, newline): keep ONLY the primary_language version. Example with primary_language=vi: "Cà phê đen / Black coffee" → "Cà phê đen".

=== LANGUAGE & CURRENCY ===
`primary_language`: ISO 639-1 of the menu's single base language — the one in which it is most complete and accurate (usually dominant by coverage). Output exactly one concrete language code. If different fields use different languages (e.g. names in vi but descriptions in en), pick one base and render the rest into it (see TEXT).
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

=== PRICE SEMANTICS ===
`price_adjust` is read differently per block:
- VARIATIONS → the FULL, absolute price printed for that choice; it REPLACES the dish price (not a delta). Use 0 only when a choice carries no price of its own.
- OPTIONS (add-ons) → the amount ADDED on top of the dish price.

`variations[].type` MUST be EXACTLY one of: portion, size, spice_level, sauce, base, protein, temperature, cooking_method, flavor, unit, other. Do NOT invent.

Write identical choices the SAME way everywhere (same wording, group_name, price) so identical sets collapse into one shared set instead of near-duplicates.

=== EXTRAS SCOPE ===
A standalone price list labelled EXTRA / ADD ON / TOPPING (or similar) is an add-on group — never a menu item and never its own section. Emit it ONCE, at the level matching its scope:
- applies to the whole menu (at menu start/end or its own page/column, no adjacent section) → top-level `global_options`.
- applies to one section (placed before/after/inside it) → that section's `section_options`.
- applies to a single dish only → that item's `options`.
Never emit such a block as an item, and never drop it.

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
  "global_options": [{"group_name":string|null,"min_select":integer,"max_select":integer|null,"options":[{"name":string|null,"price_adjust":number}]}],
  "sections": [
    {
      "category_name": string|null,
      "category_icon": string|null,
      "sort_order": integer,
      "section_options": [{"group_name":string|null,"min_select":integer,"max_select":integer|null,"options":[{"name":string|null,"price_adjust":number}]}],
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

1. Return EXACTLY the same lines with the same IDs; translate only the text parts. No extra lines, markdown, or explanations. Keep empty descriptions empty; do not pad.

2. Translate the MEANING into natural, idiomatic {target_locale} food language — word it the way a native menu in {target_locale} would. Generic words (ingredients, drinks, cooking methods, temperature, taste) always have a real translation; use it.

3. NEVER transliterate: do not respell a source word with target-script letters to imitate its sound. A phonetic spelling that carries no meaning in {target_locale} is always wrong.

4. Leave text unchanged ONLY when it has no meaningful translation — a brand, place or person name, or a globally-recognised signature dish known by its own name. For these, use the established {target_locale} spelling if one exists; otherwise keep the original source spelling. Never invent a phonetic spelling.

5. When unsure, translate the meaning; if a term genuinely has none, keep the original source text verbatim — never transliterate.

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
