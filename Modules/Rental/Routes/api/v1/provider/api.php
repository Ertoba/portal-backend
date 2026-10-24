<?php

use Modules\Rental\Http\Controllers\Api\V1\Vendor\Promotion\BannerController;
use Modules\Rental\Http\Controllers\Api\V1\Customer\Promotion\BannerController as RentalBannerController;
use Modules\Rental\Http\Controllers\Api\V1\Vendor\Promotion\CouponController;
use Modules\Rental\Http\Controllers\Api\V1\Customer\Promotion\CouponController as RentalCouponController;
use Modules\Rental\Http\Controllers\Api\V1\Vendor\Profile\ProfileController;
use Modules\Rental\Http\Controllers\Api\V1\Vendor\Store\ScheduleController;
use Modules\Rental\Http\Controllers\Api\V1\Vendor\Vehicle\BrandController;
use Modules\Rental\Http\Controllers\Api\V1\Vendor\Vehicle\CategoryController;
use Modules\Rental\Http\Controllers\Api\V1\Customer\Store\ProviderController as RentalProviderController;
use Modules\Rental\Http\Controllers\Api\V1\Customer\Store\ProviderReviewController;
use Modules\Rental\Http\Controllers\Api\V1\Vendor\Report\EarningReportController;
use Modules\Rental\Http\Controllers\Api\V1\Vendor\Report\TaxReportController;
use Modules\Rental\Http\Controllers\Api\V1\Vendor\Chat\ConversationController;
use Modules\Rental\Http\Controllers\Api\V1\Vendor\Store\BusinessSetupController;
use Modules\Rental\Http\Controllers\Api\V1\Customer\Vehicle\BrandController as RentalBrandController;
use Modules\Rental\Http\Controllers\Api\V1\Customer\Vehicle\CategoryController as RentalCategoryController;
use Modules\Rental\Http\Controllers\Api\V1\Customer\Vehicle\VehicleController as RentalVehicleController;
use Modules\Rental\Http\Controllers\Api\V1\Customer\Vehicle\ReviewController as VehicleReviewController;
use Modules\Rental\Http\Controllers\Api\V1\Vendor\Vehicle\DriverController;
use Modules\Rental\Http\Controllers\Api\V1\Vendor\Ai\VehicleAiController;
use Modules\Rental\Http\Controllers\Api\V1\Vendor\Vehicle\VehicleController;
use Modules\Rental\Http\Controllers\Api\V1\Customer\Wishlist\WishlistController as RentalWishlistController;
use Modules\Rental\Http\Controllers\Api\V1\Customer\Cart\CartController;
use Modules\Rental\Http\Controllers\Api\V1\Vendor\Trip\TripController as ProviderTripController;
use Modules\Rental\Http\Controllers\Api\V1\Customer\Trip\TripController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::middleware(['localization'])->prefix('rental')->name('rental.')->group(function () {
    Route::middleware(['vendor.api'])->prefix('vendor')->group(function () {
        Route::prefix('driver')->name('driver.')->group(function () {
            Route::controller(DriverController::class)->group(function () {
                Route::get('list', 'index');
                Route::post('create', 'store');
                Route::post('update/{id}', 'update');
                Route::get('details/{id}', 'show');
                Route::get('status/{id}', 'updateStatus');
                Route::delete('delete/{id}', 'destroy');
            });
        });
        Route::prefix('vehicle')->name('vehicle.')->group(function () {
            Route::controller(VehicleController::class)->group(function () {
                Route::get('list', 'index');
                Route::get('edit/{id}', 'edit');
                Route::post('create', 'store');
                Route::post('update/{id}', 'update');
                Route::get('details/{id}', 'show');
                Route::get('status/{id}', 'updateStatus');
                Route::get('new-tag/{id}', 'updateNewTag');
                Route::delete('delete/{id}', 'destroy');
                Route::get('reviews', 'reviews');
                Route::put('reply-update', 'updateReply');
            });
            Route::prefix('ai')->name('ai.')->group(function () {
                Route::controller(VehicleAiController::class)->group(function () {
                    Route::get('generate-title-and-description', 'titleAndDescription');
                    Route::get('generate-other-data', 'otherData');
                    Route::get('generate-title-suggestions', 'titleSuggestions');
                    Route::post('generate-form-image', 'imageAnalysis');
                });
            });
        });
        Route::prefix('banner')->name('vehicle.')->group(function () {
            Route::controller(BannerController::class)->group(function () {
                Route::get('list', 'index');
                Route::get('edit/{id}', 'show');
                Route::post('create', 'store');
                Route::post('update/{id}', 'update');
                Route::get('status/{id}', 'updateStatus');
                Route::get('featured/{id}', 'updateFeatured');
                Route::delete('delete/{id}', 'destroy');
            });
        });
        Route::prefix('coupon')->name('coupon.')->group(function () {
            Route::controller(CouponController::class)->group(function () {
                Route::get('list', 'index');
                Route::post('create', 'store');
                Route::get('edit/{id}', 'show');
                Route::post('update/{id}', 'update');
                Route::get('status/{id}', 'updateStatus');
                Route::delete('delete/{id}', 'destroy');
            });
        });
        Route::prefix('profile')->name('profile.')->group(function () {
            Route::controller(ProfileController::class)->group(function () {
                Route::get('/', 'show');
                Route::post('update', 'update');
            });
        });
        Route::prefix('schedule')->name('schedule.')->group(function () {
            Route::controller(ScheduleController::class)->group(function () {
                Route::post('/create', 'store');
                Route::delete('delete/{id}', 'destroy');
            });
        });
        Route::prefix('message')->name('message.')->group(function () {
            Route::controller(ConversationController::class)->group(function () {
                Route::get('list', 'index');
                Route::get('search-list', 'search');
                Route::get('details', 'show');
                Route::post('send', 'store');
            });
        });
        Route::prefix('trip')->name('trip.')->group(function () {
            Route::controller(ProviderTripController::class)->group(function () {
                Route::get('list/{all}', 'index');
                Route::get('details', 'show');
                Route::get('status', 'updateStatus');
                Route::get('payment', 'updatePaymentStatus');
                Route::put('assign-vehicle', 'assignVehicle');
                Route::put('assign-driver', 'assignDriver');
                Route::put('edit-trip', 'edit');
            });
        });
        Route::controller(EarningReportController::class)->group(function () {
            Route::get('get-earning-report', 'index');
        });
        Route::controller(TaxReportController::class)->group(function () {
            Route::get('get-tax-report', 'index')->name('providerTax');
        });
        Route::controller(CategoryController::class)->group(function () {
            Route::get('category/list', 'index');
        });
        Route::controller(BrandController::class)->group(function () {
            Route::get('brand/list', 'index');
        });
        Route::controller(BusinessSetupController::class)->group(function () {
            Route::post('update-business-setup', 'update');
        });
    });
    Route::middleware('module-check')->group(function () {
        Route::controller(RentalCouponController::class)->group(function () {
            Route::get('coupon/list/all', 'index');
        });
        Route::middleware('auth:api')->prefix('coupon')->group(function () {
            Route::controller(RentalCouponController::class)->group(function () {
                Route::get('list', 'index');
                Route::get('apply', 'apply');
            });
        });
        Route::prefix('banners')->group(function () {
            Route::controller(RentalBannerController::class)->group(function () {
                Route::get('/', 'index');
                Route::get('/{store_id}', 'listForStore');
            });
        });
        Route::prefix('vehicle')->group(function () {
            Route::controller(RentalVehicleController::class)->group(function () {
                Route::get('top-rated/', 'topRated');
                Route::get('search/', 'search');
                Route::get('search/suggestion', 'suggestion');
                Route::get('get-provider-vehicles', 'providerVehicles');
                Route::get('get-vehicle-details/{vehicle}', 'show');
            });
            Route::get('popular-suggestion/', [RentalBrandController::class, 'popular']);
            Route::get('reviews/{id}', [VehicleReviewController::class, 'index']);
            Route::controller(RentalCategoryController::class)->group(function () {
                Route::get('category-list/', 'index');
            });
            Route::controller(RentalBrandController::class)->group(function () {
                Route::get('brand-list/', 'index');
            });
        });
        Route::prefix('provider')->group(function () {
            Route::controller(RentalProviderController::class)->group(function () {
                Route::get('get-provider-details/{provider}', 'show');
                Route::get('get-provider-reviews/{provider}', [ProviderReviewController::class, 'index']);
                Route::get('popular', 'popular');
                Route::get('latest', 'latest');
                Route::get('verified', 'verified');
            });
        });
        Route::middleware('apiGuestCheck')->prefix('user')->group(function () {
            Route::prefix('cart')->group(function () {
                Route::controller(CartController::class)->group(function () {
                    Route::get('get-cart', 'index');
                    Route::post('add-to-cart', 'store');
                    Route::put('update-cart', 'update');
                    Route::put('update-user-data/{user_data}', 'updateUserData');
                    Route::delete('remove-vehicle/{cart_id}', 'destroy');
                    Route::delete('remove-cart', 'clear');
                    Route::delete('remove-multiple-cart', 'destroyMany');
                });
            });
            Route::prefix('trip')->group(function () {
                Route::controller(TripController::class)->group(function () {
                    Route::post('trip-booking', 'store');
                    Route::get('get-trip-list/{all}', 'index');
                    Route::get('get-trip-details', 'show');
                    Route::post('payment', 'pay');
                    Route::put('cancel-trip', 'cancel');
                    Route::delete('delete-trip', 'destroy');
                    Route::post('get-tax', 'tax');
                });
            });
            Route::middleware('auth:api')->group(function () {
                Route::prefix('wish-list')->group(function () {
                    Route::controller(RentalWishlistController::class)->group(function () {
                        Route::get('/', 'index');
                        Route::post('add', 'store');
                        Route::delete('remove', 'destroy');
                    });
                });
                Route::prefix('review')->group(function () {
                    Route::controller(VehicleReviewController::class)->group(function () {
                        Route::post('add', 'store');
                    });
                });
                Route::controller(TripController::class)->group(function () {
                    Route::post('trip/reorder', 'reorder');
                });
            });
        });
        Route::controller(TripController::class)->group(function () {
            Route::get('customer/trip/last', 'lastTrips');
        });
    });
});
