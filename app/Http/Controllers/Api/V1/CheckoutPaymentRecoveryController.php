<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CheckoutPaymentRecoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutPaymentRecoveryController extends Controller
{
    public function __construct(
        private readonly CheckoutPaymentRecoveryService $recoveryService
    ) {
    }

    public function recoverable(Request $request): JsonResponse
    {
        return $this->recoveryService->recoverable($request);
    }

    public function status(Request $request, int $order): JsonResponse
    {
        return $this->recoveryService->status($request, $order);
    }

    public function prepareRetry(Request $request, int $order): JsonResponse
    {
        return $this->recoveryService->prepareRetry($request, $order);
    }

    public function switchToCod(Request $request, int $order): JsonResponse
    {
        return $this->recoveryService->switchToCod($request, $order);
    }
}
