<?php

namespace App\Builder\Resources;

use App\Builder\Support\CardContext;
use App\Builder\Support\ItemPricing;
use App\CentralLogics\Helpers;
use App\Models\Item;
use App\Models\Review;
use Illuminate\Support\Str;
use Modules\Builder\Contracts\WishlistProvider;
use Modules\Builder\ValueObjects\Storefront\ItemDetailDTO;

class ItemDetailResource
{
    public static function fromOne(Item $item): array
    {
        $formatted = Helpers::product_data_formatting($item, false, true, app()->getLocale());
        $images = collect($formatted['images_full_url'] ?? [])
            ->filter()
            ->values();

        if ($images->isEmpty() && !empty($formatted['image_full_url'])) {
            $images = collect([$formatted['image_full_url']]);
        }

        if ($images->isEmpty()) {
            $images = collect([asset('public/assets/admin/img/100x100/2.jpg')]);
        }

        $variationCombinations = self::detailVariationCombinations($formatted, $item);
        $pricing = ItemPricing::compute($item);

        $data = [
            'id' => $item->id,
            'name' => $formatted['name'] ?? $item->name,
            'unit' => $item->unit_type,
            'currency' => CardContext::default()['currency'] ?? '$',
            'price' => $pricing['price'],
            'oldPrice' => $pricing['oldPrice'],
            'discountPercent' => $pricing['discountPercent'],
            'discountType' => $pricing['discountType'],
            'discountSource' => $pricing['discountSource'],
            'rating' => round((float) ($formatted['avg_rating'] ?? $item->avg_rating ?? 0), 1),
            'ratingCount' => (int) ($formatted['rating_count'] ?? $item->rating_count ?? 0),
            'reviewCount' => (int) ($formatted['review_count'] ?? 0),
            // 5-bucket distribution for the "View All" reviews drawer's
            // summary bars. One GROUP-BY query, status-approved only.
            'ratingDistribution' => self::ratingDistribution($item),
            'inStock' => ((int) ($formatted['stock'] ?? $item->stock ?? 0)) > 0,
            'stock' => (int) ($formatted['stock'] ?? $item->stock ?? 0),
            'lowStockThreshold' => 10,
            'maxCartQuantity' => (int) ($item->maximum_cart_quantity ?? 0),
            'moduleType' => $formatted['module_type'] ?? $item->module?->module_type,
            'genericName' => collect($formatted['generic_name'] ?? [])->filter()->implode(', ') ?: null,
            'images' => $images->all(),
            'variations' => self::detailVariations($formatted),
            'variationCombinations' => $variationCombinations,
            'tags' => $item->tags->pluck('tag')->filter()->values()->all(),
            'description' => (string) ($formatted['description'] ?? ''),
            'reviews' => self::detailReviews($item),
            'isWishlist' => app(WishlistProvider::class)->has((int) $item->id),
            'nutritionsName' => collect($formatted['nutritions_name'] ?? [])->filter()->values()->all(),
            'allergiesName' => collect($formatted['allergies_name'] ?? [])->filter()->values()->all(),
            'seoTitle' => $formatted['meta_title'] ?? null,
            'seoDescription' => $formatted['meta_description'] ?? null,
            'seoImage' => $formatted['meta_image'] ?? ($images->first() ?: null),
        ];

        return ItemDetailDTO::fromArray($data)->toArray();
    }

    private static function detailVariations($formatted): array
    {
        return collect($formatted['choice_options'] ?? [])
            ->map(function ($choice) {
                $title = (string) ($choice['title'] ?? $choice['name'] ?? 'Option');
                $isColor = \str_contains(\strtolower($title), 'color');

                return [
                    'title' => $title,
                    'type' => $isColor ? 'color' : 'text',
                    'required' => true,
                    'options' => collect($choice['options'] ?? [])
                        ->map(function ($option) use ($title, $isColor) {
                            $label = (string) $option;

                            return [
                                'id' => Str::slug($title . '-' . $label),
                                'label' => $label,
                                'value' => $isColor ? $label : null,
                                'variantKey' => self::normalizeVariationValue($label),
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn (array $choice) => $choice['options'] !== [])
            ->values()
            ->all();
    }

    private static function detailVariationCombinations($formatted, Item $item): array
    {
        return collect($formatted['variations'] ?? [])
            ->filter(fn ($variation) => \filled($variation['type'] ?? null))
            ->mapWithKeys(function ($variation) use ($item) {
                $originalPrice = (float) ($variation['price'] ?? 0);
                // Apply the same flash/store/product discount to each
                // combination's base price so the modal's per-combo display
                // matches the page header (and the cart line).
                $pricing = ItemPricing::compute($item, $originalPrice);
                $stock = (int) ($variation['stock'] ?? 0);

                return [
                    (string) $variation['type'] => [
                        'price' => $pricing['price'],
                        'oldPrice' => $pricing['oldPrice'],
                        'discountPercent' => $pricing['discountPercent'],
                        'stock' => $stock,
                        'inStock' => $stock > 0,
                    ],
                ];
            })
            ->all();
    }

    /**
     * Map a single Review row to the shape the frontend expects.
     * Shared between `detailReviews()` (first-page bootstrap) and
     * `ItemProvider::listReviews()` (paginated drawer fetch) so both
     * surfaces render identical cards.
     */
    public static function reviewRow(Review $review): array
    {
        $images = collect(Helpers::decodeJsonToArray($review->attachment ?? []))
            ->map(function ($image) {
                if (!\is_string($image) || $image === '') {
                    return null;
                }
                return Helpers::get_full_url('review', $image, 'public');
            })
            ->filter()
            ->values()
            ->all();

        return [
            'id' => $review->id,
            'authorName' => $review->customer?->f_name
                ? \trim(($review->customer?->f_name ?? '') . ' ' . ($review->customer?->l_name ?? ''))
                : ($review->customer?->name ?? 'Customer'),
            'avatar' => $review->customer?->image_full_url ?? null,
            'rating' => round((float) ($review->rating ?? 0), 1),
            'date' => optional($review->created_at)->format('F j, Y'),
            'text' => (string) ($review->comment ?? ''),
            'images' => $images,
            'hasReply' => \filled($review->reply),
            'reply' => (string) ($review->reply ?? ''),
            'replyAuthor' => $review->store?->name ?? null,
            'replyDate' => optional($review->replied_at ?? $review->updated_at)->format('F j, Y'),
        ];
    }

    private static function detailReviews(Item $item): array
    {
        // Reviewer identity should display regardless of which storefront
        // the active customer is on — otherwise reviews authored by users
        // bound to a different tenant fall out of `$review->customer` and
        // render as "Customer" with no avatar. Re-query the reviews here
        // (instead of using the upstream-loaded `$item->reviews`
        // collection) so the `customer` eager-load can drop HostScope.
        $reviews = $item->reviews()
            ->where('status', 1)
            ->take(12)
            ->with([
                'customer' => fn ($q) => $q->withoutGlobalScope(\App\Scopes\HostScope::class),
                'store:id,name',
            ])
            ->get();

        return $reviews
            ->map(fn (Review $review) => self::reviewRow($review))
            ->values()
            ->all();
    }

    private static function normalizeVariationValue(string $value): string
    {
        return \preg_replace('/\s+/', '', \trim($value)) ?? '';
    }

    /**
     * Approved-review counts grouped by integer star bucket (5..1).
     * Returns every bucket so the drawer's bar chart renders a row
     * even for empty buckets (matching the screenshot's layout).
     *
     * Public so the food modal's `FoodDetailsResource` can reuse it.
     */
    public static function ratingDistribution(Item $item): array
    {
        $rows = Review::query()
            ->where('item_id', $item->id)
            ->where('status', 1)
            ->selectRaw('ROUND(rating) as bucket, COUNT(*) as cnt')
            ->groupBy('bucket')
            ->pluck('cnt', 'bucket');

        $out = [];
        foreach ([5, 4, 3, 2, 1] as $star) {
            $out[$star] = (int) ($rows[$star] ?? 0);
        }
        return $out;
    }
}
