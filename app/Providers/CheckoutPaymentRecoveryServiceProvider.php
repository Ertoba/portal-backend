<?php

namespace App\Providers;

use App\Http\Controllers\Api\V1\CheckoutPaymentRecoveryController;
use App\Http\Middleware\CheckoutOrderLifecycleMiddleware;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CheckoutPaymentRecoveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Services are resolved through Laravel's container.
    }

    public function boot(Router $router): void
    {
        // The middleware is appended to the existing API group, but it exits
        // immediately for every route except the 6amMart customer order place API.
        $router->pushMiddlewareToGroup('api', CheckoutOrderLifecycleMiddleware::class);

        Route::middleware(['api', 'localization', 'apiGuestCheck'])
            ->prefix('api/v1/customer/order-payment')
            ->group(function () {
                Route::get('recoverable', [CheckoutPaymentRecoveryController::class, 'recoverable']);
                Route::get('{order}/status', [CheckoutPaymentRecoveryController::class, 'status'])
                    ->whereNumber('order');
                Route::post('{order}/prepare-retry', [CheckoutPaymentRecoveryController::class, 'prepareRetry'])
                    ->whereNumber('order');
                Route::post('{order}/switch-to-cod', [CheckoutPaymentRecoveryController::class, 'switchToCod'])
                    ->whereNumber('order');
            });
    }
}
