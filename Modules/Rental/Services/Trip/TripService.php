<?php

namespace Modules\Rental\Services\Trip;

use App\CentralLogics\Helpers;
use App\Services\BaseService;
use Carbon\Carbon;
use App\Models\CashBackHistory;
use App\Traits\Customer\PersonalizationTrait;
use App\Traits\Payment\PaymentRedirectLinkTrait;
use App\Traits\Marketing\CouponDiscountTrait;
use App\Traits\Payment\CustomerTransactionsTrait;
use App\Traits\Store\StoreDataTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Rental\Exceptions\BookingRejected;
use Modules\Rental\Support\CouponContext;
use Modules\TaxModule\Services\CalculateTaxService;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Rental\Emails\TripBooking;
use Modules\Rental\Entities\PartialPayment;
use Modules\Rental\Entities\Trips;
use Modules\Rental\Traits\RentalPushNotification;
use Modules\Rental\Traits\TripLogicTrait;
use Modules\Rental\Services\Trip\TripVehicleDetailsService;
use Modules\Rental\Services\Trip\TripDetailsService;
use Modules\Rental\Services\Trip\TripsService;
use Modules\Rental\Services\Cart\RentalCartService;
use Modules\Rental\Services\Vehicle\VehicleService;
use Modules\Rental\Services\Vehicle\VehicleReviewService;
use App\Services\Customer\UserService;
use App\Services\Store\StoreService;
use App\Services\Zone\ZoneService;
use App\Traits\System\ReelsAddonTrait;
use App\Support\Notification\SendNotification;

class TripService extends BaseService
{
    use ReelsAddonTrait;
    use TripLogicTrait, PersonalizationTrait, PaymentRedirectLinkTrait, RentalPushNotification, StoreDataTrait, CouponDiscountTrait, CustomerTransactionsTrait;

    private const CUSTOMER_LIST_RELATIONS = [
        'provider:id,name,logo,cover_photo,phone',
        'provider.storage',
        'proDiscount',
        'trip_details',
        'vehicle_identity.driver_data:id,first_name,last_name,email,phone,image',
        'vehicle_identity.driver_data.storage',
        'vehicle_identity.vehicles:id,name,thumbnail',
        'vehicle_identity.vehicles.storage',
        'trip_details.vehicle:id,name,thumbnail,hourly_price,distance_price,provider_id',
        'trip_details.vehicle.storage',
    ];

    private const LAST_TRIP_LIMIT = 10;

    private const LIST_STATUSES = ['pending', 'confirmed', 'ongoing', 'completed', 'canceled', 'payment_failed'];

    private const CLOSED_STATUSES = ['completed', 'canceled'];

    private const UNPAID_STATUSES = ['payment_failed'];

    private const SETTLED_METHODS = ['cash_payment', 'wallet'];

    private const UNPAID_WINDOW_MONTHS = 1;

    private const DETAIL_RELATIONS = [
        'customer:id,f_name,l_name,phone,email,image',
        'proDiscount',
        'vehicle_identity.driver_data:id,first_name,last_name,email,phone,image',
        'vehicle_identity.driver_data.storage',
        'vehicle_identity.vehicle_identity_data:id,vin_number,license_plate_number',
        'vehicle_identity.vehicles:id,name,thumbnail',
        'vehicle_identity.vehicles.storage',
    ];

    private const EARNING_STATUSES = ['refunded', 'completed'];

    private const TAX_STATUSES = ['completed', 'refund_requested', 'refund_request_canceled'];

    private const TAX_ORDER_COLUMNS = ['id', 'trip_amount', 'tax_amount', 'trip_type', 'created_at', 'trip_status', 'payment_status'];

    private $cashBackResolver = null;

    public function couponUsage(mixed $customerId): array
    {
        return $customerId ? Trips::userCouponUsage(userId: $customerId)->all() : [];
    }

    /**
     * Whether this customer has never taken a trip -- what a first_order coupon is offered on.
     *
     * Trips, not orders: CouponService::firstOrderEligible() answers this through
     * OrderService::hasNoPastOrders(), which reads the `orders` table -- a rental customer never
     * appears there, so it would call everybody a first-timer. Guest trips are excluded on the
     * same reasoning as the order-side check.
     */
    public function hasNoPastTrips(mixed $customerId): bool
    {
        return $customerId
            ? Trips::where('user_id', $customerId)->where('is_guest', 0)->doesntExist()
            : false;
    }

    public function providerStats(mixed $providerId): array
    {
        $row = app(TripsService::class)->query()
            ->where('provider_id', $providerId)
            ->whereIn('trip_status', self::EARNING_STATUSES)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN DATE(completed) = ? THEN 1 ELSE 0 END) as today', [now()->toDateString()])
            ->selectRaw('SUM(CASE WHEN completed BETWEEN ? AND ? AND YEAR(completed) = ? THEN 1 ELSE 0 END) as this_week',
                [now()->startOfWeek(), now()->endOfWeek(), now()->year])
            ->selectRaw('SUM(CASE WHEN MONTH(completed) = ? AND YEAR(completed) = ? THEN 1 ELSE 0 END) as this_month',
                [now()->month, now()->year])
            ->first();

        return [
            'order_count' => (int) $row->total,
            'todays_order_count' => (int) $row->today,
            'this_week_order_count' => (int) $row->this_week,
            'this_month_order_count' => (int) $row->this_month,
        ];
    }

    public function getProviderList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $trips = app(TripsService::class)->query()->where('provider_id', $filters['provider_id'] ?? null)
            ->when(in_array($filters['trip_status'] ?? null, self::LIST_STATUSES, true),
                fn ($query) => $query->where('trip_status', $filters['trip_status']))
            ->with('proDiscount')
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        $trips->getCollection()->transform(fn ($trip) => $this->withProDiscount($trip));

        return $trips;
    }

    public function findForProvider(mixed $tripId, mixed $providerId, array $relations = []): ?Trips
    {
        return app(TripsService::class)->query()->where('provider_id', $providerId)
            ->where('id', $tripId)
            ->when($relations, fn ($query) => $query->with($relations))
            ->first();
    }

    public function findProviderDetail(mixed $tripId, mixed $providerId): ?Trips
    {
        $trip = $this->findForProvider($tripId, $providerId, array_merge(self::DETAIL_RELATIONS, [
            'trip_details.vehicle' => fn ($query) => $query
                ->select('id', 'hourly_price', 'distance_price', 'day_wise_price', 'discount_type', 'discount_price')
                ->withCount('vehicleIdentities as total_vehicle_count'),
        ]));

        if (! $trip) {
            return null;
        }

        $this->withProDiscount($trip);

        if ($trip->customer) {
            $trip->customer->trips_count = $trip->customer->trips()->count();
        }

        return $trip;
    }

    public function isClosed(Trips $trip): bool
    {
        return in_array($trip->trip_status, self::CLOSED_STATUSES, true);
    }

    public function hasAssignedVehicle(Trips $trip): bool
    {
        return count($trip->assignedVehicle) > 0;
    }

    public function hasDeletedVehicle(Trips $trip): bool
    {
        return $trip->trip_details->contains(fn ($detail) => ! $detail->vehicle);
    }

    public function updateStatus(Trips $trip, array $data): bool
    {
        $status = $data['trip_status'];

        if ($status === 'canceled') {
            $trip->canceled_by = 'vendor';
            $trip->cancellation_reason = $data['cancellation_reason'] ?? null;
            foreach ($trip->trip_details as $detail) {
                if ($detail?->vehicle?->total_trip > 0) {
                    $detail->vehicle->decrement('total_trip', $detail->quantity);
                }
            }
            Helpers::increment_order_count($trip->provider);
        } elseif ($trip->trip_status !== 'pending' && $status === 'pending') {
            $trip->vehicle_identity()->delete();
        } elseif ($status === 'completed' && $trip->payment_status === 'paid' && ! $trip->trip_transaction) {
            if (self::create_transaction($trip, 'vendor') === false) {
                return false;
            }
        }

        $trip->trip_status = $status;
        $trip[$status] = now();
        $trip->save();

        if ($status === 'completed') {
            $this->completeTrip($trip);
        }

        return true;
    }

    public function updatePaymentStatus(Trips $trip, array $data): bool
    {
        $trip->payment_status = $data['payment_status'];
        $trip->payment_method = $data['payment_method'] ?? $trip->payment_method ?? 'cash_payment';
        $trip->transaction_reference = $data['transaction_reference'] ?? $trip->transaction_reference;
        $trip->save();

        if ($trip->trip_status === 'completed' && $trip->payment_status === 'paid' && ! $trip->trip_transaction) {
            return self::create_transaction($trip, 'vendor') !== false;
        }

        return true;
    }

    public function assignDrivers(Trips $trip, array $driverMap): ?array
    {
        $rows = [];

        foreach ($driverMap as $detailId => $driverId) {
            $detail = app(TripVehicleDetailsService::class)->findForTrip($trip->id, $detailId);

            if (! $detail) {
                return null;
            }

            $detail->vehicle_driver_id = $driverId;
            $detail->save();
            $rows[] = $detail;
        }

        return $rows;
    }

    public function providerOwnsVehicle(mixed $vehicleId, mixed $providerId): bool
    {
        return app(VehicleService::class)->existsForProvider($vehicleId, $providerId);
    }

    public function assignVehicles(Trips $trip, array $data): array
    {
        $rows = [];

        foreach ($data['vehicle_identity_ids'] as $identityId) {
            $detail = app(TripVehicleDetailsService::class)->findOrNewForIdentity($trip->id, $data['vehicle_id'], $identityId);
            $detail->trip_id = $trip->id;
            $detail->vehicle_id = $data['vehicle_id'];
            $detail->trip_details_id = $data['trip_details_id'];
            $detail->vehicle_identity_id = $identityId;
            $detail->estimated_trip_end_time = $trip->estimated_trip_end_time;
            $detail->save();
            $rows[] = $detail;
        }

        app(TripVehicleDetailsService::class)->deleteIdentitiesExcept($trip->id, $data['vehicle_id'], $data['vehicle_identity_ids']);

        return $rows;
    }


    public function applyEdit(mixed $context, Trips $trip, array $data): Trips|array
    {
        return self::getUpdatedTrip($context, $trip, $data);
    }

    public function book(array $input, array $context): array
    {
        $scheduleAt = $input['schedule_at'] ? Carbon::parse($input['schedule_at']) : now();
        $userData = app(RentalCartService::class)->findUserData($context);
        $carts = app(RentalCartService::class)->linesWithVehicles($context);

        if ($carts->isEmpty()) {
            return ['code' => 'cart', 'message' => translate('Your cart is empty'), 'status_code' => 403];
        }

        if (! $userData) {
            return ['code' => 'cart', 'message' => translate('Your location is empty'), 'status_code' => 403];
        }

        $endTime = $scheduleAt->copy()->addHours(ceil(
            in_array($userData->rental_type, ['hourly', 'day_wise'], true)
                ? $userData->estimated_hours ?? 1
                : $userData->destination_time ?? 1
        ));

        $check = $this->validateBooking($input, $scheduleAt, $userData);

        if (data_get($check, 'status_code') === 403) {
            return $check;
        }

        $provider = $check['store'];
        $pickupZone = $check['pickup_zone'];

        return DB::transaction(function () use ($input, $context, $carts, $userData, $scheduleAt, $endTime, $provider, $pickupZone) {
            $coupon = null;
            $couponDiscountBy = 'none';

            if ($input['coupon_code'] ?? null) {
                $couponCheck = self::couponCheck($this->couponContext($input, $context));

                if (data_get($couponCheck, 'code') === 'coupon') {
                    throw new BookingRejected($couponCheck);
                }

                $coupon = data_get($couponCheck, 'coupon');
                $couponDiscountBy = data_get($couponCheck, 'coupon_discount_by');
            }

            $details = $this->buildTripDetails($input, $userData, $carts, $scheduleAt, $endTime, $provider);

            if (data_get($details, 'code') === 'details_data') {
                throw new BookingRejected($details);
            }

            $price = data_get($details, 'price');
            $discountOnTrip = data_get($details, 'discount');
            $quantity = data_get($details, 'quantity');
            $discountOnTripBy = data_get($details, 'discount_on_trip_by');
            $detailRows = data_get($details, 'details_data');

            $proSubtotal = $price;
            $discountOnTrip = Helpers::minDiscountCheck(productPrice: $price, discount: $discountOnTrip)['discount_applied'];
            $totalDiscount = $discountOnTrip;
            $price -= $discountOnTrip;

            $couponDiscount = $coupon ? $this->calculateDiscount($coupon, $price) : 0;
            $couponDiscount = Helpers::minDiscountCheck(productPrice: $price, discount: $couponDiscount)['discount_applied'];
            $totalDiscount += $couponDiscount;
            $price -= $couponDiscount;

            $refBonus = 0;
            $proOffer = ['status' => false, 'benefit' => null];
            $proDiscount = 0.0;

            if ($context['is_guest'] == 0 && $context['user_id']) {
                $user = app(UserService::class)->findWithTripCount($context['user_id']);
                $firstOrder = Helpers::getCusromerFirstOrderDiscount(
                    order_count: $user->trips_count,
                    user_creation_date: $user->created_at,
                    refby: $user->ref_by,
                    price: $price
                );

                if (data_get($firstOrder, 'is_valid') == true && data_get($firstOrder, 'calculated_amount') > 0) {
                    $refBonus = Helpers::minDiscountCheck(
                        productPrice: $price,
                        discount: data_get($firstOrder, 'calculated_amount')
                    )['discount_applied'];
                    $totalDiscount += $refBonus;
                    $price -= $refBonus;
                }

                $proApply = $context['pro_apply']($proSubtotal, $price);
                $proOffer = $proApply['offer'];
                $proDiscount = $proApply['discount'];
                $price = $proApply['total_price'];
                $totalDiscount += $proDiscount;
            }

            $additionalCharge = Helpers::get_business_settings('additional_charge_status', false) == 1
                ? (Helpers::get_business_settings('additional_charge', false) ?? 0)
                : 0;

            $tax = $this->calculateBookingTax($detailRows, [], $totalDiscount, $price, $provider->id);
            $taxMap = $tax['taxMap'];
            $price = max(0, $price) + $tax['tax_amount'] + $additionalCharge;

            $trip = $this->persistTrip($input, [
                'user_id' => $context['user_id'],
                'pickup_zone_id' => $pickupZone->id,
                'is_guest' => $context['is_guest'],
                'schedule_at' => $scheduleAt,
                'estimated_trip_end_time' => $endTime,
                'user_data' => $userData,
                'provider' => $provider,
                'coupon' => $coupon,
                'discount_on_trip' => $discountOnTrip,
                'coupon_discount_amount' => $couponDiscount,
                'coupon_discount_by' => $couponDiscountBy,
                'coupon_code' => $coupon?->code,
                'tax_amount' => $tax['tax_amount'],
                'tax_status' => $tax['tax_status'],
                'tax_included' => $tax['tax_included'],
                'trip_amount' => $price,
                'discount_on_trip_by' => $discountOnTripBy,
                'additional_charge' => $additionalCharge,
                'distance' => $userData->distance ?? 0,
                'estimated_hours' => $userData->estimated_hours ?? 0,
                'ref_bonus_amount' => $refBonus,
                'cash_back_id' => null,
                'quantity' => $quantity,
                'pickup_location' => json_encode($userData->pickup_location),
                'destination_location' => json_encode($userData->destination_location),
                'user_info' => json_encode($context['user_info']),
            ]);

            if ($context['is_guest'] == 0 && $context['user_id'] && ($proOffer['status'] ?? false)) {
                $context['pro_record']($trip->id, $proOffer, $proDiscount);
            }

            foreach ($detailRows as $key => $item) {
                $detailRows[$key]['trip_id'] = $trip->id;
                if ($taxMap && isset($taxMap[$item['vehicle_id']])) {
                    $detailRows[$key]['tax_percentage'] = $taxMap[$item['vehicle_id']]['totalTaxPercent'];
                    $detailRows[$key]['tax_status'] = $taxMap[$item['vehicle_id']]['include'] == 1 ? 'included' : 'excluded';
                    $detailRows[$key]['tax_amount'] = $taxMap[$item['vehicle_id']]['totalTaxamount'];
                }
                unset($detailRows[$key]['category_id']);
            }

            app(TripDetailsService::class)->insertMany($detailRows);

            $orderTaxIds = data_get($tax, 'taxData.orderTaxIds', []);
            if (count($orderTaxIds)) {
                CalculateTaxService::updateOrderTaxData(orderId: $trip->id, orderTaxIds: $orderTaxIds);
            }

            $this->recordReelSales($carts, $trip);
            $carts->each(fn ($cart) => $cart->delete());

            if ($trip->is_guest == 0 && $trip->user_id) {
                $this->recordCashBack($trip->trip_amount, $trip->user_id, $trip->id);
            }

            $userData->delete();

            return ['trip' => $trip];
        });
    }

    public function estimateTax(array $input, array $context): array
    {
        $scheduleAt = $input['schedule_at'] ? Carbon::parse($input['schedule_at']) : now();
        $userData = app(RentalCartService::class)->findUserData($context);
        $carts = app(RentalCartService::class)->linesWithVehicles($context);

        if ($carts->isEmpty()) {
            return ['code' => 'cart', 'message' => translate('Your cart is empty'), 'status_code' => 403];
        }

        if (! $userData) {
            return ['code' => 'cart', 'message' => translate('Your location is empty'), 'status_code' => 403];
        }

        $endTime = $scheduleAt->copy()->addHours(ceil(
            in_array($userData->rental_type, ['hourly', 'day_wise'], true)
                ? $userData->estimated_hours ?? 1
                : $userData->destination_time ?? 1
        ));

        $check = $this->validateBooking($input, $scheduleAt, $userData);

        if (data_get($check, 'status_code') === 403) {
            return $check;
        }

        $provider = $check['store'];
        $coupon = null;

        if ($input['coupon_code'] ?? null) {
            $couponCheck = self::couponCheck($this->couponContext($input, $context), false);

            if (data_get($couponCheck, 'code') === 'coupon') {
                return $couponCheck;
            }

            $coupon = data_get($couponCheck, 'coupon');
        }

        $details = $this->buildTripDetails($input, $userData, $carts, $scheduleAt, $endTime, $provider, false);

        if (data_get($details, 'code') === 'details_data') {
            return $details;
        }

        $price = data_get($details, 'price');
        $discountOnTrip = Helpers::minDiscountCheck(productPrice: $price, discount: data_get($details, 'discount'))['discount_applied'];
        $totalDiscount = $discountOnTrip;
        $price -= $discountOnTrip;

        $couponDiscount = $coupon ? $this->calculateDiscount($coupon, $price) : 0;
        $couponDiscount = Helpers::minDiscountCheck(productPrice: $price, discount: $couponDiscount)['discount_applied'];
        $totalDiscount += $couponDiscount;
        $price -= $couponDiscount;

        if ($context['is_guest'] == 0 && $context['user_id']) {
            $user = app(UserService::class)->findWithTripCount($context['user_id']);
            $firstOrder = Helpers::getCusromerFirstOrderDiscount(
                order_count: $user->trips_count,
                user_creation_date: $user->created_at,
                refby: $user->ref_by,
                price: $price
            );

            if (data_get($firstOrder, 'is_valid') == true && data_get($firstOrder, 'calculated_amount') > 0) {
                $bonus = Helpers::minDiscountCheck(productPrice: $price, discount: data_get($firstOrder, 'calculated_amount'))['discount_applied'];
                $totalDiscount += $bonus;
                $price -= $bonus;
            }
        }

        $tax = $this->calculateBookingTax(data_get($details, 'details_data'), [], $totalDiscount, $price, $provider->id, false);

        return [
            'tax_amount' => $tax['tax_amount'],
            'tax_status' => $tax['tax_status'],
            'tax_included' => $tax['tax_included'],
        ];
    }

    public function useCashBackResolver(callable $resolver): void
    {
        $this->cashBackResolver = $resolver;
    }

    public function notifyTripPlaced($trip)
    {
        $order_mail_status = SendNotification::mailTemplateEnabled('rental_place_order_mail_status_user');
        try {
            if ($trip->provider?->is_valid_subscription == 1 && $trip->provider?->store_sub?->max_order != "unlimited" && $trip->provider?->store_sub?->max_order > 0) {
                $trip->provider?->store_sub?->decrement('max_order', 1);
            }
            if (config('mail.status') && $order_mail_status && SendNotification::rentalChannelEnabled('customer', 'customer_trip_notification', 'mail_status')) {
                if (!$trip->is_guest && $trip->customer) {
                    SendNotification::mail($trip->customer?->getRawOriginal('email'), new TripBooking($trip->id));
                } elseif ($trip->is_guest == 1  && isset($trip->user_info['contact_person_email'])) {
                    SendNotification::mail($trip->user_info['contact_person_email'], new TripBooking($trip->id));
                }
            }
        } catch (\Exception $ex) {
            Log::error('Rental trip booking notification failed: ' . $ex->getMessage());
        }

        $this->sendTripNotificationToAll($trip);
        return true;
    }

    public function getCustomerList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $trips = app(TripsService::class)->query()->where(['user_id' => $filters['user_id'], 'is_guest' => $filters['is_guest']])
            ->NotHiddenForCustomer()
            ->with(self::CUSTOMER_LIST_RELATIONS)
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($group) => $this->matchTripId($group, $search)
            ))
            ->when($filters['trip_status'] ?? null, fn ($query, $status) => $query->where('trip_status', $status))
            ->when(($filters['type'] ?? null) === 'completed', fn ($query) => $query->whereIn('trip_status', ['completed', 'canceled']))
            ->when(($filters['type'] ?? null) === 'running', fn ($query) => $query->whereIn('trip_status', ['pending', 'confirmed', 'ongoing', 'payment_failed']))
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        $trips->getCollection()->transform(fn ($trip) => $this->withProDiscount($trip));

        return $trips;
    }

    public function findForCustomer(mixed $tripId, array $context, array $relations = []): ?Trips
    {
        return app(TripsService::class)->query()->where(['user_id' => $context['user_id'], 'is_guest' => $context['is_guest'], 'id' => $tripId])
            ->NotHiddenForCustomer()
            ->when($relations, fn ($query) => $query->with($relations))
            ->first();
    }

    public function findCustomerDetail(mixed $tripId, array $context): ?Trips
    {
        $trip = app(TripsService::class)->query()->where(['user_id' => $context['user_id'], 'is_guest' => $context['is_guest'], 'id' => $tripId])
            ->NotHiddenForCustomer()
            ->with([
                'trip_details.tripVehicleDetails.vehicle_identity_data',
                'proDiscount',
                'vehicle_identity.driver_data:id,first_name,last_name,email,phone,image',
                'vehicle_identity.driver_data.storage',
                'vehicle_identity.vehicle_identity_data:id,vin_number,license_plate_number',
                'vehicle_identity.vehicles:id,name,thumbnail',
                'vehicle_identity.vehicles.storage',
                'provider' => fn ($query) => $query
                    ->select('id', 'name', 'logo', 'cover_photo', 'rating', 'phone', 'reviews_section')
                    ->with('store_sub')
                    ->withCount('vehicle_identity as total_vehicles'),
            ])
            ->first();

        if (! $trip) {
            return null;
        }

        $this->withProDiscount($trip);
        $this->attachLicencePlates($trip);
        $this->attachIdentityReviews($trip);
        $this->attachProviderSummary($trip);

        return $trip;
    }

    public function getLastList(array $filters = []): Collection
    {
        $trips = app(TripsService::class)->query()->where('user_id', $filters['user_id'])
            ->where('is_guest', 0)
            ->NotHiddenForCustomer()
            ->where('trip_status', 'completed')
            ->when($filters['module_id'] ?? null, fn ($query, $id) => $query->where('module_id', $id))
            ->when($filters['provider_id'] ?? null, fn ($query, $id) => $query->where('provider_id', $id))
            ->with([
                'provider:id,name,logo,module_id,zone_id',
                'provider.storage',
                'trip_details:id,trip_id,vehicle_id,quantity',
                'trip_details.vehicle:id,name,thumbnail,provider_id',
                'trip_details.vehicle.storage',
                'proDiscount',
            ])
            ->latest()
            ->take(self::LAST_TRIP_LIMIT)
            ->get();

        $trips->each(fn ($trip) => $this->withProDiscount($trip));

        return $trips;
    }

    public function findReorderSource(mixed $tripId, array $context): ?Trips
    {
        return app(TripsService::class)->query()->where('id', $tripId)
            ->where('user_id', $context['user_id'])
            ->where('is_guest', $context['is_guest'])
            ->with(['trip_details', 'provider'])
            ->first();
    }

    public function cancel(Trips $trip, ?string $reason): void
    {
        $trip->trip_status = 'canceled';
        $trip->canceled_by = 'customer';
        $trip->cancellation_reason = $reason;
        $trip->canceled = now();
        $trip->save();

        foreach ($trip->trip_details as $detail) {
            if ($detail?->vehicle?->total_trip > 0) {
                $detail->vehicle->decrement('total_trip', $detail->quantity);
            }
        }
    }

    public function hideForCustomer(array $tripIds, array $context): int
    {
        return app(TripsService::class)->query()->whereIn('id', $tripIds)
            ->where(['user_id' => $context['user_id'], 'is_guest' => $context['is_guest']])
            ->whereIn('trip_status', ['completed', 'canceled'])
            ->update(['is_hidden' => 1]);
    }

    public function isPaid(Trips $trip): bool
    {
        return $trip->payment_status === 'paid';
    }

    public function findUnpaid(array $filters = []): ?Trips
    {
        $tripId = $filters['trip_id'] ?? null;

        $owner = ['user_id' => $filters['user_id'] ?? null, 'is_guest' => ($filters['is_guest'] ?? 0) === 0 ? 0 : 1];

        $trip = $tripId
            ? app(TripsService::class)->query()->where($owner + ['id' => $tripId])->first()
            : $this->findLatestUnpaid($owner);

        return $trip ? $this->loadUnpaidPaymentContext($trip) : null;
    }

    public function paymentMethodEnabled(Trips $trip, string $method): bool
    {
        $zone = $trip->relationLoaded('zone') ? $trip->zone : $trip->zone()->first();

        return match ($method) {
            'cash_payment' => (bool) data_get(Helpers::get_business_settings('cash_on_delivery'), 'status')
                && (bool) $zone?->cash_on_delivery,
            'digital_payment' => (bool) data_get(Helpers::get_business_settings('digital_payment'), 'status')
                && (bool) $zone?->digital_payment,
            'wallet' => (int) Helpers::get_business_settings('wallet_status') === 1,
            'partial_payment' => (int) Helpers::get_business_settings('partial_payment_status') === 1
                && (int) Helpers::get_business_settings('wallet_status') === 1,
            default => false,
        };
    }

    public function payWithCash(Trips $trip): bool
    {
        return $this->settleUnpaid($trip, fn (Trips $locked) => $locked->update([
            'payment_status' => 'paid',
            'payment_method' => 'cash_payment',
        ]));
    }

    public function payWithWallet(Trips $trip): bool
    {
        return $this->settleUnpaid($trip, function (Trips $locked) {
            if (! $this->recordWalletTransaction($locked->user_id, $locked->trip_amount, 'trip_booking', $locked->id)) {
                return false;
            }

            return $locked->update(['payment_status' => 'paid', 'payment_method' => 'wallet']);
        });
    }

    public function payPartially(Trips $trip, mixed $walletBalance, string $gateway): bool
    {
        return $this->settleUnpaid($trip, function (Trips $locked) use ($walletBalance, $gateway) {
            if ($locked->partialPayment()->exists()) {
                return true;
            }

            $paid = min($walletBalance, $locked->trip_amount);

            $locked->update(['partially_paid_amount' => $paid, 'payment_method' => 'partial_payment']);
            $this->recordPartialPayment($locked->id, $paid, 'wallet');
            $this->recordPartialPayment($locked->id, $locked->trip_amount - $paid, $gateway);

            return true;
        });
    }

    public function paymentLink(Trips $trip, mixed $customer, array $payment): ?string
    {
        return $this->paymentRedirectLink($customer, [
            'success_hook' => 'trip_payment_success',
            'failure_hook' => 'trip_payment_fail',
            'payment_method' => $payment['gateway'],
            'payment_platform' => $payment['platform'],
            'payer_id' => $trip->user_id,
            'payer_name' => data_get($customer, 'f_name', ''),
            'payer_email' => (string) data_get($customer, 'email', ''),
            'payer_phone' => (string) data_get($customer, 'phone', ''),
            'receiver_id' => 1,
            'receiver_name' => 'Admin',
            'amount' => $trip->trip_amount - $trip->partially_paid_amount,
            'callback' => $payment['callback'],
            'attribute' => 'trip_booking',
            'attribute_id' => $trip->id,
        ]);
    }

    public function guestContact(Trips $trip, mixed $userId): Collection
    {
        $info = $trip['user_info'];

        return collect([
            'id' => $userId,
            'f_name' => $info['contact_person_name'] ?? '',
            'l_name' => '',
            'phone' => $info['contact_person_number'] ?? '',
            'email' => $info['contact_person_email'] ?? '',
        ]);
    }

    public function ensureTransaction(Trips $trip): bool
    {
        if ($trip->trip_status !== 'completed' || $trip->payment_status !== 'paid' || $trip->trip_transaction) {
            return true;
        }

        return self::create_transaction($trip, 'vendor') !== false;
    }

    public function taxReport(array $filters = [], array $paginate = []): array
    {
        $orders = $this->taxOrderQuery($filters);
        $summary = $this->taxTotals($filters);

        return [
            'orders' => $orders->clone()->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate)),
            'taxSummary' => $this->taxBreakdown($filters, $orders),
            'totalOrders' => (int) $summary->total_orders,
            'totalOrderAmount' => (float) $summary->total_order_amount,
            'totalTax' => (float) $summary->total_tax,
        ];
    }

    private function validateBooking(array $input, $schedule_at, $user_data)
    {

        $longitude =  $user_data->pickup_location['lng'] ?? 0;
        $latitude = $user_data->pickup_location['lat'] ?? 0;

        $store = app(StoreService::class)->findWithOpenStateAt($input['provider_id'], $schedule_at);

        $zone_id = isset($store) ? [$store->zone_id] : $input['zone_ids'];

        $pickup_zone = app(ZoneService::class)->findContaining($zone_id, $latitude, $longitude);


        $response = match (true) {
            !$store => [
                'code' => 'provider',
                'message' =>  'provider_not_found',
                'status' => 403
            ],
            !$pickup_zone  => [
                'code' => 'Zone',
                'message' =>  'out_of_zone',
                'status' => 403
            ],
            in_array($store->store_business_model, ['unsubscribed', 'none']) || (in_array($store->store_business_model, ['subscription']) && $store?->store_sub == null) || (in_array($store->store_business_model, ['subscription']) && $store?->store_sub?->max_order != "unlimited" && $store?->store_sub?->max_order <= 0) => [
                'code' => 'provider',
                'message' =>  'Sorry_the_provider_is_unable_to_take_any_trip',
                'status' => 403
            ],
            ($input['scheduled'] ?? 0) == 1  && !$store->schedule_order => [
                'code' => 'schedule_at',
                'message' => 'schedule_trip_not_available',
                'status' => 403
            ],
            $store->open == false => [
                'code' => 'schedule_at',
                'message' => 'This provider isn\'t available at the selected time',
                'status' => 403
            ],
            ($input['scheduled'] ?? 0) == 1  && $schedule_at < now() => [
                'code' => 'trip_time',
                'message' =>  'you_can_not_schedule_a_trip_in_past',
                'status' => 403
            ],
            default => null
        };

        if ($response) {
            return ['code' => $response['code'], 'message' => translate($response['message']), 'status_code' => $response['status']];
        }

        return ['store' => $store, 'pickup_zone' => $pickup_zone];
    }

    private function buildTripDetails(array $input, $user_data, $carts, $schedule_at, $estimated_trip_end_time, $provider, $increment = true)
    {
        $price = 0;
        $discount_on_trip = 0;
        $quantity = 0;
        $details_data = [];
        $discount_on_trip_by = 'vendor';
        foreach ($carts as $cart) {

            $vehicle = app(VehicleService::class)->query()->where('id', $cart->vehicle_id)->active()
                ->withCount([
                    'vehicleIdentities as total_vehicle_count' => function ($query) use ($schedule_at) {
                        $query->DynamicVehicleQuantity($schedule_at);
                    },
                ])->first();


            $response = match (true) {
                !$vehicle => [
                    'code' => 'details_data',
                    'message' =>  'Vehicle_not_found',
                    'status' => 404
                ],
                !$cart->vehicle => [
                    'code' => 'details_data',
                    'message' =>  'Vehicle_not_found',
                    'status' => 404
                ],
                $cart->vehicle->status != 1 => [
                    'code' => 'details_data',
                    'message' =>  'Vehicle_is_unavailable',
                    'status' => 403
                ],
                $vehicle->total_vehicle_count <= 0 => [
                    'code' => 'details_data',
                    'message' =>  'This_Vehicle_is_not_available_on_this_pickup_time',
                    'status' => 403
                ],
                $vehicle->total_vehicle_count < $cart->quantity => [
                    'code' => 'details_data',
                    'message' =>  'Quantity_not_available',
                    'status' => 403
                ],

                default => null
            };

            if ($response) {
                return ['code' => $response['code'], 'message' => translate($response['message']), 'status_code' => $response['status']];
            }


            if ($user_data->rental_type == 'hourly') {
                $getPrice = $cart->vehicle->hourly_price *  $user_data->estimated_hours;
            } elseif ($user_data->rental_type == 'day_wise') {
                $getPrice = $cart->vehicle->day_wise_price * ((int) round($user_data->estimated_hours / 24));
                $getPrice = max($cart->vehicle->day_wise_price, $getPrice);
            } else {
                // §3.4 — convert the measured kilometres into the rate's unit before multiplying.
                $getPrice = $cart->vehicle->distance_price
                    * app(\App\Services\System\DistanceService::class)->chargeable($user_data->distance);
            }

            $discount_data = self::getDiscount(price: $getPrice, discount_type: $cart->vehicle->discount_type, discount: $cart->vehicle->discount_price);

            $trip_details_data = [
                'vehicle_id' => $cart->vehicle_id,
                'quantity' => $cart->quantity,
                'discount_on_trip_by' => 'vendor',
                'discount_percentage' => $cart->vehicle->discount_type == 'amount' ? 0 : $cart->vehicle->discount_price,

                'category_id' => $cart->vehicle->category_id,
                'price' => round($discount_data['price'], config('round_up_to_digit')) *  $cart->quantity,
                'original_price' => round($discount_data['price'], config('round_up_to_digit')),
                'calculated_price' => round($discount_data['price'], config('round_up_to_digit')) *  $cart->quantity,

                'discount_on_trip' => round($discount_data['discount'], config('round_up_to_digit')),
                'discount_type' => $cart->vehicle->discount_type,

                'tax_percentage' => 0,
                'tax_amount' => 0,
                'tax_status' => 'excluded',

                'vehicle_details' => json_encode($cart->vehicle),
                'rental_type' => $user_data->rental_type,
                'estimated_hours' => $user_data->estimated_hours,
                'distance' => $user_data->distance,
                'scheduled' => $input['scheduled'] ?? 0,
                'schedule_at' => $schedule_at,
                'estimated_trip_end_time' => $estimated_trip_end_time,

            ];
            if ($increment == true) {
                $cart->vehicle->increment('total_trip', $cart->quantity);
            }
            $details_data[] = $trip_details_data;

            $price += $trip_details_data['price'];
            $discount_on_trip += $trip_details_data['discount_on_trip'] * $cart->quantity;
            $quantity += $cart->quantity;
        }

        $discount = $discount_on_trip;
        $provider_discount = Helpers::get_store_discount($provider);
        if (isset($provider_discount)) {
            $admin_discount = self::checkAdminDiscount(price: $price, discount: $provider_discount['discount'], max_discount: $provider_discount['max_discount'], min_purchase: $provider_discount['min_purchase']);

            $discount = max($discount_on_trip, $admin_discount);

            if ($admin_discount > 0 &&  $discount == $admin_discount) {
                $discount_on_trip_by = 'admin';
                foreach ($details_data as $key => $trip_data) {
                    $details_data[$key]['discount_on_trip_by'] = $discount_on_trip_by;
                    $details_data[$key]['discount_type'] = 'precentage';
                    $details_data[$key]['discount_percentage'] = $provider_discount['discount'];
                    $details_data[$key]['discount_on_trip'] =  self::checkAdminDiscount(price: $price, discount: $provider_discount['discount'], max_discount: $provider_discount['max_discount'], min_purchase: $provider_discount['min_purchase'], vehicle_wise_price: $trip_data['price']);
                }
            }
        }

        if (count($details_data) > 0) {
            return ['details_data' => $details_data, 'price' => $price,  'discount' => $discount, 'quantity' => $quantity, 'discount_on_trip_by' => $discount_on_trip_by];
        }
        return ['code' => 'details_data', 'message' => translate('messages.Details data not found'), 'status_code' => 404];
    }

    private function persistTrip(array $input, $make_trip_data)
    {
        $trip = new Trips();
        $trip->user_id = $make_trip_data['user_id'];
        $trip->provider_id = $make_trip_data['provider']['id'];
        $trip->zone_id = $make_trip_data['provider']['zone_id'];
        $trip->pickup_zone_id = $make_trip_data['pickup_zone_id'];
        $trip->module_id =  $make_trip_data['provider']['module_id'];
        $trip->cash_back_id = $make_trip_data['cash_back_id'];
        $trip->trip_amount = $make_trip_data['trip_amount'];
        $trip->discount_on_trip = $make_trip_data['discount_on_trip'];
        $trip->discount_on_trip_by = $make_trip_data['discount_on_trip_by'];
        $trip->coupon_discount_amount = $make_trip_data['coupon_discount_amount'];
        $trip->coupon_discount_by = $make_trip_data['coupon_discount_by'];
        $trip->coupon_code = $make_trip_data['coupon_code'];
        $trip->trip_status = 'pending';
        $trip->payment_status = 'unpaid';
        $trip->tax_amount = $make_trip_data['tax_amount'];
        $trip->tax_status = $make_trip_data['tax_status'];
        $trip->tax_percentage = 0;
        $trip->trip_type = $input['trip_type'];
        $trip->additional_charge = $make_trip_data['additional_charge'];
        $trip->distance = $make_trip_data['distance'];
        $trip->estimated_hours = $make_trip_data['estimated_hours'];
        $trip->ref_bonus_amount = $make_trip_data['ref_bonus_amount'];
        $trip->trip_note = $input['additional_note'] ?? null;
        $trip->otp = rand(1000, 9999);
        $trip->is_guest = $make_trip_data['is_guest'];
        $trip->scheduled = $input['scheduled'] ?? 0;
        $trip->schedule_at = $make_trip_data['schedule_at'];
        $trip->quantity = $make_trip_data['quantity'];
        $trip->estimated_trip_end_time = $make_trip_data['estimated_trip_end_time'];
        $trip->destination_location = $make_trip_data['destination_location'];
        $trip->pickup_location = $make_trip_data['pickup_location'];
        $trip->pending = now();
        $trip->user_info = $make_trip_data['user_info'];
        $trip->save();
        return $trip;
    }

    private function recordReelSales($carts, $trip): void
    {
        try {
            $reelService = $this->reelEngagementService();

            if (!$reelService) {
                return;
            }

            foreach ($carts ?? [] as $cart) {
                $reelId = (int) ($cart->reel_id ?? 0);

                if ($reelId <= 0) {
                    continue;
                }

                $reelService->recordOrderSale(
                    reelId: $reelId,
                    amount: (float) ($cart->price ?? 0),
                    userId: $trip->is_guest ? null : (int) $trip->user_id,
                    guestId: $trip->is_guest ? (string) $trip->user_id : null,
                );
            }
        } catch (\Throwable $exception) {
            Log::error('Reel rental sale tracking failed', [
                'trip_id' => $trip->id ?? null,
                'error' => $exception->getMessage(),
            ]);
        }
    }


    private function recordCashBack($trip_amount, $user_id, $trip_id)
    {
        $cashBack = ($this->cashBackResolver)(amount: $trip_amount, customerId: $user_id, type: 1);
        if (data_get($cashBack, 'calculated_amount') > 0) {
            $CashBackHistory = new CashBackHistory();
            $CashBackHistory->user_id = $user_id;
            $CashBackHistory->trip_id = $trip_id;
            $CashBackHistory->calculated_amount = data_get($cashBack, 'calculated_amount');
            $CashBackHistory->cashback_amount = data_get($cashBack, 'cashback_amount');
            $CashBackHistory->cash_back_id = data_get($cashBack, 'id');
            $CashBackHistory->cashback_type = data_get($cashBack, 'cashback_type');
            $CashBackHistory->min_purchase = data_get($cashBack, 'min_purchase');
            $CashBackHistory->max_discount = data_get($cashBack, 'max_discount');
            $CashBackHistory->save();

            $CashBackHistory?->trip()->update([
                'cash_back_id' => $CashBackHistory->id
            ]);
        }
        return true;
    }

    private function calculateBookingTax($details_data, $additionalCharges, $totalDiscount, $price, $provider_id, $storeData = true)
    {

        $products = [];
        $tempList = [];
        $productDiscountTotal = 0;
        $totalAfterOwnDiscounts = 0;
        if (addon_published_status('TaxModule')) {

          foreach ($details_data as $item) {
                $item_id = $item['vehicle_id'] ;
                $itemWiseDiscount = $item['discount_on_trip_by'] === 'admin'  ? $item['discount_on_trip'] : $item['discount_on_trip']  * $item['quantity'];
                $productDiscountTotal += $itemWiseDiscount;
                $itemFinal = $item['price']  - $itemWiseDiscount;
                $tempList[] = [
                    'id' => $item_id,
                    'original_price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'category_id' => $item['category_id'],
                    'discount' => $item['discount_on_trip'],
                    'discount_on_trip_by' => $item['discount_on_trip_by'],
                    'base_final' => $itemFinal,
                ];

                $totalAfterOwnDiscounts += $itemFinal;

            }

            $otherDiscounts = $totalDiscount - $productDiscountTotal ;

            foreach ($tempList as $entry) {
                $share = ($entry['base_final'] / $totalAfterOwnDiscounts) * $otherDiscounts;
                $finalPrice = $entry['base_final'] - $share;
                    $products[] = [
                        'id' => $entry['id'],
                        'original_price' => $entry['original_price'],
                        'quantity' => $entry['quantity'],
                        'category_id' => $entry['category_id'],
                        'discount' => $entry['discount'],
                        'discount_on_trip_by' => $entry['discount_on_trip_by'],
                        'after_discount_final_price' => $finalPrice,
                    ];
            }

            $taxData =  CalculateTaxService::getCalculatedTax(
                amount: $price,
                productIds: $products,
                storeData: $storeData,
                additionalCharges: $additionalCharges,
                taxPayer: 'rental_provider',
                orderId: null,
                storeId: $provider_id
            );
            $tax_amount = $taxData['totalTaxamount'];
            $tax_included = $taxData['include'];
            $tax_status = $tax_included ?  'included' : 'excluded';


            foreach ($taxData['productWiseData'] ?? [] as $item) {
                $taxMap[$item['product_id']] = $item;
            }
        }

        return [
            'tax_amount' => $tax_amount ?? 0,
            'tax_included' => $tax_included ?? 0,
            'tax_status' => $tax_status ?? 'excluded',
            'taxMap' => $taxMap ?? [],
            'taxData' => $taxData ?? [],
        ];
    }


    private function couponContext(array $input, array $context): CouponContext
    {
        return new CouponContext(
            couponCode: $input['coupon_code'] ?? null,
            providerId: $input['provider_id'] ?? null,
            isGuest: $context['is_guest'],
            userId: $context['user_id'],
        );
    }

    private function attachLicencePlates(Trips $trip): void
    {
        $trip->trip_details->each(function ($detail) {
            $detail->license_plate_number = $detail->tripVehicleDetails
                ->pluck('vehicle_identity_data.license_plate_number')
                ->filter()
                ->values()
                ->toArray();
            unset($detail->tripVehicleDetails);
        });
    }

    private function attachIdentityReviews(Trips $trip): void
    {
        $trip->vehicle_identity->each(function ($identity) {
            $identity->license_plate_number = $identity?->vehicle_identity_data?->license_plate_number;
            $identity->vehicle_name = $identity?->vehicles?->name;
            $identity->vehicle_thumbnail = $identity?->vehicles?->thumbnail_full_url;

            $review = app(VehicleReviewService::class)->findForIdentityAndTrip($identity->vehicle_identity_data?->id, $identity->trip_id);

            $identity->rating = $review?->rating;
            $identity->comment = $review?->comment;
            $identity->reply = $review?->reply;
            $identity->replied_at = $review?->replied_at;
        });
    }

    private function attachProviderSummary(Trips $trip): void
    {
        if (! isset($trip['provider'])) {
            return;
        }

        $ratings = $this->calculateRating($trip['provider']['rating']);
        $trip['provider']['avg_rating'] = $ratings['rating'];
        $trip['provider']['rating_count'] = $ratings['total'];
        $trip['provider']['verified_seller'] = Helpers::get_verified_seller_status($trip['provider'], $trip['provider']?->storeConfig);

        if (isset($trip['provider']->store_sub)) {
            $trip['provider']['chat'] = $trip?->provider?->store_sub?->chat ?? 0;
            $trip['provider']['reviews_section'] = $trip?->provider?->store_sub?->review ?? $trip['provider']['reviews_section'] ?? 0;
            unset($trip['provider']->store_sub);

            return;
        }

        $trip['provider']['chat'] = 1;
        $trip['provider']['reviews_section'] = $trip['provider']['reviews_section'] ?? 0;
    }

    private function matchTripId(mixed $group, string $search): void
    {
        foreach (array_filter(explode(' ', $search), fn ($token) => $token !== '') as $token) {
            $group->orWhere('id', 'LIKE', '%' . $token . '%');
        }
    }

    private function settleUnpaid(Trips $trip, callable $settle): bool
    {
        return (bool) DB::transaction(function () use ($trip, $settle) {
            $locked = app(TripsService::class)->query()->whereKey($trip->getKey())->lockForUpdate()->first();

            if (! $locked || $locked->payment_status === 'paid') {
                return false;
            }

            $settled = $settle($locked);

            if ($settled !== false) {
                $this->restoreStatusAfterPaymentFailure($locked);
                $trip->setRawAttributes($locked->getAttributes(), true);
            }

            return $settled !== false;
        });
    }

    private function restoreStatusAfterPaymentFailure(Trips $trip): void
    {
        if ($trip->trip_status !== 'payment_failed') {
            return;
        }

        $trip->update(['trip_status' => $trip->statusBeforePaymentFailure()]);
    }

    private function findLatestUnpaid(array $owner): ?Trips
    {
        return app(TripsService::class)->query()
            ->where($owner)
            ->where('created_at', '>=', now()->subMonths(self::UNPAID_WINDOW_MONTHS))
            ->whereIn('trip_status', self::UNPAID_STATUSES)
            ->where('payment_status', '!=', 'paid')
            ->where(fn ($query) => $this->whereMethodNotSettled($query, self::SETTLED_METHODS))
            ->where(function ($query) {
                $query->where(function ($partial) {
                    $partial->where('payment_method', 'partial_payment')
                        ->whereHas('partialPayment', fn ($payment) => $payment->where('payment_status', 'unpaid')
                            ->whereNotIn('payment_method', self::SETTLED_METHODS));
                })
                    ->orWhere(fn ($digital) => $this->whereMethodNotSettled($digital, [...self::SETTLED_METHODS, 'partial_payment']));
            })
            ->latest()
            ->first();
    }

    private function whereMethodNotSettled(mixed $query, array $methods): mixed
    {
        return $query->where(fn ($method) => $method->whereNull('payment_method')
            ->orWhereNotIn('payment_method', $methods));
    }

    private function loadUnpaidPaymentContext(Trips $trip): Trips
    {
        $trip->load([
            'zone' => fn ($query) => $query->select('id', 'cash_on_delivery', 'digital_payment', 'offline_payment'),
            'module' => fn ($query) => $query->withoutGlobalScope('translate')->select('id', 'module_type')
                ->with(['zones' => fn ($zone) => $zone->select('zones.id')->where('zones.id', $trip->zone_id)]),
        ]);

        return $trip;
    }

    private function recordPartialPayment(mixed $tripId, mixed $amount, ?string $method): void
    {
        $payment = new PartialPayment();
        $payment->trip_id = $tripId;
        $payment->amount = $amount;
        $payment->payment_status = 'unpaid';
        $payment->payment_method = $method;
        $payment->save();
    }

    private function withProDiscount(Trips $trip): Trips
    {
        foreach (Helpers::pro_discount_data($trip) as $key => $value) {
            $trip->{$key} = $value;
        }
        $trip->unsetRelation('proDiscount');

        return $trip;
    }

    private function completeTrip(Trips $trip): void
    {
        app(TripVehicleDetailsService::class)->markCompletedForTrip($trip->id);

        if (! $trip->user_id) {
            return;
        }

        foreach ($trip->trip_details as $detail) {
            if ($detail->vehicle_id) {
                $this->recordVehicleAction(
                    (int) $trip->user_id,
                    (int) $detail->vehicle_id,
                    'order',
                    $trip->module_id ? (int) $trip->module_id : null
                );
            }
        }
    }


    private function taxOrderQuery(array $filters): Builder
    {
        return app(TripsService::class)->query()->when(addon_published_status('TaxModule'), fn ($query) => $query->with([
            'orderTaxes' => fn (MorphMany $relation) => $relation->where('order_type', Trips::class)
                ->select('id', 'order_id', 'tax_name', 'tax_amount', 'tax_type'),
        ]))
            ->where('provider_id', $filters['provider_id'] ?? null)
            ->whereIn('trip_status', self::TAX_STATUSES)
            ->tap(fn ($query) => $this->applyTaxSearch($query, $filters, 'id'))
            ->tap(fn ($query) => $this->applyTaxPeriod($query, $filters, 'created_at'))
            ->select(self::TAX_ORDER_COLUMNS)
            ->latest('created_at');
    }

    private function taxTotals(array $filters): object
    {
        return DB::table('trips')
            ->where('provider_id', $filters['provider_id'] ?? null)
            ->whereIn('trip_status', self::TAX_STATUSES)
            ->tap(fn ($query) => $this->applyTaxPeriod($query, $filters, 'created_at'))
            ->tap(fn ($query) => $this->applyTaxSearch($query, $filters, 'id'))
            ->selectRaw('COUNT(*) as total_orders, SUM(trip_amount) as total_order_amount, SUM(tax_amount) as total_tax')
            ->first();
    }

    private function taxBreakdown(array $filters, Builder $orders): Collection
    {
        return DB::table('order_taxes')
            ->select('tax_name', DB::raw('SUM(tax_amount) as total_tax'), DB::raw('CONCAT(tax_rate) as tax_label'))
            ->where('order_type', Trips::class)
            ->where('store_id', $filters['provider_id'] ?? null)
            ->tap(fn ($query) => $this->applyTaxSearch($query, $filters, 'order_id'))
            ->whereIn('order_id', $orders->clone()->reorder()->select('trips.id'))
            ->tap(fn ($query) => $this->applyTaxPeriod($query, $filters, 'created_at'))
            ->groupBy('tax_name', 'tax_rate')
            ->get();
    }

    private function applyTaxSearch(mixed $query, array $filters, string $column): void
    {
        $tokens = array_filter(explode(' ', (string) ($filters['search'] ?? '')), fn ($token) => $token !== '');

        $query->when($tokens, fn ($builder) => $builder->where(function ($group) use ($tokens, $column) {
            foreach ($tokens as $token) {
                $group->orWhere($column, 'like', "%{$token}%");
            }
        }));
    }

    private function applyTaxPeriod(mixed $query, array $filters, string $column): void
    {
        $query->when(
            ($filters['from'] ?? null) && ($filters['to'] ?? null),
            fn ($builder) => $builder->whereBetween($column, [$filters['from'], $filters['to']])
        );
    }
}
