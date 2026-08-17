<?php

namespace Modules\AI\app\Services;

use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Item;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

final class CustomerChatReadOnlyTools
{
    private const DEFAULT_LIMIT = 6;
    private const MAX_LIMIT = 10;

    private array $products = [];
    private array $stores = [];
    private array $categories = [];
    private array $usedTools = [];

    public function __construct(
        private readonly int $moduleId,
        private readonly array $zoneIds,
    ) {
    }

    public function definitions(): array
    {
        return [
            $this->tool(
                'search_products',
                'Search active products in the current module and delivery zone. Use before claiming whether a product, price, stock, category, or vendor item is available.',
                [
                    'query' => ['type' => 'string', 'description' => 'Product name or search phrase.'],
                    'category_id' => ['type' => 'integer', 'description' => 'Optional category ID.'],
                    'max_price' => ['type' => 'number', 'description' => 'Optional maximum price in the platform currency.'],
                    'limit' => ['type' => 'integer', 'description' => 'Maximum results, from 1 to 10.'],
                ],
                ['query']
            ),
            $this->tool(
                'get_popular_items',
                'Get the most popular active products in the current module and delivery zone.',
                ['limit' => ['type' => 'integer', 'description' => 'Maximum results, from 1 to 10.']]
            ),
            $this->tool(
                'get_best_deals',
                'Get active discounted products in the current module and delivery zone.',
                ['limit' => ['type' => 'integer', 'description' => 'Maximum results, from 1 to 10.']]
            ),
            $this->tool(
                'search_stores',
                'Search active stores, restaurants, pharmacies, or vendors in the current module and delivery zone.',
                [
                    'query' => ['type' => 'string', 'description' => 'Optional store name or keyword. Omit it to return popular stores.'],
                    'limit' => ['type' => 'integer', 'description' => 'Maximum results, from 1 to 10.'],
                ]
            ),
            $this->tool(
                'get_store_details',
                'Get verified public details for one active store in the current module and delivery zone.',
                ['store_id' => ['type' => 'integer', 'description' => 'Store ID from search_stores or a product result.']],
                ['store_id']
            ),
            $this->tool(
                'get_categories',
                'List or search active categories in the current module.',
                [
                    'keyword' => ['type' => 'string', 'description' => 'Optional category name keyword.'],
                    'limit' => ['type' => 'integer', 'description' => 'Maximum results, from 1 to 10.'],
                ]
            ),
            $this->tool(
                'get_platform_info',
                'Get allow-listed public platform contact, currency, and delivery-threshold information.',
                []
            ),
            $this->tool(
                'get_supported_languages',
                'Get the languages currently enabled by the platform.',
                []
            ),
        ];
    }

    public function execute(string $name, array $arguments): string
    {
        $this->usedTools[] = $name;

        try {
            return match ($name) {
                'search_products' => $this->searchProducts($arguments),
                'get_popular_items' => $this->popularItems($arguments),
                'get_best_deals' => $this->bestDeals($arguments),
                'search_stores' => $this->searchStores($arguments),
                'get_store_details' => $this->storeDetails($arguments),
                'get_categories' => $this->categories($arguments),
                'get_platform_info' => $this->platformInfo(),
                'get_supported_languages' => $this->supportedLanguages(),
                default => 'Unsupported read-only tool.',
            };
        } catch (Throwable) {
            return 'The requested platform data could not be loaded safely. Do not infer or invent a result.';
        }
    }

    public function metadata(): array
    {
        $metadata = array_filter([
            'products' => array_values($this->products),
            'stores' => array_values($this->stores),
            'categories' => array_values($this->categories),
        ]);

        if ($metadata !== []) {
            $metadata['currency'] = [
                'code' => Helpers::currency_code(),
                'symbol' => Helpers::currency_symbol(),
                'position' => Helpers::get_business_settings('currency_symbol_position') ?: 'left',
            ];
        }

        return $metadata;
    }

    public function usedTools(): array
    {
        return array_values(array_unique($this->usedTools));
    }

    private function searchProducts(array $arguments): string
    {
        if (! $this->hasZoneContext()) {
            return $this->missingLocationMessage();
        }

        $query = $this->cleanQuery($arguments['query'] ?? '');
        if ($query === '') {
            return 'A product search phrase is required.';
        }

        $categoryId = $this->nullablePositiveInt($arguments['category_id'] ?? null);
        $maxPrice = is_numeric($arguments['max_price'] ?? null)
            ? max(0, (float) $arguments['max_price'])
            : null;

        $items = $this->itemQuery()
            ->where(function (Builder $builder) use ($query): void {
                $builder->where('name', 'like', "%{$query}%")
                    ->orWhereHas('translations', function (Builder $translation) use ($query): void {
                        $translation->where('key', 'name')->where('value', 'like', "%{$query}%");
                    });
            })
            ->when($categoryId, function (Builder $builder) use ($categoryId): void {
                $builder->where(function (Builder $category) use ($categoryId): void {
                    $category->where('category_id', $categoryId)
                        ->orWhereJsonContains('category_ids', ['id' => (string) $categoryId]);
                });
            })
            ->when($maxPrice !== null, fn (Builder $builder) => $builder->where('price', '<=', $maxPrice))
            ->orderByDesc('order_count')
            ->limit($this->limit($arguments['limit'] ?? null))
            ->get();

        return $this->recordProducts($items, 'No matching products are currently available in this delivery area.');
    }

    private function popularItems(array $arguments): string
    {
        if (! $this->hasZoneContext()) {
            return $this->missingLocationMessage();
        }

        $items = $this->itemQuery()
            ->orderByDesc('order_count')
            ->limit($this->limit($arguments['limit'] ?? null))
            ->get();

        return $this->recordProducts($items, 'No popular products are currently available in this delivery area.');
    }

    private function bestDeals(array $arguments): string
    {
        if (! $this->hasZoneContext()) {
            return $this->missingLocationMessage();
        }

        $items = $this->itemQuery()
            ->discounted()
            ->orderByDesc('discount')
            ->orderByDesc('order_count')
            ->limit($this->limit($arguments['limit'] ?? null))
            ->get();

        return $this->recordProducts($items, 'No discounted products are currently available in this delivery area.');
    }

    private function searchStores(array $arguments): string
    {
        if (! $this->hasZoneContext()) {
            return $this->missingLocationMessage();
        }

        $query = $this->cleanQuery($arguments['query'] ?? '');
        $stores = Store::query()
            ->active()
            ->module($this->moduleId)
            ->whereIn('zone_id', $this->zoneIds)
            ->when($query !== '', function (Builder $builder) use ($query): void {
                $builder->where(function (Builder $store) use ($query): void {
                    $store->where('name', 'like', "%{$query}%")
                        ->orWhereHas('translations', function (Builder $translation) use ($query): void {
                            $translation->where('key', 'name')->where('value', 'like', "%{$query}%");
                        });
                });
            })
            ->orderByDesc('order_count')
            ->limit($this->limit($arguments['limit'] ?? null))
            ->get(['id', 'name', 'logo', 'cover_photo', 'rating', 'delivery_time', 'minimum_order', 'free_delivery', 'address', 'zone_id', 'module_id', 'order_count', 'featured']);

        if ($stores->isEmpty()) {
            return 'No matching stores are currently available in this delivery area.';
        }

        foreach ($stores as $store) {
            $this->addStore($this->formatStore($store));
        }

        return 'Available stores: ' . $stores
            ->map(fn (Store $store) => sprintf('%s [store_id:%d]', $store->name, $store->id))
            ->implode('; ');
    }

    private function storeDetails(array $arguments): string
    {
        if (! $this->hasZoneContext()) {
            return $this->missingLocationMessage();
        }

        $storeId = $this->nullablePositiveInt($arguments['store_id'] ?? null);
        if (! $storeId) {
            return 'A valid store ID is required.';
        }

        $store = Store::query()
            ->active()
            ->module($this->moduleId)
            ->whereIn('zone_id', $this->zoneIds)
            ->find($storeId, ['id', 'name', 'logo', 'cover_photo', 'rating', 'delivery_time', 'minimum_order', 'free_delivery', 'address', 'zone_id', 'module_id', 'order_count', 'featured', 'schedule_order', 'delivery', 'take_away']);

        if (! $store) {
            return 'That store is not currently available in this module and delivery area.';
        }

        $formatted = $this->formatStore($store);
        $this->addStore($formatted);

        return sprintf(
            '%s [store_id:%d]; rating %.1f/5; delivery time %s; minimum order %s; %s.',
            $formatted['name'],
            $formatted['id'],
            $formatted['rating'],
            $formatted['delivery_time'] ?: 'not configured',
            Helpers::format_currency($formatted['minimum_order']),
            $formatted['free_delivery'] ? 'free delivery is enabled' : 'delivery may have a fee'
        );
    }

    private function categories(array $arguments): string
    {
        $keyword = $this->cleanQuery($arguments['keyword'] ?? '');
        $categories = Category::query()
            ->active()
            ->module($this->moduleId)
            ->when($keyword === '', function (Builder $builder): void {
                $builder->where(function (Builder $parent): void {
                    $parent->whereNull('parent_id')->orWhere('parent_id', 0);
                });
            })
            ->when($keyword !== '', function (Builder $builder) use ($keyword): void {
                $builder->where(function (Builder $category) use ($keyword): void {
                    $category->where('name', 'like', "%{$keyword}%")
                        ->orWhereHas('translations', function (Builder $translation) use ($keyword): void {
                            $translation->where('key', 'name')->where('value', 'like', "%{$keyword}%");
                        });
                });
            })
            ->orderBy('priority')
            ->limit($this->limit($arguments['limit'] ?? null))
            ->get(['id', 'name', 'image', 'parent_id', 'module_id']);

        if ($categories->isEmpty()) {
            return 'No matching active categories were found in this module.';
        }

        foreach ($categories as $category) {
            $formatted = [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'parent_id' => (int) ($category->parent_id ?? 0),
                'image_full_url' => $category->image_full_url,
            ];
            $this->categories[$formatted['id']] = $formatted;
        }

        return 'Active categories: ' . $categories
            ->map(fn (Category $category) => sprintf('%s [category_id:%d]', $category->name, $category->id))
            ->implode('; ');
    }

    private function platformInfo(): string
    {
        $allowedKeys = [
            'business_name', 'address', 'phone', 'email_address', 'country',
            'currency', 'currency_symbol_position', 'digit_after_decimal_point',
            'free_delivery_over', 'free_delivery_over_status',
        ];
        $settings = BusinessSetting::query()
            ->whereIn('key', $allowedKeys)
            ->pluck('value', 'key')
            ->all();

        $public = array_filter([
            'business_name' => $settings['business_name'] ?? null,
            'country' => $settings['country'] ?? null,
            'currency' => $settings['currency'] ?? Helpers::currency_code(),
            'address' => $settings['address'] ?? null,
            'phone' => $settings['phone'] ?? null,
            'support_email' => $settings['email_address'] ?? null,
            'free_delivery_over' => ($settings['free_delivery_over_status'] ?? '0') == '1'
                ? $settings['free_delivery_over'] ?? null
                : null,
        ]);

        return $public === []
            ? 'Public platform information is not configured.'
            : 'Public platform information: ' . collect($public)
                ->map(fn ($value, $key) => $key . '=' . $value)
                ->implode('; ');
    }

    private function supportedLanguages(): string
    {
        $raw = BusinessSetting::query()->where('key', 'system_language')->value('value');
        $languages = json_decode((string) $raw, true);
        if (! is_array($languages)) {
            return 'Supported languages: Georgian (ka), English (en), Russian (ru).';
        }

        $active = collect($languages)
            ->filter(fn ($language) => is_array($language) && (! empty($language['status']) || ! empty($language['default'])))
            ->map(fn ($language) => (string) ($language['code'] ?? ''))
            ->filter()
            ->values()
            ->all();

        return $active === []
            ? 'Supported languages: Georgian (ka), English (en), Russian (ru).'
            : 'Enabled language codes: ' . implode(', ', $active) . '.';
    }

    private function itemQuery(): Builder
    {
        return Item::query()
            ->active()
            ->module($this->moduleId)
            ->with(['store:id,name,logo,zone_id,module_id', 'category:id,name'])
            ->whereHas('store', fn (Builder $store) => $store->whereIn('zone_id', $this->zoneIds));
    }

    private function recordProducts($items, string $emptyMessage): string
    {
        if ($items->isEmpty()) {
            return $emptyMessage;
        }

        $lines = [];
        foreach ($items as $item) {
            $formatted = $this->formatProduct($item);
            $this->products[$formatted['id']] = $formatted;
            $lines[] = sprintf(
                '%s [item_id:%d, store_id:%d] %s%s',
                $formatted['name'],
                $formatted['id'],
                $formatted['store_id'],
                Helpers::format_currency($formatted['discounted_price']),
                $formatted['in_stock'] ? '' : ' (out of stock)'
            );
        }

        return 'Verified product results: ' . implode('; ', $lines) . '.';
    }

    private function formatProduct(Item $item): array
    {
        $price = (float) $item->price;
        $discount = Helpers::product_discount_calculate($item, $price, $item->store, true);
        $discountAmount = min($price, max(0, (float) ($discount['discount_amount'] ?? 0)));

        return [
            'id' => (int) $item->id,
            'name' => (string) $item->name,
            'image_full_url' => $item->image_full_url,
            'price' => $price,
            'discounted_price' => round($price - $discountAmount, 2),
            'has_discount' => $discountAmount > 0,
            'rating' => (float) $item->avg_rating,
            'rating_count' => (int) $item->rating_count,
            'in_stock' => $item->stock === null || (int) $item->stock > 0,
            'store_id' => (int) $item->store_id,
            'store_name' => (string) ($item->store?->name ?? ''),
            'category_id' => (int) $item->category_id,
            'category_name' => (string) ($item->category?->name ?? ''),
        ];
    }

    private function formatStore(Store $store): array
    {
        $ratings = is_array($store->rating) ? $store->rating : [];
        $count = (int) array_sum($ratings);
        $weighted = $count > 0
            ? ((5 * ($ratings[0] ?? 0)) + (4 * ($ratings[1] ?? 0)) + (3 * ($ratings[2] ?? 0)) + (2 * ($ratings[3] ?? 0)) + ($ratings[4] ?? 0)) / $count
            : 0;

        return [
            'id' => (int) $store->id,
            'name' => (string) $store->name,
            'logo_full_url' => $store->logo_full_url,
            'cover_photo_full_url' => $store->cover_photo_full_url,
            'rating' => round($weighted, 1),
            'rating_count' => $count,
            'delivery_time' => (string) ($store->delivery_time ?? ''),
            'minimum_order' => (float) ($store->minimum_order ?? 0),
            'free_delivery' => (bool) $store->free_delivery,
            'address' => (string) ($store->address ?? ''),
            'featured' => (bool) $store->featured,
        ];
    }

    private function addStore(array $store): void
    {
        $this->stores[$store['id']] = $store;
    }

    private function hasZoneContext(): bool
    {
        return $this->zoneIds !== [];
    }

    private function missingLocationMessage(): string
    {
        return 'The customer must select a delivery location before availability can be checked. Do not show cross-zone results.';
    }

    private function cleanQuery(mixed $value): string
    {
        $query = trim(preg_replace('/[\r\n\t]+/u', ' ', (string) $value) ?? '');

        return mb_substr($query, 0, 120);
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function limit(mixed $value): int
    {
        if (! is_numeric($value)) {
            return self::DEFAULT_LIMIT;
        }

        return min(self::MAX_LIMIT, max(1, (int) $value));
    }

    private function tool(string $name, string $description, array $properties, array $required = []): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object) $properties,
                    'required' => $required,
                    'additionalProperties' => false,
                ],
            ],
        ];
    }
}
