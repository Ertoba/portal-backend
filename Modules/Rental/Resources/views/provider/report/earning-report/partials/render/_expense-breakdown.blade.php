<div class="border rounded-10 p-3 p-xxl-20">
    <div class="row g-lg-5 gx-3 gy-3 earnings-breakdown">
        <div class="col-lg-4 col-sm-6">
            <div class="item border-right">
                <div class="flex-shrink-0 info rounded-10 w-40px aspect-1-1 d-flex justify-content-center align-items-center mb-3">
                    <img src="{{ asset('public/assets/admin/img/report/earning-breakdown/order-commission.svg') }}" alt="trip-commission">
                </div>
                <div class="mb-2">{{ translate('Trip Commission') }}</div>
                <h2 class="font-medium fs-24 fs-18-mobile mb-2">{{ \App\CentralLogics\Helpers::format_currency($expenses['trip_commission'] ?? 0) }}</h2>
                <div class="fs-12 bg-light px-2 py-1 rounded-lg w-max-content">
                    {{ $expenses['trip_commission_percentage'] ?? 0 }}% {{ translate('messages.of Total') }}
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-sm-6">
            <div class="item border-right">
                <div class="flex-shrink-0 purple rounded-10 w-40px aspect-1-1 d-flex justify-content-center align-items-center mb-3">
                    <img src="{{ asset('public/assets/admin/img/report/earning-breakdown/subscription.svg') }}" alt="subscription">
                </div>
                <div class="mb-2">{{ translate('Subscription Amount') }}</div>
                <h2 class="font-medium fs-24 fs-18-mobile mb-2">{{ \App\CentralLogics\Helpers::format_currency($expenses['subscription_amount'] ?? 0) }}</h2>
                <div class="fs-12 bg-light px-2 py-1 rounded-lg w-max-content">
                    {{ $expenses['subscription_amount_percentage'] ?? 0 }}% {{ translate('messages.of Total') }}
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-sm-6">
            <div class="item border-right">
                <div class="flex-shrink-0 success rounded-10 w-40px aspect-1-1 d-flex justify-content-center align-items-center mb-3">
                    <img src="{{ asset('public/assets/admin/img/report/earning-breakdown/additional-fees.svg') }}" alt="discount-on-trip">
                </div>
                <div class="mb-2">{{ translate('messages.discount_on_trip') }}</div>
                <h2 class="font-medium fs-24 fs-18-mobile mb-2">{{ \App\CentralLogics\Helpers::format_currency($expenses['discount_on_trip'] ?? 0) }}</h2>
                <div class="fs-12 bg-light px-2 py-1 rounded-lg w-max-content">
                    {{ $expenses['discount_on_trip_percentage'] ?? 0 }}% {{ translate('messages.of Total') }}
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-sm-6">
            <div class="item border-right">
                <div class="flex-shrink-0 warning rounded-10 w-40px aspect-1-1 d-flex justify-content-center align-items-center mb-3">
                    <img src="{{ asset('public/assets/admin/img/report/earning-breakdown/coupon-offers.svg') }}" alt="coupon-discount">
                </div>
                <div class="mb-2">{{ translate('messages.coupon_discount') }}</div>
                <h2 class="font-medium fs-24 fs-18-mobile mb-2">{{ \App\CentralLogics\Helpers::format_currency($expenses['coupon_discount'] ?? 0) }}</h2>
                <div class="fs-12 bg-light px-2 py-1 rounded-lg w-max-content">
                    {{ $expenses['coupon_discount_percentage'] ?? 0 }}% {{ translate('messages.of Total') }}
                </div>
            </div>
        </div>
    </div>
</div>
