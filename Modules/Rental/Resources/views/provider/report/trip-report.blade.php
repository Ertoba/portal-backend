@extends('layouts.vendor.app')

@section('title', translate('Trip report'))

@section('content')
    @php
        $vendorData = \App\CentralLogics\Helpers::get_store_data();
        $vendor = $vendorData?->module_type;
        $title = $vendor == 'rental' ? 'Provider' : 'Store';
        $orderOrTrip = $vendor == 'rental' ? 'trip' : 'order';
    @endphp

    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('/public/assets/admin/img/outline/report-search.svg')}}" class="w--26" alt="">
                </span>
                <span>
                    {{ translate('Trip report') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('How many trips ran, what they were worth and how many fell through.') }}</p>
        </div>

        <div class="card mb-20">
            <div class="card-body">
                <h4 class="">{{ translate('Search data') }}</h4>
                <form action="{{ route('vendor.report.set-date') }}" method="post">
                    @csrf
                    <div class="row g-3">
                        <div class="col-sm-6 col-md-3">
                            <select class="form-control set-filter" data-url="{{ url()->full() }}" data-filter="filter" name="filter">
                                <option value="all_time" {{ isset($filter) && $filter == 'all_time' ? 'selected' : '' }}>
                                    {{ translate('All time') }}</option>
                                <option value="this_year" {{ isset($filter) && $filter == 'this_year' ? 'selected' : '' }}>
                                    {{ translate('This year') }}</option>
                                <option value="previous_year"
                                    {{ isset($filter) && $filter == 'previous_year' ? 'selected' : '' }}>
                                    {{ translate('Previous year') }}</option>
                                <option value="this_month"
                                    {{ isset($filter) && $filter == 'this_month' ? 'selected' : '' }}>
                                    {{ translate('This month') }}</option>
                                <option value="this_week" {{ isset($filter) && $filter == 'this_week' ? 'selected' : '' }}>
                                    {{ translate('This week') }}</option>
                                <option value="custom" {{ isset($filter) && $filter == 'custom' ? 'selected' : '' }}>
                                    {{ translate('messages.Custom') }}</option>
                            </select>
                        </div>
                        @if (isset($filter) && $filter == 'custom')
                            <div class="col-sm-6 col-md-3">

                                <input type="date" name="from" id="from_date" class="form-control"
                                    placeholder="{{ translate('Start date') }}"
                                    {{ session()->has('from_date') ? 'value=' . session('from_date') : '' }} required>

                            </div>
                            <div class="col-sm-6 col-md-3">

                                <input type="date" name="to" id="to_date" class="form-control"
                                    placeholder="{{ translate('End date') }}"
                                    {{ session()->has('to_date') ? 'value=' . session('to_date') : '' }} required>

                            </div>
                            <div class="col-sm-6 col-md-3 ml-auto">
                                <button type="submit"
                                    class="btn btn-primary btn-block h--45px">{{ translate('Filter') }}</button>
                            </div>
                        @endif
                    </div>
                </form>
            </div>
        </div>
        @php
            $from = session('from_date') . ' 00:00:00';
            $to = session('to_date') . ' 23:59:59';
        @endphp
        <div class="mb-20">
            <div class="row g-4">
                <div class="col-lg-4">
                    <a class="__card-1 h-100" href="#">
                        <img src="{{asset('/public/assets/admin/img/report/new/total.png')}}" class="icon" alt="report/new">
                        <h3 class="title">{{$trips->total()}}</h3>
                        <h6 class="subtitle">{{translate('Total trips')}}</h6>
                    </a>
                </div>
                <div class="col-lg-8">
                    <div class="row g-3">
                        <div class="col-sm-6 col-md-6">
                            <a class="__card-2 __bg-1" href="#">
                            <h4 class="title">{{$total_progress_count}}</h4>
                            <span class="subtitle">{{translate('messages.In progress trips')}} <span data-toggle="tooltip" data-placement="right" data-original-title="{{translate('This count includes all the pending & confirmed trips')}}"><img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="{{translate('messages.In progress trips')}}"></span></span>
                            <img src="{{asset('/public/assets/admin/img/report/new/progress-report.png')}}" alt="report/new" class="card-icon">
                            </a>
                        </div>
                        <div class="col-sm-6 col-md-6">
                            <a class="__card-2 __bg-2" href="#">
                            <h4 class="title">{{$total_ongoing_count}}</h4>
                            <span class="subtitle">{{translate('messages.Ongoing trips')}}</span>
                            <img src="{{asset('/public/assets/admin/img/report/new/on-the-way.png')}}" alt="report/new" class="card-icon">
                            </a>
                        </div>
                        <div class="col-sm-6 col-md-6">
                            <a class="__card-2 __bg-3" href="#">
                            <h4 class="title">{{$total_completed_count}}</h4>
                            <span class="subtitle">{{ translate('messages.Completed trips') }}</span>
                            <img src="{{asset('/public/assets/admin/img/report/new/delivered.png')}}" alt="report/new" class="card-icon">
                            </a>
                        </div>
                        <div class="col-sm-6 col-md-6">
                            <a class="__card-2 __bg-6" href="#">
                            <h4 class="title">{{$total_canceled_count}}</h4>
                            <span class="subtitle">{{translate('messages.Canceled trips')}}</span>
                            <img src="{{asset('/public/assets/admin/img/report/new/canceled.png')}}" alt="report/new" class="card-icon">
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header border-0 py-2">
                <div class="search--button-wrapper">
                    <h3 class="card-title">
                        {{ translate('Total trips') }} <span
                            class="badge badge-soft-secondary" id="countItems">{{ $trips->total() }}</span>
                    </h3>
                    <form class="search-form">
                        <div class="input--group input-group input-group-merge input-group-flush">
                            <input name="search" type="search" class="form-control" value="{{request()->query('search')}}" placeholder="{{ translate('Search by trip ID') }}">
                            <button type="button" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>
                    <div class="hs-unfold ml-3">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle btn export-btn font--sm"
                            href="javascript:;"
                            data-hs-unfold-options="{
                                &quot;target&quot;: &quot;#usersExportDropdown&quot;,
                                &quot;type&quot;: &quot;css-animation&quot;
                            }"
                            data-hs-unfold-target="#usersExportDropdown" data-hs-unfold-invoker="">
                            <i class="tio-download-to mr-1"></i> {{ translate('Export') }}
                        </a>

                        <div id="usersExportDropdown"
                            class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right hs-unfold-content-initialized hs-unfold-css-animation animated hs-unfold-reverse-y hs-unfold-hidden">

                            <span class="dropdown-header">{{ translate('Download options') }}</span>
                            <a id="export-excel" class="dropdown-item"
                                href="{{ route('vendor.report.trip-report-export', ['type' => 'excel', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin/svg/components/excel.svg') }}"
                                    alt="Image Description">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item"
                                href="{{ route('vendor.report.trip-report-export', ['type' => 'csv', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin/svg/components/placeholder-csv-format.svg') }}"
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
                                <th class="text-capitalize border-top border-bottom">{{ translate('messages.SL') }}</th>
                                <th class="text-capitalize border-top border-bottom">{{ translate('messages.Trip ID') }}</th>
                                <th class="text-capitalize border-top border-bottom">{{ translate('Customer information') }}</th>
                                <th class="text-capitalize border-top border-bottom">{{ translate('Total fare of vehicle') }}</th>
                                <th class="text-capitalize border-top border-bottom">{{ translate('Discount on vehicle') }}</th>
                                <th class="text-capitalize border-top border-bottom">{{ translate('Coupon discount') }}</th>
                                <th class="text-capitalize border-top border-bottom">{{ translate('Referral discount') }}</th>
                                <th class="text-capitalize border-top border-bottom">{{ translate('messages.Pro discount') }}</th>
                                <th class="text-capitalize border-top border-bottom">{{ translate('messages.Total discounted amount') }}</th>
                                <th class="text-capitalize border-top border-bottom text-center">{{ translate('messages.tax') }}</th>
                                <th class="text-capitalize border-top border-bottom text-center">{{ \App\CentralLogics\Helpers::get_business_data('additional_charge_name')??translate('Additional charge') }}</th>
                                <th class="text-capitalize border-top border-bottom">{{ translate('Total trip amount') }}</th>
                                <th class="text-capitalize border-top border-bottom">{{ translate('Total amount received by') }}</th>
                                <th class="text-capitalize border-top border-bottom">{{ translate('messages.Payment method') }}</th>
                                <th class="text-capitalize border-top border-bottom">{{ translate('Trip status') }}</th>
                                <th class="text-capitalize border-top border-bottom text-center">{{ translate('messages.Action') }}
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
                                        <a href="{{ route('vendor.trip.details', $trip->id) }}">{{ $trip->id }}</a>
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
                                        </a>
                                        @else
                                            <label class="badge badge-danger">{{ translate('messages.Invalid customer data') }}</label>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="text-right mw--85px">
                                            <div>
                                                {{ \App\CentralLogics\Helpers::number_format_short(($trip['trip_amount']+$trip['coupon_discount_amount'] + $trip['discount_on_trip'] + $trip['ref_bonus_amount'] + ($trip->proDiscount?->amount_saved ?? 0)) - ($trip->additional_charge + $trip['tax_amount']) ) }}
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
                                        {{ \App\CentralLogics\Helpers::number_format_short( $trip['discount_on_trip'] ) }}
                                    </td>
                                    <td class="text-center mw--85px">
                                        {{ \App\CentralLogics\Helpers::number_format_short($trip['coupon_discount_amount']) }}
                                    </td>
                                    <td class="text-center mw--85px">
                                        {{ \App\CentralLogics\Helpers::number_format_short($trip['ref_bonus_amount']) }}
                                    </td>
                                    <td class="text-center mw--85px">
                                        {{ \App\CentralLogics\Helpers::number_format_short($trip->proDiscount?->amount_saved ?? 0) }}
                                    </td>
                                    <td class="text-center mw--85px">
                                        {{ \App\CentralLogics\Helpers::number_format_short($trip['coupon_discount_amount'] + $trip['discount_on_trip'] + $trip['ref_bonus_amount'] + ($trip->proDiscount?->amount_saved ?? 0))  }}
                                    </td>
                                    <td class="text-center mw--85px white-space-nowrap">
                                        {{ \App\CentralLogics\Helpers::number_format_short($trip['tax_amount']) }}
                                    </td>
                                    <td class="text-center mw--85px">
                                        {{ \App\CentralLogics\Helpers::number_format_short($trip['additional_charge']) }}
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
                                    <td class="text-center mw--85px text-capitalize">
                                        {{isset($trip->trip_transaction) ? $trip->trip_transaction->received_by : translate('messages.Not received yet')}}
                                    </td>
                                    <td class="text-center mw--85px text-capitalize">
                                            {{ payment_method_label($trip['payment_method']) }}
                                    </td>
                                    <td class="text-center mw--85px text-capitalize">
                                        @if($trip['trip_status']=='pending')
                                                <span class="badge badge-soft-info">
                                                  {{translate('Pending')}}
                                                </span>
                                            @elseif($trip['trip_status']=='confirmed')
                                                <span class="badge badge-soft-info">
                                                  {{translate('messages.confirmed')}}
                                                </span>
                                            @elseif($trip['trip_status']=='ongoing')
                                                <span class="badge badge-soft-warning">
                                                  {{translate('Ongoing')}}
                                                </span>
                                            @elseif($trip['trip_status']=='picked_up')
                                                <span class="badge badge-soft-warning">
                                                  {{translate('Out for delivery')}}
                                                </span>
                                            @elseif($trip['trip_status']=='completed')
                                                <span class="badge badge-soft-success">
                                                  {{translate('messages.Completed')}}
                                                </span>
                                            @elseif($trip['trip_status']=='failed')
                                                <span class="badge badge-soft-danger">
                                                  {{translate('Payment failed')}}
                                                </span>
                                            @elseif($trip['trip_status']=='handover')
                                                <span class="badge badge-soft-danger">
                                                  {{translate('messages.handover')}}
                                                </span>
                                            @elseif($trip['trip_status']=='canceled')
                                                <span class="badge badge-soft-danger">
                                                  {{translate('Canceled')}}
                                                </span>
                                            @elseif($trip['trip_status']=='accepted')
                                                <span class="badge badge-soft-danger">
                                                  {{translate('Accepted')}}
                                                </span>
                                            @elseif($trip['trip_status']=='refund_request_canceled')
                                                <span class="badge badge-soft-danger">
                                                  {{translate('Refund request canceled')}}
                                                </span>
                                            @else
                                                <span class="badge badge-soft-danger">
                                                  {{str_replace('_',' ',$trip['trip_status'])}}
                                                </span>
                                            @endif

                                    </td>


                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="ml-2 btn btn-sm action-btn action-btn--view"
                                                href="{{ route('vendor.trip.details', $trip->id) }}">
                                                <i class="tio-visible-outlined"></i>
                                            </a>
                                            <a class="ml-2 btn btn-sm btn--primary btn-outline-primary action-btn"
                                                href="{{route("vendor.trip.generate-invoice",["id" => $trip->id])}}">
                                                <i class="tio-print"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>


            </div>
            @if (count($trips) !== 0)
                <hr>
            @endif
            <div class="page-area">
                {!! $trips->links() !!}
            </div>
            @if (count($trips) === 0)
                <div class="empty--data">
                    <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
                    <h5>
                        {{ translate('No data found') }}
                    </h5>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin') }}/vendor/chart.js/dist/Chart.min.js"></script>
    <script src="{{ asset('public/assets/admin') }}/vendor/chartjs-chart-matrix/dist/chartjs-chart-matrix.min.js"></script>
    <script src="{{ asset('public/assets/admin') }}/js/hs.chartjs-matrix.js"></script>
    <script src="{{ asset('public/assets/admin') }}/js/view-pages/admin-reports.js"></script>
@endpush

