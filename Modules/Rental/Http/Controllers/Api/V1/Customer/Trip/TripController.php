<?php

namespace Modules\Rental\Http\Controllers\Api\V1\Customer\Trip;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Api\BaseApiController;
use App\Services\Marketing\CashBackService;
use App\Services\Zone\ZoneService;
use App\Traits\Api\ApiRequestContextTrait;
use App\Traits\Payment\ProCustomerSubscriptionTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Rental\Exceptions\BookingRejected;
use Modules\Rental\Http\Requests\Customer\Trip\TripBookingRequest;
use Modules\Rental\Http\Requests\Customer\Trip\TripCancelRequest;
use Modules\Rental\Http\Requests\Customer\Trip\TripDeleteRequest;
use Modules\Rental\Http\Requests\Customer\Trip\TripListRequest;
use Modules\Rental\Http\Requests\Customer\Trip\TripPaymentRequest;
use Modules\Rental\Http\Requests\Customer\Trip\TripReorderRequest;
use Modules\Rental\Http\Requests\Customer\Trip\TripTargetRequest;
use Modules\Rental\Http\Requests\Customer\Trip\TripTaxRequest;
use Modules\Rental\Http\Resources\Customer\Trip\TripSummaryResource;
use Modules\Rental\Http\Resources\Customer\Trip\TripResource;
use Modules\Rental\Services\Cart\RentalCartService;
use Modules\Rental\Services\Trip\TripService;

class TripController extends BaseApiController
{
    use ApiRequestContextTrait, ProCustomerSubscriptionTrait;

    public function __construct(
        private readonly TripService $tripService,
        private readonly RentalCartService $rentalCartService,
        private readonly CashBackService $cashBackService,
        private readonly ZoneService $zoneService
    ) {
        $this->tripService->useCashBackResolver(
            fn (mixed $amount, mixed $customerId, mixed $type) => $this->cashBackService
                ->calculateForCustomer(amount: $amount, customerId: $customerId, type: $type)
        );
    }

    public function store(TripBookingRequest $request): JsonResponse
    {
        try {
            $result = $this->tripService->book($request->bookingInput(), $this->bookingContext($request));
        } catch (BookingRejected $rejected) {
            return $this->rejection($rejected->rejection());
        }

        if (! isset($result['trip'])) {
            return $this->rejection($result);
        }

        $this->tripService->notifyTripPlaced($result['trip']);

        return $this->responseFormatter(config('response.default_store_201'), ['trip_id' => $result['trip']->id]);
    }

    public function index(TripListRequest $request, string $type): JsonResponse
    {
        $trips = $this->tripService->getCustomerList($request->filters($type), $this->pageParams($request));

        return $this->pagedResponse($trips, TripResource::class);
    }

    public function show(TripTargetRequest $request): JsonResponse
    {
        $trip = $this->tripService->findCustomerDetail($request->tripId(), $request->context());

        return $trip
            ? $this->responseFormatter(config('response.default_200'), new TripResource($trip))
            : $this->notFound();
    }

    public function lastTrips(Request $request): JsonResponse
    {
        if (! auth('api')->id()) {
            return $this->responseFormatter(config('response.default_200'), []);
        }

        $trips = $this->tripService->getLastList([
            'user_id' => auth('api')->id(),
            'module_id' => $this->headerModuleId($request),
            'provider_id' => $request->input('provider_id'),
        ]);

        return $this->responseFormatter(config('response.default_200'), TripSummaryResource::collection($trips));
    }

    public function cancel(TripCancelRequest $request): JsonResponse
    {
        $trip = $this->tripService->findForCustomer($request->tripId(), $request->context(), ['trip_details.vehicle']);

        if (! $trip) {
            return $this->notFound();
        }

        if ($trip->trip_status !== 'pending') {
            return $this->errorResponse(config('response.forbidden_403'), translate('You can not cancel this trip'), 'trip');
        }

        $this->tripService->cancel($trip, $request->input('cancellation_reason'));

        return $this->responseFormatter(config('response.default_update_200'));
    }

    public function destroy(TripDeleteRequest $request): JsonResponse
    {
        $hidden = $this->tripService->hideForCustomer($request->tripIds(), $request->context());

        return $hidden
            ? $this->responseFormatter(config('response.default_delete_200'))
            : $this->notFound();
    }

    public function pay(TripPaymentRequest $request): JsonResponse
    {
        $context = $request->context();
        $trip = $this->tripService->findForCustomer($request->tripId(), $context, ['zone']);

        if (! $trip) {
            return $this->notFound();
        }

        $method = $request->input('payment_method');

        if ($context['is_guest'] && in_array($method, ['wallet', 'partial_payment'], true)) {
            return $this->paymentError(translate('This payment method is not available for guest users'));
        }

        if ($this->tripService->isPaid($trip)) {
            return $this->paymentError(translate('This trip is already paid'));
        }

        if ($trip->trip_status === 'canceled') {
            return $this->paymentError(translate('This trip has been canceled'));
        }

        if (! $this->tripService->paymentMethodEnabled($trip, $method)) {
            return $this->paymentError(translate('This payment method is currently unavailable'));
        }

        $customer = $request->tripUser() ?: $this->tripService->guestContact($trip, $context['user_id']);

        return $this->settle($request, $trip, $customer);
    }

    public function tax(TripTaxRequest $request): JsonResponse
    {
        $result = $this->tripService->estimateTax($request->bookingInput(), $request->context());

        return isset($result['tax_amount'])
            ? $this->responseFormatter(config('response.default_200'), $result)
            : $this->rejection($result);
    }

    public function reorder(TripReorderRequest $request): JsonResponse
    {
        if (! Helpers::get_business_settings('repeat_order_option')) {
            return $this->errorResponse(
                config('response.forbidden_403'),
                translate('Repeat order is currently disabled.'),
                'feature_disabled'
            );
        }

        $context = ['user_id' => auth('api')->id(), 'is_guest' => 0, 'module_id' => null];
        $trip = $this->tripService->findReorderSource($request->tripId(), $context);

        if (! $trip) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'not_found');
        }

        $context['module_id'] = (int) $trip->module_id;
        $result = $this->rentalCartService->rebuildFromTrip($trip, $context, $this->zoneService->findIdsByCoordinates(
            data_get($trip->pickup_location, 'lat'),
            data_get($trip->pickup_location, 'lng')
        ));

        return $this->responseFormatter(['message' => 'Items added to cart'] + config('response.default_200'), $result);
    }

    private function settle(TripPaymentRequest $request, mixed $trip, mixed $customer): JsonResponse
    {
        $gateway = $request->gatewayPayload();

        switch ($request->input('payment_method')) {
            case 'cash_payment':
                if (! $this->tripService->payWithCash($trip)) {
                    return $this->paymentError(translate('Payment could not be completed'));
                }
                break;

            case 'digital_payment':
                return $this->paymentRedirect($trip, $customer, $gateway);

            case 'wallet':
                if ($customer->wallet_balance < $trip->trip_amount) {
                    return $this->paymentError(translate('Insufficient balance'));
                }
                if (! $this->tripService->payWithWallet($trip)) {
                    return $this->paymentError(translate('Wallet payment could not be completed'));
                }
                break;

            case 'partial_payment':
                if ($customer->wallet_balance >= $trip->trip_amount) {
                    return $this->paymentError(translate('Trip amount must be greater than wallet amount'));
                }
                if ($customer->wallet_balance <= 0) {
                    return $this->paymentError(translate('Insufficient balance for partial amount'));
                }
                if (! $this->tripService->payPartially($trip, $customer->wallet_balance, $gateway['gateway'])) {
                    return $this->paymentError(translate('Payment could not be completed'));
                }

                if ($gateway['gateway'] !== 'cash_payment') {
                    return $this->paymentRedirect($trip, $customer, $gateway);
                }
                break;

            default:
                return $this->paymentError(translate('Something went wrong'));
        }

        if (! $this->tripService->ensureTransaction($trip)) {
            return $this->paymentError(translate('Failed to create transaction'));
        }

        return $this->paymentSuccess('Payment successful');
    }

    private function bookingContext(TripBookingRequest $request): array
    {
        return $request->context() + [
            'user_info' => $request->contactInfo(),
            'pro_apply' => fn ($subtotal, $total) => $this->applyProCustomerDiscount(
                userId: (int) $request->context()['user_id'],
                subtotal: (float) $subtotal,
                totalPrice: (float) $total,
                moduleType: 'rental',
            ),
            'pro_record' => function ($tripId, $offer, $discount) use ($request) {
                $this->recordOrderProDiscount(
                    tripId: (int) $tripId,
                    userId: (int) $request->context()['user_id'],
                    proOffer: $offer,
                    amountSaved: (float) $discount,
                    moduleType: 'rental',
                );
                $this->getProCustomerOffer(userId: $request->context()['user_id'], incrementCount: true);
            },
        ];
    }

    private function rejection(array $rejection): JsonResponse
    {
        return $this->errorResponse(
            $this->statusConfig((int) ($rejection['status_code'] ?? 403)),
            $rejection['message'] ?? translate('messages.Something went wrong'),
            $rejection['code'] ?? 'trip'
        );
    }

    private function paymentRedirect(mixed $trip, mixed $customer, array $gateway): JsonResponse
    {
        return $this->responseFormatter(config('response.default_200'), [
            'redirect_link' => $this->tripService->paymentLink($trip, $customer, $gateway),
        ]);
    }

    private function paymentSuccess(string $message): JsonResponse
    {
        return $this->responseFormatter(['message' => $message] + config('response.default_200'));
    }

    private function paymentError(string $message): JsonResponse
    {
        return $this->errorResponse(config('response.forbidden_403'), $message, 'payment');
    }

    private function notFound(): JsonResponse
    {
        return $this->errorResponse(config('response.default_404'), translate('No data found'), 'trip');
    }
}
