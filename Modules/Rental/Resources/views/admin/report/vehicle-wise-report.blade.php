@extends('layouts.admin.app')

@section('title',translate('Vehicle report'))

@push('css_or_js')

@endpush

@section('content')

    @php
        $from = session('from_date');
        $to = session('to_date');
    @endphp
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/outline/report.svg')}}" class="w--26" alt="">
                </span>
                <span>
                    {{translate('Vehicle report')}}
                    @if (isset($filter) && $filter != 'all_time')
                    <span class="mb-0 h6 badge badge-soft-success ml-2"
                        id="itemCount">( {{ session('from_date') }} - {{ session('to_date') }} )</span>
                        @endif
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Which vehicles earn and which sit idle, ranked over the period you choose.') }}</p>
        </div>

        <div class="card mb-20">
            <div class="card-body">
                <h4 class="">{{translate('Search data')}}</h4>
                <form action="{{ route('admin.transactions.rental.report.set-date') }}" method="post">
                    @csrf
                <div class="row g-3">
                    <div class="col-sm-6 col-md-3">
                        <select name="zone_id" class="form-control js-select2-custom set-filter" data-url="{{ url()->full() }}" data-filter="zone_id" id="zone">
                    <option value="all">{{ translate('All zones') }}</option>
                    @foreach(\App\CentralLogics\Helpers::zones_dropdown() as $z)
                        <option
                            value="{{$z['id']}}" {{isset($zone) && $zone->id == $z['id']?'selected':''}}>
                            {{$z['name']}}
                        </option>
                    @endforeach
                </select>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <select name="provider_id" id="provider_id"

                        data-get-provider-url="{{route('admin.store.get-providers')}}"
                        data-zone-id="{{ isset($zone) ? $zone->id : '' }}"
                        data-module-id="{{ request('module_id') ?? '' }}"

                        data-placeholder="{{translate('Select provider')}}" class="js-data-example-ajax form-control set-filter" data-url="{{ url()->full() }}" data-filter="provider_id" >
                            @if(isset($provider))
                            <option value="{{$provider->id}}" selected>{{$provider->name}}</option>
                            @else
                            <option value="all" selected>{{translate('All providers')}}</option>
                            @endif
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <select name="category_id"

                        data-get-category-url="{{route('admin.rental.category.get-categories')}}"
                        data-zone-id="{{ isset($zone) ? $zone->id : '' }}"
                        data-module-id="{{ request('module_id') ?? '' }}"

                        class="js-data-example-ajax form-control set-filter" data-url="{{ url()->full() }}" data-filter="category_id"  id="category_id">
                        @if(isset($category))
                        <option value="{{$category->id}}" selected>{{$category->name}}</option>
                        @else
                        <option value="all" selected>{{ translate('All categories') }}</option>
                        @endif
                    </select>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <select class="form-control set-filter" data-url="{{ url()->full() }}" data-filter="filter"  name="filter">
                            <option value="all_time" {{ isset($filter) && $filter == "all_time" ? 'selected' : '' }}>{{ translate('All time') }}</option>
                            <option value="this_year" {{ isset($filter) && $filter == "this_year" ? 'selected' : '' }}>{{ translate('This year') }}</option>
                            <option value="previous_year" {{ isset($filter) && $filter == "previous_year" ? 'selected' : '' }}>{{ translate('Previous year') }}</option>
                            <option value="this_month" {{ isset($filter) && $filter == "this_month" ? 'selected' : '' }}>{{ translate('This month') }}</option>
                            <option value="this_week" {{ isset($filter) && $filter == "this_week" ? 'selected' : '' }}>{{ translate('This week') }}</option>
                            <option value="custom" {{ isset($filter) && $filter == 'custom' ? 'selected' : '' }}>
                                {{ translate('messages.Custom') }}</option>
                        </select>
                    </div>
                    @if (isset($filter) && $filter == 'custom')
                    <div class="col-sm-6 col-md-3">

                            <input type="date" name="from" id="from_date" class="form-control" placeholder="{{ translate('Start date') }}" {{session()->has('from_date')?'value='.session('from_date'):''}} required>

                    </div>
                    <div class="col-sm-6 col-md-3">

                            <input type="date" name="to" id="to_date" class="form-control" placeholder="{{ translate('End date') }}" {{session()->has('to_date')?'value='.session('to_date'):''}} required>

                    </div>
                    @endif
                    <div class="col-sm-6 col-md-3 ml-auto">
                        <button type="submit" class="btn btn-primary btn-block h--45px"><i class="tio-filter-list"></i> {{translate('Filter')}}</button>
                    </div>
                </div>
            </form>
            </div>
        </div>
        <div class="row card mt-4">
            <div class="card-header border-0 py-2">
                <div class="search--button-wrapper">
                    <h3 class="card-title">{{translate('Vehicle report table')}}
                        @if ($vehicles->total() > 0)
                            <span class="badge badge-soft-secondary ml-2 rounded-circle" id="countItems">{{ $vehicles->total() }}</span>
                        @endif
                    </h3>
                    <form class="search-form">
                    <div class="input--group input-group">
                        <input id="datatableSearch" name="search" type="search" class="form-control" placeholder="{{translate('Ex') . ' : ' . translate('Search by vehicle name')}}" value="{{ request()?->search ?? null}}" aria-label="{{translate('Search')}}">
                        <button type="button" class="btn btn--secondary"><i class="tio-search"></i></button>
                    </div>
                    </form>
                    @if(request()->input('search'))
                        <button type="reset" class="btn btn--primary ml-2 location-reload-to-base" data-url="{{url()->full()}}"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                    @endif
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
                            <a id="export-excel" class="dropdown-item" href="{{route('admin.transactions.rental.report.vehicle-wise-export', ['type'=>'excel',request()->getQueryString()])}}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                    alt="Image Description">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item" href="{{route('admin.transactions.rental.report.vehicle-wise-export', ['type'=>'csv',request()->getQueryString()])}}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                    alt="Image Description">
                                CSV
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive datatable-custom" id="table-div">
                <table id="datatable" class="table table-borderless table-thead-bordered table-nowrap card-table">
                    <thead class="thead-light">
                    <tr>
                        <th>{{translate('SL')}}</th>
                        <th class="w--2">{{translate('Vehicle information')}}</th>
                        <th>{{translate('Number of vehicles')}}</th>
                        <th class="w--2">{{translate('messages.Provider')}}</th>
                        <th>{{translate('messages.Hourly rate')}}</th>
                        <th>{{translate('messages.Distance wise rate')}}</th>
                        <th>{{translate('messages.Day wise rate')}}</th>
                        <th>{{translate('messages.Total trip count')}}</th>
                        <th>{{translate('messages.Total trip vehicles')}}</th>
                        <th>{{translate('Total trip amount')}}</th>
                        <th>{{translate('Total discount given')}}</th>
                        <th>{{translate('Average trip value')}}</th>
                        <th>{{translate('messages.Average ratings')}}</th>
                    </tr>
                    </thead>

                    <tbody id="set-rows">

                    @foreach($vehicles as $key=>$vehicle)
                        <tr>
                            <td>{{$key+$vehicles->firstItem()}}</td>
                            <td>

                                <a class="media align-items-center" href="{{ route('admin.rental.provider.vehicle.details', $vehicle->id)}}">
                                    <img class="avatar avatar-lg mr-3 onerror-image"
                                    src="{{ $vehicle?->thumbnail_full_url ?? asset('public/assets/admin/img/160x160/img2.jpg') }}"


                                    data-onerror-image="{{asset('public/assets/admin/img/160x160/img2.jpg')}}" alt="{{$vehicle->name}} image">
                                    <div class="media-body">
                                        <h5 class="text-hover-primary mb-0" title="{{ $vehicle['name'] }}">
                                            {{ strlen($vehicle['name']) > 30 ? substr($vehicle['name'], 0, 30).'...' : $vehicle['name'] }}
                                        </h5>
                                    </div>
                                </a>
                            </td>
                            <td>
                                {{$vehicle->vehicle_identities_count ?? 0}}
                            </td>
                            <td>
                                @if($vehicle->provider)
                                {{Str::limit($vehicle->provider->name,20,'...')}}
                                @else
                                {{translate('messages.Provider deleted')}}
                                @endif
                            </td>
                            <td>
                                {{ \App\CentralLogics\Helpers::format_currency($vehicle->hourly_price) }}
                            </td>
                            <td>
                                {{ \App\CentralLogics\Helpers::format_currency($vehicle->distance_price) }}
                            </td>
                            <td>
                                {{ \App\CentralLogics\Helpers::format_currency($vehicle->day_wise_price) }}
                            </td>
                            <td>
                                {{$vehicle->trips_count ?? 0}}
                            </td>
                            <td>
                                {{$vehicle->trip_details_sum_quantity ?? 0}}
                            </td>
                            <td>
                                {{ \App\CentralLogics\Helpers::format_currency($vehicle->trips_sum_price) }}
                            </td>
                            <td>
                                {{ \App\CentralLogics\Helpers::format_currency($vehicle->total_discount) }}
                            </td>
                            <td>
                                {{ $vehicle->trips_count>0? \App\CentralLogics\Helpers::format_currency(($vehicle->trips_sum_price-$vehicle->total_discount)/($vehicle->trip_details_sum_quantity ?? 0) ) :0 }}
                            </td>
                            <td>
                                <div class="rating">
                                    <span><i class="tio-star"></i></span>{{ round($vehicle->avg_rating,1) }} ({{ $vehicle->total_reviews }})
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @if(count($vehicles) !== 0)
            <hr>
            @endif
            @if(count($vehicles) === 0)
            <div class="empty--data">
                <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                <h5>
                    {{translate('No data found')}}
                </h5>
            </div>
            @endif
            </div>
        <div class="page-area">
            {!! $vehicles->links() !!}
        </div>
        </div>
    </div>
@endsection

@push('script')

@endpush

@push('script_2')

    <script src="{{asset('public/assets/admin')}}/vendor/chart.js/dist/Chart.min.js"></script>
    <script src="{{asset('public/assets/admin')}}/vendor/chartjs-chart-matrix/dist/chartjs-chart-matrix.min.js"></script>
    <script src="{{asset('public/assets/admin')}}/js/hs.chartjs-matrix.js"></script>
    <script src="{{ asset('public/assets/admin') }}/js/view-pages/admin-reports.js"></script>
    <script src="{{asset('Modules/Rental/public/assets/js/admin/view-pages/vehicle-report.js')}}"></script>

@endpush
