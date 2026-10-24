@extends('layouts.admin.app')

@section('title',translate('Provider summary report'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')

<div class="content container-fluid">

    <div class="page-header report-page-header">
        <div class="d-flex">
            <img src="{{asset('public/assets/admin/img/outline/report-search.svg')}}" class="page-header-icon" alt="">
            <div class="w-0 flex-grow-1 pl-3">
                <h1 class="page-header-title m-0">
                    {{translate('Provider report')}}
                </h1>
                <p class="page-header-desc">{{ translate('A provider\'s whole picture on one page, from trips to earnings to reviews.') }}</p>
                <span>
                    {{ translate('Monitor provider\'s business analytics & reports') }}
                </span>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs page-header-tabs mb-2">
        <li class="nav-item">
            <a href="{{route('admin.transactions.rental.report.provider-summary-report')}}" class="nav-link active">{{translate('Summary report')}}</a>
        </li>
        <li class="nav-item">
            <a href="{{route('admin.transactions.rental.report.provider-sales-report')}}" class="nav-link">{{translate('Vehicle report')}}</a>
        </li>
        <li class="nav-item">
            <a href="{{ route('admin.transactions.rental.report.provider-trip-report') }}" class="nav-link">{{translate('Trip report')}}</a>
        </li>
    </ul>

    <div class="card border-0 mb-3">
        <div class="card-body">
            <div class="statistics-btn-grp">
                <label>
                    <input type="radio" name="filter" value="all_time" {{ isset($filter) && $filter == "all_time" ? 'checked' : '' }} data-url="{{ url()->full() }}" data-filter="filter" class="set-filter" hidden>
                    <span>{{ translate('All time') }}</span>
                </label>
                <label>
                    <input type="radio" name="filter" value="this_year" {{ isset($filter) && $filter == "this_year" ? 'checked' : '' }} data-url="{{ url()->full() }}" data-filter="filter" class="set-filter" hidden>
                    <span>{{ translate('This year') }}</span>
                </label>
                <label>
                    <input type="radio" name="filter" value="previous_year" {{ isset($filter) && $filter == "previous_year" ? 'checked' : '' }} data-url="{{ url()->full() }}" data-filter="filter" class="set-filter" hidden>
                    <span>{{ translate('Previous year') }}</span>
                </label>
                <label>
                    <input type="radio" name="filter" value="this_month" {{ isset($filter) && $filter == "this_month" ? 'checked' : '' }} data-url="{{ url()->full() }}" data-filter="filter" class="set-filter" hidden>
                    <span>{{ translate('This month') }}</span>
                </label>
                <label>
                    <input type="radio" name="filter" value="this_week" {{ isset($filter) && $filter == "this_week" ? 'checked' : '' }} data-url="{{ url()->full() }}" data-filter="filter" class="set-filter" hidden>
                    <span>{{ translate('This week') }}</span>
                </label>
            </div>
        </div>
    </div>
    <div class="store-report-content">
        <div class="left-content">
            <div class="left-content-card">
                <img src="{{asset('/public/assets/admin/img/report/store.svg')}}" alt="">
                <div class="info">
                    <h4 class="subtitle">{{ $new_providers }}</h4>
                    <h6 class="subtext">{{ translate('Registered providers') }}</h6>
                </div>
            </div>
            <div class="left-content-card">
                <img src="{{asset('/public/assets/admin/img/report/cart.svg')}}" alt="">
                <div class="info">
                    <h4 class="subtitle">{{ $total_trips }}</h4>
                    <h6 class="subtext">{{ translate('Total trips') }}</h6>
                </div>
                <div class="coupon__discount w-100 text-right d-flex justify-content-between">
                    <div>
                        <strong class="text-danger">{{ $total_canceled }}</strong>
                        <div>{{ translate('Canceled') }}</div>
                    </div>
                    <div>
                        <strong>{{ $total_ongoing }}</strong>
                        <div>
                            {{ translate('Incomplete') }}
                        </div>
                    </div>
                    <div>
                        <strong class="text-success">{{ $total_completed }}</strong>
                        <div>
                            {{ translate('Completed') }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="left-content-card">
                <img src="{{asset('/public/assets/admin/img/report/product.svg')}}" alt="">
                <div class="info">
                    <h4 class="subtitle">{{ $vehicles }}</h4>
                    <h6 class="subtext">{{ translate('Total vehicles') }}</h6>
                </div>
            </div>
        </div>
        <div class="center-chart-area">
            <div class="center-chart-header">
                <h4 class="title">{{ translate('Total trips') }}</h4>
                <h5 class="subtitle">{{ translate('Average trip value') }}:
                    {{ $total_completed > 0 ? \App\CentralLogics\Helpers::number_format_short($total_trip_amount/ $total_completed) : 0 }}
                    <span class="input-label-secondary text--title" data-toggle="tooltip"
                    data-placement="right"
                    data-original-title="{{ translate('This average trip value is calculated from all completed trips.') }}">
                    <i class="tio-info-outined"></i>
                </span></h5>
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
                        <span>{{ translate('Completed payment statistics') }}</span>
                    </h5>
                </div>
                <div class="card-body px-0 pt-0">
                    <div class="position-relative pie-chart">
                        <div id="dognut-pie"></div>
                        <div class="total--orders">
                            <h3>{{ \App\CentralLogics\Helpers::number_format_short($total_trip_amount) }}
                            </h3>
                        </div>
                    </div>
                    <div class="apex-legends">
                        <div class="before-bg-107980">
                            <span>{{ translate('Cash payments') }}
                                ({{ count($trip_payment_methods)>0?\App\CentralLogics\Helpers::number_format_short(isset($trip_payment_methods[0])?$trip_payment_methods[0]->total_trip_amount:0):0 }})</span>
                        </div>
                        <div class="before-bg-56B98F">
                            <span>{{ translate('Digital payments') }} (
                                {{ count($trip_payment_methods)>0?\App\CentralLogics\Helpers::number_format_short(isset($trip_payment_methods[1])?$trip_payment_methods[1]->total_trip_amount:0):0 }})</span>
                        </div>
                        <div class="before-bg-E5F5F1">
                            <span>{{ translate('messages.Wallet') }}
                                ({{ count($trip_payment_methods)>0?\App\CentralLogics\Helpers::number_format_short(isset($trip_payment_methods[2])?$trip_payment_methods[2]->total_trip_amount:0):0 }})</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-11px card">
        <div class="card-header border-0 py-2">
            <div class="search--button-wrapper">
                <h5 class="card-title">{{translate('Total providers')}}
                    @if ($providers->total() > 0)
                        <span class="badge badge-soft-secondary ml-2 rounded-circle" id="itemCount">{{ $providers->total() }}</span>
                    @endif
                </h5>
                <form class="search-form">
                    <div class="input-group input--group">
                        <input id="datatableSearch_" type="search" name="search" class="form-control"
                                placeholder="{{translate('Ex') . ' : ' . translate('Search provider name')}}" value="{{ request()?->search ?? null}}" aria-label="{{translate('messages.Search')}}" required>
                        <button type="button" class="btn btn--secondary"><i class="tio-search"></i></button>

                    </div>
                </form>
                <div class="hs-unfold mr-2 flex-grow-0">
                    <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40" href="javascript:;"
                        data-hs-unfold-options='{
                                "target": "#usersExportDropdown",
                                "type": "css-animation"
                            }'>
                        <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
                    </a>

                    <div id="usersExportDropdown"
                        class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                        <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                        <a id="export-excel" class="dropdown-item" href="{{route('admin.transactions.rental.report.provider-summary-report-export', ['type'=>'excel',request()->getQueryString()])}}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                alt="Image Description">
                            Excel
                        </a>
                        <a id="export-csv" class="dropdown-item" href="{{route('admin.transactions.rental.report.provider-summary-report-export', ['type'=>'csv',request()->getQueryString()])}}">
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
                            <th class="border-top border-bottom text-capitalize">{{translate('SL')}}</th>
                            <th class="border-top border-bottom text-capitalize">{{translate('Provider')}}</th>
                            <th class="border-top border-bottom text-capitalize text-center">{{translate('Total amount')}}</th>
                            <th class="border-top border-bottom text-capitalize">{{translate('Total trips')}}</th>
                            <th class="border-top border-bottom text-capitalize">{{translate('Total completed trips')}}</th>
                            <th class="border-top border-bottom text-capitalize text-center">{{translate('Trip completion rate')}}</th>
                            <th class="border-top border-bottom text-capitalize text-center">{{translate('Ongoing trip rate')}}</th>
                            <th class="border-top border-bottom text-capitalize text-center">{{translate('Trip cancellation rate')}}</th>
                            <th class="border-top border-bottom text-capitalize text-center">{{translate('Action')}}</th>
                        </tr>
                    </thead>
                    <tbody id="set-rows">
                    @foreach ($providers as $k => $provider)
                        {{-- Aggregate columns from the controller; the trips relation is no
                             longer eager-loaded, because doing so pulled every trip of every
                             provider on the page into memory. --}}
                        @php($completed = (int) $provider->completed_trips_count)
                        @php($canceled = (int) $provider->canceled_trips_count)
                        @php($total = (int) $provider->trips_count)
                        <tr>
                            <td>{{$k+$providers->firstItem()}}</td>
                            <td>
                                <a href="{{route('admin.rental.provider.details', $provider->id)}}">{{ $provider->name }}</a>
                            </td>
                            <td class="text-center white-space-nowrap">
                                {{\App\CentralLogics\Helpers::number_format_short($provider->completed_trips_amount ?? 0)}}
                            </td>
                            <td class="text-center">
                                {{ $total }}
                            </td>
                            <td class="text-center">
                                {{ $completed }}
                            </td>
                            <td class="text-center white-space-nowrap">
                                {{ ($total > 0 && $completed > 0)? number_format((100*$completed)/$total, config('round_up_to_digit')): 0 }}%
                            </td>
                            <td class="text-center">
                                {{ ($total > 0 && $completed > 0)? number_format((100*($total-($completed+$canceled)))/$total, config('round_up_to_digit')): 0 }}%
                            </td>
                            <td class="text-center">
                                {{ ($total > 0 && $canceled > 0)? number_format((100*$canceled)/$total, config('round_up_to_digit')): 0 }}%
                            </td>
                            <td>
                                <div class="btn--container justify-content-center">
                                    <a href="{{route('admin.rental.provider.details', $provider->id)}}" class="btn action-btn action-btn--view">
                                        <i class="tio-visible-outlined"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if(count($providers) === 0)
                <div class="empty--data">
                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                    <h5>
                        {{translate('No data found')}}
                    </h5>
                </div>
                @endif
            </div>
            @if(count($providers) !== 0)
            <hr>
            <div class="page-area">
                {!! $providers->withQueryString()->links() !!}
            </div>
            @endif
        </div>
    </div>


</div>

@endsection


@push('script_2')
    <script src="{{asset('public/assets/admin')}}/vendor/chart.js/dist/Chart.min.js"></script>
    <script src="{{asset('public/assets/admin')}}/vendor/chart.js.extensions/chartjs-extensions.js"></script>
    <script src="{{asset('public/assets/admin')}}/vendor/chartjs-plugin-datalabels/dist/chartjs-plugin-datalabels.min.js"></script>
    <script src="{{asset('/public/assets/admin/js/apex-charts/apexcharts.js')}}"></script>
    <script src="{{asset('Modules/Rental/public/assets/js/admin/view-pages/provider-summary-report.js')}}"></script>

    <script>
        "use strict";
        window.chartData = {
        series: @json([
            $trip_payment_methods[0]->trip_count ?? 0,
            $trip_payment_methods[1]->trip_count ?? 0,
            $trip_payment_methods[2]->trip_count ?? 0
        ]),
        labels: @json([
            __('Cash Payments') . ' (' . ($trip_payment_methods[0]->total_trip_amount ?? 0) . ')',
            __('Digital Payments') . ' (' . ($trip_payment_methods[1]->total_trip_amount ?? 0) . ')',
            __('Wallet') . ' (' . ($trip_payment_methods[2]->total_trip_amount ?? 0) . ')'
        ])
    };

</script>


@endpush
