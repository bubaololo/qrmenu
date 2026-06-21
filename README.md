# Menu

AI-powered restaurant menu digitization system. Scan a menu photo → structured multilingual data → public menu page.

**Stack:** Laravel 13, PHP 8.4, PostgreSQL, Filament v5, Livewire v4, Tailwind v4, Docker

---

## Getting Started

```bash
docker compose up -d

composer install
cp .env.example .env
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
npm install && npm run build
```

---

## Development

```bash
# Server + queue + logs + Vite hot reload (all in one)
composer run dev

# If frontend changes aren't showing up
npm run build
```

---

## Migrations & Seeders

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:fresh --seed

# Update LLM prompts in DB after editing PromptSeeder.php
docker compose exec app php artisan db:seed --class=PromptSeeder
```

---

## Custom artisan commands

| Command | Purpose |
|---|---|
| `menu:import-json {file} [--restaurant=] [--activate] [--dry-run]` | Import a menu from a JSON fixture (debugging the LLM pipeline). See *Debug: LLM Pipeline* below. |
| `prompts:export` | Export all prompts from the DB to `database/prompts/*.json`. |
| `prompts:import` | Import prompts from `database/prompts/` into the DB. |
| `llm:benchmark [--only=] [--model=] [--skip=] [--dry-run]` | Benchmark all vision LLM providers against the three image packs. See *LLM Benchmark* below. |
| `llm:bbox-test [--image=] [--model=] [--skip=] [--output=] [--max-dim=]` | Test bbox detection on one image via the LLM preflight model. |
| `icons:sync` | Re-read `resources/img/menu/*.svg` and upsert each as an `icons.svg` row (cleaned to `<symbol id="…">`, ink themed to `currentColor`). Flushes Redis sprite caches. Run after adding or editing icon SVGs. Also wired into `DatabaseSeeder` so `migrate:fresh --seed` populates the table automatically. |

---

## Tests

Tests run against a separate `menu_test` PostgreSQL database.

```bash
# Create test DB (once)
docker compose exec postgres psql -U menu -c "CREATE DATABASE menu_test;"

docker compose exec app php artisan test --compact
docker compose exec app php artisan test --compact tests/Feature/MenuTranslationTest.php
docker compose exec app php artisan test --compact --filter=test_creates_sections_and_items
```

Key LLM-pipeline tests:

- `tests/Unit/MenuJsonDecodeTest.php` — LLM JSON decoder (markdown fences, prose prefix/suffix, top-level arrays).
- `tests/Feature/SaveMenuAnalysisTest.php` — end-to-end ingestion via `tests/llm_responce.json` fixture: sections/items/options/variations/translations all get persisted.
- `tests/Feature/MenuPageTest.php` — public menu page renders parsed fixture data.
- `tests/Feature/ImageUploadTest.php` — upload → analysis job dispatch.

See `## LLM Benchmark` below for multi-provider quality testing on real image packs.

---

## Debug: LLM Pipeline

```bash
# Parse JSON without writing to DB
docker compose exec app php artisan menu:import-json tests/llm_responce.json --dry-run

# Write to DB
docker compose exec app php artisan menu:import-json tests/llm_responce.json

# Write to existing restaurant and activate
docker compose exec app php artisan menu:import-json tests/llm_responce.json --restaurant=1 --activate
```

---

## Prompts

LLM prompts are stored in the `prompts` table and managed via `PromptSeeder`. Two types:
- `menu_analyzer` — extracts structured data from menu photos
- `menu_translator` — translates menu content via TSV pipe format

```bash
# Export from DB to database/prompts/
docker compose exec app php artisan prompts:export

# Import from database/prompts/ into DB
docker compose exec app php artisan prompts:import
```

---

## Admin Panel

Filament admin panel: `http://localhost:8000/panel`

---

## Documentation

- **Admin guide** — LaRecipe, trilingual (ru/en/vi): `http://localhost:8000/docs`
- **API reference** — Scramble / OpenAPI UI, public (no auth): `http://localhost:8000/api/docs`
- **OpenAPI spec** — raw JSON: `http://localhost:8000/api/docs.json`

In production the same paths sit under the app domain, e.g. `https://qreaty.com/docs`, `https://qreaty.com/api/docs`.

---

## API Authentication

Session-based SPA auth via Laravel Fortify + Sanctum. CSRF flow:

```
1. GET  /sanctum/csrf-cookie     — init CSRF
2. POST /api/v1/auth/login       — authenticate
3. All subsequent requests use session cookie automatically
```

Full route list: `php artisan route:list --path=api/v1/auth`

---

## Postman Setup

### Environment

| Variable | Value |
|----------|-------|
| `base_url` | `http://localhost:8000` |
| `xsrf_token` | _(leave empty — auto-populated)_ |

### Pre-request Script (collection level)

```js
pm.request.headers.add({ key: 'Origin', value: 'http://localhost' });
pm.request.headers.add({ key: 'Accept', value: 'application/json' });

if (pm.request.method !== 'GET') {
    const xsrf = pm.environment.get('xsrf_token');
    if (xsrf) {
        pm.request.headers.upsert({ key: 'X-XSRF-TOKEN', value: xsrf });
    }
}
```

### Tests Script (collection level)

```js
const xsrf = pm.cookies.get('XSRF-TOKEN');
if (xsrf) {
    pm.environment.set('xsrf_token', decodeURIComponent(xsrf));
}
```

> If you get `401 Unauthenticated` after login — clear cookies for `localhost:8000` in the Postman cookie manager and repeat from step 1.

---

## Translation System

Переводимые сущности — только содержимое меню: `MenuSection.name`, `MenuItem.name|description`, `MenuOptionGroup.name`, `MenuOptionGroupOption.name`. Подключены через трейт `HasTranslations` (`app/Models/Concerns/HasTranslations.php`).

`Restaurant.name|address` и `Zone.name` — обычные колонки, не переводятся (имена ресторанов/зон собственные).

### Хранение

Полиморфная таблица `translations`:

| Колонка | Описание |
|---|---|
| `translatable_type` + `translatable_id` | морф-указатель на сущность |
| `field_id` → `translation_fields` | справочник полей (`name`, `description`) |
| `locale` | ISO 639-1 (`en`, `vi`, …) |
| `value` | текст |
| `is_initial` | `true` = исходник, `false` = машинный перевод |

Инварианты на уровне БД:
- `unique(translatable_type, translatable_id, locale, field_id)` — один перевод на (сущность + поле + локаль)
- `unique(translatable_type, translatable_id, field_id) WHERE is_initial = true` — **ровно один initial** на (сущность + поле), независимо от языка

### Ключевые концепции

**`is_initial`** — универсальный исходник смысла. Каждое поле имеет ровно одну initial-запись. Все остальные локали — машинные переводы. При смене source_locale меню старый initial удаляется автоматически в `HasTranslations::setTranslation()`.

**`menus.source_locale`** — язык, на котором меню было распознано из фото. Хранится на уровне меню (у одного ресторана может быть много меню на разных языках).

**`menus.source_locale = 'mixed'`** — спец-значение для меню, где OCR увидел несколько языков одновременно (например, английские названия + вьетнамские описания). В таком случае initial-переводы для items не сохраняются вообще: пользователь правит их через API, а `TranslateMenuJob` переводит всё на конкретный target locale.

**`Menu::availableLocales()`** — список локалей, доступных для редактирования: `source_locale` + `restaurant.primary_language` + все локали с реальными переводами для items этого меню. `'mixed'` отфильтровано.

### Заголовок `X-Locale`

Фронт указывает активную локаль редактора через **`X-Locale`** (не `Accept-Language`, чтобы браузерный `en-US,en;q=0.9` не триггерил валидацию). `Accept-Language` остался как fallback-подсказка для read-only публичных страниц.

**Запись (PUT/POST на переводимые сущности):**

| `X-Locale` | Поведение |
|---|---|
| не передан | пишем в `source_locale` как `is_initial = true` |
| = `source_locale` | то же — `is_initial = true` |
| есть в `availableLocales` | пишем как `is_initial = false` |
| **нет в `availableLocales`** | **422** — добавить язык можно только через `POST /menus/{id}/translations/{locale}` |
| `mixed` | **422** — это атрибут source_locale, не локаль перевода |

Реализация валидации: `ResolvesLocale::resolveLocale(Menu)` (`app/Http/Controllers/Menus/Concerns/ResolvesLocale.php`), вызывается из всех 4 контроллеров переводимых сущностей.

**Чтение** — `translate($field, $locale)` ищет запись на нужную локаль, fallback на `is_initial = true`. `localizedText($field)` использует `request()->attributes->get('locale_from_header')`.

### Реактивный перевод

`TranslationObserver` (`app/Observers/TranslationObserver.php`) подписан на `Translation::saved`. При изменении initial-записи диспатчит `TranslateEntityJob` на все локали из `availableLocales` (кроме source). Non-initial записи игнорируются, чтобы не было цикла.

### Флоу добавления нового языка

```
POST /api/v1/menus/{id}/translations/{locale}
  → TranslateMenuJob (queue, разбивает на чанки по 80 строк)
  → Bus::batch[ TranslateChunkJob × N ] (DeepSeek API)
  → setTranslation(is_initial=false) для каждого item/section/option
  → новый язык появляется в availableLocales автоматически
```

После завершения job'а фронт может слать `X-Locale: <newLocale>` и редактировать переводы вручную.

---

## Menu Analysis (Async)

End-to-end pipeline for turning a pack of menu photos into a structured, saved menu:

1. **Upload** — `POST /api/v1/menu-analyses` with `multipart/form-data`, field `images[]` (one or more JPEGs/PNGs ≤10MB each). Optional: `restaurant_id` (save result against an existing restaurant the user owns), `model` (override the cascade), `sync=1` (run inline instead of queueing).
2. **Preflight** — a lightweight vision LLM detects per-image rotation + content bbox and rewrites the originals on disk. See the *Image Preflight* section below.
3. **Preprocess** — trim, deskew, contrast, resize, convert to WebP.
4. **Enqueue** — async mode returns `202 Accepted` with `{ data: { id: <uuid>, attributes: { status: "pending", image_count } } }`, and an `AnalyzeMenuJob` is dispatched to the `llm-analysis` queue.
5. **LLM cascade** — `LlmCascadeService` tries providers in order until one returns valid JSON:

   | Pack size | Tier 1 | Tier 2 | Tier 3 |
   |-----------|--------|--------|--------|
   | 1-4 images | qwen3-vl-plus (DashScope) | gemini-2.5-flash | gemma-4-26b free (OpenRouter) |
   | 5+ images | qwen3-vl-plus (DashScope) | gemma-4-31b-it @ DeepInfra fp8 (OpenRouter) | gemini-2.5-flash |

   **Chunking for 6+ images**: packs above the `chunk_when_images_gt` threshold (default 5) are split into chunks of `chunk_size` images (default 4). The orchestrator creates an empty `Menu` shell up-front and dispatches a `Bus::batch` of `AnalyzeChunkJob`s so chunks run **in parallel** on Horizon's `llm-analysis` supervisor; each chunk runs its own small-tier cascade, retries up to 3 times with exponential backoff, and appends its sections/items straight to the DB via `SaveMenuAnalysisAction::appendChunk`. The append step continues `sort_order`, remaps `image_bbox.image_index` by the cumulative image offset, and backfills restaurant metadata (name, phone, opening_hours, etc.) only for fields that are still empty. The batch's `then()` hook runs `FinalizeAnalysisJob` (activates the menu, dispatches `CropMenuItemImagesJob`); its `catch()` hook marks the analysis `failed` and cleans up images if any chunk exhausts retries.

   Translation uses the same pattern: `TranslateMenuJob` splits the TSV payload into `llm.translation.chunk_lines` batches (default 80 lines) and fans out `TranslateChunkJob`s via `Bus::batch` to DeepSeek in parallel, with the same 3× retry + backoff policy. Each chunk writes its translations independently; a transient timeout on one chunk doesn't affect the others.

   Every call is logged to the `llm_requests` table with provider, model, duration, tokens, status, and error details.
6. **Save** — if `restaurant_id` was supplied, `SaveMenuAnalysisAction` materialises the JSON into `menus` → `menu_sections` → `menu_items` with translations and per-item `image_bbox` + confidence.
7. **Crop** — `CropMenuItemImagesJob` extracts each item's image from the originals using its saved bbox, writes main + thumb sizes.

### How the client learns the result: polling (only)

There is no webhook, websocket, or push channel. After POST returns `202`, the client keeps the `uuid` and polls `GET /v1/menu-analyses/{uuid}` until `attributes.status` reaches a terminal state.

Status lifecycle: `pending` → `processing` → `completed` **or** `failed`.

```bash
# Start async analysis
curl -X POST /api/v1/menu-analyses -F 'images[]=@menu.jpg' -F 'restaurant_id=42'
# → 202, data.id = <uuid>, data.attributes.status = "pending"

# Poll every 2–3 seconds with the JSON:API Accept header
curl -H 'Accept: application/vnd.api+json' /api/v1/menu-analyses/{uuid}
# Terminal states:
#   status = "completed" → attributes.menu (JSON tree),
#                          attributes.saved_menu_id, attributes.saved_restaurant_id,
#                          attributes.item_count, attributes.completed_at
#   status = "failed"    → attributes.error_message

# Sync mode for dev/testing — blocks the HTTP request until the LLM returns,
# then responds 200 with the full menu inline. Don't use from the UI; the
# cascade can take tens of seconds to minutes.
curl -X POST /api/v1/menu-analyses?sync=1 -F 'images[]=@menu.jpg'
```

Typical total time end-to-end (upload → `completed`): ~30–90 s for 1–4 images on the fast-path model, longer if the cascade falls through tiers or the crop job is slow. The `pending → processing` transition happens within ~1 s once the Horizon worker picks the job up; clients can treat either value the same way and just keep polling.

Once `status = completed`, the saved menu tree is also reachable directly at `GET /api/v1/menus/{saved_menu_id}` (append `?confidence=1` within 7 days of analysis to also get per-item text/bbox confidence from Redis). `restaurant_id` in the upload is required to get a `saved_menu_id` — analyses run without one return the parsed menu in `attributes.menu` but never persist.

**OpenAPI:** live API reference at `/api/docs` (raw spec at `/api/docs.json`); export a static file with `php artisan scramble:export --path=api.json`.

**Queue & monitoring:** Horizon runs two supervisors — `supervisor-1` (default queue) and `supervisor-llm` (`llm-analysis`, 10-min timeout, 1 try). Dashboards: `/horizon`, `/pulse`, plus the Filament admin panel at `/panel`. In `local` they're open to everyone; in any other environment access is gated by a single `ADMIN_EMAILS` env var (comma-separated list), which guards all three. Empty in non-local → all three are locked down.

---

## Image Preflight (LLM-based rotation & cropping)

Before the main analysis, each uploaded menu photo is normalised in two steps. First, EXIF orientation from the camera is applied physically via `Imagick::autoOrient()` — this handles the 99% case where modern phones correctly tag rotation in the file's metadata. Second, a lightweight vision LLM (Gemini 2.5 Flash Lite by default) verifies the EXIF-oriented image and detects two things: any **remaining** rotation (for the rare cases where EXIF was wrong or absent — flat top-down shots, scans, screenshots) and the menu's bounding box within the frame so we can crop away irrelevant parts (table, fingers, background) that would otherwise waste tokens during the main analysis.

| Variable | Default | Description |
|----------|---------|-------------|
| `IMAGE_PREFLIGHT_ENABLED` | `true` | Toggle the LLM preflight stage. Set to `false` to skip the LLM call — EXIF auto-orientation still happens via the upstream `ImageProcessor`/preprocess pipeline, but no content cropping or fallback rotation correction. |
| `IMAGE_PREFLIGHT_MODEL` | `gemini-2.5-flash-lite` | Gemini model used for preflight. Must support vision input. Keep it cheap — preflight is a simple JSON classification task. |
| `IMAGE_PREFLIGHT_MAX_DIM` | `768` | Longest-side in pixels for the downsampled copy sent to the LLM. 768 keeps the image inside a single Gemini tile (≤ 768 longest side → 1 tile, 258 tokens, ~$0.00003 per image) while leaving menu text legible — important for the rare cases where the LLM has to actually correct rotation. Lower values save bytes but at 384 fine print becomes unreadable. |
| `IMAGE_PREFLIGHT_TIMEOUT` | `15` | HTTP timeout in seconds per preflight request. On timeout, preflight falls back to a no-op (no rotation, no crop). |

**Cost**: ~$0.00003 per image with Gemini 2.5 Flash Lite. For a 10-image pack: ~$0.0003.

**Latency**: preflight calls run in parallel via `Http::pool`. A 10-image pack typically completes preflight in 300–500ms.

**Fallback**: if preflight fails (timeout, JSON parse error, API error), the service returns a no-op result (`rotation_cw=0`, no crop) and the main analysis continues unaffected. Failures are logged to the `llm` channel.

---

## Image Test Sets

Handcrafted menu image packs for regression testing and LLM benchmarking live in `tests/image_test_sets/`.

| Pack | Images | Restaurant | Primary language |
|---|---:|---|---|
| `easy/` | 1 | Amélie Pâtisserie et Café (ĐL) | en |
| `medium/` | 5 | Bia Khô Mực Đà Lạt (ĐL) | vi |
| `hard/` | 15 | Pizza Dalat 24h (ĐL) | vi |

Each pack ships with a `ground_truth.json` hand-typed against the active `menu_analyzer` prompt schema. The Match % metric in the benchmark compares parsed `price.original_text` against these ground truths.

Prompt details and hand-written decisions:

- `tests/image_test_sets/PROMPT_REVIEW.md` — what was ambiguous in the original prompt and what was tightened.
- `tests/image_test_sets/GROUND_TRUTH_NOTES.md` — coverage notes and interpretation choices per pack.

---

## LLM Benchmark

Benchmarks all vision LLM providers (Gemini, OpenRouter Gemma/Qwen/InternVL/Reka/Arcee/Llama, OpenAI GPT-4.1/4o/4-turbo, DashScope Qwen VL) against the three image packs and writes a results table.

Before running: make sure the DB has the latest prompt:

```bash
docker compose exec app php artisan db:seed --class=PromptSeeder
```

Usage:

```bash
docker compose exec app php artisan llm:benchmark --dry-run
docker compose exec app php artisan llm:benchmark --only=easy --model=gemini-2.5-flash
docker compose exec app php artisan llm:benchmark
docker compose exec app php artisan llm:benchmark --skip=openrouter/reka-edge,openrouter/arcee-spotlight
```

Artifacts are written to `tests/image_test_sets/benchmarks/{YYYYMMDD_HHMMSS}/` and committed to git for historical comparison:

- `{pack}__{model-slug}/raw.txt` — raw LLM response
- `{pack}__{model-slug}/parsed.json` — `MenuJson::decodeMenuFromLlmText()` output
- `{pack}__{model-slug}/meta.json` — per-run metrics
- `summary.md` — combined results table
- `summary.csv` — same data in CSV

The command also prints the table directly to the console when finished.

---

## Code Style

```bash
vendor/bin/pint --dirty
```
