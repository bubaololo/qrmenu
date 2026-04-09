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

## Code Style

```bash
vendor/bin/pint --dirty
```
