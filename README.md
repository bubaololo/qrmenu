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

Переводы хранятся в таблице `translations` и подключаются к любой модели через трейт `HasTranslations`.

### Таблицы

**`translations`** — все переводы всех моделей (полиморфная таблица):

| Колонка | Тип | Описание |
|---------|-----|----------|
| `translatable_type` | string | Класс модели (напр. `App\Models\MenuItem`) |
| `translatable_id` | bigint | ID записи |
| `locale` | varchar(10) | ISO 639-1 код языка (`vi`, `en`, `und`) |
| `field` | varchar(100) | Поле (`name`, `description`, `address`) |
| `value` | text | Текст |
| `is_initial` | bool | `true` = оригинал, `false` = перевод |

Уникальный индекс: `(translatable_type, translatable_id, locale, field)` — одна запись на поле + язык.

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

## Menu API

All endpoints require authentication. Responses follow [JSON:API](https://jsonapi.org/) format.

Authorization: owners can create/update/delete; owners and waiters can read.

### Endpoints

#### Menus

| Method | URL | Description |
|--------|-----|-------------|
| `GET` | `/api/v1/restaurants/{id}/menus` | List menus for a restaurant |
| `POST` | `/api/v1/restaurants/{id}/menus` | Create menu |
| `GET` | `/api/v1/menus/{id}` | Full menu tree (locale via `Accept-Language` header) |
| `PUT` | `/api/v1/menus/{id}` | Update menu |
| `DELETE` | `/api/v1/menus/{id}` | Delete menu |
| `POST` | `/api/v1/menus/{id}/activate` | Activate menu (deactivates siblings) |
| `POST` | `/api/v1/menus/{id}/clone` | Clone menu (deep copy with sections/items/groups) |
| `GET` | `/api/v1/menus/{id}/locales` | List locales that have translations |
| `POST` | `/api/v1/menus/{id}/translations/{locale}` | Trigger LLM translation to a locale |

#### Sections

| Method | URL | Description |
|--------|-----|-------------|
| `POST` | `/api/v1/menus/{id}/sections` | Create section |
| `PUT` | `/api/v1/menus/{id}/sections/reorder` | Bulk reorder sections |
| `PUT` | `/api/v1/menu-sections/{id}` | Update section (use `Accept-Language` to target a translation locale) |
| `DELETE` | `/api/v1/menu-sections/{id}` | Delete section |

#### Items

| Method | URL | Description |
|--------|-----|-------------|
| `POST` | `/api/v1/menu-sections/{id}/items` | Create item |
| `PUT` | `/api/v1/menu-sections/{id}/items/reorder` | Bulk reorder items |
| `PUT` | `/api/v1/menu-items/{id}` | Update item (use `Accept-Language` to target a translation locale) |
| `DELETE` | `/api/v1/menu-items/{id}` | Delete item |

#### Option Groups

| Method | URL | Description |
|--------|-----|-------------|
| `POST` | `/api/v1/menu-sections/{id}/option-groups` | Create option group |
| `PUT` | `/api/v1/menu-option-groups/{id}` | Update option group (use `Accept-Language` to target a translation locale) |
| `DELETE` | `/api/v1/menu-option-groups/{id}` | Delete option group |
| `POST` | `/api/v1/menu-option-groups/{id}/attach-items` | Link items to group |
| `POST` | `/api/v1/menu-option-groups/{id}/detach-items` | Unlink items from group |

#### Options

| Method | URL | Description |
|--------|-----|-------------|
| `POST` | `/api/v1/menu-option-groups/{id}/options` | Create option |
| `PUT` | `/api/v1/menu-option-group-options/{id}` | Update option (use `Accept-Language` to target a translation locale) |
| `DELETE` | `/api/v1/menu-option-group-options/{id}` | Delete option |

---

### POST /api/v1/restaurants/{id}/menus — Create Menu

```json
{
  "source_locale": "vi",
  "detected_date": "2026-04-01",
  "is_active": false
}
```

**Response** `201 Created`:

```json
{
  "data": {
    "id": "1",
    "type": "menus",
    "attributes": {
      "source_locale": "vi",
      "detected_date": "2026-04-01",
      "source_images_count": 0,
      "is_active": false,
      "created_from_menu_id": null,
      "created_at": "2026-04-09T12:00:00Z"
    },
    "relationships": {
      "restaurant": { "data": { "id": "1", "type": "restaurants" } },
      "sections": { "data": [] }
    }
  }
}
```

---

### POST /api/v1/menus/{id}/activate — Activate Menu

No body. Deactivates all other menus for the same restaurant, activates this one.

**Response** `200 OK` — updated menu resource with `"is_active": true`.

---

### POST /api/v1/menus/{id}/clone — Clone Menu

No body. Creates a deep copy: new menu + all sections, items, option groups, options, and their translations.

**Response** `201 Created` — new menu resource with `"created_from_menu_id": <original_id>`.

---

### POST /api/v1/menus/{id}/sections — Create Section

`name` is stored as a translation in `menu.source_locale` (or `und` if not set).

```json
{
  "name": "Salads",
  "sort_order": 0
}
```

**Response** `201 Created`:

```json
{
  "data": {
    "id": "5",
    "type": "menu-sections",
    "attributes": {
      "name": "Salads",
      "sort_order": 0
    },
    "relationships": {
      "items": { "data": [] },
      "optionGroups": { "data": [] }
    }
  }
}
```

---

### PUT /api/v1/menus/{id}/sections/reorder — Reorder Sections

```json
{
  "order": [
    { "id": 5, "sort_order": 0 },
    { "id": 3, "sort_order": 1 },
    { "id": 8, "sort_order": 2 }
  ]
}
```

**Response** `200 OK` — array of updated section resources.

---

### POST /api/v1/menu-sections/{id}/items — Create Item

```json
{
  "name": "Caesar Salad",
  "description": "Romaine, croutons, parmesan",
  "starred": false,
  "price_type": "fixed",
  "price_value": "12.50",
  "price_original_text": "12.50",
  "sort_order": 0
}
```

`price_type` values: `fixed`, `range`, `per_unit`, `market`, `free`.

**Response** `201 Created`:

```json
{
  "data": {
    "id": "42",
    "type": "menu-items",
    "attributes": {
      "name": "Caesar Salad",
      "description": "Romaine, croutons, parmesan",
      "starred": false,
      "price_type": "fixed",
      "price_value": "12.50",
      "price_min": null,
      "price_max": null,
      "price_unit": null,
      "price_original_text": "12.50",
      "image": null,
      "sort_order": 0
    },
    "relationships": {
      "optionGroups": { "data": [] }
    }
  }
}
```

---

### POST /api/v1/menu-sections/{id}/option-groups — Create Option Group

```json
{
  "name": "Add-ons",
  "is_variation": false,
  "required": false,
  "allow_multiple": true,
  "min_select": 0,
  "max_select": 3,
  "sort_order": 0
}
```

**Response** `201 Created`:

```json
{
  "data": {
    "id": "10",
    "type": "menu-option-groups",
    "attributes": {
      "name": "Add-ons",
      "type": null,
      "is_variation": false,
      "required": false,
      "allow_multiple": true,
      "min_select": 0,
      "max_select": 3,
      "sort_order": 0
    },
    "relationships": {
      "options": { "data": [] },
      "items": { "data": [] }
    }
  }
}
```

---

### POST /api/v1/menu-option-groups/{id}/attach-items

Links menu items to an option group. Only items belonging to the same section as the group are linked (others are silently ignored).

```json
{ "item_ids": [42, 43] }
```

**Response** `200 OK` — updated option group resource with populated `items` relationship.

---

### POST /api/v1/menu-option-groups/{id}/options — Create Option

```json
{
  "name": "Extra Spicy",
  "price_adjust": "0.50",
  "is_default": false,
  "sort_order": 0
}
```

**Response** `201 Created`:

```json
{
  "data": {
    "id": "20",
    "type": "menu-option-group-options",
    "attributes": {
      "name": "Extra Spicy",
      "price_adjust": "0.50",
      "is_default": false,
      "sort_order": 0
    }
  }
}
```

---

## Translation API

The full menu tree returns text in `source_locale` by default. All read and write endpoints respect the `Accept-Language` header to select the active locale.

### Localization via Accept-Language

Send `Accept-Language: {code}` (ISO 639-1) with any API request:

```
Accept-Language: vi
```

**Effect on reads** — `GET /api/v1/menus/{id}` returns translations for that locale, falling back to source text when a translation doesn't exist yet.

**Effect on writes** — `PUT /api/v1/menu-items/{id}` (and sections, groups, options) writes the provided field values as a translation for that locale:
- Header locale = `source_locale` → overwrites the source text (`is_initial: true`)
- Header locale ≠ `source_locale` → saves a translation (`is_initial: false`)
- No header → falls back to `source_locale`, same as writing to source

**Frontend integration** — set the header globally in your HTTP client when the user selects a language:

```js
axios.defaults.headers.common['Accept-Language'] = selectedLocale;
```

---

### GET /api/v1/menus/{id}

Without `Accept-Language` — returns `source_locale` text.
With `Accept-Language: en` — returns English translations, falling back to source text if none exist.

The response includes `locale` (active locale) and `locales` (all available locales for the selector):

```json
{
  "data": {
    "id": "1",
    "source_locale": "vi",
    "locale": "en",
    "locales": [
      { "code": "en", "name": "English", "is_source": false },
      { "code": "vi", "name": "Tiếng Việt", "is_source": true }
    ],
    "sections": [
      {
        "id": "3",
        "name": "Starters",
        "items": [...]
      }
    ]
  }
}
```

---

### GET /api/v1/menus/{id}/locales

Returns all locales that have translations for this menu. Always includes `source_locale` and the restaurant's `primary_language` even if no translations exist yet.

**Response** `200 OK`:

```json
{
  "data": [
    { "code": "en", "name": "English", "is_source": false },
    { "code": "ru", "name": "русский язык", "is_source": false },
    { "code": "vi", "name": "Tiếng Việt", "is_source": true }
  ],
  "meta": {
    "source_locale": "vi",
    "primary_language": "ru"
  }
}
```

`meta.primary_language` is the restaurant's default language — use it to set the initial `Accept-Language` value when the owner opens a menu.

---

### POST /api/v1/menus/{id}/translations/{locale}

Triggers an LLM translation job for the whole menu. Rate-limited: 1 job per menu+locale per hour.

```
POST /api/v1/menus/1/translations/en
```

**Response** `202 Accepted`:

```json
{ "message": "Translation queued." }
```

Returns `422` for invalid locale codes or if `locale === source_locale`.

---

### PUT /api/v1/menu-items/{id} (with Accept-Language)

Manually update a translation by sending the regular update request with an `Accept-Language` header. Only the fields you include are updated.

```
PUT /api/v1/menu-items/5
Accept-Language: en
Content-Type: application/json

{
  "name": "Beef Pho",
  "description": "Classic Vietnamese noodle soup with beef"
}
```

**Response** `200 OK` — updated item resource.

The same pattern applies to sections, option groups, and options:

| Entity | Endpoint |
|--------|----------|
| Section | `PUT /api/v1/menu-sections/{id}` |
| Item | `PUT /api/v1/menu-items/{id}` |
| Option Group | `PUT /api/v1/menu-option-groups/{id}` |
| Option | `PUT /api/v1/menu-option-group-options/{id}` |

---

## Code Style

```bash
vendor/bin/pint --dirty
```
