# Menu

AI-powered restaurant menu digitization system. Scan a menu photo → structured multilingual data → public menu page.

**Stack:** Laravel 13, PHP 8.4, PostgreSQL, Filament v5, Livewire v4, Tailwind v4, Docker

---

## Getting Started

```bash
# Start containers
docker compose up -d

# Install dependencies and set up DB
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
# Apply new migrations
docker compose exec app php artisan migrate

# Recreate DB from scratch
docker compose exec app php artisan migrate:fresh --seed

# Update the LLM prompt in DB after editing PromptSeeder.php
docker compose exec app php artisan db:seed --class=PromptSeeder
```

---

## Tests

Tests run against a separate `menu_test` PostgreSQL database.

```bash
# Create test DB (once)
docker compose exec postgres psql -U menu -c "CREATE DATABASE menu_test;"

# Run all tests
docker compose exec app php artisan test --compact

# Run a specific file
docker compose exec app php artisan test --compact tests/Feature/SaveMenuAnalysisTest.php

# Run by test name
docker compose exec app php artisan test --compact --filter=test_creates_sections_and_items
```

---

## Debug: LLM Pipeline

```bash
# Parse JSON and show breakdown without writing to DB
docker compose exec app php artisan menu:import-json tests/llm_responce.json --dry-run

# Write to DB (creates a new restaurant automatically)
docker compose exec app php artisan menu:import-json tests/llm_responce.json

# Write to existing restaurant and activate the menu immediately
docker compose exec app php artisan menu:import-json tests/llm_responce.json --restaurant=1 --activate
```

---

## Prompts

```bash
# Export prompts from DB to database/prompts/
docker compose exec app php artisan prompts:export

# Import prompts from database/prompts/ into DB
docker compose exec app php artisan prompts:import
```

---

## API Authentication (Fortify + Sanctum)

Session-based SPA authentication via Laravel Fortify. CSRF flow:

```
1. GET  /sanctum/csrf-cookie          — init CSRF
2. POST /api/v1/auth/register         — create account + start session
   POST /api/v1/auth/login            — login + start session
3. All subsequent requests use session cookie automatically
```

### Auth routes

| Method | URL | Auth | Description |
|--------|-----|------|-------------|
| `POST` | `/api/v1/auth/register` | — | Register + auto-login |
| `POST` | `/api/v1/auth/login` | — | Login |
| `POST` | `/api/v1/auth/logout` | Yes | Logout |
| `GET` | `/api/v1/auth/user` | Yes | Current user |
| `PUT` | `/api/v1/auth/user/password` | Yes | Change password |
| `POST` | `/api/v1/auth/forgot-password` | — | Send reset link |
| `POST` | `/api/v1/auth/reset-password` | — | Reset password |

---

## Postman Setup

The API uses Laravel Sanctum session-based auth. Postman needs to be configured to handle CSRF tokens and session cookies properly.

### 1. Environment

Create an environment with variable:

| Variable | Value |
|----------|-------|
| `base_url` | `http://localhost:8000` |
| `xsrf_token` | _(leave empty — auto-populated)_ |

### 2. Collection — Pre-request Script

Add to the **Pre-request** tab of the collection (runs before every request):

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

### 3. Collection — Tests Script (Post-response)

Add to the **Tests** tab of the collection (runs after every response):

```js
const xsrf = pm.cookies.get('XSRF-TOKEN');
if (xsrf) {
    pm.environment.set('xsrf_token', decodeURIComponent(xsrf));
}
```

This keeps `xsrf_token` in sync with the latest cookie after every response, including after login (which regenerates the session and issues a new token).

### 4. Auth Flow

Execute in order:

1. `GET {{base_url}}/sanctum/csrf-cookie` — initializes CSRF, sets session cookie
2. `POST {{base_url}}/api/v1/auth/login` — authenticates, session becomes active
3. All subsequent requests are authenticated automatically via session cookie

> If you get `401 Unauthenticated` after login — clear cookies for `localhost:8000` in the Postman cookie manager and repeat from step 1. This usually means a stale session from a previous run was overwriting the new one.

### Why this works

- `Origin: http://localhost` tells Sanctum to treat the request as stateful (session-based), not token-based
- `XSRF-TOKEN` cookie is sent automatically by Postman's cookie jar
- `X-XSRF-TOKEN` header (from the pre-request script) is compared against the cookie by Laravel's CSRF middleware
- After each response the Tests script updates `xsrf_token` in env, so the next request always uses the latest token

---

## Translation System

Переводы хранятся в двух вспомогательных таблицах и подключаются к любой модели через трейт `HasTranslations`.

### Таблицы

**`locales`** — справочник языков:

| Колонка | Тип | Описание |
|---------|-----|----------|
| `id` | bigint | PK |
| `code` | varchar(10) | BCP-47 код (`uk`, `en`, `und`) |
| `name` | varchar(100) | Человекочитаемое название |

**`translations`** — все переводы всех моделей (полиморфная таблица):

| Колонка | Тип | Описание |
|---------|-----|----------|
| `translatable_type` | string | Класс модели (напр. `App\Models\MenuItem`) |
| `translatable_id` | bigint | ID записи |
| `locale_id` | FK → locales | Язык перевода |
| `field` | varchar(100) | Поле (`name`, `description`, `address`) |
| `value` | text | Текст |
| `is_initial` | bool | `true` = оригинал, `false` = LLM-перевод |

Уникальный индекс: `(translatable_type, translatable_id, locale_id, field)` — одна запись на поле + язык.

### Модели с переводами

| Модель | Переводимые поля |
|--------|-----------------|
| `Restaurant` | `name`, `address` |
| `MenuSection` | `name` |
| `MenuItem` | `name`, `description` |
| `MenuOptionGroup` | `name` |
| `MenuOptionGroupOption` | `name` |

### `is_initial` — оригинал vs перевод

- `is_initial = true` — исходный текст: введён пользователем или распознан LLM при анализе меню. На каждое поле может быть только один такой перевод.
- `is_initial = false` — машинный перевод, созданный `TranslateMenuJob`.

При чтении (`translate($field, $locale)`) сначала ищется перевод на нужный язык, при отсутствии — fallback на `is_initial`.

### `source_locale` в таблице `menus`

Язык оригинала конкретного меню — определяется LLM при анализе фото. Используется как источник в `TranslateMenuJob` (откуда переводить) и при формировании TSV-payload. Хранится на уровне меню, а не ресторана, потому что одно заведение может иметь несколько меню на разных языках.

### Флоу перевода

```
1. Анализ фото → LLM → SaveMenuAnalysisAction
      Записывает is_initial=true для source_locale меню

2. TranslateMenuJob::handle()
      Строит TSV из is_initial текстов всего меню
      → DeepSeek API (deepseek-chat)
      → parseTsvAndSave(): setTranslation(is_initial=false) для target_locale
```

### `und` — неизвестный язык

Код `und` (undetermined, BCP-47) используется когда язык не определён — например, при ручном вводе без указания `primary_language`. Это валидный код, а не заглушка.

---

## Restaurant API

All restaurant endpoints require authentication. Responses follow [JSON:API](https://jsonapi.org/) format.

### Endpoints

| Method | URL | Description |
|--------|-----|-------------|
| `GET` | `/api/v1/restaurants` | List own restaurants |
| `POST` | `/api/v1/restaurants` | Create restaurant |
| `GET` | `/api/v1/restaurants/{id}` | Get restaurant |
| `PUT` | `/api/v1/restaurants/{id}` | Update restaurant |
| `DELETE` | `/api/v1/restaurants/{id}` | Delete restaurant |
| `GET` | `/api/v1/restaurants/active-menus` | Active menus with sections & items |

---

### POST /api/v1/restaurants — Create

**Request body** (`application/json`):

```json
{
  "name": "My Restaurant",
  "address": "123 Main St",
  "city": "Kyiv",
  "country": "UA",
  "phone": "+380441234567",
  "currency": "UAH",
  "primary_language": "uk",
  "opening_hours": {
    "raw_text": "Mon-Fri 10:00-22:00",
    "is_24_7": false,
    "periods": [
      { "days": ["Mon", "Tue", "Wed", "Thu", "Fri"], "open": "10:00", "close": "22:00" }
    ]
  }
}
```

All fields are optional. `name` and `address` are stored as translations in `primary_language` (defaults to `und`).

**Response** `201 Created`:

```json
{
  "data": {
    "id": "1",
    "type": "restaurants",
    "attributes": {
      "name": "My Restaurant",
      "address": "123 Main St",
      "city": "Kyiv",
      "country": "UA",
      "phone": "+380441234567",
      "currency": "UAH",
      "primary_language": "uk",
      "opening_hours": { "raw_text": "Mon-Fri 10:00-22:00", "is_24_7": false, "periods": [...] },
      "image": null
    },
    "relationships": {
      "activeMenu": { "data": null },
      "menus": { "data": [] }
    }
  }
}
```

---

### PUT /api/v1/restaurants/{id} — Update

Same body schema as POST, all fields optional (`sometimes`). Returns `200` with the updated resource.

---

### DELETE /api/v1/restaurants/{id} — Delete

No body. Returns `204 No Content`. Only the restaurant owner can delete.

---

### GET /api/v1/restaurants/active-menus — Active Menus

Returns all active menus across restaurants owned by the current user, with full section/item tree.

> **Note:** This route must come before `{restaurant}` in `api.php` — it does, so `/active-menus` is not captured as a restaurant ID.

**Response** `200 OK`:

```json
{
  "data": [
    {
      "restaurant_id": 1,
      "restaurant_name": "My Restaurant",
      "menu_id": 3,
      "source_locale": "uk",
      "detected_date": "2026-04-01",
      "sections": [
        {
          "id": 10,
          "name": "Salads",
          "sort_order": 0,
          "items": [
            {
              "id": 42,
              "name": "Caesar",
              "description": "Classic caesar salad",
              "starred": false,
              "price_type": "fixed",
              "price_value": "12.50",
              "price_min": null,
              "price_max": null,
              "price_unit": null,
              "price_original_text": "12.50"
            }
          ]
        }
      ]
    }
  ]
}
```

---

## Code Style

```bash
vendor/bin/pint --dirty
```
