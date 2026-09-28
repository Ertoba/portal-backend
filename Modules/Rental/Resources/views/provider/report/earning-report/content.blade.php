@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('Modules/Rental/public/assets/css/provider/rental-earning-report.css') }}">
@endpush

@push('script_2')
    <script src="{{ asset('public/assets/admin/apexcharts/apexcharts.min.js') }}"></script>
    <script src="{{ asset('public/assets/admin/js/view-pages/apex-charts.js') }}"></script>
    <script src="{{ asset('Modules/Rental/public/assets/js/view-pages/provider/earning-report.js') }}"></script>
@endpush

<div
    id="rental-provider-earning-report"
    data-summary-url="{{ $summary_url }}"
    data-breakdown-url="{{ $breakdown_url }}"
    data-expense-url="{{ $expense_url }}"
    data-trend-url="{{ $trend_url }}"
    data-transactions-url="{{ $transactions_url }}"
    data-export-url="{{ $transactions_export_url }}"
    data-placeholder-order="{{ translate('messages.Search_by_Transaction_ID_or_Provider') }}"
    data-placeholder-expense="{{ translate('messages.Search_by_Transaction_ID_or_Provider_or_Expense_Source') }}"
    data-placeholder-subscription="{{ translate('messages.Search_by_Transaction_ID_or_Provider_or_Subscription_Type') }}"
>
    @include('rental::provider.report.earning-report.partials._filter')

    <div class="card card-body mb-20">
        @include('rental::provider.report.earning-report.partials._summary-section')
        @include('rental::provider.report.earning-report.partials._earning-breakdown-section')
        @include('rental::provider.report.earning-report.partials._expense-breakdown-section')
    </div>

    @include('rental::provider.report.earning-report.partials._trend-section')

    @include('rental::provider.report.earning-report.partials._recent-transactions')
</div>
