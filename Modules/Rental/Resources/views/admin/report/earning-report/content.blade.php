@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('Modules/Rental/public/assets/css/admin/rental-earning-report.css') }}">
@endpush

@push('script_2')
    <script src="{{ asset('public/assets/admin/apexcharts/apexcharts.min.js') }}"></script>
    <script src="{{ asset('public/assets/admin/js/view-pages/apex-charts.js') }}"></script>
    <script src="{{ asset('Modules/Rental/public/assets/js/admin/view-pages/admin-earning-report.js') }}"></script>
@endpush

@php
    $resetUrl = route('admin.transactions.report.admin-earning-report', ['tab' => 'rental']);
    $currentModuleId = request('module_id', 'all');
@endphp

<div
    id="rental-admin-earning-report"
    data-summary-url="{{ route('admin.transactions.rental.report.admin-earning-summary') }}"
    data-breakdown-url="{{ route('admin.transactions.rental.report.admin-earning-breakdown') }}"
    data-expense-url="{{ route('admin.transactions.rental.report.admin-expense-breakdown') }}"
    data-trend-url="{{ route('admin.transactions.rental.report.admin-monthly-earnings') }}"
    data-top-provider-url="{{ route('admin.transactions.rental.report.admin-top-earning-providers') }}"
    data-zone-wise-url="{{ route('admin.transactions.rental.report.admin-zone-wise-earnings') }}"
    data-transactions-url="{{ route('admin.transactions.rental.report.admin-earning-transactions') }}"
    data-export-url="{{ route('admin.transactions.rental.report.admin-earning-export') }}"
    data-currency-code="{{ \App\CentralLogics\Helpers::currency_code() }}"
    data-currency-symbol="{{ \App\CentralLogics\Helpers::currency_symbol() }}"
    data-currency-position="{{ \App\CentralLogics\Helpers::get_business_settings('currency_symbol_position') ?? 'left' }}"
    data-currency-decimals="{{ (int) config('round_up_to_digit') }}"
    data-placeholder-order="{{ translate('messages.Search_by_Transaction_ID_or_Provider') }}"
    data-placeholder-expense="{{ translate('messages.Search_by_Transaction_ID_or_Provider_or_Expense_Source') }}"
    data-placeholder-subscription="{{ translate('messages.Search_by_Transaction_ID_or_Provider_or_Subscription_Type') }}"
>
    @include('rental::admin.report.earning-report.partials._filter', [
        'resetUrl' => $resetUrl,
        'currentModuleId' => $currentModuleId,
    ])

    <div class="card card-body mb-20">
        @include('rental::admin.report.earning-report.partials._summary-section')
        @include('rental::admin.report.earning-report.partials._earning-breakdown-section')
        @include('rental::admin.report.earning-report.partials._expense-breakdown-section')
    </div>

    @include('rental::admin.report.earning-report.partials._trend-section')
    <div class="row g-3 mb-20">
        @include('rental::admin.report.earning-report.partials._earning-vs-expense-section')
        @include('rental::admin.report.earning-report.partials._earning-by-source-section')
    </div>
    <div class="row g-3 mb-20">
        @include('rental::admin.report.earning-report.partials._top-earning-provider-section')
        @include('rental::admin.report.earning-report.partials._zone-wise-earnings-section')
    </div>
    @include('rental::admin.report.earning-report.partials._recent-transactions')
</div>
