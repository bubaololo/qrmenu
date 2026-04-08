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

## Code Style

```bash
vendor/bin/pint --dirty
```
