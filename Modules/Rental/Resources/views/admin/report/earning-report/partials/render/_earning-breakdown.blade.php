@php($additionalChargeLabel = \App\CentralLogics\Helpers::get_business_data('additional_charge_name') ?? translate('messages.additional_charge'))
<div class="border rounded-10 p-3 p-xxl-20 mb-20">
    <div class="row g-lg-5 gx-3 gy-3 earnings-breakdown">
        <div class="col-lg-4 col-md-6 col-sm-6">
            <div class="item border-right">
                <div class="flex-shrink-0 info rounded-10 w-40px aspect-1-1 d-flex justify-content-center align-items-center mb-3">
                    <img src="{{ asset('public/assets/admin/img/report/earning-breakdown/order-commission.svg') }}" alt="trip commission">
                </div>
                <div class="mb-2">{{ translate('Trip Commission') }}</div>
                <h2 class="font-medium fs-24 fs-18-mobile mb-2">
                    {{ \App\CentralLogics\Helpers::format_currency($earnings['trip_commission']) }}
                </h2>
                <div class="fs-12 bg-light px-2 py-1 rounded-lg w-max-content">
                    {{ $earnings['trip_commission_percentage'] }}% {{ translate('messages.of Total') }}
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6 col-sm-6">
            <div class="item border-right">
                <div class="flex-shrink-0 purple rounded-10 w-40px aspect-1-1 d-flex justify-content-center align-items-center mb-3">
                    <img src="{{ asset('public/assets/admin/img/report/earning-breakdown/subscription.svg') }}" alt="subscription">
                </div>
                <div class="mb-2">{{ translate('messages.Subscription') }}</div>
                <h2 class="font-medium fs-24 fs-18-mobile mb-2">
                    {{ \App\CentralLogics\Helpers::format_currency($earnings['subscription_earning']) }}
                </h2>
                <div class="fs-12 bg-light px-2 py-1 rounded-lg w-max-content">
                    {{ $earnings['subscription_percentage'] }}% {{ translate('messages.of Total') }}
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6 col-sm-6">
            <div class="item">
                <div class="flex-shrink-0 warning rounded-10 w-40px aspect-1-1 d-flex justify-content-center align-items-center mb-3">
                    <img src="{{ asset('public/assets/admin/img/report/earning-breakdown/additional-fees.svg') }}" alt="additional charge">
                </div>
                <div class="mb-2">{{ $additionalChargeLabel }}</div>
                <h2 class="font-medium fs-24 fs-18-mobile mb-2">
                    {{ \App\CentralLogics\Helpers::format_currency($earnings['additional_charge']) }}
                </h2>
                <div class="fs-12 bg-light px-2 py-1 rounded-lg w-max-content">
                    {{ $earnings['additional_charge_percentage'] }}% {{ translate('messages.of Total') }}
                </div>
            </div>
        </div>
    </div>
</div>
