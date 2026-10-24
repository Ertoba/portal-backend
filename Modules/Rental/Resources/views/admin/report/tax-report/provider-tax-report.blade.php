@extends('layouts.admin.app')

@section('title', translate('Provider tax report'))

@section('provider_tax_report')
    active
@endsection
@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/tax.css') }}">
@endpush

@section('content')
    <div class="content container-fluid txr">


        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/outline/report.svg') }}" class="w--26" alt="">
                </span>
                <span>{{ translate('Provider tax report') }}</span>
            </h1>
            <p class="page-header-desc">{{ translate('The tax collected on each provider\'s trips, ready for your return.') }}</p>
        </div>
            <div class="txr-query">
                <form action="" method="get" id="txr-filter" class="txr-query__body">
                    {{-- One label element and one field wrapper for both controls: the
                         date field used `label.form-label` and the provider picker a bare
                         `span.title-clr`, so the two labels were different sizes and the
                         inputs under them started at different heights. --}}
                    <div class="txr-fields">
                        <div class="txr-field">
                            <label class="form-label" for="dates">{{ translate('Date range') }}</label>
@php
                                $dataRange = Carbon\Carbon::parse($startDate)->format('m/d/Y') . ' - ' . Carbon\Carbon::parse($endDate)->format('m/d/Y');
                            @endphp
                            <div class="txr-field__control txr-field__control--icon">
                                <i class="tio-calendar-month"></i>
                                <input type="text" id="dates" data-title="{{ translate('Select date range') }}" name="dates"
                                       value="{{ $dataRange ?? null }}" class="date-range-picker form-control">
                            </div>
                        </div>
                        <div class="txr-field">
                            <label class="form-label" for="provider_id">{{ translate('Select provider') }}</label>
                            <select name="provider_id" id="provider_id" data-placeholder="{{ translate('Select provider') }}"
                                data-get-provider-url="{{ route('admin.store.get-providers') }}"
                                data-zone-id="{{ isset($zone) ? $zone->id : '' }}"
                                data-module-id="{{ request('module_id') ?? '' }}" class="js-data-example-ajax form-control "
                                data-url="{{ url()->full() }}" data-filter="provider_id">
                                @if (isset($store))
                                <option value="{{ $store->id }}" data-verified="{{ (int) $store->verified_seller }}" selected>{{ $store->name }}</option>
                                @else
                                <option value="all" selected>{{ translate('All providers') }}</option>
                                @endif
                                </select>
                        </div>
                    </div>
                </form>
                <div class="txr-query__foot">
                    <button type="submit" form="txr-filter" class="btn btn--primary"><i class="tio-filter-list"></i> {{ translate('Filter') }}</button>
                </div>
            </div>
            @include('admin-views.report.tax-report.partials._summary-strip', ['tiles' => [
                [
                    'icon' => 'tio-receipt-outlined',
                    'value' => $totalOrders,
                    'label' => translate('Total trips'),
                ],
                [
                    'icon' => 'tio-money', 'tone' => 'income',
                    'value' => \App\CentralLogics\Helpers::format_currency($totalOrderAmount),
                    'label' => translate('Total trip amount'),
                ],
                [
                    'icon' => 'tio-dollar-outlined', 'tone' => 'tax',
                    'value' => \App\CentralLogics\Helpers::format_currency($totalTax),
                    'label' => translate('Total tax amount'),
                ],
            ]])
            <div class="card">
                {{-- The bare `<h4>` became `_table-head`, so this table carries the
                     same title-plus-subtitle block as every other list in the panel. --}}
                <div class="card-header border-0 py-2">
                    <div class="search--button-wrapper">
                        @include('partials._table-head', [
                            'title'    => translate('Provider tax report'),
                            'subtitle' => translate('messages.Tax collected per provider over the selected period.'),
                            'count'    => null,
                        ])
                        <form class="search-form min--260">
                            <div class="input-group input--group">
                                <input id="datatableSearch_" type="search" name="search" class="form-control h--40px"
                                    placeholder="{{ translate('messages.Ex') . ': ' . translate('Name') }} "
                                    value="{{ request()?->search ?? null }}"
                                    aria-label="{{ translate('messages.Search') }}">
                                    <input type="hidden" name="dates" value="{{ $dateRange}}">
                                <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                            </div>
                        </form>

                        <div class="hs-unfold mr-2 flex-grow-0">
                            <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle h--40px" href="javascript:;"
                                data-hs-unfold-options='{
                            "target": "#usersExportDropdown", "type": "css-animation" }'>
                                <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
                            </a>
                            <div id="usersExportDropdown"
                                class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                                <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                                <a id="export-excel" class="dropdown-item"
                                    href="{{ route('admin.transactions.rental.report.providerWiseTaxExport', array_merge(request()->except(['page', 'export_type']), ['export_type' => 'excel'])) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                        alt="Image Description">
                                    Excel
                                </a>
                                <a id="export-csv" class="dropdown-item"
                                    href="{{ route('admin.transactions.rental.report.providerWiseTaxExport', array_merge(request()->except(['page', 'export_type']), ['export_type' => 'csv'])) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                        alt="Image Description">
                                    CSV
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive datatable-custom">
                    <table id="datatable"
                        class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table fz--14px">
                        <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{ translate('SL') }}</th>
                                <th class="border-0">{{ translate('Provider information') }}</th>
                                <th class="border-0">{{ translate('Total trips') }}</th>
                                <th class="border-0">{{ translate('Total trip amount') }}</th>
                                <th class="border-0">{{ translate('Tax amount') }}</th>
                                <th class="border-0 text-end">{{ translate('Action') }}</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($stores as $key => $store)
                                <tr>
                                    <td>
                                        {{ $key + $stores->firstItem() }}
                                    </td>
                                    <td>
                                        <span class="fz-14 title-clr">
                                            <a href="{{ route('admin.rental.provider.details', $store->store_id) }}" target="_blank"
                                                rel="noopener noreferrer"> {{ $store->store_name }}</a>

                                            <span class="fz-11 d-block"> <a href="tel:{{ $store->store_phone }}">
                                                    {{ $store->store_phone }}</a></span>
                                        </span>
                                    </td>
                                    <td>
                                        {{ $store->total_orders }}
                                    </td>
                                    <td>
                                        {{ \App\CentralLogics\Helpers::format_currency($store->total_order_amount) }}
                                    </td>
                                    <td>
                                        @php($sum_tax_amount=collect($store->tax_data)->sum('total_tax_amount'))

                                        <div class="d-flex flex-column gap-1">
                                            @if ($store->store_total_tax_amount - $sum_tax_amount> 0 )

                                            <div class="d-flex fz-14 gap-3 align-items-center title-clr">
                                                {{ translate('Tax amount') }}: <span>
                                                    {{ \App\CentralLogics\Helpers::format_currency($store->store_total_tax_amount - $sum_tax_amount) }}</span>
                                            </div>
                                            @endif
                                            @if ($sum_tax_amount > 0 )
                                                <div class="d-flex fz-14 gap-3 align-items-center title-clr">
                                                    {{ translate('Sum of taxes') }}: <span>
                                                    {{ \App\CentralLogics\Helpers::format_currency($sum_tax_amount) }}</span>
                                                </div>
                                                @foreach ($store->tax_data as $tax)
                                                    <div class="d-flex fz-11 gap-3 align-items-center">
                                                        {{ $tax['tax_name'] }}:
                                                        <span>{{ \App\CentralLogics\Helpers::format_currency($tax['total_tax_amount']) }}
                                                    </span>
                                                    </div>
                                                @endforeach

                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <a class="btn btn-sm action-btn action-btn--view" target="_blank"
                                                href="{{ route('admin.transactions.rental.report.providerTax', ['id' => $store->store_id, 'dates' => $dateRange]) }}">
                                                <i class="tio-visible-outlined"></i>
                                            </a>
                                            <a class="btn btn-sm action-btn success-border btn-outline-varify text-success"
                                                href="{{ route('admin.transactions.rental.report.providerTaxExport', array_merge(request()->except(['page', 'export_type']), ['export_type' => 'excel', 'id' => $store->store_id])) }}">
                                                <svg width="11" height="12" viewBox="0 0 11 12" fill="none"
                                                    xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M9.87499 4.31958H7.37499V0.56958H3.625V4.31958H1.125L5.5 9.31957L9.87499 4.31958ZM0.5 10.5696H10.5V11.8196H0.5V10.5696Z"
                                                        fill="#04BB7B" />
                                                </svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach

                        </tbody>
                    </table>
                </div>
                @if (count($stores) !== 0)
                    <hr>
                @endif
                <div class="page-area">
                    {!! $stores->links() !!}
                </div>
                @if (count($stores) === 0)
                    <div class="empty--data">
                        <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
                        <h5>
                            {{ translate('No data found') }}
                        </h5>
                    </div>
                @endif
            </div>
    </div>

</div>

@endsection

@push('script_2')
    <script src="{{ asset('Modules/Rental/public/assets/js/admin/view-pages/provider-tax-report.js') }}"></script>
@endpush
