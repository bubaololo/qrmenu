# Menu Analyzer Prompt Review

Оценка промпта `menu_analyzer` из `database/seeders/PromptSeeder.php` с точки зрения исполнителя (LLM/человека), который по нему строит JSON-разметку меню. Здесь зафиксированы найденные неоднозначности оригинальной версии, и что именно в неё добавлено.

> **Важно.** В репозитории есть второй, несовместимый промпт — `database/prompts/menu_analyzer.json` (RU, `{local, en}`-схема, `price.type`, расширенный `restaurant`). Он устаревший и в benchmark не используется — источник истины `PromptSeeder.php`. Перед прогоном benchmark всегда делаем `php artisan db:seed --class=PromptSeeder`, чтобы в БД попала именно эта версия.

---

## Оригинальная версия — что осталось неоднозначным

1. **variations vs options.** Оригинал разделял их как «mutually exclusive pick ONE» vs «optional multiple». Пограничные случаи (Hot/Cold с дефолтом, «Add Shot») по этому правилу нельзя уверенно разметить: одна LLM положит в variations с `is_default=true`, другая в options с `price_adjust`.
2. **`variations[].type`.** Было описано как «free-form English label». Разные LLM предсказуемо дадут `"Size"` / `"size"` / `"Portion"` / `"portion size"` — метрика diff тонет в капитализации.
3. **`sections[].category_name: null`.** Не описано поведение: куда девать items при отсутствии заголовка, создавать ли «пустую» секцию.
4. **`sort_order`.** Поле используется в коде (`MenuSection`, `MenuJson`, RU-промпт), но в оригинале `PromptSeeder` отсутствовало. Прямое расхождение схемы и БД.
5. **ADD ON / extras-блоки.** Правило scope упоминает «start/end of menu → GLOBAL», но без примеров — блок справа-внизу рядом с одной секцией трактовался моделями и как SECTION, и как GLOBAL.
6. **`price.value` для «from X».** В оригинале не описано. Модели кладут в `value` либо min-цену, либо null — несогласованно.
7. **`image_bbox` при multi-image.** Нет поля `image_index` — при 5–15 картинках в паке физически невозможно связать bbox с конкретным фото. Данные bbox становятся бесполезными для medium/hard.
8. **Bilingual меню (vi + en рядом).** «Exact original text» vs «no translations» — неясно, класть ли строку целиком, только vi, или только en.

---

## Версия после правок — что добавлено/переписано

Нумерация соответствует списку выше.

### 1. variations vs options
Полностью переписан блок с жёстким разделением:

- **variations** = mutually exclusive, pick **exactly one** per group; `allow_multiple` прибит к `false` на уровне схемы. Явный список use-cases: portion size, temperature, spice level, base, protein, cooking method, pieces-per-order, sauce-choice.
- **options** = additive, 0..N. Явный список: extra cheese/shot/egg/sauce-as-addon.

Граница Hot/Cold решена: если температура меняет цену, это variation с `required=false` и `is_default=true` для дефолтного варианта (обычно hot).

### 2. Закрытый список `variations[].type`
Прямо в промпте и в JSON schema прописаны значения:

`"portion" | "size" | "spice_level" | "sauce" | "base" | "protein" | "temperature" | "cooking_method" | "flavor" | "unit" | "other"`

Любые другие значения запрещены. `"other"` — безопасный fallback.

### 3. `category_name`
Добавлено правило:
- есть видимый заголовок — используй его;
- нет заголовка в середине меню → items уходят в предыдущую секцию;
- нет заголовка в самом начале → одна секция с `category_name=null`.

### 4. `sort_order`
Добавлен в схему как **обязательное** 0-based целое и в секциях, и в items. Порядок — визуальный (top-to-bottom, left-to-right для multi-column layouts).

### 5. Extras scope с примером
Правило GLOBAL vs SECTION переформулировано, добавлен прямой пример: «ADD ON в bottom-right углу кофейного меню → GLOBAL на все кофе-items».

### 6. `price.value` правила
Табличкой в промпте все 4 кейса:
- fixed → `value=X, min=null, max=null`
- range → `value=null, min=X, max=Y`
- from → `value=null, min=X, max=null`
- unspecified/market → всё null, но `original_text` обязателен

### 7. `image_bbox` с `image_index`
Схема изменена с плоской `[x1,y1,x2,y2]` на объект:
```json
"image_bbox": { "image_index": 0, "coords": [0.12, 0.34, 0.45, 0.67] } | null
```
`image_index` — 0-based по порядку подачи картинок в запрос. Для single-image пака это всегда 0. Для multi-image это критично — без него bbox бесполезны.

### 8. Bilingual меню
Прямое правило: если название дано на нескольких языках через `/`, `-`, скобки или новую строку — бери только версию на `primary_language`. Пример: `"Cà phê đen / Black coffee"` + `primary_language=vi` → `name="Cà phê đen"`.

---

## Что осталось на откуп интерпретатору

- Какой язык считать primary_language при меню с равными долями vi/en — применяем практическое правило: «язык заголовков секций > язык названий блюд».
- Где проходит граница между `starred=true` и декоративным маркером — оставлено списком явных триггеров (star/heart/fire/flame + явные текстовые метки), всё остальное → false.

Эти случаи задокументированы в `GROUND_TRUTH_NOTES.md` — для benchmark они не критичны, т.к. Match% метрика считается по `price.original_text`, а не по `starred`/`primary_language`.

---

## Impact на benchmark

С новой схемой:
- **image_index** разблокирует валидацию bbox на multi-image паках.
- **Закрытый type** даёт точный diff по variations — можно считать Variations Match %.
- **sort_order** делает порядок выходного JSON детерминированным — diff стабилен.
- **Правила цены и scope** убирают две из четырёх основных причин «разброса» ответов между моделями при равном понимании картинки.
