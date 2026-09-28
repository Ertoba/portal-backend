# Last Orders (Store-wise)

API for the "Your Last Orders" rail. Returns a customer's most recent delivered orders, scoped to the current module, with each order carrying its store and a short item preview so a reorder card can render without a second round-trip.

## Endpoint

| Method | Path | Controller |
| --- | --- | --- |
| GET | `/api/v1/customer/order/last` | `OrderController@get_last_orders` |

Registered under the `module-check` middleware group in `routes/api/v1/api.php`, **outside** any auth group. The handler reads `auth('api')->user()` itself: if no bearer token is supplied (or it can't be resolved to a user) the response is `[]` with HTTP 200 — never a 401.

### Request

Headers:

- `moduleId` — numeric module id. Enforced by the surrounding `module-check` middleware.
- `zoneId` — JSON array (set by the global flow; not consumed by this endpoint).
- `Authorization: Bearer <token>` — optional. Without it (or with `Bearer null`) the response is an empty array.

Query params: none.

### Response

Top level is a JSON array (not wrapped). Each entry represents one delivered order, ordered most-recent first, capped at 10.

```json
[
  {
    "order_id": 1234,
    "order_amount": 20.32,
    "created_at": "2026-03-20T08:14:11.000000Z",
    "store": {
      "id": 12,
      "name": "Hungry Puppets",
      "slug": "hungry-puppets12",
      "logo_full_url": "https://.../store/2026-...webp"
    },
    "items_preview": [
      { "id": 88, "name": "Cheeseburger", "image_full_url": "...", "quantity": 1 },
      { "id": 91, "name": "Fries",         "image_full_url": "...", "quantity": 2 },
      { "id": 102,"name": "Coke",          "image_full_url": "...", "quantity": 1 }
    ],
    "extra_items_count": 1,
    "item_count": 4
  }
]
```

The card in the screenshot uses `store.logo_full_url`, `store.name`, `created_at` (formatted client-side), the first three `items_preview[*].image_full_url` plus `+extra_items_count`, and `order_amount`. The reorder button reuses `order_id` against the existing `POST /api/v1/customer/order/place`-style flow (or `OrderController@order_again`).

## Query shape

Single Eloquent query against `Order`, no logic class:

- `user_id = auth user`
- `is_guest = 0` — registered customer only (mirrors `order_again`)
- `module_id = current_module_data.id` when present, otherwise unfiltered
- `order_status = 'delivered'`
- `latest()` + `take(10)`
- Eager loads `store:id,name,logo,module_id,zone_id,slug`, `details:id,order_id,item_id,quantity`, `details.item:id,name,image,store_id`

The result is mapped via Collection `map()` (no API resource). For each order the details are projected into `items_preview` (up to 3) plus `extra_items_count = max(0, total − 3)` so the rail can render a stable "+N" badge without inspecting the full item list.

`logo_full_url` and `image_full_url` come from the existing model accessors (`Store::$appends`, `Item::$appends`) — no manual URL building.

## Not in scope (intentional)

- **Guest orders.** Guests have no stable identity across module/store contexts on this surface, and the screenshot shows logged-in user data. The endpoint returns `[]` for guests.
- **Pagination.** Fixed at 10. Add `limit` / `offset` only if a "see all" view appears.
- **Cross-module aggregation.** `module_id` is enforced when `module.current_module_data` is set. If the header is missing the surrounding middleware would have already rejected the request; the `when()` guard is defensive.
- **Helper formatting.** Deliberately bypasses `Helpers::store_data_formatting` / `product_data_formatting` because the card only needs ~6 fields and pulling those formatters in would re-introduce dozens of computed properties this view doesn't render.

## Regeneration prompt

```
Work on: New Feature-> Last ordered items list store-wise
Store wise users last orders(Module wise/Store wise + Latest 10 + Status delivered). Controller function get_last_orders. Here is the attatchment [Image #3]. User should be authenticate otherwise empty array
```
