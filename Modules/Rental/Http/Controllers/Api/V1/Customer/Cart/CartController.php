<?php

namespace Modules\Rental\Http\Controllers\Api\V1\Customer\Cart;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\Zone\ZoneService;
use Illuminate\Http\JsonResponse;
use Modules\Rental\Http\Requests\Customer\Cart\CartListRequest;
use Modules\Rental\Http\Requests\Customer\Cart\CartRemoveManyRequest;
use Modules\Rental\Http\Requests\Customer\Cart\CartStoreRequest;
use Modules\Rental\Http\Requests\Customer\Cart\CartUpdateRequest;
use Modules\Rental\Http\Requests\Customer\Cart\CartUserDataRequest;
use Modules\Rental\Services\Cart\RentalCartService;

class CartController extends BaseApiController
{

    private const ITEM_ERROR = 'cart_item';

    public function __construct(
        private readonly RentalCartService $rentalCartService,
        private readonly ZoneService $zoneService
    ) {
    }

    public function index(CartListRequest $request): JsonResponse
    {
        $context = $request->context();

        if (! $this->rentalCartService->findUserData($context)) {
            return $this->responseFormatter(config('response.default_200'), ['carts' => [], 'user_data' => []]);
        }

        return $this->cartResponse($context, $request->inputs());
    }

    public function store(CartStoreRequest $request): JsonResponse
    {
        $context = $request->context();
        $inputs = $request->inputs();
        $userData = $this->rentalCartService->findUserData($context);
        $pickupTime = $this->rentalCartService->resolvePickupTime($inputs, $userData);
        $vehicle = $this->rentalCartService->findAvailableVehicle($request->input('vehicle_id'), $pickupTime);

        $rejection = $this->rentalCartService->additionRejection(
            $vehicle,
            ['vehicle_id' => $request->input('vehicle_id'), 'quantity' => $request->quantity(), 'rental_type' => $request->input('rental_type')],
            $context,
            $this->pickupZones($context, $inputs),
            $userData,
            $pickupTime
        );

        if ($rejection) {
            return $this->itemError($rejection['message']);
        }

        $this->rentalCartService->add([
            'vehicle_id' => $request->input('vehicle_id'),
            'provider_id' => $vehicle->provider_id,
            'quantity' => $request->quantity(),
            'reel_id' => $request->reelId(),
            'price' => $this->rentalCartService->calculatePrice(
                $vehicle,
                $request->input('rental_type'),
                $request->input('estimated_hours'),
                $request->input('distance')
            ),
        ], $context);

        return $this->cartResponse($context, $inputs);
    }

    public function update(CartUpdateRequest $request): JsonResponse
    {
        $context = $request->context();
        $inputs = $request->inputs();
        $cart = $this->rentalCartService->findLine($request->input('cart_id'), $context);

        if (! $cart) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'cart');
        }

        $userData = $this->rentalCartService->findUserData($context);

        if (! $userData) {
            return $this->itemError(translate('messages.Cart data not found'));
        }

        $pickupTime = $this->rentalCartService->resolvePickupTime($inputs, $userData);
        $vehicle = $this->rentalCartService->findAvailableVehicle($cart->vehicle_id, $pickupTime);

        if (! $vehicle) {
            return $this->itemError(translate('messages.Vehicle currently unavailable!'));
        }

        if ($vehicle->total_vehicle_count <= 0) {
            return $this->itemError(translate('This vehicle is not available on this pickup time'));
        }

        if ($vehicle->total_vehicle_count < $request->input('quantity')) {
            return $this->itemError(translate('messages.Max vehicle available quantity is') . ' ' . $vehicle->total_vehicle_count);
        }

        $this->rentalCartService->updateLine($cart, [
            'quantity' => $request->input('quantity'),
            'price' => $this->rentalCartService->calculatePrice(
                $cart->vehicle,
                $userData->rental_type,
                $userData->estimated_hours,
                $userData->distance
            ),
        ], $context);

        return $this->cartResponse($context, $inputs);
    }

    public function updateUserData(CartUserDataRequest $request): JsonResponse
    {
        $context = $request->context();
        $inputs = $request->inputs();
        $userData = $this->rentalCartService->findUserData($context);
        $pickupTime = $this->rentalCartService->resolvePickupTime($inputs, $userData);

        if ($error = $this->userDataError($request, $context, $userData, $pickupTime)) {
            return $error;
        }

        return $this->cartResponse($context, $inputs);
    }

    public function destroy(CartListRequest $request, mixed $cartId): JsonResponse
    {
        $context = $request->context();
        $this->rentalCartService->removeLine($cartId, $context);

        return $this->cartResponse($context, $request->inputs());
    }

    public function destroyMany(CartRemoveManyRequest $request): JsonResponse
    {
        $context = $request->context();
        $this->rentalCartService->removeLines($request->cartIds(), $context);

        return $this->cartResponse($context, $request->inputs());
    }

    public function clear(CartListRequest $request): JsonResponse
    {
        $this->rentalCartService->clear($request->context());

        return $this->responseFormatter(config('response.default_200'), [
            'carts' => [],
            'user_data' => [],
            'status' => 'success',
        ]);
    }

    private function cartResponse(array $context, array $inputs): JsonResponse
    {
        $cart = $this->rentalCartService->refresh($context, $inputs, $this->pickupZones($context, $inputs));

        return $this->responseFormatter(config('response.default_200'), [
            'carts' => $cart['carts'],
            'user_data' => $cart['user_data'],
        ]);
    }


    private function userDataError(CartUserDataRequest $request, array $context, mixed $userData, mixed $pickupTime): ?JsonResponse
    {
        $unsupported = [];
        $reason = null;

        foreach ($this->rentalCartService->linesWithVehicles($context) as $line) {
            $store = $this->rentalCartService->findOpenProvider($line->provider_id, $pickupTime);

            if (! $store || ! $store->open) {
                return $this->itemError(translate('messages.Provider is closed at trip time'));
            }

            $rejection = $userData?->rental_type !== $request->input('rental_type')
                ? $this->rentalCartService->unsupportedRentalType($line->vehicle, $request->input('rental_type'))
                : null;

            if ($rejection) {
                $unsupported[] = $line->id;
                $reason ??= trim(($line->vehicle?->name ? $line->vehicle->name . ': ' : '') . translate($rejection));
            }
        }

        return $unsupported
            ? $this->responseFormatter(
                config('response.forbidden_403'),
                $unsupported,
                [['code' => self::ITEM_ERROR, 'message' => $reason]]
            )
            : null;
    }


    private function pickupZones(array $context, array $inputs): array
    {
        $location = $inputs['pickup_location']
            ?: $this->rentalCartService->findUserData($context)?->pickup_location;

        return $this->zoneService->findIdsByCoordinates(data_get($location, 'lat'), data_get($location, 'lng'));
    }

    private function itemError(string $message): JsonResponse
    {
        return $this->errorResponse(config('response.forbidden_403'), $message, self::ITEM_ERROR);
    }
}
