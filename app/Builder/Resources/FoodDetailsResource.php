<?php

namespace App\Builder\Resources;

use App\Builder\Support\ItemPricing;
use App\CentralLogics\Helpers;
use App\Models\Item;
use Modules\Builder\ValueObjects\Storefront\FoodDetailDTO;

class FoodDetailsResource
{
    public static function fromOne(Item $item): array
    {
        $formatted = Helpers::product_data_formatting($item, false, true, app()->getLocale());
        $pricing   = ItemPricing::compute($item);

        $moduleType = $item->module?->module_type;

        $foodVariations = \is_array($formatted['food_variations'] ?? null)
            ? array_values($formatted['food_variations'])
            : [];

        $addOns = collect($formatted['add_ons'] ?? [])
            ->map(fn ($addon) => [
                'id'    => (int) ($addon['id'] ?? 0),
                'name'  => (string) ($addon['name'] ?? ''),
                'price' => (float) ($addon['price'] ?? 0),
            ])
            ->filter(fn (array $addon) => $addon['id'] > 0)
            ->values()
            ->all();

        $nutritionDetails = collect($formatted['nutritions_name'] ?? [])
            ->filter()
            ->implode(', ');
        $allergicIngredients = collect($formatted['allergies_name'] ?? [])
            ->filter()
            ->implode(', ');

        // Veg badge gating mirrors ItemCardResource: module supports veg_non_veg
        // (only food per config/module.php) AND the store has its veg toggle on
        // (vendor business settings) AND the item's own veg flag is 1.
        $vegRaw          = $item->getAttributes()['veg'] ?? null;
        $moduleAllowsVeg = (bool) config("module.{$moduleType}.veg_non_veg", false);
        $storeAllowsVeg  = (int) ($item->store?->veg ?? 0) === 1;
        $isVeg  = $moduleAllowsVeg && $storeAllowsVeg && $vegRaw !== null && (int) $vegRaw === 1;

        $images = collect($formatted['images_full_url'] ?? [])
            ->filter()
            ->values();
        if ($images->isEmpty() && !empty($formatted['image_full_url'])) {
            $images = collect([$formatted['image_full_url']]);
        }

        $data = [
            'id'           => (int) $item->id,
            'module_type'  => $moduleType,
            'store_id'     => (int) ($item->store_id ?? $item->store?->id ?? 0),
            'vendor_id'    => (int) ($item->store?->vendor_id ?? 0),
            'module_id'    => (int) ($item->module_id ?? $item->module?->id ?? 0),
            'zone_id'      => (int) ($item->store?->zone_id ?? 0),

            'name'         => (string) ($formatted['name'] ?? $item->name ?? ''),
            'image'        => $formatted['image_full_url'] ?? null,
            'images'       => $images->all(),
            'rating'          => round((float) ($item->getRawOriginal('avg_rating') ?? $item->avg_rating ?? 0), 1),
            'ratingCount'     => (int) ($item->getRawOriginal('rating_count') ?? $item->rating_count ?? 0),
            // 5-bucket counts — backs the rating-click reviews drawer
            // opened from the food modal.
            'ratingDistribution' => ItemDetailResource::ratingDistribution($item),
            'isVeg'        => $isVeg,
            'description'  => (string) ($formatted['description'] ?? ''),
            'nutritionDetails'    => $nutritionDetails ?: null,
            'allergicIngredients' => $allergicIngredients ?: null,

            // Pricing: send the raw base via `price` (the modal needs it to
            // add per-variation and per-addon extras at runtime), and the
            // canonical discount via `discount` + `discount_type`.
            //
            // `store_discount` is intentionally zeroed: ItemPricing already
            // picks the winner among flash/store/product, so the modal's
            // legacy "apply discount THEN apply store_discount" pass would
            // double-count when the store discount won (or stack store on
            // top of product when product won). Both behaviours produced
            // visibly wrong totals (e.g. card 1640, modal 1344.80 for 18%).
            'price'          => $pricing['oldPrice'],
            'oldPrice'       => $pricing['oldPrice'],
            'displayPrice'   => $pricing['price'],
            'discount'       => (float) $pricing['discountPercent'],
            'discount_type'  => 'percent',
            'discountSource' => $pricing['discountSource'],
            'store_discount' => 0.0,

            'maximum_cart_quantity'  => (int) ($item->maximum_cart_quantity ?? 0),
            'available_time_starts'  => $formatted['available_time_starts'] ?? $item->available_time_starts,
            'available_time_ends'    => $formatted['available_time_ends'] ?? $item->available_time_ends,
            'available_date_starts'  => $formatted['available_date_starts'] ?? null,
            'available_date_ends'    => $formatted['available_date_ends'] ?? null,

            'food_variations' => $foodVariations,
            'add_ons'         => $addOns,
        ];

        return FoodDetailDTO::fromArray($data)->toArray();
    }
}
