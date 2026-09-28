<?php

use Illuminate\Support\Facades\Route;
use Modules\Rental\Http\Controllers\Web\Admin\AdminTripTaxReportController;
use Modules\Rental\Http\Controllers\Web\Admin\AdminEarningReportController;
use Modules\Rental\Http\Controllers\Web\Admin\ProviderEarningReportController;
use Modules\Rental\Http\Controllers\Web\Admin\BrandController;
use Modules\Rental\Http\Controllers\Web\Admin\Promotions\BannerController;
use Modules\Rental\Http\Controllers\Web\Admin\Promotions\CouponController;
use Modules\Rental\Http\Controllers\Web\Admin\Promotions\CashBackController;
use Modules\Rental\Http\Controllers\Web\Admin\Promotions\NotificationController;
use Modules\Rental\Http\Controllers\Web\Admin\CategoryController;
use Modules\Rental\Http\Controllers\Web\Admin\DriverController;
use Modules\Rental\Http\Controllers\Web\Admin\ProviderController;
use Modules\Rental\Http\Controllers\Web\Admin\DashboardController;
use Modules\Rental\Http\Controllers\Web\Admin\ProviderTaxReportController;
use Modules\Rental\Http\Controllers\Web\Admin\ReportController;
use Modules\Rental\Http\Controllers\Web\Admin\TripController;
use Modules\Rental\Http\Controllers\Web\Admin\VehicleController;
use Modules\Rental\Http\Controllers\Web\Admin\VehicleAiController;
use Modules\Rental\Http\Controllers\Web\Admin\SettingsController;

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

Route::group(['middleware' => ['admin', 'current-module']], function () {
    Route::group(['prefix' => 'rental', 'as' => 'rental.','middleware' => 'admin-rental-module'], function () {
        Route::get('/', [DashboardController::class, 'dashboard'])->name('dashboard');
        Route::group(['prefix' => 'dashboard-stats', 'as' => 'dashboard-stats.'], function () {
            Route::get('/commission-overview', [DashboardController::class, 'commissionOverview'])->name('commission_overview');
            Route::get('/trip-by-trip-type', [DashboardController::class, 'byTripType'])->name('trip_by_trip_type');
        });

        Route::group(['prefix' => 'category', 'as' => 'category.', 'middleware' => ['module:rental_vehicle_setup']], function () {
            Route::get('list', [CategoryController::class, 'list'])->name('list');
            Route::post('list', [CategoryController::class, 'store']);
            Route::get('edit/{id}', [CategoryController::class, 'edit'])->name('edit');
            Route::post('edit/{id}', [CategoryController::class, 'update']);
            Route::delete('delete/{id}', [CategoryController::class, 'destroy'])->name('delete');
            Route::get('status/{id}', [CategoryController::class, 'status'])->name('status');
            Route::get('export-categories', [CategoryController::class, 'export'])->name('export-categories');
            Route::get('get-categories', [CategoryController::class, 'getCategories'])->name('get-categories');
        });

        Route::group(['prefix' => 'brand', 'as' => 'brand.', 'middleware' => ['module:rental_vehicle_setup']], function () {
            Route::get('list', [BrandController::class, 'list'])->name('list');
            Route::post('list', [BrandController::class, 'store']);
            Route::get('edit/{id}', [BrandController::class, 'edit'])->name('edit');
            Route::post('edit/{id}', [BrandController::class, 'update']);
            Route::delete('delete/{id}', [BrandController::class, 'destroy'])->name('delete');
            Route::get('status/{id}', [BrandController::class, 'status'])->name('status');
            Route::get('export', [BrandController::class, 'export'])->name('export-brands');

        });

        Route::group(['prefix' => 'provider', 'as' => 'provider.'], function () {
            Route::group(['middleware' => ['module:provider']], function () {
                Route::get('list', [ProviderController::class, 'list'])->name('list');
                Route::get('create', [ProviderController::class, 'create'])->name('create');
                Route::post('create', [ProviderController::class, 'store']);
                Route::get('edit-basic-setup/{id}', [ProviderController::class, 'editBasicSetup'])->name('edit-basic-setup');
                Route::post('edit-basic-setup/{id}', [ProviderController::class, 'updateBasicSetup']);
                Route::get('edit-business-setup/{id}', [ProviderController::class, 'editBusinessSetup'])->name('edit-business-setup');
                Route::post('edit-business-setup/{id}', [ProviderController::class, 'updateBusinessSetup']);
                // Route::delete('delete/{id}', [ProviderController::class, 'destroy'])->name('delete');
                Route::get('status/{id}', [ProviderController::class, 'status'])->name('status');
                Route::get('verified-seller/{id}', [ProviderController::class, 'verifiedSeller'])->name('verified-seller');
                Route::get('details/{id}/{tab?}/{sub_tab?}', [ProviderController::class, 'details'])->name('details');
                Route::post('update-settings/{id}', [ProviderController::class, 'updateSettings'])->name('update_settings');
                Route::get('export-review', [ProviderController::class, 'exportReview'])->name('export-review');
                // Route::get('export-categories', [ProviderController::class, 'export'])->name('export-brands');
                Route::get('new-requests', [ProviderController::class, 'newRequests'])->name('new-requests');
                Route::get('new-requests-details/{id}', [ProviderController::class, 'newRequestsDetails'])->name('new-requests-details');
                Route::get('approve-or-deny/{id}', [ProviderController::class, 'approveOrDeny'])->name('approve-or-deny');
                Route::get('status/{store_id}', [ProviderController::class, 'status'])->name('status-by-store');

            });
            Route::group(['middleware' => ['module:rental_provider_bulk']], function () {
                Route::get('bulk-import', [ProviderController::class, 'bulkImportIndex'])->name('bulk_import');
                Route::post('bulk-import', [ProviderController::class, 'bulkImportData']);
                Route::get('bulk-export', [ProviderController::class, 'bulkExportIndex'])->name('bulk_export_index');
                Route::post('bulk-export', [ProviderController::class, 'bulkExportData']);
            });

            Route::group(['prefix' => 'driver', 'as' => 'driver.', 'middleware' => ['module:driver']], function () {
                Route::get('create/{provider_id}', [DriverController::class, 'create'])->name('create');
                Route::post('create/{provider_id}', [DriverController::class, 'store']);
                Route::get('update/{id}', [DriverController::class, 'edit'])->name('edit');
                Route::post('update/{id}', [DriverController::class, 'update']);
                Route::get('details/{id}', [DriverController::class, 'details'])->name('details');
                Route::get('status/{id}', [DriverController::class, 'status'])->name('status');
                Route::delete('delete/{id}', [DriverController::class, 'destroy'])->name('delete');
                Route::get('export', [DriverController::class, 'export'])->name('export');
                Route::get('trip-export', [DriverController::class, 'tripExport'])->name('trip.export');
            });

            Route::group(['prefix' => 'vehicle', 'as' => 'vehicle.', 'middleware' => ['module:vehicle']], function () {
                Route::get('list', [VehicleController::class, 'index'])->name('list');
                Route::get('create', [VehicleController::class, 'create'])->name('create');
                Route::post('create', [VehicleController::class, 'store']);
                Route::post('ai/generate-titles', [VehicleAiController::class, 'generateTitleSuggestions'])->name('ai.generate-titles');
                Route::post('ai/generate-description', [VehicleAiController::class, 'generateDescription'])->name('ai.generate-description');
                Route::post('ai/analyze-image', [VehicleAiController::class, 'analyzeImage'])->name('ai.analyze-image');
                Route::post('ai/title', [VehicleAiController::class, 'titleAutoFill'])->name('ai.title');
                Route::post('ai/vehicle-info', [VehicleAiController::class, 'vehicleInfoAutoFill'])->name('ai.vehicle-info');
                Route::post('ai/pricing', [VehicleAiController::class, 'pricingAutoFill'])->name('ai.pricing');
                Route::post('ai/tags', [VehicleAiController::class, 'tagsAutoFill'])->name('ai.tags');
                Route::post('ai/seo', [VehicleAiController::class, 'seoAutoFill'])->name('ai.seo');
                Route::get('update/{id}', [VehicleController::class, 'edit'])->name('edit');
                Route::post('update/{id}', [VehicleController::class, 'update']);
                Route::get('details/{id}', [VehicleController::class, 'details'])->name('details');
                Route::get('status/{id}', [VehicleController::class, 'status'])->name('status');
                Route::get('new-tag/{id}', [VehicleController::class, 'newTag'])->name('new-tag');
                Route::delete('delete/{id}', [VehicleController::class, 'destroy'])->name('delete');
                Route::get('export', [VehicleController::class, 'export'])->name('export');
                Route::get('review-status/{id}', [VehicleController::class, 'reviewStatus'])->name('review.status');
                Route::get('review-export', [VehicleController::class, 'reviewExport'])->name('review.export');
                Route::get('review-list', [VehicleController::class, 'reviews'])->name('reviews');

                Route::get('bulk-import', [VehicleController::class, 'bulkImportIndex'])->name('bulk_import');
                Route::POST('bulk-import', [VehicleController::class, 'bulkImportData']);
                Route::get('bulk-export', [VehicleController::class, 'bulkExportIndex'])->name('bulk-export-index');
                Route::POST('bulk-export', [VehicleController::class, 'bulkExportData']);
            });
        });

        Route::group(['prefix' => 'trip', 'as' => 'trip.', 'middleware' => ['module:trip']], function () {
            Route::get('/', [TripController::class,'list'])->name('list');
            Route::get('details/{id}', [TripController::class,'details'])->name('details');
            Route::get('status/{id}/{status}', [TripController::class,'status'])->name('status');
            Route::get('payment/status/{id}/{status}', [TripController::class,'paymentStatus'])->name('payment.status');
            Route::post('assign/vehicle', [TripController::class,'assignVehicle'])->name('assign.vehicle');
            Route::post('assign/driver', [TripController::class,'assignDriver'])->name('assign.driver');
            Route::get('export', [TripController::class, 'export'])->name('export');
            Route::post('get-calculation', [TripController::class, 'update'])->name('get-calculation');
            Route::get('generate-invoice/{id}', [TripController::class, 'generateInvoice'])->name('generate-invoice');
            Route::get('print-invoice/{id}', [TripController::class, 'printInvoice'])->name('print-invoice');
        });

        Route::group(['prefix' => 'banner', 'as' => 'banner.', 'middleware' => ['module:rental_banners']], function () {
            Route::get('/', [BannerController::class,'list'])->name('add-new');
            Route::post('store', [BannerController::class,'store'])->name('store');
            Route::get('edit/{banner}', [BannerController::class,'edit'])->name('edit');
            Route::post('edit/{banner}', [BannerController::class,'update'])->name('update');
            Route::delete('delete/{banner}', [BannerController::class,'destroy'])->name('delete');
            Route::get('status/{banner}/{status}', [BannerController::class,'status'])->name('status');
            Route::get('featured/{banner}/{status}', [BannerController::class,'updateFeatured'])->name('featured');
            Route::get('export', [BannerController::class, 'export'])->name('export');
        });

        Route::group(['prefix' => 'coupon', 'as' => 'coupon.', 'middleware' => ['module:promotion']], function () {
            Route::get('/', [CouponController::class,'list'])->name('add-new');
            Route::post('store', [CouponController::class,'store'])->name('store');
            Route::get('edit/{coupon}', [CouponController::class,'edit'])->name('edit');
            Route::post('edit/{coupon}', [CouponController::class,'update'])->name('update');
            Route::delete('delete/{coupon}', [CouponController::class,'destroy'])->name('delete');
            Route::get('status/{coupon}', [CouponController::class,'status'])->name('status');
            Route::get('export', [CouponController::class, 'export'])->name('export');
        });

        Route::group(['prefix' => 'cashback', 'as' => 'cashback.', 'middleware' => ['module:promotion']], function () {
            Route::get('/', [CashBackController::class,'list'])->name('list');
            Route::post('/', [CashBackController::class,'store']);
            Route::get('edit/{id}', [CashBackController::class,'edit'])->name('edit');
            Route::post('edit/{id}', [CashBackController::class,'update']);
            Route::delete('delete/{id}', [CashBackController::class,'destroy'])->name('delete');
            Route::get('status/{id}', [CashBackController::class,'status'])->name('status');
        });

        Route::group(['prefix' => 'notification', 'as' => 'notification.', 'middleware' => ['module:rental_communication']], function () {
            Route::get('/', [NotificationController::class,'list'])->name('list');
            Route::post('store', [NotificationController::class,'store'])->name('store');
            Route::get('edit/{notification}', [NotificationController::class,'edit'])->name('edit');
            Route::post('update/{notification}', [NotificationController::class,'update'])->name('update');
            Route::get('status/{notification}', [NotificationController::class,'status'])->name('status');
            Route::delete('delete/{notification}', [NotificationController::class,'destroy'])->name('delete');
            Route::get('export', [NotificationController::class,'export'])->name('export');
        });
        Route::group(['prefix' => 'settings', 'as' => 'settings.', 'middleware' => ['module:download_app']], function () {
            Route::get('/', [SettingsController::class,'homePageDownApp'])->name('down_app');
            Route::post('/down_app_update', [SettingsController::class,'homePageDownAppUpdate'])->name('down_app_update');
            Route::get('vendors-registration/', [SettingsController::class,'vendorsRegistration'])->name('vendors_registration');
            Route::post('/vendors-registration-update', [SettingsController::class,'vendorsRegistrationUpdate'])->name('vendors_registration_update');
        });


    });
    Route::group(['prefix' => 'business-settings', 'as' => 'business-settings.', 'middleware' => ['module:email_setups', 'mili.feature']], function () {
        Route::get('rental-email-setup/{type}/{tab?}', [SettingsController::class,'email_index'])->name('rental-email-setup');
        Route::POST('rental-email-setup/{type}/{tab?}', [SettingsController::class,'update_email_index'])->name('rental-email-setup-update');
        Route::get('rental-email-status/{type}/{tab}/{status}', [SettingsController::class,'update_email_status'])->name('rental-email-status');
    });
    Route::group(['prefix' => 'transactions', 'as' => 'transactions.'], function () {
        Route::group(['prefix' => 'rental', 'as' => 'rental.'], function () {
            Route::group(['middleware' => ['module:sales_report']], function () {
                Route::get('trip/details/{id}', [TripController::class,'details'])->name('trip.details');
                Route::get('trip/generate-invoice/{id}', [TripController::class, 'generateInvoice'])->name('trip.generate-invoice');
            });
            Route::group(['prefix' => 'report', 'as' => 'report.'], function () {
                Route::group(['middleware' => ['module:report']], function () {
                    Route::get('transaction-report', [ReportController::class, 'transactionReport'])->name('transaction-report');
                    Route::get('transaction-report-export', [ReportController::class, 'transactionExport'])->name('transaction-report-export');
                    Route::get('generate-statement/{id}', [ReportController::class, 'generateStatement'])->name('generate-statement');
                });

                Route::group(['middleware' => ['module:sales_report']], function () {
                    Route::get('trip-report', [ReportController::class, 'tripReport'])->name('trip-report');
                    Route::get('trip-report-export', [ReportController::class, 'tripReportExport'])->name('trip-report-export');
                    Route::post('set-date', [ReportController::class, 'set_date'])->name('set-date');
                });

                Route::group(['middleware' => ['module:performance_report']], function () {
                    Route::get('vehicle-report', [ReportController::class, 'vehicleReport'])->name('vehicle-wise-report');
                    Route::get('vehicle-report-export', [ReportController::class, 'vehicleReportExport'])->name('vehicle-wise-export');
                    Route::get('provider-wise-report', [ReportController::class, 'providerSummaryReport'])->name('provider-summary-report');
                    Route::get('provider-summary-report-export', [ReportController::class, 'providerSummaryExport'])->name('provider-summary-report-export');
                    Route::get('provider-wise-sales-report', [ReportController::class, 'providerSalesReport'])->name('provider-sales-report');
                    Route::get('provider-wise-sales-report-export', [ReportController::class, 'providerSalesExport'])->name('provider-sales-report-export');
                    Route::get('provider-wise-trip-report', [ReportController::class, 'providerTripReport'])->name('provider-trip-report');
                    Route::get('provider-wise-trip-report-export', [ReportController::class, 'providerTripExport'])->name('provider-trip-report-export');
                });

                Route::group(['middleware' => ['module:vendor_vat_report']], function () {
                    Route::get('provider-wise-taxes', [ProviderTaxReportController::class, 'providerWiseTaxes'])->name('providerWiseTaxes');
                    Route::get('provider-wise-taxes-export', [ProviderTaxReportController::class, 'providerWiseTaxExport'])->name('providerWiseTaxExport');
                    Route::get('provider-tax-report',[ProviderTaxReportController::class, 'providerTax'])->name('providerTax');
                    Route::get('provider-tax-export', [ProviderTaxReportController::class, 'providerTaxExport'])->name('providerTaxExport');
                });

                Route::group(['middleware' => ['module:admin_text_module']], function () {
                    Route::get('get-tax-export',[AdminTripTaxReportController::class, 'getTaxReport'])->name('getTaxReport');
                    Route::get('get-tax-list',[AdminTripTaxReportController::class, 'getTaxList'])->name('getTaxList');
                    Route::get('get-tax-details',[AdminTripTaxReportController::class, 'getTaxDetails'])->name('getTaxDetails');
                    Route::get('tax-details-report-export',[AdminTripTaxReportController::class, 'adminTaxDetailsExport'])->name('getTaxDetailsExport');
                    Route::get('admin-tax-report-export',[AdminTripTaxReportController::class, 'adminTaxReportExport'])->name('adminTaxReportExport');
                });

                Route::group(['middleware' => ['module:earning_report']], function () {
                    Route::get('admin-earning-summary', [AdminEarningReportController::class, 'getAdminEarningSummary'])->name('admin-earning-summary');
                    Route::get('admin-earning-breakdown', [AdminEarningReportController::class, 'getAdminEarningBreakdown'])->name('admin-earning-breakdown');
                    Route::get('admin-expense-breakdown', [AdminEarningReportController::class, 'getAdminExpenseBreakdown'])->name('admin-expense-breakdown');
                    Route::get('admin-monthly-earnings', [AdminEarningReportController::class, 'getMonthlyEarningsReport'])->name('admin-monthly-earnings');
                    Route::get('admin-top-earning-providers', [AdminEarningReportController::class, 'getTopEarningProviders'])->name('admin-top-earning-providers');
                    Route::get('admin-zone-wise-earnings', [AdminEarningReportController::class, 'getZoneWiseEarnings'])->name('admin-zone-wise-earnings');
                    Route::get('admin-earning-transactions', [AdminEarningReportController::class, 'getEarningTransactions'])->name('admin-earning-transactions');
                    Route::get('admin-earning-export', [AdminEarningReportController::class, 'exportEarningTransactions'])->name('admin-earning-export');
                    Route::get('provider-earning-summary', [ProviderEarningReportController::class, 'getProviderEarningSummary'])->name('provider-earning-summary');
                    Route::get('provider-earning-breakdown', [ProviderEarningReportController::class, 'getProviderEarningBreakdown'])->name('provider-earning-breakdown');
                    Route::get('provider-expense-breakdown', [ProviderEarningReportController::class, 'getProviderExpenseBreakdown'])->name('provider-expense-breakdown');
                    Route::get('provider-earning-trend', [ProviderEarningReportController::class, 'getProviderEarningTrend'])->name('provider-earning-trend');
                    Route::get('provider-earning-transactions', [ProviderEarningReportController::class, 'getProviderEarningTransactions'])->name('provider-earning-transactions');
                    Route::get('provider-earning-export', [ProviderEarningReportController::class, 'exportProviderEarningTransactions'])->name('provider-earning-export');
                });
            });
        });
    });
});
Route::get('trip-invoice/{id}', [ReportController::class, 'tripInvoice'])->name('trip_invoice');
