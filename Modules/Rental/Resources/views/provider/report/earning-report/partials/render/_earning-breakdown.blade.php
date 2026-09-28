<div class="border rounded-10 p-3 p-xxl-20 mb-20">
    <div class="row g-lg-5 gx-3 gy-3 earnings-breakdown">
        <div class="col-lg-6 col-sm-6">
            <div class="item border-right">
                <div class="flex-shrink-0 info rounded-10 w-40px aspect-1-1 d-flex justify-content-center align-items-center mb-3">
                    <img src="{{ asset('public/assets/admin/img/report/earning-breakdown/order-commission.svg') }}" alt="trip-earning">
                </div>
                <div class="mb-2">{{ translate('Trip Earning') }}</div>
                <h2 class="font-medium fs-24 fs-18-mobile mb-2">{{ \App\CentralLogics\Helpers::format_currency($breakdown['trip_store_earning'] ?? 0) }}</h2>
                <div class="fs-12 bg-light px-2 py-1 rounded-lg w-max-content">
                    {{ $breakdown['trip_store_earning_percentage'] ?? 0 }}% {{ translate('messages.of Total') }}
                </div>
            </div>
        </div>
        <div class="col-lg-6 col-sm-6">
            <div class="item">
                <div class="flex-shrink-0 success rounded-10 w-40px aspect-1-1 d-flex justify-content-center align-items-center mb-3">
                    <img src="{{ asset('public/assets/admin/img/report/earning-breakdown/tax-collected.svg') }}" alt="tax">
                </div>
                <div class="mb-2">{{ translate('Tax Amount') }}</div>
                <h2 class="font-medium fs-24 fs-18-mobile mb-2">{{ \App\CentralLogics\Helpers::format_currency($breakdown['tax_amount'] ?? 0) }}</h2>
                <div class="fs-12 bg-light px-2 py-1 rounded-lg w-max-content">
                    {{ $breakdown['tax_amount_percentage'] ?? 0 }}% {{ translate('messages.of Total') }}
                </div>
            </div>
        </div>
    </div>
</div>
