<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use App\CentralLogics\PersonalizationService;
use App\Traits\ItemFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WishlistController extends Controller
{
    use ItemFilter;

    public function add_to_wishlist(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required_without:store_id',
            'store_id' => 'required_without:item_id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        if ($request->item_id && $request->store_id) {
            $errors = [];
            array_push($errors, ['code' => 'data', 'message' => translate('messages.can_not_add_both_food_and_restaurant_at_same_time')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $wishlist = Wishlist::where('user_id', $request->user()->id)->where('item_id', $request->item_id)->where('store_id', $request->store_id)->first();
        if (empty($wishlist)) {
            $wishlist = new Wishlist;
            $wishlist->user_id = $request->user()->id;
            $wishlist->item_id = $request->item_id;
            $wishlist->store_id = $request->store_id;
            $wishlist->save();

            if($request->item_id){
                PersonalizationService::recordItemAction($request->user()->id, (int)$request->item_id, 'item_wishlist');
            }
            if($request->store_id){
                PersonalizationService::recordStoreAction($request->user()->id, (int)$request->store_id, 'store_wishlist');
            }

            $text= $request->store_id ? 'Store added to favorites' :'Item added to favorites';
            return response()->json(['message' => translate($text)], 200);
        }

        return response()->json(['message' => translate('messages.already_in_wishlist')], 409);
    }

    public function remove_from_wishlist(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required_without:store_id',
            'store_id' => 'required_without:item_id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $wishlist = Wishlist::when($request->item_id, function($query)use($request){
            return $query->where('item_id', $request->item_id);
        })
        ->when($request->store_id, function($query)use($request){
            return $query->where('store_id', $request->store_id);
        })
        ->where('user_id', $request->user()->id)->first();

        if ($wishlist) {
            $wishlist->delete();
             $text= $request->store_id ? 'Store removed from favorites' :'Item removed from favorites';
            return response()->json(['message' => translate($text)], 200);

        }
        return response()->json(['message' => translate('messages.not_found')], 404);
    }

    public function wish_list(Request $request)
    {
        Helpers::setZoneIds($request);
        $zone_id = $request->header('zoneId');
        $longitude = $request->header('longitude');
        $latitude = $request->header('latitude');
        $zones = json_decode($zone_id, true);

        $moduleHeader = $request->header('moduleId');
        $module_id = $moduleHeader ? getModuleId($moduleHeader) : (config('module.current_module_data')['id'] ?? null);
        $module_id = is_numeric($module_id) ? (int) $module_id : null;

        $type = $request->query('type', 'all');
        $search = $request->query('search');
        $filters = $this->resolveSearchFilters($request, $request['filter'] ?? null);
        $filter_list = $filters['filter_list'];
        $additional_data = ['sort_by' => $filters['sort_by'], 'filter_by' => $filters['filter_by']];

        $storeFilter = $this->storeFilterInputs($request);

        $wishlists = Wishlist::where('user_id', $request->user()->id)
            ->with([
                'item' => function ($q) use ($zones, $module_id, $filter_list, $additional_data, $type, $search, $request) {
                    $q->active(zone_ids: $zones, module_id: $module_id)->type($type);
                    if ($module_id) {
                        $q->where('items.module_id', $module_id);
                    }
                    $q->when($search, function ($query) use ($search) {
                        $query->search(keywords: $search, relations: [
                            'translations' => 'value',
                            'tags' => 'tag',
                            'category.parent' => 'name',
                            'category' => 'name',
                            'nutritions' => 'nutrition',
                            'allergies' => 'allergy',
                            'generic' => 'generic_name',
                            'ecommerce_item_details.brand' => 'name',
                            'pharmacy_item_details.common_condition' => 'name',
                        ]);
                    })
                    ->when($filter_list && in_array('coupon', $filter_list), function ($query) use ($zones) {
                        $query->whereHas('module.zones', function ($qq) use ($zones) {
                            if (!empty($zones)) {
                                $qq->whereIn('zones.id', $zones);
                            }
                            $qq->has('activeCoupons');
                        });
                    })
                    ->when($filter_list && in_array('available_now', $filter_list), function ($query) {
                        $query->where(function ($qq) {
                            $currentTime = now()->format('H:i:s');
                            $qq->whereRaw("(available_time_starts < available_time_ends AND TIME(?) BETWEEN available_time_starts AND available_time_ends)", [$currentTime])
                            ->orWhereRaw("(available_time_starts > available_time_ends AND (TIME(?) >= available_time_starts OR TIME(?) <= available_time_ends))", [$currentTime, $currentTime]);
                        });
                    })
                    ->applyRating($request)
                    ->applyFilters($additional_data)
                    ->applySorting($additional_data['sort_by'])
                    ->applyPriceRange($request);
                },
                'store' => function ($q) use ($zones, $longitude, $latitude, $module_id, $storeFilter) {
                    $q->where('status', 1)
                        ->withOpen($longitude ?? 0, $latitude ?? 0)
                        ->whereHas('module', fn ($query) => $query->where('status', 1))
                        ->whereIn('zone_id', $zones);
                    if ($module_id) {
                        $q->where('stores.module_id', $module_id);
                    }
                    $q->applyStoreFilter($storeFilter);
                },
            ])
            ->get();

        $wishlists = Helpers::wishlist_data_formatting($wishlists, true);
        return response()->json($wishlists, 200);
    }
}
