<?php

namespace App\Builder\Resources;

use App\Builder\Support\ItemPricing;
use App\Models\Item;
use Modules\Builder\ValueObjects\Storefront\ItemCardDTO;

/**
 * Canonical transformer that maps an App\Models\Item into the exact shape
 * Modules/Builder/resources/js/Components/shared/ItemCard.jsx consumes.
 *
 * Covers the union of all 8 CARD_TYPE variants:
 *   id, name, slug, image, price (final), oldPrice (original), discountPercent,
 *   rating, rating_count, currency, isVeg, isNonVeg, inCart, cartQty, isWishlist.
 *
 * Accepts a $context array so callers can inject:
 *   - 'currency'         => string symbol (default '$')
 *   - 'cart_lookup'      => callable(int $itemId): ?array  // e.g. fn($id) => $cart[$id] ?? null
 *   - 'wishlist_lookup'  => callable(int $itemId): bool
 */
class ItemCardResource
{
    public static function fromCollection(iterable $items, array $context = []): array
    {
        $result = [];
        foreach ($items as $item) {
            $result[] = self::fromOne($item, $context);
        }
        return $result;
    }

    public static function fromOne(Item $item, array $context = []): array
    {
        $pricing = ItemPricing::compute($item);

        // The card needs one boolean to route Add-to-Cart: does this item
        // require user choices before it can land in the cart?
        //   - food: only when at least one food_variation has required='on'.
        //     Optional add-ons / optional variations don't force the modal.
        //   - non-food: when the item has any catalog variations rows.
        $moduleType = $item->module?->module_type;
        $needsConfig = $moduleType === 'food'
            ? self::hasRequiredFoodVariation($item)
            : self::hasNonFoodVariations($item);

        // Veg/non-veg badge visibility is gated by THREE things, not just the
        // item's own flag:
        //   1. The module supports veg_non_veg (only `food` in config/module.php).
        //   2. The store toggles `veg` / `non_veg` ON in vendor business settings.
        //      Vendors who flip "veg" off should not see veg badges on their items.
        //   3. The item itself has the veg flag set (1 = veg, 0 = non-veg).
        // Raw attribute read (not cast) so a null `veg` doesn't coerce to 0 and
        // falsely flag every grocery/pharmacy item as non-veg.
        $vegRaw            = $item->getAttributes()['veg'] ?? null;
        $moduleAllowsVeg   = (bool) config("module.{$moduleType}.veg_non_veg", false);
        $storeAllowsVeg    = (int) ($item->store?->veg ?? 0) === 1;
        $storeAllowsNonVeg = (int) ($item->store?->non_veg ?? 0) === 1;
        $isVeg    = $moduleAllowsVeg && $storeAllowsVeg    && $vegRaw !== null && (int) $vegRaw === 1;
        $isNonVeg = $moduleAllowsVeg && $storeAllowsNonVeg && $vegRaw !== null && (int) $vegRaw === 0;

        $currency       = $context['currency']        ?? '$';
        $cartLookup     = $context['cart_lookup']     ?? null;
        $wishlistLookup = $context['wishlist_lookup'] ?? null;

        $cartEntry = is_callable($cartLookup) ? $cartLookup($item->id) : null;


        $data = [
            'id'              => $item->id,
            'name'            => $item->name,
            'slug'            => $item->slug ?? null,
            'image'           => $item->image_full_url ?? null,
            'price'           => $pricing['price'],
            'oldPrice'        => $pricing['oldPrice'],
            'discountPercent' => $pricing['discountPercent'],
            'discountSource'  => $pricing['discountSource'],
            'rating'          => round((float) ($item->avg_rating ?? 0), 1),
            'ratingCount'     => (int) ($item->rating_count ?? 0),
            'currency'        => $currency,
            'isVeg'           => $isVeg,
            'isNonVeg'        => $isNonVeg,
            'inCart'          => $cartEntry !== null,
            'cartQty'         => (int) ($cartEntry['qty'] ?? 0),
            'isWishlist'      => is_callable($wishlistLookup) ? (bool) $wishlistLookup($item->id) : false,
            'moduleType'      => $moduleType,
            'needsConfig'     => $needsConfig,
        ];

        return ItemCardDTO::fromArray($data)->toArray();
    }

    private static function hasRequiredFoodVariation(Item $item): bool
    {
        $foodVariations = self::decodeJsonField($item->getAttributes()['food_variations'] ?? null);
        foreach ($foodVariations as $variation) {
            $required = $variation['required'] ?? 'off';
            if ($required === 'on' || $required === '1' || $required === 1 || $required === true) {
                return true;
            }
        }
        return false;
    }

    private static function hasNonFoodVariations(Item $item): bool
    {
        $variations = self::decodeJsonField($item->getAttributes()['variations'] ?? null);
        return is_array($variations) && count($variations) > 0;
    }

    private static function decodeJsonField($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}
