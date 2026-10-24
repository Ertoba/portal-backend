@extends('layouts.admin.app')

@section('title', translate('Admin tax report'))

@section('trip_tax_report')
    active
@endsection

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/tax.css') }}">
@endpush

@section('content')

@php
    /* Income sources are a fixed set of platform revenue lines, so they map from
       literal keys — translate($key) would write whatever the query returned
       into the language file (§8). Anything unmapped falls back to a humanised
       key rather than vanishing. */
    $source_labels = [
        'admin_commission' => translate('Order commission'),
        'delivery_commission' => translate('messages.Delivery charge commission'),
        'delivery_fee_comission' => translate('messages.Delivery charge commission'),
        'service_charge' => translate('messages.Service charge'),
        'additional_charge' => translate('Additional charge'),
        'vendor_subscription' => translate('Vendor subscription'),
    ];
@endphp
    <div class="content container-fluid txr">


        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/outline/report.svg') }}" class="w--26" alt="">
                </span>
                <span>{{ translate('Trip tax report') }}</span>
            </h1>
            <p class="page-header-desc">{{ translate('The tax collected on trips, ready for your return.') }}</p>
        </div>
            <div class="txr-query">
                <form action="" method="get">
                <div class="txr-query__body">

                <div class="txr-note">
                    <i class="tio-info-outined"></i>
                    <span id="info_for_item">
                        {{ translate('Admin tax report') }}: {{ translate('Rental') }}. {{ translate('View the report for all modules separately') }}: <a href="{{ route('admin.transactions.report.getTaxReport') }}">{{ translate('All module combine tax report') }}</a>
                    </span>
                </div>

                    <div class="txr-fields">
                        {{-- One grid, not two hand-built columns: half these fields
                             toggle `d-none` with the calculation mode, and the old layout
                             ran the two columns on different rhythms, leaving holes where
                             the hidden ones were. --}}
                        <div class="txr-field ">
                            <label class="form-label">{{ translate('Date range type') }}</label>
                            <select name="date_range_type" id="date_range_type"
                                class="form-control js-select2-custom">
                                <option value="this_fiscal_year"
                                    {{ $date_range_type == 'this_fiscal_year' ? 'selected' : '' }}>
                                    {{ translate('This fiscal year') }}
                                </option>
                                <option value="custom" {{ $date_range_type == 'custom' ? 'selected' : '' }}>
                                    {{ translate('Custom') }}
                                </option>

                            </select>
                        </div>

                        <div class="txr-field {{ $date_range_type == 'custom' ? '' : 'd-none' }}" id="date_range">
                            <label class="form-label">{{ translate('Date range') }}</label>
                            <div class="txr-field__control txr-field__control--icon">
                                <i class="tio-calendar-month"></i>
                                <input type="text" class="form-control"
                                    name="dates" placeholder="{{ translate('Select date') }}">
                            </div>
                        </div>

                        <div class="txr-field ">
                            <label class="form-label">{{ translate('Select how to calculate tax') }}</label>
                            <select name="calculate_tax_on" id="calculate_tax_on"
                                class="form-control js-select2-custom">
                                <option {{ $calculate_tax_on == 'all_source' ? 'selected' : '' }}
                                    value="all_source">
                                    {{ translate('Same tax for all income sources') }}
                                </option>
                                <option {{ $calculate_tax_on == 'individual_source' ? 'selected' : '' }}
                                    value="individual_source">
                                    {{ translate('Different tax for different income sources') }}
                                </option>

                            </select>
                        </div>

                        <div class="txr-field {{ $calculate_tax_on == 'individual_source' ? '' : 'd-none' }}" id="calculate_commission_tax">
                            <label class="form-label">{{ translate('Tax on trip commission') }}</label>
                            <select name="tax_on_order_commission[]" id="select_customer_fiscal1"
                                class="form-control js-select2-custom" multiple="multiple"
                                placeholder="{{ translate('Type & select tax rate') }}">

                            </select>
                        </div>

                        <div class="txr-field {{ $calculate_tax_on == 'individual_source' ? '' : 'd-none' }}" id="calculate_service_charge_tax">
                            <label class="form-label">{{ translate('Tax on service charge') }}</label>
                            <select name="tax_on_service_charge[]" id="select_customer_fiscal-3"
                                class="form-control js-select2-custom" multiple="multiple"
                                placeholder="{{ translate('Type & select tax rate') }}">

                            </select>
                        </div>

                        <div class="txr-field {{ $calculate_tax_on == 'individual_source' ? 'd-none' : '' }}" id="calculate_tax_rate">
                            <label class="form-label">{{ translate('Select tax rate') }}</label>
                            <select {{ $calculate_tax_on == 'individual_source' ? '' : 'required' }}
                                name="tax_rate[]" id="select_customer_fiscal-5"
                                class="form-control js-select2-custom" multiple="multiple"
                                placeholder="{{ translate('Type & select tax rate') }}">

                            </select>
                        </div>
                    </div>
                </div>
                <div class="txr-query__foot">
                    <button type="reset" id="reset_button_id" class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('Reset') }}</button>
                    <button type="submit" class="btn btn--primary"><i class="tio-checkmark-circle-outlined"></i> {{ translate('Submit') }}</button>
                </div>
                </form>
            </div>
            @include('admin-views.report.tax-report.partials._summary-strip', ['tiles' => [
                [
                    'icon' => 'tio-money', 'tone' => 'income',
                    'value' => \App\CentralLogics\Helpers::format_currency($totalBase),
                    'label' => translate('Total income'),
                ],
                [
                    'icon' => 'tio-dollar-outlined', 'tone' => 'tax',
                    'value' => \App\CentralLogics\Helpers::format_currency($totalTax),
                    'label' => translate('Total tax'),
                ],
            ]])
            <div class="card">
                {{-- The bare `<h4>` became `_table-head`, so this table carries the
                     same title-plus-subtitle block as every other list in the panel.
                     The counter block went: there is no row selection here, so
                     "0 selected" could never show. --}}
                <div class="card-header border-0 py-2">
                    <div class="search--button-wrapper">
                        @include('partials._table-head', [
                            'title'    => translate('Tax report'),
                            'subtitle' => translate('messages.Tax collected on rental trips over the selected period.'),
                            'count'    => null,
                        ])


                        @include('admin-views.report.tax-report.partials._export-dropdown', [
                            'export_route' => 'admin.transactions.rental.report.adminTaxReportExport',
                            'export_target' => 'usersExportDropdown__rental',
                        ])
                    </div>
                </div>

                <div class="card-body p-0">
                <div class="table-responsive datatable-custom">
                    <table id="datatable"
                        class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table fz--14px">
                        <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{ translate('messages.SL') }}</th>
                                <th class="border-0">{{ translate('Income source') }}</th>
                                <th class="border-0">{{ translate('Total income') }}</th>
                                <th class="border-0">{{ translate('Total tax') }}</th>
                                <th class="border-0 text-center">{{ translate('Action') }}</th>
                            </tr>
                        </thead>

                        <tbody>
                            @php
                                $count = 1;
                            @endphp
                            @forelse ($combinedResults as $key => $item)
                                <tr>
                                    <td>
                                        {{ $count++ }}

                                    </td>
                                    <td>
                                        <span class="txr-source"><i class="tio-label-outlined"></i> {{ $source_labels[$key] ?? ucfirst(str_replace('_', ' ', $key)) }}</span>
                                    </td>
                                    <td>

                                        {{ \App\CentralLogics\Helpers::format_currency($item['total_base_amount']) }}
                                    </td>
                                    <td>
                                        @php
                                            $totalTaxAmount = collect($item['taxes'] ?? [])
                                                ->flatten(1)
                                                ->sum('total_tax_amount');
                                            $totalTax = collect($item['taxes'] ?? [])
                                                ->flatten(1)
                                                ->sum('tax_rate');
                                        @endphp
                                        <div class="d-flex flex-column gap-1">
                                            <div class="d-flex fz-14 gap-3 align-items-center title-clr">
                                                {{ translate('Total') }} ({{ $totalTax }}%): <span>
                                                    {{ \App\CentralLogics\Helpers::format_currency($totalTaxAmount) }}</span>
                                            </div>

                                            @foreach ($item['taxes'] as $taxName => $taxItems)
                                                @foreach ($taxItems as $tax)
                                                    <div class="d-flex fz-11 gap-3 align-items-center">
                                                        {{ $taxName }} ({{ $tax['tax_rate'] }}%) :
                                                        <span>{{ \App\CentralLogics\Helpers::format_currency($tax['total_tax_amount']) }}</span>
                                                    </div>
                                                @endforeach
                                            @endforeach

                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex gap-2 justify-content-center">
                                            <a class="btn btn-sm action-btn action-btn--view" target="_blank"
                                                href="{{ route('admin.transactions.rental.report.getTaxDetails', array_merge(request()->except(['page']), ['source' => $key])) }}">
                                                <i class="tio-visible-outlined"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                @include('admin-views.report.tax-report.partials._blank', [
                                    'blank_colspan' => 5,
                                    'blank_title' => translate('No tax report generated'),
                                    'blank_body' => translate('messages.To generate your tax report please select & input above field and submit for the result'),
                                ])
                            @endforelse

                        </tbody>
                    </table>
                </div>
                </div>
            </div>
    </div>



@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin/js/view-pages/tax-report-filters.js')}}"></script>
    <script src="{{asset('Modules/Rental/public/assets/js/admin/view-pages/admin-tax-report.js')}}"></script>
    <script>
        "use strict";

        $(function() {
            $('input[name="dates"]').daterangepicker({
                startDate: moment('{{ $startDate }}'),
                endDate: moment('{{ $endDate }}'),
                maxDate: moment(),
                locale: {
                    format: 'MM/DD/YYYY'
                }
            });
        });


        // `ready`, not `on('ready')`: the panel-wide `.js-select2-custom` pass in
        // admin.js is a `ready` callback, and those run after the `ready` event
        // handlers. Registered the other way round, this card's select2 config —
        // the tax list AJAX included — was built and then immediately replaced by
        // the panel-wide one, which is why the tax rate picker listed nothing.
        $(document).ready(function() {
            TaxReportFilters.initFixedSelect($('#date_range_type'));
            TaxReportFilters.initFixedSelect($('#calculate_tax_on'));

            const selectedTax = @json($selectedTax);
            Object.entries(selectedTax).forEach(([key, taxArray]) => {
                const $select = $(`select[name="${key}[]"]`);
                if (!$select.length || !Array.isArray(taxArray)) return;
                taxArray.forEach(tax => {
                    if (!tax.id || !tax.name) return;

                    const displayText = `${tax.name} (${tax.tax_rate}%)`;
                    const option = new Option(displayText, tax.id, true, true);
                    $select.append(option);
                });
                $select.trigger('change');
                TaxReportFilters.initTaxSelect($select, {
                    placeholder: "{{ translate('Type & select tax rate') }}",
                    ajax: {
                        url: '{{ route('admin.transactions.rental.report.getTaxList') }}',
                        data: function(params) {
                            return {
                                q: params.term,
                                page: params.page
                            };
                        },
                        processResults: function(data) {
                            return {
                                results: data
                            };
                        }
                    }
                });
            });
        });
    </script>
@endpush
