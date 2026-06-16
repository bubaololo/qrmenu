# Orders API

Order placement, tracking, kitchen statuses, bills, and SSE realtime stream.

- All admin endpoints live under `/api/v1/...` and use **session cookie auth (Sanctum)** — clients must obtain CSRF token via `GET /sanctum/csrf-cookie` first.
- Public endpoints use `/api/v1/public/...` with no auth, identified by `restaurant_uniqid` + `table_uniqid` and a `guest_token` HttpOnly cookie.
- Responses follow **JSON:API** (`{ data: { id, type, attributes, relationships } }`). Errors are standard Laravel `{ message, errors: { field: [...] } }`.

---

## Domain model

| Entity | Purpose |
| --- | --- |
| **Bill** | Aggregates orders for a single table session. One **open** bill per table at a time; closes when the table is settled. `total_amount` is recalculated on close from all `order_items`. |
| **Order** | Created from a single client cart submission. Belongs to a `Bill`. Statuses: `pending` → `in_progress` → `completed` (or `cancelled`). |
| **OrderItem** | A line in an order. Has its own kitchen workflow: `waiting` → `cooking` → `ready` → `served` (or `cancelled`). Stores a price snapshot (`unit_price` × `quantity`) — server-controlled, not from client. |

### Status transitions (Order)

```
pending ──┬─► in_progress ──► completed
          ├──────────────────► completed
          └──────────────────► cancelled
in_progress ─► cancelled
completed/cancelled  (terminal)
```

`completed` and `cancelled` are terminal — `PATCH` will return 422 when trying to reopen.

---

## Public endpoints

### `POST /api/v1/public/orders`

Place an order from the public Blade menu (or any external client).

**Auth**: none. Throttled to 30 requests / minute / IP.

**Request body:**

```json
{
  "restaurant_uniqid": "abc12345",
  "table_uniqid": "abc12345xyz123abc12345xy",
  "note": "Extra napkins, please",
  "items": [
    {
      "menu_item_id": 42,
      "quantity": 2,
      "variation_option_id": 7,
      "addon_ids": [11, 12],
      "note": "no salt"
    }
  ]
}
```

**Field rules:**

| Field | Required | Notes |
| --- | --- | --- |
| `restaurant_uniqid` | ✓ | 8–32 chars; resolved server-side. |
| `table_uniqid` | ✓ | Must belong to a zone of the same restaurant. |
| `items[].menu_item_id` | ✓ | Must belong to an active section of the restaurant's single menu, with both `is_visible=true` and `is_orderable=true` on the item. Hidden items (`is_visible=false`) and "out of stock" items (`is_orderable=false`) are rejected. |
| `items[].quantity` | ✓ | 1..99 |
| `items[].variation_option_id` | – | Chosen variation option id (`menu_variation_options.id`). Its price is **absolute** — it *replaces* the dish base price. |
| `items[].addon_ids` | – | Array of chosen atomic add-on ids (`menu_addons.id`). Each price is a **delta** added on top. |
| `items[].note`, top-level `note` | – | ≤255 / ≤500 chars. |
| `items[]` | ✓ | 1..100 entries. |

> **Cart prices are not trusted** — the server snapshots `(variation_option.price ?? menu_items.price_value) + Σaddon.price` into `order_items.unit_price`. Anything the client sends in price fields is ignored. The chosen `addon_ids` are stored verbatim in `order_items.selected_options` (a JSON array of add-on ids).

#### How `variation_option_id` and `addon_ids` differ

The menu models two distinct, separately-stored shapes:

| shape | what it represents | how to send | price semantics |
| --- | --- | --- | --- |
| **Variation** (`menu_variations` + `menu_variation_options`) | A pick-exactly-one axis (Size, Choice, Hot/Iced). Selects the "main" form of the dish. | A single `variation_option_id` per item. | **Absolute** — replaces the dish base price. |
| **Add-on** (`menu_addons`) | Independent atomic extras (toppings, "+ shot", "+ oat milk"). Pick any number, 0..N. | Each chosen id in `addon_ids[]`. | **Delta** — added on top. |

Worked example: an item with base 35 000, variation option `Lớn` (absolute 45 000) and two add-ons `+shot` (25 000) and `+oat milk` (5 000):

```json
{
  "menu_item_id": 1,
  "quantity": 2,
  "variation_option_id": 2,
  "addon_ids": [3, 4]
}
```

```
unit_price = 45 000 (variation, replaces base) + 25 000 + 5 000 = 75 000
line_total = 75 000 × 2                                          = 150 000
```

The server validates that `variation_option_id` exists in `menu_variation_options` and each `addon_ids[]` exists in `menu_addons` (it does not currently enforce that they're attached to the specific item).

**Response 201:**

```json
{
  "data": {
    "id": "5",
    "type": "orders",
    "attributes": {
      "bill_id": 3,
      "guest_token": "550e8400-e29b-41d4-a716-446655440000",
      "status": "pending",
      "placed_at": "2026-05-01T19:46:04+00:00",
      "...": "..."
    },
    "relationships": {
      "items":     { "data": [ /* OrderItem resources */ ] },
      "bill":      { "data": { "id": "3", "type": "bills" } },
      "diningTable": { "data": { "id": "7", "type": "dining_tables" } }
    }
  }
}
```

**Cookies:** sets `Set-Cookie: guest_token=<uuid>; HttpOnly; SameSite=Lax; Path=/; Max-Age=604800` (7 days). On subsequent requests the cookie is read; if present, the same `guest_token` is reused so a single guest's orders can be queried via `/public/orders/{guestToken}`.

**Errors:**

- `422` — validation: unknown restaurant/table/menu, item from another menu, missing required field.
- `429` — throttled.

---

### `GET /api/v1/public/orders/{guestToken}`

List a guest's recent orders (max 20, newest first). Used by the public menu's "your order" status screen.

**Auth**: none. `guestToken` is the UUID from the `guest_token` cookie.

**Response 200:** `{ data: ApiOrder[] }` (collection envelope).

---

## Authenticated endpoints

All endpoints below require **session cookie (Sanctum)** + the user must be in `restaurant_users` for the target restaurant (role `owner` or `waiter` — both are accepted by `OrderPolicy`/`BillPolicy`).

### Orders

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/api/v1/restaurants/{restaurant}/orders` | List orders. Filters: `?status=pending&dining_table_id=7&bill_id=3&per_page=50`. Paginated. |
| `GET` | `/api/v1/orders/{order}` | Order detail (with items, bill, diningTable, menu). |
| `PATCH` | `/api/v1/orders/{order}` | Body: `{ "status": "in_progress" \| "completed" \| "cancelled", "cancelled_reason"?: "…" }`. Enforces the allowed transitions above. |
| `DELETE` | `/api/v1/orders/{order}` | Soft-cancels (sets `status=cancelled`, fills `cancelled_at`). Returns 204. |

### Order items (kitchen)

| Method | Path | Purpose |
| --- | --- | --- |
| `PATCH` | `/api/v1/order-items/{orderItem}` | Body: `{ "kitchen_status": "waiting" \| "cooking" \| "ready" \| "served" \| "cancelled" }`. Auto-fills `started_cooking_at` / `ready_at` / `served_at`. |

### Bills

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/api/v1/restaurants/{restaurant}/bills` | List bills. Filters: `?status=open&dining_table_id=7`. |
| `GET` | `/api/v1/bills/{bill}` | Bill with all orders + items. |
| `POST` | `/api/v1/bills/{bill}/close` | Recalculates `total_amount` from items, sets `status=closed`, `closed_at=now`, `closed_by_user_id`. Errors 422 if already closed. |
| `POST` | `/api/v1/bills/{bill}/split` | Body: `{ "splits": [ { "order_item_ids": [1, 2] }, { "order_item_ids": [3] } ] }`. Creates one new closed bill per split (with the corresponding line items rebound). The original bill closes with whatever wasn't split out. Returns the new bills as a collection. |

**Split rules:**
- Every `order_item_id` must belong to the bill.
- An item may appear in **only one** split.
- Items not assigned to any split stay on the original bill (which is also closed).
- If an order has items in different splits, the order is cloned for each subsequent split (each clone keeps the original `placed_at` etc.).

---

## SSE: realtime stream

### `GET /api/v1/restaurants/{restaurant}/orders/events`

**Auth**: session cookie. Long-lived stream — never terminates server-side; closes on client disconnect or after `300s` idle.

**Headers** (response): `Content-Type: text/event-stream`, `X-Accel-Buffering: no`, heartbeat comments every ~25s.

**Last-Event-ID** is supported for resume: `Last-Event-ID: 42` returns events from index 42 onward.

**Event payload format:**

```
id: 17
data: { "event": "order.placed", "data": { "order_id": 5, "bill_id": 3, "dining_table_id": 7, "guest_token": "..." }, "ts": 1714592764.123 }
```

**Event types:**

| Event | When | Data |
| --- | --- | --- |
| `order.placed` | New order created via public POST. | `{ order_id, bill_id, dining_table_id, guest_token }` |
| `order.status-changed` | `PATCH /orders/{id}` or `DELETE`. | `{ order_id, status, previous? }` |
| `order-item.kitchen-status-changed` | `PATCH /order-items/{id}`. | `{ order_item_id, order_id, kitchen_status }` |
| `bill.closed` | `POST /bills/{id}/close`. | `{ bill_id, dining_table_id, total_amount }` |
| `bill.split` | `POST /bills/{id}/split`. | `{ original_bill_id, new_bill_ids: [...] }` |

> SSE topics use Redis lists keyed `events:restaurant-orders.{id}` (TTL 1h, max 500 events). Client should use these for invalidation hints — fetch the canonical state via the REST endpoints (don't rely on SSE payload alone for displayable data).

**JS example (admin frontend):**

```js
const es = new EventSource(`/api/v1/restaurants/${id}/orders/events`, { withCredentials: true })
es.onmessage = (e) => {
  const { event, data } = JSON.parse(e.data)
  if (event === "order.placed") queryClient.invalidateQueries(["orders"])
  // …
}
```

---

## Authentication flow (summary)

For an SPA driving these endpoints from a different origin, do this once per browser session:

1. `GET /sanctum/csrf-cookie` — sets `XSRF-TOKEN` cookie.
2. `POST /api/v1/auth/login` (Fortify) — establishes session cookie.
3. Subsequent requests must send `X-XSRF-TOKEN` header + `credentials: include`.

The public order endpoint (`/api/v1/public/orders`) is throttled but does **not** require CSRF for the initial POST when used from same-origin Blade — the existing CSRF middleware excludes it via the public route group's lack of session middleware. From a different origin, hitting it requires the standard CSRF setup above.

---

## Manual test recipe

```bash
# 0. Make sure migrations are applied
docker compose exec app php artisan migrate --no-interaction

# 1. Create test data via tinker (one-off)
docker compose exec app php artisan tinker --execute '
$r = App\Models\Restaurant::factory()->create(["currency" => "USD"]);
$z = App\Models\Zone::factory()->create(["restaurant_id" => $r->id]);
$t = App\Models\DiningTable::factory()->create(["zone_id" => $z->id]);
$m = App\Models\Menu::factory()->create(["restaurant_id" => $r->id]);
$s = App\Models\MenuSection::factory()->create(["menu_id" => $m->id, "is_active" => true]);
$i = App\Models\MenuItem::factory()->create(["section_id" => $s->id, "price_value" => 12.50, "is_visible" => true, "is_orderable" => true]);
echo "uniqid: {$r->uniqid} / {$t->uniqid} / item_id: {$i->id}\n";
'

# 2. Place an order (public)
curl -i -X POST http://menu.test/api/v1/public/orders \
  -H "Content-Type: application/json" \
  -d '{
    "restaurant_uniqid": "<from step 1>",
    "table_uniqid": "<from step 1>",
    "items": [{ "menu_item_id": <from step 1>, "quantity": 2 }]
  }'
# Expect: 201, body with the order, Set-Cookie: guest_token=...

# 3. Subscribe to SSE in another terminal (auth cookie required)
#    Use the browser's DevTools or curl with cookies after login.
curl -N --cookie cookies.txt http://menu.test/api/v1/restaurants/1/orders/events

# 4. Run order tests
docker compose exec app php artisan test --compact tests/Feature/Orders/
```

---

## See also

- `app/Services/Orders/OrderCreationService.php` — the single entry point for order creation (price snapshot logic).
- `app/Http/Controllers/SseEventsController.php` — SSE stream implementation.
- `app/Enums/OrderStatus.php` — transition rules.
- `tests/Feature/Orders/` — feature tests covering all endpoints.
