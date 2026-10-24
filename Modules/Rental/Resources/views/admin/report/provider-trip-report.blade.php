@extends('layouts.admin.app')

@section('title', translate('Provider trip report'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')

    <div class="content container-fluid">

        <div class="page-header report-page-header">
            <div class="d-flex">
                <img src="{{ asset('public/assets/admin/img/outline/report-search.svg') }}" class="page-header-icon" alt="">
                <div class="w-0 flex-grow-1 pl-3">
                    <h1 class="page-header-title m-0">
                        {{ translate('Provider wise report') }}
                    </h1>
                    <p class="page-header-desc">{{ translate('How each provider is doing, by trips run, money made and trips lost.') }}</p>
                    <span>
                        {{ translate('Monitor provider\'s business analytics & reports') }}
                    </span>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs page-header-tabs mb-2">
            <li class="nav-item">
                <a href="{{route('admin.transactions.rental.report.provider-summary-report')}}" class="nav-link">{{translate('Summary report')}}</a>
            </li>
            <li class="nav-item">
                <a href="{{route('admin.transactions.rental.report.provider-sales-report')}}" class="nav-link">{{translate('Vehicle report')}}</a>
            </li>
            <li class="nav-item">
                <a href="{{ route('admin.transactions.rental.report.provider-trip-report') }}" class="nav-link active">{{translate('Trip report')}}</a>
            </li>
        </ul>

        <div class="card filter--card">
            <div class="card-body p-xl-5">
                <h5 class="form-label m-0 mb-3">
                    {{ translate('Filter data') }}
                </h5>
                <form action="{{ route('admin.transactions.rental.report.set-date') }}" method="post">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-4 col-sm-6">
                            <select name="zone_id" class="form-control js-select2-custom set-filter" data-url="{{ url()->full() }}" data-filter="zone_id" id="zone">
                                <option value="all">{{ translate('All zones') }}</option>
                                @foreach (\App\CentralLogics\Helpers::zones_dropdown() as $z)
                                    <option value="{{ $z['id'] }}"
                                        {{ isset($zone) && $zone->id == $z['id'] ? 'selected' : '' }}>
                                        {{ $z['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <select name="provider_id"
                                    data-get-provider-url="{{route('admin.store.get-providers')}}"
                                    data-zone-id="{{ isset($zone) ? $zone->id : '' }}"
                                data-placeholder="{{ translate('Select provider') }}"
                                class="js-data-example-ajax form-control set-filter" data-url="{{ url()->full() }}" data-filter="provider_id">
                                @if (isset($provider))
                                    <option value="{{ $provider->id }}" selected>{{ $provider->name }}</option>
                                @else
                                    <option value="all" selected>{{ translate('All providers') }}</option>
                                @endif
                            </select>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <select class="form-control set-filter" data-url="{{ url()->full() }}" data-filter="filter" name="filter">
                                <option value="all_time" {{ isset($filter) && $filter == 'all_time' ? 'selected' : '' }}>
                                    {{ translate('All time') }}</option>
                                <option value="this_year" {{ isset($filter) && $filter == 'this_year' ? 'selected' : '' }}>
                                    {{ translate('This year') }}</option>
                                <option value="previous_year"
                                    {{ isset($filter) && $filter == 'previous_year' ? 'selected' : '' }}>{{ translate('Previous year') }}
                                </option>
                                <option value="this_month"
                                    {{ isset($filter) && $filter == 'this_month' ? 'selected' : '' }}>{{ translate('This month') }}</option>
                                <option value="this_week" {{ isset($filter) && $filter == 'this_week' ? 'selected' : '' }}>
                                    {{ translate('This week') }}</option>
                                <option value="custom" {{ isset($filter) && $filter == 'custom' ? 'selected' : '' }}>
                                    {{ translate('Custom') }}</option>
                            </select>
                        </div>
                        @if (isset($filter) && $filter == 'custom')
                        <div class="col-md-4 col-sm-6">
                            <input type="date" name="from" id="from_date"
                                {{ session()->has('from_date') ? 'value=' . session('from_date') : '' }}
                                class="form-control" required>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <input type="date" name="to" id="to_date"
                                {{ session()->has('to_date') ? 'value=' . session('to_date') : '' }} class="form-control"
                                required>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <button type="submit" class="btn btn--primary btn-block"><i class="tio-visible-outlined"></i> {{ translate('Show data') }}</button>
                        </div>
                        @endif
                    </div>
                </form>
            </div>
        </div>


        <div class="store-report-content mt-11px">
            <div class="left-content">
                <div class="left-content-card">
                    <img src="{{ asset('/public/assets/admin/img/report/cart.svg') }}" alt="">
                    <div class="info">
                        <h4 class="subtitle">{{ $trips_count }}</h4>
                        <h6 class="subtext">{{ translate('Total trip') }}</h6>
                    </div>
                </div>
                <div class="left-content-card">
                    <img src="{{ asset('/public/assets/admin/img/report/total-order.svg') }}" alt="">
                    <div class="info">
                        <h4 class="subtitle">{{ \App\CentralLogics\Helpers::number_format_short($total_trip_amount) }}
                        </h4>
                        <h6 class="subtext">{{ translate('Total trip amount') }}</h6>
                    </div>
                    <div class="coupon__discount w-100 text-right d-flex justify-content-between">
                        <div>
                            <strong class="text-danger">{{ \App\CentralLogics\Helpers::number_format_short($total_canceled) }}</strong>
                            <div>{{ translate('Canceled') }}</div>
                        </div>
                        <div>
                            <strong>{{ \App\CentralLogics\Helpers::number_format_short($total_ongoing) }}</strong>
                            <div>
                                {{ translate('Incomplete') }}
                            </div>
                        </div>
                        <div>
                            <strong class="text-success">{{ \App\CentralLogics\Helpers::number_format_short($total_completed) }}</strong>
                            <div>
                                {{ translate('Completed') }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="left-content-card">
                    <img src="{{ asset('/public/assets/admin/img/report/total-discount.svg') }}" alt="">
                    <div class="info">
                        <h4 class="subtitle">
                            {{ \App\CentralLogics\Helpers::number_format_short($total_coupon_discount + $total_product_discount + ($total_pro_discount ?? 0)) }}
                        </h4>
                        <h6 class="subtext">{{ translate('Total discount given') }}</h6>
                    </div>
                    <div class="coupon__discount w-100 text-right d-flex justify-content-between">
                        <div>
                            <strong>{{ \App\CentralLogics\Helpers::number_format_short($total_coupon_discount) }}</strong>
                            <div>{{ translate('Coupon discount') }}</div>
                        </div>
                        <div>
                            <strong>{{ \App\CentralLogics\Helpers::number_format_short($total_product_discount) }}</strong>
                            <div>
                                {{ translate('Vehicle discount') }}
                            </div>
                        </div>
                        <div>
                            <strong>{{ \App\CentralLogics\Helpers::number_format_short($total_pro_discount ?? 0) }}</strong>
                            <div>
                                {{ translate('messages.Pro discount') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="center-chart-area">
                <div class="center-chart-header">
                    <h4 class="title">{{ translate('Total trips') }}</h4>
                    <h5 class="subtitle">{{ translate('Average trip value') }}:
                        {{ $trips->count() > 0 ? \App\CentralLogics\Helpers::number_format_short($total_trip_amount / $trips->total()) : 0 }}
                        <span class="input-label-secondary text--title" data-toggle="tooltip"
                    data-placement="right"
                    data-original-title="{{ translate('This average trip value is calculated from all completed trips.') }}">
                    <i class="tio-info-outined"></i>
                </span>
                    </h5>
                </div>

                <canvas id="updatingData" class="store-center-chart"
                data-chart-labels='[{{ implode(',', $label) }}]'
                data-chart-data='[{{ implode(',', $data) }}]'
                data-chart-currency-symbol="{{ \App\CentralLogics\Helpers::currency_symbol() }}">
        </canvas>
            </div>
            <div class="right-content">
                <div class="card h-100 bg-white payment-statistics-shadow">
                    <div class="card-header border-0 ">
                        <h5 class="card-title">
                            <span>{{ translate('Trip statistics') }}</span>
                        </h5>
                    </div>
                    <div class="card-body px-0 pt-0">
                        <div class="position-relative pie-chart">
                            <div id="dognut-pie"></div>
                            <div class="total--orders">
                                <h3>{{ $trips_count }}
                                </h3>
                                <span>{{ translate('messages.Trips') }}</span>
                            </div>
                        </div>
                        <div class="apex-legends">
                            <div class="before-bg-107980">
                                <span>{{ translate('Total canceled') }}
                                    ({{ $total_canceled_count }})</span>
                            </div>
                            <div class="before-bg-56B98F">
                                <span>{{ translate('Total ongoing') }} (
                                    {{ $total_ongoing_count }})</span>
                            </div>
                            <div class="before-bg-E5F5F1">
                                <span>{{ translate('Total completed') }}
                                    ({{ $total_completed_count }})</span>
                            </div>
                        </div>
                        <div class="earning-statistics-content mt-3">
                            <a href="{{ route('admin.rental.trip.list', ['status' =>'all', 'module_id' => $rentalModuleId]) }}" class="trx-btn">{{ translate('View all trips') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-11px card">
            <div class="card-header border-0 py-2">
                <div class="search--button-wrapper">
                    <h5 class="card-title">{{ translate('Total trip') }}</h5>
                    <form class="search-form">
                        <div class="input-group input--group">
                            <input id="datatableSearch_" type="search" name="search" class="form-control"
                                placeholder="{{ translate('Search by ID') }}"
                                aria-label="{{ translate('messages.Search') }}" value="{{ request()?->search ?? null}}" required>
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>

                        </div>
                    </form>
                    <div class="hs-unfold mr-2 flex-grow-0">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40"
                            href="javascript:;"
                            data-hs-unfold-options='{
                                "target": "#usersExportDropdown",
                                "type": "css-animation"
                            }'>
                            <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
                        </a>

                        <div id="usersExportDropdown"
                            class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                            <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                            <a id="export-excel" class="dropdown-item"
                                href="{{ route('admin.transactions.rental.report.provider-trip-report-export', ['type' => 'excel', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                    alt="Image Description">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item"
                                href="{{ route('admin.transactions.rental.report.provider-trip-report-export', ['type' => 'csv', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                    alt="Image Description">
                                CSV
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-borderless middle-align __txt-14px">
                        <thead class="thead-light white--space-false">
                            <tr>
                                <th class="border-top border-bottom text-capitalize">{{ translate('SL') }}</th>
                                <th class="border-top border-bottom text-capitalize">{{ translate('Trip ID') }}</th>
                                <th class="border-top border-bottom text-capitalize">{{ translate('Trip booking date') }}</th>
                                <th class="border-top border-bottom text-capitalize">{{ translate('Trip schedule date') }}</th>
                                <th class="border-top border-bottom text-capitalize">{{ translate('Customer information') }}</th>
                                <th class="border-top border-bottom text-capitalize">{{ translate('Total trip amount') }}</th>
                                <th class="border-top border-bottom text-capitalize text-center">
                                    {{ translate('Discount') }}</th>
                                <th class="border-top border-bottom text-capitalize text-center">{{ translate('tax') }}
                                </th>
                                <th class="border-top border-bottom text-capitalize text-center">
                                    {{ \App\CentralLogics\Helpers::get_business_data('additional_charge_name')??translate('Additional charge') }}</th>
                                <th class="border-top border-bottom text-capitalize text-center">{{ translate('Action') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody id="set-rows">
                            @foreach ($trips as $key => $trip)
                                <tr class="status-{{ $trip['trip_status'] }} class-all">
                                    <td class="">
                                        {{ $key + $trips->firstItem() }}
                                    </td>
                                    <td class="table-column-pl-0">
                                        <a
                                            href="{{ route('admin.rental.trip.details', ['id' => $trip->id, 'module_id' => $rentalModuleId]) }}">{{ $trip['id'] }}</a>
                                    </td>
                                    <td>
                                        <div>
                                            <div>
                                                {{ date('d M Y', strtotime($trip['created_at'])) }}
                                            </div>
                                            <div class="d-block text-uppercase">
                                                {{ date(config('timeformat'), strtotime($trip['created_at'])) }}
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <div>
                                                {{ date('d M Y', strtotime($trip['schedule_at'])) }}
                                            </div>
                                            <div class="d-block text-uppercase">
                                                {{ date(config('timeformat'), strtotime($trip['schedule_at'])) }}
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if($trip->is_guest)
                                        @php($customer_details = $trip['user_info'])
                                        <strong>{{$customer_details['contact_person_name']}}</strong>
                                        <div>{{$customer_details['contact_person_number']}}</div>
                                        @elseif ($trip->customer)
                                        <a class="text-body text-capitalize"
                                            href="{{ route('admin.users.customer.view', [$trip['user_id']]) }}">
                                            <strong>{{ $trip->customer['f_name'] . ' ' . $trip->customer['l_name'] }}</strong>
                                            <div>{{ $trip->customer['phone'] }}</div>
                                        </a>
                                        @else
                                            <label class="badge badge-danger">{{ translate('messages.Invalid customer data') }}</label>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="text-right mw--85px">
                                            <div>
                                                {{ \App\CentralLogics\Helpers::number_format_short($trip['trip_amount']) }}
                                            </div>
                                            @if ($trip->payment_status == 'paid')
                                                <strong class="text-success">
                                                    {{ translate('messages.paid') }}
                                                </strong>
                                            @else
                                                <strong class="text-danger">
                                                    {{ translate('messages.unpaid') }}
                                                </strong>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-center mw--85px">
                                        {{ \App\CentralLogics\Helpers::number_format_short($trip['coupon_discount_amount'] + $trip['discount_on_trip']  + $trip['ref_bonus_amount'] + ($trip->proDiscount?->amount_saved ?? 0)) }}
                                    </td>
                                    <td class="text-center mw--85px">
                                        {{ \App\CentralLogics\Helpers::number_format_short($trip['tax_amount']) }}
                                    </td>
                                    <td class="text-center mw--85px">
                                        {{ \App\CentralLogics\Helpers::number_format_short($trip['additional_charge']) }}
                                    </td>

                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="ml-2 btn btn-sm action-btn action-btn--view"
                                                href="{{ route('admin.rental.trip.details', ['id' => $trip->id, 'module_id' => $rentalModuleId]) }}">
                                                <i class="tio-visible-outlined"></i>
                                            </a>
                                            <a class="ml-2 btn btn-sm btn--primary btn-outline-primary action-btn"
                                                href="{{ route('admin.rental.trip.generate-invoice', ['id' => $trip['id'], 'module_id' => $rentalModuleId]) }}">
                                                <i class="tio-print"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if (count($trips) === 0)
                        <div class="empty--data">
                            <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
                            <h5>
                                {{ translate('No data found') }}
                            </h5>
                        </div>
                    @endif
                </div>
                @if (count($trips) !== 0)
                    <hr>
                    <div class="page-area">
                        {!! $trips->withQueryString()->links() !!}
                    </div>
                @endif


            </div>
        </div>


    </div>

@endsection


@push('script_2')
    <script src="{{ asset('public/assets/admin') }}/vendor/chart.js/dist/Chart.min.js"></script>
    <script src="{{ asset('public/assets/admin') }}/vendor/chart.js.extensions/chartjs-extensions.js"></script>
    <script  src="{{ asset('public/assets/admin') }}/vendor/chartjs-plugin-datalabels/dist/chartjs-plugin-datalabels.min.js"> </script>
    <script src="{{ asset('/public/assets/admin/js/apex-charts/apexcharts.js') }}"></script>
    <script src="{{asset('Modules/Rental/public/assets/js/admin/view-pages/provider-trip-report.js')}}"></script>
    <script>
        "use strict";
            window.chartData = {
                series: @json([$total_canceled_count, $total_ongoing_count, $total_completed_count]),
                labels: @json([
                    __('Total canceled') . ' (' . $total_canceled_count . ')',
                    __('Total ongoing') . ' (' . $total_ongoing_count . ')',
                    __('Total delivered') . ' (' . $total_completed_count . ')'
                ])
            };
    </script>
@endpush
