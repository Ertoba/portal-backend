@extends('layouts.admin.app')

@section('title', translate('Provider sales report'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')

    <div class="content container-fluid">
        @php
            $from = session('from_date');
            $to = session('to_date');
        @endphp

        <div class="page-header report-page-header">
            <div class="d-flex">
                <img src="{{ asset('public/assets/admin/img/outline/report-search.svg') }}" class="page-header-icon" alt="">
                <div class="w-0 flex-grow-1 pl-3">
                    <h1 class="page-header-title m-0">
                        {{ translate('Provider report') }}
                    </h1>
                    <p class="page-header-desc">{{ translate('What each provider sold over the period you choose.') }}</p>
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
                <a href="{{route('admin.transactions.rental.report.provider-sales-report')}}" class="nav-link active">{{translate('Vehicle report')}}</a>
            </li>
            <li class="nav-item">
                <a href="{{ route('admin.transactions.rental.report.provider-trip-report') }}" class="nav-link">{{translate('Trip report')}}</a>
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
                            <select name="provider_id" data-get-provider-url="{{route('admin.store.get-providers')}}"
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
                    <img src="{{ asset('/public/assets/admin/img/report/gross.svg') }}" alt="">
                    <div class="info">
                        <h4 class="subtitle">
                            {{ \App\CentralLogics\Helpers::number_format_short($trip_totals->trip_amount) }}</h4>
                        <h6 class="subtext">{{ translate('Total trip amount') }}</h6>
                    </div>
                </div>
                <div class="left-content-card">
                    <img src="{{ asset('/public/assets/admin/img/report/tax.svg') }}" alt="">
                    <div class="info">
                        <h4 class="subtitle">
                            {{ \App\CentralLogics\Helpers::number_format_short($trip_totals->tax_amount) }}</h4>
                        <h6 class="subtext">{{ translate('Total tax') }}</h6>
                    </div>
                </div>
                <div class="left-content-card">
                    <img src="{{ asset('/public/assets/admin/img/report/commission.svg') }}" alt="">
                    <div class="info">
                        <h4 class="subtitle">
                            {{ \App\CentralLogics\Helpers::number_format_short($trip_totals->admin_commission - $trip_totals->admin_expense) }}
                        </h4>
                        <h6 class="subtext">{{ translate('Total commission') }}</h6>
                    </div>
                </div>
            </div>
            <div class="center-chart-area">
                <div class="center-chart-header">
                    <h4 class="title">{{ translate('Total trip') }}</h4>
                    <h5 class="subtitle">{{ translate('Average trip value') }}:
                        {{ $trip_totals->trips_count > 0 ? \App\CentralLogics\Helpers::number_format_short($trip_totals->trip_amount / $trip_totals->trips_count) : 0 }}
                        <span class="input-label-secondary text--title" data-toggle="tooltip"
                    data-placement="right"
                    data-original-title="{{ translate('This average trip value is calculated from all completed trips.') }}">
                    <i class="tio-info-outined"></i>
                </span>
                    </h5>
                </div>


                <canvas id="updatingData" class="store-center-chart"
                    data-chart-labels='[{{ implode(",",$label) }}]'
                    data-chart-data='[{{ implode(",",$data) }}]'
                    data-chart-currency-symbol="{{ \App\CentralLogics\Helpers::currency_symbol() }}">
                </canvas>

            </div>
            <div class="right-content">
                <div class="card h-100 bg-white payment-statistics-shadow">
                    <div class="card-body d-flex flex-column justify-content-center">
                        <div class="earning-statistics-content">
                            <h6 class="subtitle">{{ translate('Total provider earnings') }}</h6>
                            <h3 class="title">
                                {{ \App\CentralLogics\Helpers::number_format_short($trip_totals->store_amount) }}
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-11px card">
            <div class="card-header border-0 py-2">
                <div class="search--button-wrapper">
                    <h5 class="card-title">{{ translate('Total trips') }}</h5>
                    <form class="search-form">
                        <div class="input-group input--group">
                            <input id="datatableSearch_" type="search" name="search" class="form-control"
                                placeholder="{{ translate('Search by vehicle') }}"
                                aria-label="{{ translate('messages.Search') }}" value="{{ request()?->search ?? null}}" required>
                            <button type="button" class="btn btn--secondary"><i class="tio-search"></i></button>

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
                                href="{{ route('admin.transactions.rental.report.provider-sales-report-export', ['type' => 'excel', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                    alt="Image Description">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item"
                                href="{{ route('admin.transactions.rental.report.provider-sales-report-export', ['type' => 'csv', request()->getQueryString()]) }}">
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
                    <table class="table table-borderless">
                        <thead class="thead-light white--space-false">
                            <tr>
                                <th class="border-top border-bottom text-capitalize">{{ translate('SL') }}</th>
                                <th class="border-top border-bottom text-capitalize">{{ translate('Vehicle information') }}</th>
                                <th class="border-top border-bottom text-capitalize text-center">{{ translate('Total trip') }}</th>
                                <th class="border-top border-bottom text-capitalize text-center">
                                    {{ translate('Total trip amount') }}</th>
                                <th class="border-top border-bottom text-capitalize text-center">
                                    {{ translate('Discount given') }}</th>
                                <th class="border-top border-bottom text-capitalize text-center">{{ translate('Action') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody id="set-rows">

                            @foreach ($vehicles as $key => $vehicle)
                                <tr>
                                    <td>{{ $key + $vehicles->firstItem() }}</td>
                                    <td>
                                        <a class="media align-vehicle-center"
                                            href="{{ route('admin.rental.provider.vehicle.details', $vehicle->id)}}">
                                            <div class="media-body">
                                                <h5 class="text-hover-primary mb-0">{{ $vehicle['name'] }}</h5>
                                            </div>
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        {{ $vehicle->trips_count ?? 0 }}
                                    </td>
                                    <td class="text-center">
                                        {{ \App\CentralLogics\Helpers::format_currency($vehicle->trips_sum_price) }}
                                    </td>
                                    <td class="text-center">
                                        {{ \App\CentralLogics\Helpers::format_currency($vehicle->total_discount) }}
                                    </td>
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a href="{{ route('admin.rental.provider.vehicle.details', $vehicle->id)}}"
                                                class="btn action-btn action-btn--view">
                                                <i class="tio-visible-outlined"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if (count($vehicles) === 0)
                        <div class="empty--data">
                            <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
                            <h5>
                                {{ translate('No data found') }}
                            </h5>
                        </div>
                    @endif
                </div>
                @if (count($vehicles) !== 0)
                    <hr>
                    <div class="page-area">
                        {!! $vehicles->withQueryString()->links() !!}
                    </div>
                @endif
            </div>


        </div>

        </div>
    @endsection


    @push('script')
    @endpush


    @push('script_2')
        <script src="{{ asset('public/assets/admin') }}/vendor/chart.js/dist/Chart.min.js"></script>
        <script src="{{ asset('public/assets/admin') }}/vendor/chart.js.extensions/chartjs-extensions.js"></script>
        <script src="{{ asset('public/assets/admin') }}/vendor/chartjs-plugin-datalabels/dist/chartjs-plugin-datalabels.min.js"> </script>
        <script src="{{asset('Modules/Rental/public/assets/js/admin/view-pages/provider-sales-report.js')}}"></script>

    @endpush
