<div class="border rounded-10 p-3 p-xxl-20">
    <div class="row g-lg-5 gx-3 gy-3 earnings-breakdown">
        <div class="col-lg-4 col-sm-6">
            <div class="item border-right">
                <div class="flex-shrink-0 warning rounded-10 w-40px aspect-1-1 d-flex justify-content-center align-items-center mb-3">
                    <img src="{{ asset('public/assets/admin/img/report/new/refunded.png') }}" alt="cashback">
                </div>
                <div class="mb-2">{{ translate('messages.Cashback') }}</div>
                <h2 class="font-medium fs-24 fs-18-mobile mb-2">
                    {{ \App\CentralLogics\Helpers::format_currency($expenses['cashback']) }}
                </h2>
                <div class="fs-12 bg-light px-2 py-1 rounded-lg w-max-content">
                    {{ $expenses['cashback_percentage'] }}% {{ translate('messages.of Total') }}
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-sm-6">
            <div class="item border-right">
                <div class="flex-shrink-0 danger rounded-10 w-40px aspect-1-1 d-flex justify-content-center align-items-center mb-3">
                    <img src="{{ asset('public/assets/admin/img/report/earning-breakdown/coupon-offers.svg') }}" alt="discount on trip">
                </div>
                <div class="mb-2">{{ translate('Discount on Trip') }}</div>
                <h2 class="font-medium fs-24 fs-18-mobile mb-2">
                    {{ \App\CentralLogics\Helpers::format_currency($expenses['discount_on_trip']) }}
                </h2>
                <div class="fs-12 bg-light px-2 py-1 rounded-lg w-max-content">
                    {{ $expenses['discount_on_trip_percentage'] }}% {{ translate('messages.of Total') }}
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-sm-6">
            <div class="item">
                <div class="flex-shrink-0 warning rounded-10 w-40px aspect-1-1 d-flex justify-content-center align-items-center mb-3">
                    <img src="{{ asset('public/assets/admin/img/report/earning-breakdown/coupon-offers.svg') }}" alt="coupon discount">
                </div>
                <div class="mb-2">{{ translate('messages.Coupon Offers') }}</div>
                <h2 class="font-medium fs-24 fs-18-mobile mb-2">
                    {{ \App\CentralLogics\Helpers::format_currency($expenses['coupon_discount']) }}
                </h2>
                <div class="fs-12 bg-light px-2 py-1 rounded-lg w-max-content">
                    {{ $expenses['coupon_discount_percentage'] }}% {{ translate('messages.of Total') }}
                </div>
            </div>
        </div>

        @if(($expenses['pro_discount'] ?? 0) > 0)
        <div class="col-lg-4 col-sm-6">
            <div class="item">
                <div class="flex-shrink-0 info rounded-10 w-40px aspect-1-1 d-flex justify-content-center align-items-center mb-3">
                    <img src="{{ asset('public/assets/admin/img/report/earning-breakdown/coupon-offers.svg') }}" alt="pro discount">
                </div>
                <div class="mb-2">{{ translate('messages.Pro_discount') }}</div>
                <h2 class="font-medium fs-24 fs-18-mobile mb-2">
                    {{ \App\CentralLogics\Helpers::format_currency($expenses['pro_discount']) }}
                </h2>
                <div class="fs-12 bg-light px-2 py-1 rounded-lg w-max-content">
                    {{ $expenses['pro_discount_percentage'] }}% {{ translate('messages.of Total') }}
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
