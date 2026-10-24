<?php

use Modules\Rental\Http\Controllers\Web\Provider\{
    DriverController,
    ProviderController,
    ProviderDashBoardController,
    ProviderEarningReportController,
    ProviderTaxReportController,
    ReportController,
    TripController,
    VehicleAiController,
    VehicleController,
};
use Modules\Rental\Http\Controllers\Web\Provider\Promotions\{
    BannerController,
    CouponController,
};
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::middleware(['vendor', 'provider-rental-module'])->group(function () {
    Route::prefix('provider-dashboard')->group(function () {
        Route::controller(ProviderDashBoardController::class)->group(function () {
            Route::get('/', 'providerDashboard')->name('providerDashboard');
            Route::get('delivery-statistics', 'deliveryStatistics')->name('deliveryStatistics');
            Route::get('commission-overview', 'commissionOverview')->name('commissionOverview');
        });
    });
    Route::middleware(['module:vehicle'])->prefix('vehicle')->name('vehicle.')->group(function () {
        Route::controller(VehicleController::class)->group(function () {
            Route::get('list', 'index')->name('list');
            Route::get('create', 'create')->name('create');
            Route::post('create', 'store');
            Route::get('update/{id}', 'edit')->name('edit');
            Route::post('update/{id}', 'update');
            Route::get('details/{id}', 'details')->name('details');
            Route::get('status/{id}', 'status')->name('status');
            Route::get('new-tag/{id}', 'newTag')->name('new-tag');
            Route::delete('delete/{id}', 'destroy')->name('delete');
            Route::get('export', 'export')->name('export');
            Route::get('review-status/{id}', 'reviewStatus')->name('review.status');
            Route::get('review-export', 'reviewExport')->name('review.export');
            Route::get('bulk-import', 'bulkImportIndex')->name('bulk_import');
            Route::post('bulk-import', 'bulkImportData');
            Route::get('bulk-export', 'bulkExportIndex')->name('bulk-export-index');
            Route::post('bulk-export', 'bulkExportData');
        });
        Route::controller(VehicleAiController::class)->group(function () {
            Route::post('ai/generate-titles', 'generateTitleSuggestions')->name('ai.generate-titles');
            Route::post('ai/generate-description', 'generateDescription')->name('ai.generate-description');
            Route::post('ai/analyze-image', 'analyzeImage')->name('ai.analyze-image');
            Route::post('ai/title', 'titleAutoFill')->name('ai.title');
            Route::post('ai/vehicle-info', 'vehicleInfoAutoFill')->name('ai.vehicle-info');
            Route::post('ai/pricing', 'pricingAutoFill')->name('ai.pricing');
            Route::post('ai/tags', 'tagsAutoFill')->name('ai.tags');
            Route::post('ai/seo', 'seoAutoFill')->name('ai.seo');
        });
    });
    Route::middleware(['module:vehicle'])->prefix('vehicle-category')->name('vehicle_category.')->group(function () {
        Route::controller(ProviderController::class)->group(function () {
            Route::get('list', 'categoryList')->name('list');
            Route::get('export', 'categoryExport')->name('export');
        });
    });
    Route::middleware(['module:trip'])->prefix('trip')->name('trip.')->group(function () {
        Route::controller(TripController::class)->group(function () {
            Route::get('/', 'list')->name('list');
            Route::get('details/{id}', 'details')->name('details');
            Route::get('status/{id}/{status}', 'status')->name('status');
            Route::get('payment/status/{id}/{status}', 'paymentStatus')->name('payment.status');
            Route::post('assign/vehicle', 'assignVehicle')->name('assign.vehicle');
            Route::post('assign/driver', 'assignDriver')->name('assign.driver');
            Route::get('export', 'export')->name('export');
            Route::post('get-calculation', 'update')->name('get-calculation');
            Route::get('generate-invoice/{id}', 'generateInvoice')->name('generate-invoice');
            Route::get('print-invoice/{id}', 'printInvoice')->name('print-invoice');
        });
    });
    Route::middleware(['module:vehicle'])->prefix('vehicle-brand')->name('vehicle_brand.')->group(function () {
        Route::controller(ProviderController::class)->group(function () {
            Route::get('list', 'brandList')->name('list');
            Route::get('export', 'brandExport')->name('export');
        });
    });
    Route::middleware(['module:marketing'])->prefix('rental-banner')->name('rental_banner.')->group(function () {
        Route::controller(BannerController::class)->group(function () {
            Route::get('/', 'list')->name('list');
            Route::post('/', 'store');
            Route::get('edit/{banner}', 'edit')->name('edit');
            Route::post('edit/{banner}', 'update')->name('update');
            Route::delete('delete/{banner}', 'destroy')->name('delete');
            Route::get('status/{banner}/{status}', 'status')->name('status');
            Route::get('featured/{banner}/{status}', 'updateFeatured')->name('featured');
            Route::get('export', 'export')->name('export');
        });
    });
    Route::middleware(['module:marketing'])->prefix('rental-coupon')->name('rental_coupon.')->group(function () {
        Route::controller(CouponController::class)->group(function () {
            Route::get('/', 'list')->name('list');
            Route::post('/', 'store');
            Route::get('edit/{id}', 'edit')->name('edit');
            Route::post('edit/{id}', 'update');
            Route::delete('delete/{coupon}', 'destroy')->name('delete');
            Route::get('status/{coupon}', 'status')->name('status');
            Route::get('export', 'export')->name('export');
        });
    });
    Route::middleware(['module:driver'])->prefix('driver')->name('driver.')->group(function () {
        Route::controller(DriverController::class)->group(function () {
            Route::get('list', 'list')->name('list');
            Route::get('/create', 'create')->name('create');
            Route::post('/create', 'store');
            Route::get('update/{id}', 'edit')->name('edit');
            Route::post('update/{id}', 'update');
            Route::get('details/{id}', 'details')->name('details');
            Route::get('status/{id}', 'status')->name('status');
            Route::delete('delete/{id}', 'destroy')->name('delete');
            Route::get('export', 'export')->name('export');
            Route::get('trip-export', 'tripExport')->name('trip.export');
        });
    });
    Route::middleware(['module:report'])->prefix('report')->name('report.')->group(function () {
        Route::controller(ProviderEarningReportController::class)->group(function () {
            Route::get('earning-report', 'index')->name('earning-report');
            Route::get('earning-summary', 'getEarningSummary')->name('earning-summary');
            Route::get('earning-breakdown', 'getEarningBreakdown')->name('earning-breakdown');
            Route::get('expense-breakdown', 'getExpenseBreakdown')->name('expense-breakdown');
            Route::get('earning-trend', 'getEarningTrend')->name('earning-trend');
            Route::get('earning-transactions', 'getEarningTransactions')->name('earning-transactions');
            Route::get('earning-export', 'exportEarningTransactions')->name('earning-export');
        });
        Route::controller(ReportController::class)->group(function () {
            Route::get('trip-report', 'tripReport')->name('trip-report');
            Route::get('trip-report-export', 'tripReportExport')->name('trip-report-export');
        });
        Route::controller(ProviderTaxReportController::class)->group(function () {
            Route::get('provider-tax-report', 'providerTax')->name('providerTax');
            Route::get('provider-tax-export', 'providerTaxExport')->name('providerTaxExport');
        });
    });
    Route::middleware(['module:employee'])->prefix('custom-role')->name('custom-role.')->group(function () {
        Route::controller(ProviderController::class)->group(function () {
            Route::get('list', 'role')->name('list');
            Route::get('update/{id}', 'update')->name('update-employee');
        });
    });
    Route::controller(ProviderController::class)->group(function () {
        Route::get('rental-reviews', 'reviews')->name('rental.reviews')->middleware('module:reviews','subscription:reviews');
        Route::post('rental-review/{id}', 'reviewReply')->name('rental.review.reply')->middleware('module:reviews','subscription:reviews');
    });
});
