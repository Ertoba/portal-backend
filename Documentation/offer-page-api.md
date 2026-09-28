# Offer Page API

API for the storefront offer page. Lists items that carry any kind of active discount (item discount, store discount, flash sale, store coupon), grouped by item and/or store, scoped to a module and zone, sorted by effective discount, searchable.

## Endpoint (single)

There is **one** endpoint. The `type` query param selects whether the response contains the items section, the stores section, or both. When `type=all`, both sections are populated and both run through their respective resources.

| Method | Path | Controller |
| --- | --- | --- |
| GET | `/api/v1/offer` | `ItemController@getOfferItems` |

The route is registered **outside** the `module-check` middleware group in `routes/api/v1/api.php`. That is intentional: the spec says "If no moduleId then all", and `module-check` 403s any request without a `moduleId` header. The controller reads the header itself and falls back to "all modules" when it's absent.

### Request

Headers (resolved by `Helpers::setZoneIds`):

- `zoneId` — JSON array, e.g. `[1]`. Falls back to default zone.
- `moduleId` — numeric module id. Optional. If absent, all modules in the active zones are considered.
- `latitude`, `longitude` — optional. When both are present, the store list adds a real `distance` column (metres, via `ST_Distance_Sphere`) using the same `Store::WithOpenWithDeliveryTime` scope that powers `stores/discounted` and friends. When missing, the scope is still applied with `0,0`; `distance` is then just the great-circle distance to `(0, 0)` and not meaningful — frontends should treat it as absent in that case.
- `Authorization: Bearer <token>` — optional. Only used to populate the `wishlist` flag.

Query params:

| Param | Default | Notes |
| --- | --- | --- |
| `type` | `all` | One of `all`, `item`, `store`. `all` → both sections. `item` → items only. `store` → stores only. |
| `limit` | `25` | Page size. For `type=all`, also caps each section preview (max 10). |
| `offset` | `1` | Page number (Laravel paginator). |
| `search` | _none_ | Whitespace-tokenised. Tokens must match item name and/or store name (the store section also searches via the store's items' names). |

### Response shape

The top-level keys present depend on `type`:

- `type=all` → both `items` and `stores` keys are present.
- `type=item` → only `items` is present.
- `type=store` → only `stores` is present.

```jsonc
// type=all
{
  "type": "all",
  "items": {
    "total_size": 120,
    "limit": 10,
    "offset": 1,
    "items": [ /* OfferItemResource[] */ ]
  },
  "stores": {
    "total_size": 18,
    "limit": 10,
    "offset": 1,
    "stores": [ /* OfferStoreResource[] */ ]
  }
}
```

```jsonc
// type=item
{
  "type": "item",
  "items": {
    "total_size": 120,
    "limit": 25,
    "offset": 1,
    "items": [ /* OfferItemResource[] */ ]
  }
}
```

```jsonc
// type=store
{
  "type": "store",
  "stores": {
    "total_size": 18,
    "limit": 25,
    "offset": 1,
    "stores": [ /* OfferStoreResource[] */ ]
  }
}
```

#### Item shape

Items are formatted by the existing `Helpers::productListDataFormatting` (same helper used by `items/popular`, `items/discounted`, etc., so item-card components are reusable without remapping). The offer endpoint then decorates each row with three offer-specific fields: `discounted_price`, `store_image`, `wishlist`.

```jsonc
{
  // from Helpers::productListDataFormatting
  "id": 42,
  "name": "Double Layer Beef Burger",
  "slug": "double-layer-beef-burger",
  "image_full_url": "https://.../items/burger.jpg",
  "price": 25.00,
  "veg": 1,
  "unit_type": "kg",
  "recommended": 0,
  "organic": 0,
  "is_halal": 0,
  "stock": 100,
  "maximum_cart_quantity": 0,
  "discount": 60,
  "discount_type": "percent",
  "rating_count": 120,
  "avg_rating": 4.5,
  "has_variant": 0,
  "available_time_starts": "00:00",
  "available_time_ends": "23:59",
  "halal_tag_status": 0,
  "store_name": "Pizza Hut",
  "store_id": 7,
  "store_category_id": 0,
  "store_category_name": null,
  "module_type": "food",
  "free_delivery": 0,
  "verified_seller": 0,
  // decorated by the offer controller
  "discounted_price": 10.00,
  "store_image": "https://.../stores/logo.jpg",
  "wishlist": 1
}
```

#### `OfferStoreResource`

`item_list` is gated by a fluent setter — controller calls `(new OfferStoreResource($store))->withItems()->toArray($request)`. Without `withItems()`, `item_list` is `[]`, useful for surfaces that just want the store header.

The embedded items are **capped at 5 per store** and, when `search` is present on the parent request, are filtered by the same search tokens (so a search for "burger" returns each store with only its burger items, not all of its discounted items).

```jsonc
{
  "store_id": 7,
  "store_name": "Burger King",
  "store_image": "https://.../stores/logo.jpg",
  "cover_image": "https://.../stores/cover.jpg",
  "delivery_time": "20-30 min",
  "distance": 3.2,
  "free_delivery": 1,
  "avg_item_discount_percentage": 18.4,
  "module_id": 2,
  "module_type": "food",
  "item_list": [
    {
      "id": 91,
      "name": "Double Layer Special Beef Burger",
      "image_full_url": "https://.../items/burger.jpg",
      "price": 25.00,
      "discounted_price": 10.00,
      "discount": 60
    }
  ]
}
```

`distance` is metres (raw `ST_Distance_Sphere`, rounded to 2dp). It is computed from the `latitude` / `longitude` request headers via `Store::WithOpenWithDeliveryTime($longitude, $latitude)` — same scope used by `stores/discounted`, `stores/popular`, etc. Storefront callers wanting km should divide by 1000.

`OfferStoreResource::withItems()` is a fluent opt-in for the embedded `item_list`. Call it before `toArray($request)` to populate the list (the controller does this for both `type=all` and `type=store`). Skip the call to get just the store header — cheap reuse for other surfaces that don't need embedded items. The setter approach keeps `toArray($request)` signature-compatible with the parent `JsonResource`.

## What counts as "an offer"?

An item qualifies if any of these is true (mirrors `Item::scopeDiscounted` plus a coupon hop):

1. `items.discount > 0` (item-level discount).
2. The store has an active `discounts` row — today is within `start_date`/`end_date` and the current time is within `start_time`/`end_time`.
3. The item belongs to a published flash sale (`flash_sales.is_publish = 1`) whose `start_date`/`end_date` window includes today.
4. The store has at least one active coupon (`Store::activeCoupons`).

A store qualifies for the store list if it has at least one item that satisfies any of (1)-(4).

## Sorting

Sort is "by effective discount", descending. The query computes `effective_discount = GREATEST(...)` of:

- `items.discount` (only when `discount_type = 'percent'`).
- The percentage from the active store `discounts` row.
- The percentage from the running flash sale row (`flash_sale_items.discount`, only when its `discount_type = 'percent'`).

Coupons contribute to qualification but not to the sort number (coupon value is cart-dependent, not a fixed per-item percent).

Stores are sorted by `avg_offer_discount` — the average `effective_discount` across that store's discounted items (excluding zero rows).

## Wishlist (relation-based)

`wishlist` on `OfferItemResource` is computed via Eloquent relations, not via a pre-collected id array:

1. Controller resolves the current `auth('api')` user (returns `null` for guests).
2. Controller eager-loads `Item::whislists` constrained to that user id (`->loadMissing(['whislists' => fn($q) => $q->where('user_id', $userId)])`). For guests, the closure forces `0 = 1` so the relation loads empty.
3. `OfferItemResource` reads `$item->relationLoaded('whislists') && count($item->getRelation('whislists')) > 0`.

The same eager-load also runs over the items embedded inside each `OfferStoreResource` (the `offerItems` relation), so the items inside the store-section share the same authoritative shape — except `item_list` deliberately stays minimal (id / name / image / price / discounted price / discount percentage) per spec.

This is one SQL hop per request, regardless of how many items are on the page, and zero rows hit the DB for unauthenticated callers. The relation name `whislists` matches the existing (misspelled) method on `Item` — don't "fix" it.

## Implementation Map

| Concern | File | Notes |
| --- | --- | --- |
| Query logic | `app/CentralLogics/ProductLogic.php` — public: `offer_items_page_content`, `get_offer_items`, `get_offer_item_stores`. Private helpers: `fetchStoreOfferItems`, `applyOfferEligibility`, `applyItemOfferEligibility`, `applyItemSearch`, `applyStoreSearch`, `decodeZones`, `searchTokens`, `offerEffectiveDiscountSql`. | Three public methods are non-static (signatures preserved from the original stubs). Only `offer_items_page_content` is called by the controller; the other two are dispatched into based on `type`. The helper extraction means offer-eligibility is defined once, not three times. |
| Item shape | `app/CentralLogics/Helpers.php` — `productListDataFormatting` (shared with `items/popular` / `items/discounted` etc.) + `ItemController::decorateOfferItems` for the three offer-specific fields. | No bespoke resource class — the controller calls the helper then merges `discounted_price` (via `Helpers::product_discount_calculate`), `store_image` (`store->logo_full_url`), and `wishlist` (eager-loaded `whislists` relation). |
| Store shape | `app/Http/Resources/RestAPI/OfferStoreResource.php` | `JsonResource`. Reads the eager-attached `offerItems` relation set in `get_offer_item_stores`. |
| Controller | `app/Http/Controllers/Api/V1/ItemController.php` — `getOfferItems`, `currentApiUserId` | Single public method handles all three `type` modes. |
| Route | `routes/api/v1/api.php` — registered outside the `module-check` group so `moduleId` stays optional | `GET /api/v1/offer`. |

### Notes on the implementation

- All three offer-eligibility blocks (items query, store→whereHas items, per-store offerItems fetch) share `applyOfferEligibility($q)` / `applyItemOfferEligibility($q, $moduleId)` private helpers, so the rule lives in exactly one place. Search tokenisation lives in `searchTokens()`, zone JSON-decoding in `decodeZones()`.
- `Item::active($zones)` does the heavy lifting for the items query (status, store status, store zone, module status, category status). The store query mirrors `StoreLogic::get_discounted_stores`.
- Search is whitespace-split (empty tokens dropped). For the items list each token must match the item name or the store name. For the store list each token must match the store name or one of its items' names. The `item_list` embedded inside each `OfferStoreResource` is **further filtered by the same tokens** — searched stores only carry searched items.
- The store list uses `selectRaw` to compute `avg_offer_discount` via a correlated subquery against `items` (uses the same `effective_discount` SQL). Per-store offer items are then fetched in `fetchStoreOfferItems()` — capped at 5, ordered by effective discount, filtered by search tokens.
- For `type=all`, each section preview is capped at `min(limit, 10)` and always uses offset 1. To paginate either section in full, call the same endpoint with `type=item` (or `type=store`) and the desired `limit`/`offset`.
- The `type=all` path runs both sections through their resources (and the wishlist eager-load reaches both), so the shapes match the single-section responses exactly.

## Calling it

```http
GET /api/v1/offer?type=all&limit=25&offset=1&search=burger
Headers:
  zoneId: [1]
  moduleId: 2
  Authorization: Bearer <token>   # optional; controls `wishlist` flag only
```

```http
GET /api/v1/offer?type=item&limit=25&offset=2&search=burger
```

```http
GET /api/v1/offer?type=store&limit=25&offset=1
```

## Regeneration prompt

```text
Work on: Offer page. Task: Analyze ProductLogic. Logic: Main Logic: Show items if that item has any kind of discount on that item. It can be coupon, item discount, flashSale etc. Flow: 1. Select module -> 2. If no moduleId then all. 3. For $type all -> 2 Section ('Item','Store') -> Show Item list with these data['item_id','product image','store image','veg/no-veg','ishalal','wishlist status','Store name','item Rating','Product name','Price', 'Discounted price','discount percentage','is free delivery']. -> 4. Store List with these data ['store_id','store name','store image','delivery_time','distance','is free delivery','avg item discount percentage',['item list'] => ['item name','item price', 'item discounted price']] -> 5. If $type item then show Item list -> If $type store then store list. These will be based on sort by discount. I need Api. No design. I have these 3 function decleared in @app/CentralLogics/ProductLogic.php public function offer_items_page_content($moduleId, $zoneId, $limit = 25, $offset = 1, $type = 'all', $search = null)
    {
        
    }

    public function get_offer_items($moduleId, $zoneId, $limit = 25, $offset = 1, $type = 'all', $search = null)
    {
        
    }

    public function get_offer_item_stores($moduleId, $zoneId, $limit = 25, $offset = 1, $type = 'all', $search = null)
    {
        
    }. And for data structure use resource and implement search on overall item queries. Here is the figma attatchment-> [Image #2] [Image #3] [Image #4]. Ignore Exclusive deals section.

Follow-up 1: Do not use OfferController. Use @app/Http/Controllers/Api/V1/ItemController.php and why used wishlist item ids. use relation. And where is the resource if the type is all?

Follow-up 2: Logic is not on point. When the type is all both resource data need to show. the api will be one endpoint.

Follow-up 3: Route was inside module-check, which 403s without a moduleId header (clashes with "if no moduleId then all"). Moved the route outside that group; controller still reads the header directly.

Follow-up 4: `distance` was just passing through whatever was on the model; nothing computed it. Threaded `latitude`/`longitude` headers (matching the StoreController pattern) through `offer_items_page_content` → `get_offer_item_stores`, and swapped `Store::query()` for `Store::WithOpenWithDeliveryTime($lng, $lat)` so `distance` and `min_delivery_time` are real columns. `OfferStoreResource::toArray` also picked up a `$withItem` opt-in toggle for the embedded item list.

Follow-up 5: (1) Simplified — three duplicated offer-eligibility blocks collapsed into `applyOfferEligibility` / `applyItemOfferEligibility`; added `searchTokens` / `decodeZones` helpers; dropped dead `withCount(offer_items_count)`. (2) `OfferItemResource` keys realigned with the canonical `Helpers::productListDataFormatting` shape (`id`/`name`/`image_full_url`/`price`/`discount`/`avg_rating`/`free_delivery` etc.) so item-card components are reusable. (3) Per-store `item_list` capped at 5 (`$itemsPerStore` constant in `get_offer_item_stores`). (4) `fetchStoreOfferItems` now also receives the search tokens, so under search each store only carries the matching items. `OfferStoreResource` switched from `toArray($req, $withItem)` to fluent `withItems()` to match parent signature.

Follow-up 6: Use existing item list formatting helper instead of OfferItemResource. → Deleted `OfferItemResource`. Controller now calls `Helpers::productListDataFormatting($items)` (same helper feeding `items/popular`, `items/discounted`, etc.) and decorates each row with the three offer-specific fields (`discounted_price`, `store_image`, `wishlist`) via `ItemController::decorateOfferItems`.
```
