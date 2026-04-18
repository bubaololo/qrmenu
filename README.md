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

Переводы хранятся в таблице `translations` и подключаются к любой модели через трейт `HasTranslations`.

**`translations`** — полиморфная таблица:

| Колонка | Тип | Описание |
|---------|-----|----------|
| `translatable_type` | string | Класс модели (`App\Models\MenuItem`) |
| `translatable_id` | bigint | ID записи |
| `locale` | varchar(10) | ISO 639-1 (`vi`, `en`, `und`) |
| `field` | varchar(100) | Поле (`name`, `description`, `address`) |
| `value` | text | Текст |
| `is_initial` | bool | `true` = оригинал, `false` = перевод |

Уникальный индекс: `(translatable_type, translatable_id, locale, field)`.

**Модели с переводами:** `Restaurant` (name, address), `MenuSection` (name), `MenuItem` (name, description), `MenuOptionGroup` (name), `MenuOptionGroupOption` (name).

### Ключевые концепции

**`is_initial`** — `true` означает исходный текст (введён пользователем или распознан LLM). На каждое поле один `is_initial`. Переводы от `TranslateMenuJob` имеют `is_initial = false`.

**`source_locale`** — язык оригинала меню, определяется LLM при анализе фото. Хранится на уровне меню (не ресторана), т.к. у одного заведения могут быть меню на разных языках.

**`und`** — код BCP-47 для неопределённого языка. Используется при ручном вводе без указания локали.

### Accept-Language

Все эндпоинты чтения и записи учитывают заголовок `Accept-Language`:

- **Чтение** — возвращает перевод для локали, fallback на source text
- **Запись** — `PUT` сохраняет текст как перевод для указанной локали (`is_initial: false`), если локаль = `source_locale` — обновляет оригинал (`is_initial: true`)

```js
// Установить глобально при смене языка
axios.defaults.headers.common['Accept-Language'] = selectedLocale;
```

### Флоу перевода

```
POST /api/v1/menus/{id}/translations/{locale}
  → TranslateMenuJob (queue)
  → buildTsvPayload()  — собирает is_initial тексты всего меню
  → DeepSeek API
  → parseTsvAndSave()  — setTranslation(is_initial=false) для target_locale
```

Промпт передаёт полное название языка (`"Kongo (kg)"`) чтобы LLM не путал ISO 639-1 коды с кодами стран.

---

## Menu Analysis (Async)

Menu images are analyzed asynchronously via a queue job with cascading LLM fallback.

**Flow:** Upload images → `AnalyzeMenuJob` dispatched → cascade tries providers in order → results saved to `menu_analyses` table.

**Model cascade tiers** (configured in `config/llm.php`):

| Pack size | Tier 1 | Tier 2 | Tier 3 |
|-----------|--------|--------|--------|
| 1-4 images | qwen3-vl-plus (DashScope) | gemini-2.5-flash | gemma-4-26b (OpenRouter) |
| 5+ images | gemini-2.5-flash | — | — |

**API endpoints:**

```bash
# Async (default) — returns 202 with UUID for polling
curl -X POST /api/v1/menu-analyses -F 'images[]=@menu.jpg'

# Poll for result
curl /api/v1/menu-analyses/{uuid}

# Sync mode for dev/testing — returns 200 with full result inline
curl -X POST /api/v1/menu-analyses?sync=1 -F 'images[]=@menu.jpg'
```

**Queue:** Jobs run on the `llm-analysis` queue with 10-minute timeout. In Docker, the `horizon` service manages all workers via Laravel Horizon. Two supervisors are configured: `supervisor-1` (default queue) and `supervisor-llm` (llm-analysis queue, 10-min timeout, 1 try).

**Monitoring:** Laravel Horizon (`/horizon`) and Pulse (`/pulse`) are available for queue and application monitoring.

- In `local` env — accessible to everyone without authentication.
- In production — set `ADMIN_EMAILS=admin@example.com,ops@example.com` in `.env` to restrict access to those addresses.

**Logging:** Every LLM API call is logged to the `llm_requests` table with provider, model, duration, tokens, status, and error details for retrospective analysis.

---

## Image Preflight (LLM-based rotation & cropping)

Before the main analysis, each uploaded menu photo is sent to a lightweight vision LLM (Gemini 2.5 Flash Lite by default) to detect the correct rotation and the menu's bounding box within the frame. This fixes cases where phones write an incorrect EXIF orientation (e.g. Samsung Galaxy bug where raw pixels are already correct but EXIF still demands rotation) and removes irrelevant parts of the photo (table, fingers, background) that would otherwise waste tokens during the main analysis.

| Variable | Default | Description |
|----------|---------|-------------|
| `IMAGE_PREFLIGHT_ENABLED` | `true` | Toggle the preflight stage. Set to `false` to skip — the analysis still runs but orientation/cropping is no longer auto-corrected. |
| `IMAGE_PREFLIGHT_MODEL` | `gemini-2.5-flash-lite` | Gemini model used for preflight. Must support vision input. Keep it cheap — preflight is a simple JSON classification task. |
| `IMAGE_PREFLIGHT_MAX_DIM` | `384` | Longest-side in pixels for the downsampled copy sent to the LLM. 384 activates Gemini's small-image rule (both dims ≤ 384 → 1 tile, 258 tokens, ~$0.00003 per image). Keep minimal — higher resolution costs the same but sends more bytes. |
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
