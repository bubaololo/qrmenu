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
Извлеки данные из меню, следуя правилам:

**Текст** — ТОЧНЫЙ оригинальный текст как написано на меню. Никаких переводов. null если отсутствует.

**primary_language** — ISO 639-1 код языка текста меню (vi, th, ko, ja, zh, id, en, ar …). Если несколько языков без доминирующего — "mixed".

**currency** — трёхбуквенный код ISO 4217 (VND, THB, JPY, KRW, CNY, TWD, USD, EUR, IDR, PHP, MYR, SGD, BDT, INR, PKR, …). Определяй по символам и контексту.

**opening_hours** — режим работы ресторана. Всегда заполняй raw_text точным текстом с меню. Периоды работы представляй как массив объектов: days — список дней (mon/tue/wed/thu/fri/sat/sun), open/close — время в формате HH:MM (24ч). Если в день есть перерыв (split-shift) — добавляй два объекта с одинаковыми days. Дни на любом языке (Thứ 2, 月, 월, จันทร์, الإثنين…) — переводи в стандартные коды. null если режим работы не указан.

**image_bbox** — [x1, y1, x2, y2] нормализованные координаты 0.0–1.0 фотографии блюда/напитка включая тарелку/стакан с небольшим отступом. null если фото нет.

**price** — числовые данные о цене:
- value: числовая цена. null если цена не фиксированная.
- min, max: для диапазонов цен (например "50–80k"). null если не диапазон.
- unit: единица продажи точно как в меню ("con", "quả", "100g", "порция" и т.д.). null если не указана.
- original_text: точный текст цены с меню — всегда заполняй.

**variations** — используй когда блюдо представлено в нескольких взаимоисключающих вариантах, из которых клиент должен или может выбрать один. Типичные случаи: размер порции, тип мяса/белка, вид основы (рис/лапша/хлеб), метод приготовления, уровень остроты, — и любые другие случаи когда выбор меняет суть или цену блюда. Каждая группа вариантов — отдельный объект. Поле type — свободное описание группы на английском (size, meat_type, spice_level, base, cooking_method и т.п.).

**options** — используй для необязательных добавок и топпингов, которые клиент может добавить по желанию. Можно выбирать несколько из группы. Примеры: дополнительный соус, сыр, топпинг, дополнительное мясо.

**starred** — true если блюдо явно выделено в меню (звёздочка, пометка "popular"/"bestseller"/"chef's choice" или аналогичная).

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
    }
}
