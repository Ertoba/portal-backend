<?php

use Modules\Rental\Http\Controllers\Web\Admin\{
    AdminEarningReportController,
    AdminTripTaxReportController,
    BrandController,
    CategoryController,
    DashboardController,
    DriverController,
    ProviderController,
    ProviderEarningReportController,
    ProviderTaxReportController,
    ReportController,
    SettingsController,
    TripController,
    VehicleAiController,
    VehicleController,
};
use Modules\Rental\Http\Controllers\Web\Admin\Promotions\{
    BannerController,
    CashBackController,
    CouponController,
    NotificationController,
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
Route::middleware(['admin', 'current-module'])->group(function () {
    Route::middleware('admin-rental-module')->prefix('rental')->name('rental.')->group(function () {
        Route::controller(DashboardController::class)->group(function () {
            Route::get('/', 'dashboard')->name('dashboard');
        });
        Route::prefix('dashboard-stats')->name('dashboard-stats.')->group(function () {
            Route::controller(DashboardController::class)->group(function () {
                Route::get('/commission-overview', 'commissionOverview')->name('commission_overview');
                Route::get('/trip-by-trip-type', 'byTripType')->name('trip_by_trip_type');
            });
        });
        Route::middleware(['module:rental_vehicle_setup'])->prefix('category')->name('category.')->group(function () {
            Route::controller(CategoryController::class)->group(function () {
                Route::get('list', 'list')->name('list');
                Route::post('list', 'store');
                Route::get('edit/{id}', 'edit')->name('edit');
                Route::post('edit/{id}', 'update');
                Route::delete('delete/{id}', 'destroy')->name('delete');
                Route::get('status/{id}', 'status')->name('status');
                Route::get('export-categories', 'export')->name('export-categories');
                Route::get('get-categories', 'getCategories')->name('get-categories');
            });
        });
        Route::middleware(['module:rental_vehicle_setup'])->prefix('brand')->name('brand.')->group(function () {
            Route::controller(BrandController::class)->group(function () {
                Route::get('list', 'list')->name('list');
                Route::post('list', 'store');
                Route::get('edit/{id}', 'edit')->name('edit');
                Route::post('edit/{id}', 'update');
                Route::delete('delete/{id}', 'destroy')->name('delete');
                Route::get('status/{id}', 'status')->name('status');
                Route::get('export', 'export')->name('export-brands');
            });
        });
        Route::prefix('provider')->name('provider.')->group(function () {
            Route::middleware(['module:provider'])->group(function () {
                Route::controller(ProviderController::class)->group(function () {
                    Route::get('list', 'list')->name('list');
                    Route::get('create', 'create')->name('create');
                    Route::post('create', 'store');
                    Route::get('edit-basic-setup/{id}', 'editBasicSetup')->name('edit-basic-setup');
                    Route::post('edit-basic-setup/{id}', 'updateBasicSetup');
                    Route::get('edit-business-setup/{id}', 'editBusinessSetup')->name('edit-business-setup');
                    Route::post('edit-business-setup/{id}', 'updateBusinessSetup');
                    Route::delete('delete/{id}', 'destroy')->name('delete');
                    Route::get('status/{id}', 'status')->name('status');
                    Route::get('verified-seller/{id}', 'verifiedSeller')->name('verified-seller');
                    Route::get('details/{id}/{tab?}/{sub_tab?}', 'details')->name('details');
                    Route::post('update-settings/{id}', 'updateSettings')->name('update_settings');
                    Route::get('export-review', 'exportReview')->name('export-review');
                    Route::get('new-requests', 'newRequests')->name('new-requests');
                    Route::get('new-requests-details/{id}', 'newRequestsDetails')->name('new-requests-details');
                    Route::get('approve-or-deny/{id}', 'approveOrDeny')->name('approve-or-deny');
                    Route::get('status/{store_id}', 'status')->name('status-by-store');
                });
            });
            Route::middleware(['module:rental_provider_bulk'])->group(function () {
                Route::controller(ProviderController::class)->group(function () {
                    Route::get('bulk-import', 'bulkImportIndex')->name('bulk_import');
                    Route::post('bulk-import', 'bulkImportData');
                    Route::get('bulk-export', 'bulkExportIndex')->name('bulk_export_index');
                    Route::post('bulk-export', 'bulkExportData');
                });
            });
            Route::middleware(['module:driver'])->prefix('driver')->name('driver.')->group(function () {
                Route::controller(DriverController::class)->group(function () {
                    Route::get('create/{provider_id}', 'create')->name('create');
                    Route::post('create/{provider_id}', 'store');
                    Route::get('update/{id}', 'edit')->name('edit');
                    Route::post('update/{id}', 'update');
                    Route::get('details/{id}', 'details')->name('details');
                    Route::get('status/{id}', 'status')->name('status');
                    Route::delete('delete/{id}', 'destroy')->name('delete');
                    Route::get('export', 'export')->name('export');
                    Route::get('trip-export', 'tripExport')->name('trip.export');
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
                    Route::get('review-list', 'reviews')->name('reviews');
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
        Route::middleware(['module:rental_banners'])->prefix('banner')->name('banner.')->group(function () {
            Route::controller(BannerController::class)->group(function () {
                Route::get('/', 'list')->name('add-new');
                Route::post('store', 'store')->name('store');
                Route::get('edit/{banner}', 'edit')->name('edit');
                Route::post('edit/{banner}', 'update')->name('update');
                Route::delete('delete/{banner}', 'destroy')->name('delete');
                Route::get('status/{banner}/{status}', 'status')->name('status');
                Route::get('featured/{banner}/{status}', 'updateFeatured')->name('featured');
                Route::get('export', 'export')->name('export');
            });
        });
        Route::middleware(['module:promotion'])->prefix('coupon')->name('coupon.')->group(function () {
            Route::controller(CouponController::class)->group(function () {
                Route::get('/', 'list')->name('add-new');
                Route::post('store', 'store')->name('store');
                Route::get('edit/{coupon}', 'edit')->name('edit');
                Route::post('edit/{coupon}', 'update')->name('update');
                Route::delete('delete/{coupon}', 'destroy')->name('delete');
                Route::get('status/{coupon}', 'status')->name('status');
                Route::get('export', 'export')->name('export');
            });
        });
        Route::middleware(['module:promotion'])->prefix('cashback')->name('cashback.')->group(function () {
            Route::controller(CashBackController::class)->group(function () {
                Route::get('/', 'list')->name('list');
                Route::post('/', 'store');
                Route::get('edit/{id}', 'edit')->name('edit');
                Route::post('edit/{id}', 'update');
                Route::delete('delete/{id}', 'destroy')->name('delete');
                Route::get('status/{id}', 'status')->name('status');
            });
        });
        Route::middleware(['module:rental_communication'])->prefix('notification')->name('notification.')->group(function () {
            Route::controller(NotificationController::class)->group(function () {
                Route::get('/', 'list')->name('list');
                Route::post('store', 'store')->name('store');
                Route::get('edit/{notification}', 'edit')->name('edit');
                Route::post('update/{notification}', 'update')->name('update');
                Route::get('status/{notification}', 'status')->name('status');
                Route::delete('delete/{notification}', 'destroy')->name('delete');
                Route::get('export', 'export')->name('export');
            });
        });
        Route::middleware(['module:download_app'])->prefix('settings')->name('settings.')->group(function () {
            Route::controller(SettingsController::class)->group(function () {
                Route::get('/', 'homePageDownApp')->name('down_app');
                Route::post('/down_app_update', 'homePageDownAppUpdate')->name('down_app_update');
                Route::get('vendors-registration/', 'vendorsRegistration')->name('vendors_registration');
                Route::post('/vendors-registration-update', 'vendorsRegistrationUpdate')->name('vendors_registration_update');
            });
        });
    });
    Route::middleware(['module:email_setups', 'actch'])->prefix('business-settings')->name('business-settings.')->group(function () {
        Route::controller(SettingsController::class)->group(function () {
            Route::get('rental-email-setup/{type}/{tab?}', 'email_index')->name('rental-email-setup');
            Route::post('rental-email-setup/{type}/{tab?}', 'update_email_index')->name('rental-email-setup-update');
            Route::get('rental-email-status/{type}/{tab}/{status}', 'update_email_status')->name('rental-email-status');
        });
    });
    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::prefix('rental')->name('rental.')->group(function () {
            Route::middleware(['module:sales_report'])->group(function () {
                Route::controller(TripController::class)->group(function () {
                    Route::get('trip/details/{id}', 'details')->name('trip.details');
                    Route::get('trip/generate-invoice/{id}', 'generateInvoice')->name('trip.generate-invoice');
                });
            });
            Route::prefix('report')->name('report.')->group(function () {
                Route::middleware(['module:report'])->group(function () {
                    Route::controller(ReportController::class)->group(function () {
                        Route::get('transaction-report', 'transactionReport')->name('transaction-report');
                        Route::get('transaction-report-export', 'transactionExport')->name('transaction-report-export');
                        Route::get('generate-statement/{id}', 'generateStatement')->name('generate-statement');
                    });
                });
                Route::middleware(['module:sales_report'])->group(function () {
                    Route::controller(ReportController::class)->group(function () {
                        Route::get('trip-report', 'tripReport')->name('trip-report');
                        Route::get('trip-report-export', 'tripReportExport')->name('trip-report-export');
                        Route::post('set-date', 'set_date')->name('set-date');
                    });
                });
                Route::middleware(['module:performance_report'])->group(function () {
                    Route::controller(ReportController::class)->group(function () {
                        Route::get('vehicle-report', 'vehicleReport')->name('vehicle-wise-report');
                        Route::get('vehicle-report-export', 'vehicleReportExport')->name('vehicle-wise-export');
                        Route::get('provider-wise-report', 'providerSummaryReport')->name('provider-summary-report');
                        Route::get('provider-summary-report-export', 'providerSummaryExport')->name('provider-summary-report-export');
                        Route::get('provider-wise-sales-report', 'providerSalesReport')->name('provider-sales-report');
                        Route::get('provider-wise-sales-report-export', 'providerSalesExport')->name('provider-sales-report-export');
                        Route::get('provider-wise-trip-report', 'providerTripReport')->name('provider-trip-report');
                        Route::get('provider-wise-trip-report-export', 'providerTripExport')->name('provider-trip-report-export');
                    });
                });
                Route::middleware(['module:vendor_vat_report'])->group(function () {
                    Route::controller(ProviderTaxReportController::class)->group(function () {
                        Route::get('provider-wise-taxes', 'providerWiseTaxes')->name('providerWiseTaxes');
                        Route::get('provider-wise-taxes-export', 'providerWiseTaxExport')->name('providerWiseTaxExport');
                        Route::get('provider-tax-report', 'providerTax')->name('providerTax');
                        Route::get('provider-tax-export', 'providerTaxExport')->name('providerTaxExport');
                    });
                });
                Route::middleware(['module:admin_text_module'])->group(function () {
                    Route::controller(AdminTripTaxReportController::class)->group(function () {
                        Route::get('get-tax-export', 'getTaxReport')->name('getTaxReport');
                        Route::get('get-tax-list', 'getTaxList')->name('getTaxList');
                        Route::get('get-tax-details', 'getTaxDetails')->name('getTaxDetails');
                        Route::get('tax-details-report-export', 'adminTaxDetailsExport')->name('getTaxDetailsExport');
                        Route::get('admin-tax-report-export', 'adminTaxReportExport')->name('adminTaxReportExport');
                    });
                });
                Route::middleware(['module:earning_report'])->group(function () {
                    Route::controller(AdminEarningReportController::class)->group(function () {
                        Route::get('admin-earning-summary', 'getAdminEarningSummary')->name('admin-earning-summary');
                        Route::get('admin-earning-breakdown', 'getAdminEarningBreakdown')->name('admin-earning-breakdown');
                        Route::get('admin-expense-breakdown', 'getAdminExpenseBreakdown')->name('admin-expense-breakdown');
                        Route::get('admin-monthly-earnings', 'getMonthlyEarningsReport')->name('admin-monthly-earnings');
                        Route::get('admin-top-earning-providers', 'getTopEarningProviders')->name('admin-top-earning-providers');
                        Route::get('admin-zone-wise-earnings', 'getZoneWiseEarnings')->name('admin-zone-wise-earnings');
                        Route::get('admin-earning-transactions', 'getEarningTransactions')->name('admin-earning-transactions');
                        Route::get('admin-earning-export', 'exportEarningTransactions')->name('admin-earning-export');
                    });
                    Route::controller(ProviderEarningReportController::class)->group(function () {
                        Route::get('provider-earning-summary', 'getProviderEarningSummary')->name('provider-earning-summary');
                        Route::get('provider-earning-breakdown', 'getProviderEarningBreakdown')->name('provider-earning-breakdown');
                        Route::get('provider-expense-breakdown', 'getProviderExpenseBreakdown')->name('provider-expense-breakdown');
                        Route::get('provider-earning-trend', 'getProviderEarningTrend')->name('provider-earning-trend');
                        Route::get('provider-earning-transactions', 'getProviderEarningTransactions')->name('provider-earning-transactions');
                        Route::get('provider-earning-export', 'exportProviderEarningTransactions')->name('provider-earning-export');
                    });
                });
            });
        });
    });
});
Route::controller(ReportController::class)->group(function () {
    Route::get('trip-invoice/{id}', 'tripInvoice')->name('trip_invoice');
});
