<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\StoreCategory;
use App\Traits\ItemFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StoreCategoryController extends Controller
{
    use ItemFilter;

    public function getCategories(Request $request): JsonResponse
    {
        if (!Helpers::storeCategoryStatus()) {
            return response()->json([]);
        }

        $categories = StoreCategory::active()
            ->when(config('module.current_module_data'), function ($q) {
                $q->module(config('module.current_module_data')['id']);
            })
            ->when($request->store_id, function ($q) use ($request) {
                $q->where('store_id', $request->store_id);
            })
            ->when($request->name, function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->name}%");
            })
            ->orderBy('priority', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($categories, 200);
    }

    public function getByStore($storeId): JsonResponse
    {
        if (!Helpers::storeCategoryStatus()) {
            return response()->json([]);
        }

        $categories = StoreCategory::active()
            ->where('store_id', $storeId)
            ->orderBy('priority', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($categories, 200);
    }
    public function getCategoriesWithItems(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        try {
            if (method_exists(Helpers::class, 'setZoneIds')) {
                Helpers::setZoneIds($request);
            }

            $zones = json_decode($request->header('zoneId'), true);
            $moduleHeader = $request->header('moduleId');
            $moduleId = $moduleHeader ? getModuleId($moduleHeader) : (config('module.current_module_data')['id'] ?? null);
            $moduleId = is_numeric($moduleId) ? (int) $moduleId : null;

            $storeId = (int) $request->query('store_id');
            $limit  = max(1, (int) $request->query('limit', 25));
            $offset = max(1, (int) $request->query('offset', 1));
            $type   = $request->query('type', 'all');

            $filters = $this->resolveSearchFilters($request);
            $sortBy  = $filters['sort_by'];
            $additionalData = ['sort_by' => $filters['sort_by'], 'filter_by' => $filters['filter_by']];

            $searchKey       = $request->query('name') ?? $request->query('search');
            $searchRelations = [
                'translations' => 'value',
                'category'     => 'name',
                'tags'         => 'tag',
            ];

            $itemsTable = (new Item)->getTable();

            $applyItemConstraints = function ($q) use ($type, $storeId, $itemsTable, $zones, $moduleId) {
                $q->active(zone_ids: $zones, module_id: $moduleId)
                  ->where("{$itemsTable}.store_id", $storeId)
                  ->type($type);
            };

            $applyItemFilters = function ($q) use ($additionalData, $request, $searchKey, $searchRelations) {
                $q->applyFilters($additionalData)
                  ->applyRating($request)
                  ->applyPriceRange($request)
                  ->when($request->rating_count && is_numeric($request->rating_count), function ($q) use ($request) {
                      $q->where('avg_rating', '<', (int) $request->rating_count + 1);
                  })
                  ->when($searchKey, function ($q) use ($searchKey, $searchRelations) {
                      $q->search($searchKey, $searchRelations);
                  });
            };

            $useStoreCategory = Helpers::storeCategoryStatus()
                && StoreCategory::active()
                    ->where('store_id', $storeId)
                    ->whereHas('items', function ($q) use ($zones, $moduleId) {
                        $q->active(zone_ids: $zones, module_id: $moduleId);
                    })
                    ->exists();

            if ($useStoreCategory) {
                $categories = StoreCategory::active()
                    ->where('store_id', $storeId)
                    ->whereHas('items', function ($q) use ($zones, $moduleId) {
                        $q->active(zone_ids: $zones, module_id: $moduleId);
                    })
                    ->orderBy('priority', 'desc')
                    ->orderBy('name', 'asc')
                    ->get(['id', 'name', 'image', 'priority', 'status']);

                $categoryIds      = $categories->pluck('id')->map(fn ($v) => (int) $v)->all();
                $categoryKeyCol   = "{$itemsTable}.store_category_id";
                $baseItemScope    = function ($q) use ($itemsTable, $categoryIds) {
                    $q->whereIn("{$itemsTable}.store_category_id", $categoryIds);
                };
            } else {
                $usedCategoryIds = Item::query()
                    ->tap($applyItemConstraints)
                    ->whereNotNull("{$itemsTable}.category_id")
                    ->distinct()
                    ->pluck("{$itemsTable}.category_id")
                    ->filter()
                    ->map(fn ($v) => (int) $v)
                    ->all();

                $categories = empty($usedCategoryIds)
                    ? collect()
                    : Category::where('status', 1)
                        ->whereIn('id', $usedCategoryIds)
                        ->orderBy('name', 'asc')
                        ->get(['id', 'name', 'image']);

                $categoryIds    = $categories->pluck('id')->map(fn ($v) => (int) $v)->all();
                $categoryKeyCol = "{$itemsTable}.category_id";
                $baseItemScope  = function ($q) use ($itemsTable, $categoryIds) {
                    if (!empty($categoryIds)) {
                        $q->whereIn("{$itemsTable}.category_id", $categoryIds);
                    }
                };
            }

            if (empty($categoryIds)) {
                return response()->json([
                    'total_size'         => 0,
                    'limit'              => $limit,
                    'offset'             => $offset,
                    'category_source'    => $useStoreCategory ? 'store_category' : 'main_category',
                    'categories'         => [],
                    'category_wise_items' => (object) [],
                ], 200);
            }

            $itemsCountByCategory = Item::query()
                ->tap($baseItemScope)
                ->tap($applyItemConstraints)
                ->tap($applyItemFilters)
                ->reorder()
                ->select(DB::raw("{$categoryKeyCol} AS cat_group"), DB::raw('COUNT(*) AS cnt'))
                ->groupBy(DB::raw($categoryKeyCol))
                ->pluck('cnt', 'cat_group');

            $categoriesData = $categories
                ->map(function ($category) use ($itemsCountByCategory) {
                    return [
                        'id'             => (int) $category->id,
                        'name'           => $category->name,
                        'image_full_url' => $category->image_full_url ?? null,
                        'items_count'    => (int) ($itemsCountByCategory[$category->id] ?? 0),
                    ];
                })
                ->values();

            $itemsBuilder = Item::query()
                ->tap($baseItemScope)
                ->tap($applyItemConstraints)
                ->tap($applyItemFilters)
                ->reorder()
                ->addSelect("{$itemsTable}.*")
                ->selectRaw("{$categoryKeyCol} AS cat_group")
                ->orderByRaw("FIELD({$categoryKeyCol}, " . implode(',', $categoryIds) . ')')
                ->orderBy("{$itemsTable}.name", 'asc')
                ->orderBy("{$itemsTable}.id", 'asc')
                ->applySorting($sortBy);

            $totalItems = (clone $itemsBuilder)->count("{$itemsTable}.id");

            $items = $itemsBuilder
                ->skip(($offset - 1) * $limit)
                ->take($limit)
                ->get();

            $formattedItems = (object) [];
            if ($items->isNotEmpty()) {
                $locale = app()->getLocale();
                $itemsByCategory = $items->groupBy(fn ($it) => (int) $it->cat_group);
                $grouped = [];

                foreach ($categoryIds as $catId) {
                    if (!$itemsByCategory->has($catId)) {
                        continue;
                    }
                    $grouped[(string) $catId] = Helpers::product_data_formatting(
                        $itemsByCategory[$catId], true, false, $locale
                    );
                }

                $formattedItems = (object) $grouped;
            }

            return response()->json([
                'total_size'         => $totalItems,
                'limit'              => $limit,
                'offset'             => $offset,
                'category_source'    => $useStoreCategory ? 'store_category' : 'main_category',
                'categories'         => $categoriesData,
                'category_wise_items' => $formattedItems,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([$e->getMessage()], 200);
        }
    }

}
