<?php

namespace Modules\Rental\Http\Controllers\Api\Public;

use App\Models\Store;
use App\Models\Coupon;
use Modules\Rental\Entities\Trips;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use App\CentralLogics\CouponLogic;
use Illuminate\Support\Facades\Validator;
use App\CentralLogics\Helpers;
use App\Traits\ManagesProCustomerSubscription;


class CouponController extends Controller
{
    use ManagesProCustomerSubscription;

    public function __construct(private Coupon $coupon, private Store $store)
    {
        $this->coupon = $coupon;
        $this->store = $store;
    }

    public function list(Request $request)
    {
        Helpers::setZoneIds($request);
        $customer_id = Auth::user()?->id ?? $request->customer_id ?? null;
        $provider_id = $request->provider_id ?? null;
        $zone_id = isset($request->zone_id) ? $request->zone_id : $request->header('zoneId');
        if (is_array($zone_id)) {
            $zone_ids = $zone_id;
        } else {
            $decoded_zone = json_decode($zone_id, true);
            $zone_ids = is_array($decoded_zone) ? $decoded_zone : array_filter([$zone_id], fn ($value) => $value !== null && $value !== '');
        }
        $data = [];
        $proOffer = $this->getProCustomerOffer(userId: $customer_id);
        $proCouponEligible = ($proOffer['status'] ?? false) && (($proOffer['benefit']['type'] ?? null) === 'coupon');

        $coupons = $this->coupon->with('store:id,name')->active()
            ->wherehas('module', function ($query) {
                $query->where('module_type', 'rental');
            })
            // Pro Customer coupons carry no start/expire date (their validity is the customer's
            // subscription window, checked below via $proCouponEligible), so they're exempted from
            // the date-window filter here instead of being silently dropped by whereDate on a null.
            ->where(function ($query) {
                $query->where('coupon_type', 'pro_customer')
                    ->orWhere(function ($query) {
                        $query->whereDate('expire_date', '>=', date('Y-m-d'))
                            ->whereDate('start_date', '<=', date('Y-m-d'));
                    });
            })
            ->get();

        $couponUsage = $customer_id ? Trips::userCouponUsage(userId: $customer_id) : collect();

        foreach ($coupons as $key => $coupon) {
            if ($customer_id && $coupon->limit != null && (int) $couponUsage->get($coupon->code, 0) >= $coupon->limit) {
                continue;
            }

            if ($coupon->coupon_type == 'store_wise') {
                $coupon_stores = json_decode($coupon->data, true) ?? [];
                if ($provider_id && !in_array($provider_id, $coupon_stores)) {
                    continue;
                }
                $temp = $this->store->active()
                    ->when(config('module.current_module_data'), function ($query) use ($zone_ids) {
                        if (!config('module.current_module_data')['all_zone_service']) {
                            $query->whereIn('zone_id', $zone_ids);
                        }
                    })
                    ->when($provider_id, fn ($query) => $query->where('id', $provider_id))
                    ->whereIn('id', $coupon_stores)->first();
                if ($temp && (in_array("all", json_decode($coupon->customer_id, true)) || in_array($customer_id, json_decode($coupon->customer_id, true)))) {
                    $coupon->data = $temp->name;
                    $coupon['store_id'] = (int)$temp->id;
                    $data[] = $coupon;
                }
            } else if ($coupon->coupon_type == 'zone_wise') {
                if (count(array_intersect($zone_ids, json_decode($coupon->data, true) ?? []))) {
                    $data[] = $coupon;
                }
            } else if ($coupon->coupon_type == 'pro_customer') {
                if ($proCouponEligible) {
                    $data[] = $coupon;
                }
            } else if (isset($coupon->store_id)) {
                if ($provider_id && $coupon->store_id != $provider_id) {
                    continue;
                }
                $temp = $this->store->active()->when(config('module.current_module_data'), function ($query) use ($zone_ids) {
                    if (!config('module.current_module_data')['all_zone_service']) {
                        $query->whereIn('zone_id', $zone_ids);
                    }
                })->where('id', $coupon->store_id)->exists();

                if ($temp) {
                    $data[] = $coupon;
                }
            } else {
                if ((in_array("all", json_decode($coupon->customer_id, true)) || in_array($customer_id, json_decode($coupon->customer_id, true)))) {
                    $data[] = $coupon;
                }
            }
        }
        
        return response()->json($data, 200);
    }



    public function apply(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'code' => 'required',
            'provider_id' => 'required',
        ]);

        if ($validator->errors()->count()>0) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        try {
            $coupon = Coupon::active()->where(['code' => $request['code']])->wherehas('module', function ($query) {
                $query->where('module_type', 'rental');
            })->first();
            if (isset($coupon)) {
                if ($coupon->coupon_type == 'free_delivery') {
                    return response()->json([
                        'errors' => [
                            ['code' => 'coupon', 'message' => translate('messages.invalid_coupon')]
                        ]
                    ], 403);
                }

                $staus = CouponLogic::is_valide($coupon, $request->user()->id ,$request['provider_id']);

                switch ($staus) {
                case 200:
                    return response()->json($coupon, 200);
                case 406:
                    return response()->json([
                        'errors' => [
                            ['code' => 'coupon', 'message' => translate('messages.coupon_usage_limit_over')]
                        ]
                    ], 406);
                case 407:
                    return response()->json([
                        'errors' => [
                            ['code' => 'coupon', 'message' => translate('messages.coupon_expire')]
                        ]
                    ], 407);
                case 408:
                    return response()->json([
                        'errors' => [
                            ['code' => 'coupon', 'message' => translate('messages.You_are_not_eligible_for_this_coupon')]
                        ]
                    ], 403);
                case 409:
                    return response()->json([
                        'errors' => [
                            ['code' => 'coupon', 'message' => translate('messages.coupon_not_valid_for_this_zone')]
                        ]
                    ], 403);
                default:
                    return response()->json([
                        'errors' => [
                            ['code' => 'coupon', 'message' => translate('messages.coupon_not_found')]
                        ]
                    ], 404);
                }
            } else {
                return response()->json([
                    'errors' => [
                        ['code' => 'coupon', 'message' => translate('messages.coupon_not_found')]
                    ]
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['errors' => $e], 403);
        }
    }


}
