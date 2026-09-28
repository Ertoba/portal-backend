<?php

namespace Modules\Rental\Http\Controllers\Api\Public;


use App\Models\Zone;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\CentralLogics\StoreLogic;
use App\Models\Store;
use Illuminate\Routing\Controller;
use Modules\Rental\Entities\Vehicle;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Modules\Rental\Entities\VehicleBrand;
use Modules\Rental\Entities\VehicleReview;
use MatanYadaev\EloquentSpatial\Objects\Point;

class VehicleController extends Controller
{
    public function __construct(private Vehicle $vehicle, private VehicleReview $review, private Helpers $helpers)
    {
        $this->review = $review;
        $this->vehicle = $vehicle;
        $this->helpers = $helpers;
    }

    public function topRatedVehicleList(Request $request)
    {
        Helpers::setZoneIds($request);
        $zone_id = $request->header('zoneId');
        $zone_id = json_decode($zone_id, true);

        $limit = $request['limit'] ?? 25;
        $offset = $request['offset'] ?? 1;

        $vehicles = $this->vehicle->active()->when(count($zone_id) > 0, function ($query) use ($zone_id) {
            $query->whereHas('provider', function ($query) use ($zone_id) {
                $query->active()->where(function ($query) use ($zone_id) {
                    $query->whereJsonContains('pickup_zone_id', (string) $zone_id[0]);
                    for ($i = 1; $i < count($zone_id); $i++) {
                        $query->orWhereJsonContains('pickup_zone_id', (string) $zone_id[$i]);
                    }
                    return $query;
                });
            });
        })
            ->with(['provider:id,name,address,tax', 'provider.discount' => function($query){
            return $query->validate();
        }])->withcount('vehicleIdentities as total_vehicle_count')
            ->orderBy('avg_rating', 'desc')
            ->orderBy('total_trip', 'desc')
            ->latest();

        if ($uid = auth('api')->id()) {
            $vehicles = \App\CentralLogics\PersonalizationService::applyItemPersonalization($vehicles, $uid);
        }

        $vehicles = $vehicles->paginate($limit, ['*'], 'page', $offset);

        $data = $this->helpers->preparePaginatedResponse(pagination: $vehicles, limit: $limit, offset: $offset, key: 'vehicles', extraData: []);
        return response()->json($data, 200);
    }

    public function getSearchedVehicles(Request $request)
    {
        Helpers::setZoneIds($request);
        $limit = $request['limit'] ?? 25;
        $offset = $request['offset'] ?? 1;
        $pickup_location = json_decode($request->pickup_location, true) ?? [];

        $pick_up_lat = data_get($pickup_location, 'lat') ?? null;
        $pick_up_lng = data_get($pickup_location, 'lng') ?? null;

        if ($pick_up_lat && $pick_up_lng) {
            $zones = Zone::whereContains('coordinates', new Point($pick_up_lat, $pick_up_lng, POINT_SRID))->pluck('id')->toArray();
        }

        if ($pick_up_lat && $pick_up_lng && count($zones) == 0) {
            $errors = [];
            array_push($errors, ['code' => 'zone', 'message' => translate('messages.Out_of_pick_up_zone')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }

        $vehicleData = $this->getVelicleListData($request, $zones ?? [], $pick_up_lat, $pick_up_lng);
        $vehicles = $vehicleData['vehicles']->paginate($limit, ['*'], 'page', $offset);
        $extraData = [
            'max_price' => (int) $vehicleData['max_price'],
            'min_price' => (int) $vehicleData['min_price'],
        ];

        $data = $this->helpers->preparePaginatedResponse(pagination: $vehicles, limit: $limit, offset: $offset, key: 'vehicles', extraData: $extraData);
        return response()->json($data, 200);
    }


    public function getSearchedVehiclesSuggestion(Request $request)
    {
        Helpers::setZoneIds($request);
        $zone_id = $request->header('zoneId');
        $zone_id = json_decode($zone_id, true);

        $vehicles = $this->vehicle->active()

            ->when(count($zone_id) > 0, function ($query) use ($zone_id) {
                $query->whereHas('provider', function ($query) use ($zone_id) {
                    $query->active()->where(function ($query) use ($zone_id) {
                        $query->whereJsonContains('pickup_zone_id', (string) $zone_id[0]);
                        for ($i = 1; $i < count($zone_id); $i++) {
                            $query->orWhereJsonContains('pickup_zone_id', (string) $zone_id[$i]);
                        }
                        return $query;
                    });
                });
            })


            ->with('brand:id,name')
            ->when($request->filled('name'), function ($query) use ($request) {
                $keys = explode(' ', $request->input('name'));
                $query->where(function ($query) use ($keys) {
                    foreach ($keys as $value) {
                        $query->orWhere('name', 'LIKE', '%' . $value . '%')->orWhere('tag', 'LIKE', '%' . $value . '%');
                    }
                    $relationships = [
                        'translations' => 'name',
                        'category' => 'name',
                        'brand' => 'name',
                    ];
                    $query->applyRelationShipSearch(relationships: $relationships, searchParameter: $keys);
                });
            })
            ->latest()
            ->take(10)
            ->get(['id', 'brand_id', 'name']);
        $vehicles = $vehicles->map(function ($vehicle) {
            if ($vehicle?->brand?->name) {
                return $vehicle?->brand?->name . ' - ' . $vehicle->name;
            } else {
                return  $vehicle->name;
            }
        });
        return response()->json($vehicles, 200);
    }

    public function getProviderWiseVehicles(Request $request)
    {
        Helpers::setZoneIds($request);
        $validator = Validator::make($request->all(), [
            'provider_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $this->helpers->error_processor($validator)], 403);
        }

        $limit = $request['limit'] ?? 25;
        $offset = $request['offset'] ?? 1;

        $provider = Store::when(is_numeric($request->provider_id),function ($qurey) use($request){
                $qurey->where('id', $request->provider_id);
            })
            ->when(!is_numeric($request->provider_id),function ($qurey) use($request){
                $qurey->where('slug', $request->provider_id);
            })
            ->first();

        if (!$provider) {
            return response()->json(['errors' => 'provider_not_found'], 404);
        }

        $request->merge(['provider_id' => $provider?->id]);

        $vehicleData = $this->getVelicleListData($request);
        $vehicles = $vehicleData['vehicles']->paginate($limit, ['*'], 'page', $offset);
        $extraData = [
            'max_price' => (int) $vehicleData['max_price'],
            'min_price' => (int) $vehicleData['min_price'],
        ];

        $data = $this->helpers->preparePaginatedResponse(pagination: $vehicles, limit: $limit, offset: $offset, key: 'vehicles', extraData: $extraData);

        return response()->json($data, 200);
    }

    public function getVehicleDetails($id)
    {
        if (!$id) {
            return response()->json(['errors' => 'Id_or_Slug_is_required'], 404);
        }
        $vehicle =  $this->vehicle->where(function ($query) use ($id) {
            $query->where('id', $id)->orWhere('slug', $id);
        })->withCount('vehicleIdentities as total_vehicles')

        ->with(['provider' => function($query) {
            $query->select('id','name','slug','logo','cover_photo','rating','address','delivery_time')
            ->withCount([
                'vehicle_identity as provider_total_vehicle_count',
            ]);
        }, 'provider.storeConfig:id,store_id,verified_seller', 'brand:id,name,image', 'provider.discount' => function($query){
            return $query->validate();
        }])

        ->first();
        if (!$vehicle) {
            return response()->json(['error' => 'vehicle_not_found'], 404);
        }

        if ($uid = auth('api')->id()) {
            $mid = is_array(config('module.current_module_data')) ? (config('module.current_module_data')['id'] ?? null) : null;
            \App\CentralLogics\PersonalizationService::recordVehicleAction($uid, (int) $vehicle->id, 'item_view', $mid);
        }

        $ratings = StoreLogic::calculate_store_rating($vehicle['provider']['rating']);
        $vehicle['provider']['avg_rating'] = $ratings['rating'];
        $vehicle['provider']['rating_count'] = $ratings['total'];
        $vehicle['provider']['verified_seller'] = Helpers::get_verified_seller_status($vehicle['provider'], $vehicle['provider']?->storeConfig);

        $vehicle['meta_title'] = $vehicle?->seoData?->title;
        $vehicle['meta_description'] = $vehicle?->seoData?->description;
        $vehicle['meta_image'] = $vehicle?->seoData?->imageFullUrl;
        $vehicle['meta_data'] = $vehicle?->seoData?->meta_data;

        return response()->json($vehicle, 200);
    }

    private function getVelicleListData($request, $zones = [], $pick_up_lat = null, $pick_up_lng = null)
    {
        $zone_id = $request->header('zoneId');
        $zone_id = json_decode($zone_id, true);

        $max_price = 999999999;
        $min_price = 1;

            if($request->trip_type == 'distance_wise'){
                $price_column ='distance_price';
            } elseif($request->trip_type == 'hourly'){
                $price_column ='hourly_price';
            } elseif($request->trip_type == 'day_wise'){
                $price_column ='day_wise_price';
            } else{
                $price_column = null;
            }

        if ($price_column) {
            $cache_key_max = "vehicle_max_price_{$price_column}";
            $cache_key_min = "vehicle_min_price_{$price_column}";

            $max_price = Cache::rememberForever($cache_key_max, function () use ($price_column) {
                return $this->vehicle->max($price_column);
            });

            $min_price = Cache::rememberForever($cache_key_min, function () use ($price_column) {
                return $this->vehicle->where($price_column ,'>','0')->min($price_column);
            });
        } else{
            $cache_dis_key_max = "vehicle_dis_max_price_{$request?->provider_id}";
            $cache_hour_key_max = "vehicle_hour_max_price_{$request?->provider_id}";
            $cache_dis_key_min = "vehicle_dis_min_price_{$request?->provider_id}";
            $cache_hour_key_min = "vehicle_hour_min_price_{$request?->provider_id}";

            $cache_day_key_max = "vehicle_day_max_price_{$request?->provider_id}";
            $cache_day_key_min = "vehicle_day_min_price_{$request?->provider_id}";

            $max_dis_price = Cache::rememberForever($cache_dis_key_max, function () use ($request) {
                return $this->vehicle->when($request->provider_id,function($query) use($request){
                    $query->where('provider_id' , $request->provider_id);
                })->max('distance_price');
            });
            $max_hour_price = Cache::rememberForever($cache_hour_key_max, function () use ($request) {
                return $this->vehicle->when($request->provider_id,function($query) use($request){
                    $query->where('provider_id' , $request->provider_id);
                })->max('hourly_price');
            });
            $max_day_price = Cache::rememberForever($cache_day_key_max, function () use ($request) {
                return $this->vehicle->when($request->provider_id,function($query) use($request){
                    $query->where('provider_id' , $request->provider_id);
                })->max('day_wise_price');
            });

            $max_price = max($max_dis_price, $max_hour_price,$max_day_price);

            $min_dis_price = Cache::rememberForever($cache_dis_key_min, function () use ($request) {
                return $this->vehicle->when($request->provider_id,function($query) use($request){
                    $query->where('provider_id' , $request->provider_id);
                })->where('distance_price' ,'>','0')->min('distance_price');
            });
            $min_hour_price = Cache::rememberForever($cache_hour_key_min, function () use ($request) {
                return $this->vehicle->when($request->provider_id,function($query) use($request){
                    $query->where('provider_id' , $request->provider_id);
                })->where('hourly_price' ,'>','0')->min('hourly_price');
            });
            $min_day_price = Cache::rememberForever($cache_day_key_min, function () use ($request) {
                return $this->vehicle->when($request->provider_id,function($query) use($request){
                    $query->where('provider_id' , $request->provider_id);
                })->where('day_wise_price' ,'>','0')->min('day_wise_price');
            });

            $min_prices = array_filter([
            $min_dis_price,
            $min_hour_price,
            $min_day_price
            ], function ($value) {
                return !is_null($value);
            });

            $min_price = !empty($min_prices) ? min($min_prices) : 0;

        }

        $brand_ids = json_decode($request->brand_ids, true) ?? null;
        $category_ids = json_decode($request->category_ids, true) ?? null;
        $seating_capacity = json_decode($request->seating_capacity) ?? null;
        $vehicles = $this->vehicle->active()
            ->when($pick_up_lat &&  $pick_up_lng && count($zones) > 0, function ($query) use ($zones) {
                $query->whereHas('provider', function ($query) use ($zones) {
                    $query->active()->where(function ($query) use ($zones) {
                        $query->whereJsonContains('pickup_zone_id', (string) $zones[0]);
                        for ($i = 1; $i < count($zones); $i++) {
                            $query->orWhereJsonContains('pickup_zone_id', (string) $zones[$i]);
                        }
                        return $query;
                    });
                });
            })
            ->with(['provider:id,name,address,tax',  'vehicleIdentities.vehicle_trip_details','provider.discount' => function($query){
            return $query->validate();
        }]);
        if ($request?->date) {
            $vehicles = $vehicles->withCount([
                'vehicleIdentities as total_vehicle_count' => function ($query) use ($request) {
                    $query->DynamicVehicleQuantity(\Carbon\Carbon::parse($request?->date) ?? now());
                },
            ])
                ->having('total_vehicle_count', '>', 0);
        } else {
            $vehicles = $vehicles->withcount('vehicleIdentities as total_vehicle_count');
        }

        $vehicles = $vehicles->when($request->provider_id, function ($query) use ($request) {
            $query->where('provider_id', $request->provider_id);
        })
            ->when($request->trip_type == 'hourly', function ($query) {
                $query->where('trip_hourly', 1);
            })
            ->when($request->trip_type == 'distance_wise', function ($query) {
                $query->where('trip_distance', 1);
            })
            ->when($request->trip_type == 'day_wise', function ($query) {
                $query->where('trip_day_wise', 1);
            })
            ->when($request->trip_type == 'day_wise' && $request->max_price > 0, function ($query) use ($request) {
                $query->wherebetween('day_wise_price', [$request->min_price ?? 0, $request->max_price]);
            })
            ->when($request->trip_type == 'distance_wise' && $request->max_price > 0, function ($query) use ($request) {
                $query->wherebetween('distance_price', [$request->min_price ?? 0, $request->max_price]);
            })
            ->when($request->trip_type == 'hourly' && $request->max_price > 0, function ($query) use ($request) {
                $query->wherebetween('hourly_price', [$request->min_price ?? 0, $request->max_price]);
            })
            ->when($request->trip_type == 'provider_wise' && $request->max_price > 0, function ($query) use ($request) {
                $query->where(function($q) use ($request) {
                    $q->whereBetween('hourly_price', [$request->min_price ?? 0, $request->max_price])
                      ->orWhereBetween('distance_price', [$request->min_price ?? 0, $request->max_price]);
                });
            })
            ->when($request->filled('name'), function ($query) use ($request) {
                $keys = explode(' ', $request->input('name'));
                $query->where(function ($query) use ($keys) {
                    foreach ($keys as $value) {
                        $query->orWhere('name', 'LIKE', '%' . $value . '%')->orWhere('tag', 'LIKE', '%' . $value . '%');
                    }
                    $relationships = [
                        'translations' => 'name',
                        'category' => 'name',
                        'brand' => 'name',
                    ];
                    $query->applyRelationShipSearch(relationships: $relationships, searchParameter: $keys);
                });
            })
            ->when($brand_ids, function ($query) use ($brand_ids) {
                $query->whereIn('brand_id', $brand_ids);
            })
            ->when($category_ids, function ($query) use ($category_ids) {
                $query->whereIn('category_id', $category_ids);
            })
            ->when($seating_capacity, function ($query) use ($seating_capacity) {
                $query->where(function ($q) use ($seating_capacity) {
                    foreach ($seating_capacity as $range) {
                        $limits = explode('-', $range);
                        $q->orWhereBetween('seating_capacity', [(int) $limits[0], (int)$limits[1]]);
                    }
                });
            })
            ->when($request->air_condition, function ($query) {
                $query->where('air_condition', 1);
            })
            ->when($request->no_air_condition, function ($query) {
                $query->where('air_condition', 0);
            })
            ->when($request->transmission_type, function ($query) use ($request) {
                $query->where('transmission_type', $request->transmission_type);
            })
            ->when($request->vehicle_type, function ($query) use ($request) {
                $query->where('type', $request->vehicle_type);
            })
            ->when($request->fuel_type, function ($query) use ($request) {
                $query->where('fuel_type', $request->fuel_type);
            });

        $vehicles = $vehicles->when(in_array($request->sortby_price, ['asc', 'desc']), function ($query) use ($request) {
            if ($request->trip_type == 'distance_wise') {
                return  $query->orderBy('distance_price', $request->sortby_price);
            } elseif ($request->trip_type == 'day_wise') {
                return  $query->orderBy('day_wise_price', $request->sortby_price);
            } elseif ($request->trip_type == 'hourly') {
                return  $query->orderBy('hourly_price', $request->sortby_price);
            }elseif($request->trip_type == 'provider_wise'){
                return $query->select('*')
                    ->selectRaw('LEAST(
                        CASE WHEN hourly_price IS NULL OR hourly_price = 0 THEN 999999999 ELSE hourly_price END,
                        CASE WHEN distance_price IS NULL OR distance_price = 0 THEN 999999999 ELSE distance_price END,
                        CASE WHEN day_wise_price IS NULL OR day_wise_price = 0 THEN 999999999 ELSE day_wise_price END
                    ) as min_price')
                    ->orderBy('min_price', $request->sortby_price);
            }
        });
        $vehicles = $vehicles->when($request->top_rated == 1, function ($query) {
            $query->orderBy('avg_rating', 'desc')->orderBy('total_trip', 'desc');
        });

        if (!in_array($request->sortby_price, ['asc', 'desc']) && $request->top_rated != 1) {
            $vehicles = $vehicles->latest();
        }

        // Personalize as a secondary sort (explicit sorts keep their order).
        if ($uid = auth('api')->id()) {
            $vehicles = \App\CentralLogics\PersonalizationService::applyItemPersonalization($vehicles, $uid);
        }

        return ['vehicles' => $vehicles, 'max_price' => $max_price, 'min_price' => $min_price];
    }


    public function getPopularSearchlist()
    {
        $brands = VehicleBrand::where('status', 1)->select(['id', 'name'])
            ->withSum('vehicles', 'total_trip')
            ->orderBy('vehicles_sum_total_trip', 'desc')
            ->take(10)
            ->get();
        return response()->json($brands, 200);
    }
    public function getVehicleReviews(Request $request, $id)
    {
        $limit = $request['limit'] ?? 25;
        $offset = $request['offset'] ?? 1;
        $key = explode(' ', $request['search'] ?? '');


        $reviews = $this->review->with(['customer', 'vehicle'])->where('vehicle_id', $id)
            ->when($request['search'], function ($query) use ($key, $request) {
                $query->where(function ($query) use ($key, $request) {
                    $query->whereHas('vehicle', function ($query) use ($key) {
                        foreach ($key as $value) {
                            $query->where('name', 'like', "%{$value}%");
                        }
                    })->orWhereHas('customer', function ($query) use ($key) {
                        foreach ($key as $value) {
                            $query->where('f_name', 'like', "%{$value}%")->orwhere('l_name', 'like', "%{$value}%");
                        }
                    })->orwhere('rating', $request['search'])->orwhere('review_id', $request['search']);
                });
            })
            ->latest()
            ->paginate($limit, ['*'], 'page', $offset);

        $storage = [];
        foreach ($reviews as $item) {
            $item['attachment'] = json_decode($item['attachment']);
            $item['vehicle_name'] = null;
            $item['vehicle_image'] = null;
            $item['customer_name'] = null;
            $item['customer_image'] = null;
            if ($item->vehicle) {
                $item['vehicle_name'] = $item->vehicle->name;
                $item['vehicle_image'] = $item->vehicle->image;
                $item['vehicle_image_full_url'] = $item->vehicle->image_full_url;
            }

            if ($item->customer) {
                $item['customer_name'] = $item->customer->f_name . ' ' . $item->customer->l_name;
                $item['customer_image'] = $item->customer->image_full_url;
            }

            unset($item['vehicle']);
            unset($item['customer']);
            array_push($storage, $item);
        }

        $data = [
            'total_size' => (int) $reviews->total(),
            'limit' => (int) $limit,
            'offset' => (int) $offset,
            'reviews' => $storage,
        ];

        return response()->json($data, 200);
    }
}
