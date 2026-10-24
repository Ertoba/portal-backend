@extends('layouts.admin.app')

@section('title', translate('Vehicle list'))

@push('css_or_js')
@endpush

@section('content')
    <div class="content container-fluid">
        @include('rental::admin.provider.details.partials._header',['store'=>$store])
        <div class="row g-2 mb-20">
            <div class="col-sm-6 col-lg-3">
                <a class="order--card h-100" href="javascript:">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-subtitle m-0">
                            <span>{{ translate('All') }}</span>
                        </h6>
                        <span class="card-title text-title">
                            {{ $totalVehicles }}
                        </span>
                    </div>
                </a>
            </div>

            <div class="col-sm-6 col-lg-3">
                <a class="order--card h-100" href="javascript:">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-subtitle m-0">
                            <span>{{ translate('Ongoing') }}</span>
                        </h6>
                        <span class="card-title text--warning">
                            {{ $ongoingVehicles }}
                        </span>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a class="order--card h-100" href="javascript:">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-subtitle m-0">
                            <span>{{ translate('messages.Active') }}</span>
                        </h6>
                        <span class="card-title text--success">
                            {{ $activeVehicles }}
                        </span>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-lg-3">
                <a class="order--card h-100" href="javascript:">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-subtitle m-0">
                            <span>{{ translate('messages.Inactive') }}</span>
                        </h6>
                        <span class="card-title text--danger">
                            {{ $inactiveVehicles }}
                        </span>
                    </div>
                </a>
            </div>
        </div>
        <div class="card">
            <div class="card-header py-2">
                <div class="search--button-wrapper">
                    <h5 class="card-title text--title">
                        {{ translate('Total vehicles') }}
                        <span class="badge badge-soft-dark ml-2 rounded-circle" id="itemCount">{{ $vehicles->total() }}</span>
                    </h5>
                    <form class="search-form">
                        <div class="input-group input--group">
                            <input id="datatableSearch_" type="search" value="{{ request()?->search ?? null }}"
                                   name="search" class="form-control"
                                   placeholder="{{ translate('Search by name or owner information') }}"
                                   aria-label="{{ translate('Search by name or owner information') }}">
                            <button type="submit" class="btn btn--secondary bg--primary"><i
                                    class="tio-search"></i></button>

                        </div>
                    </form>
                    @if (request()->input('search'))
                        <button type="reset" class="btn btn--primary ml-2 location-reload-to-base"
                                data-url="{{ url()->full() }}"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                    @endif


                    <div class="hs-unfold mr-2">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40 font-semibold"
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
                               href="{{ route('admin.rental.provider.vehicle.export', ['provider_id'=>request()->id,'type' => 'excel', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                     src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                     alt="{{translate('Image description')}}">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item"
                               href="{{ route('admin.rental.provider.vehicle.export', ['provider_id'=>request()->id,'type' => 'csv', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                     src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                     alt="{{translate('Image description')}}">
                                CSV
                            </a>

                        </div>
                    </div>
                    <a class="btn btn--primary font-weight-bold float-right mr-2 mb-0"
                       href="{{ route('admin.rental.provider.vehicle.create') }}?provider_id={{request()->id}}"><i class="tio-add-circle"></i> {{ translate('messages.New vehicle') }}</a>
                </div>
            </div>

            <div class="table-responsive datatable-custom">
                <table id="columnSearchDatatable"
                       class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                       data-hs-datatables-options='{
                        "order": [],
                        "orderCellsTop": true,
                        "paging":false

                    }'>
                    <thead class="thead-light">
                    <tr>
                        <th class="border-0">{{ translate('SL') }}</th>
                        <th class="border-0">{{ translate('Vehicle information') }}</th>
                        <th class="border-0">{{ translate('messages.Category') }}</th>
                        <th class="border-0">{{ translate('messages.Brand') }}</th>
                        <th class="border-0">{{ translate('Total trip') }}</th>
                        <th class="border-0">{{ translate('Trip fare') }}</th>
                        <th class="text-center border-0">{{ translate('New tag') }}</th>
                        <th class="text-center border-0">{{ translate('messages.Status') }}</th>
                        <th class="text-center border-0">{{ translate('messages.Action') }}</th>
                    </tr>
                    </thead>

                    <tbody id="set-rows">
                    @foreach($vehicles as $key => $vehicle)
                        <tr>
                            <td>{{ $key+$vehicles->firstItem() }}</td>
                            <td>
                                <div class="text--title">
                                    <a href="{{ route('admin.rental.provider.vehicle.details', $vehicle->id) }}" class="font-medium">
                                        {{ $vehicle->name }}
                                    </a>
                                    <div class="opacity-lg">
                                        {{ $vehicle->model }}
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="text--title font-medium">
                                    {{ $vehicle?->category?->name }}
                                </div>
                            </td>
                            <td>
                                <div class="text--title font-medium">
                                    {{ $vehicle?->brand?->name }}
                                </div>
                            </td>
                            <td>
                                <div class="text--title font-medium">
                                    {{$vehicle->trip_details_count}}
                                </div>
                            </td>
                            <td>
                                <div class="text--title">
                                    @if($vehicle->trip_hourly)
                                        <div>
                                            <span class="opacity-lg">{{translate('Hourly')}}: </span>
                                            <span class="font-semibold">{{\App\CentralLogics\Helpers::format_currency($vehicle['hourly_price'])}}</span>
                                        </div>
                                    @endif
                                    @if($vehicle->trip_distance)
                                        <div>
                                            <span class="opacity-lg">{{translate('Distance wise')}}: </span>
                                            <span class="font-semibold">{{\App\CentralLogics\Helpers::format_currency($vehicle['distance_price'])}}</span>
                                        </div>
                                    @endif
                                    @if($vehicle->trip_day_wise)
                                        <div>
                                            <span class="opacity-lg">{{translate('Per day')}}: </span>
                                            <span class="font-semibold">{{\App\CentralLogics\Helpers::format_currency($vehicle['day_wise_price'])}}</span>
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center">
                                    <label class="toggle-switch toggle-switch-sm" for="stocksCheckboxNew{{$vehicle->id}}">
                                        <input type="checkbox" data-url="{{route('admin.rental.provider.vehicle.new-tag',[$vehicle['id'],$vehicle->new_tag?0:1])}}"
                                               class="toggle-switch-input redirect-url" id="stocksCheckboxNew{{$vehicle->id}}" {{$vehicle->new_tag?'checked':''}}>
                                        <span class="toggle-switch-label">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                    </label>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center">
                                    <label class="toggle-switch toggle-switch-sm" for="stocksCheckbox{{$vehicle->id}}">
                                        <input type="checkbox" data-url="{{route('admin.rental.provider.vehicle.status',[$vehicle['id'],$vehicle->status?0:1])}}"
                                               class="toggle-switch-input redirect-url" id="stocksCheckbox{{$vehicle->id}}" {{$vehicle->status?'checked':''}}>
                                        <span class="toggle-switch-label">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                    </label>
                                </div>
                            </td>
                            <td>
                                <div class="btn--container justify-content-center">
                                    <a class="btn action-btn action-btn--view" href="{{ route('admin.rental.provider.vehicle.details', $vehicle->id)}}?provider_id={{$vehicle->provider_id}}&provider_vehicle_list=true"
                                       title="{{ translate('messages.View') }}"><i class="tio-visible-outlined"></i>
                                    </a>
                                    <a class="btn action-btn action-btn--edit" href="{{ route('admin.rental.provider.vehicle.edit', $vehicle->id)}}"
                                       title="{{ translate('Edit store') }}"><i class="tio-edit"></i>
                                    </a>
                                    <a class="btn action-btn action-btn--delete form-alert" href="javascript:"
                                       data-id="vehicle-{{$vehicle['id']}}" data-message="{{ translate('Want to delete this vehicle?') }}" title="{{translate('messages.Delete vehicle')}}"><i
                                            class="tio-delete-outlined"></i>
                                    </a>

                                </div>
                                <form action="{{route('admin.rental.provider.vehicle.delete',[$vehicle['id']])}}" method="post" id="vehicle-{{$vehicle->id}}">
                                    @csrf @method('delete')
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

            </div>
            @if(count($vehicles) !== 0)
                <hr>
            @endif
            <div class="page-area">
                {!! $vehicles->appends($_GET)->links() !!}
            </div>
            @if(count($vehicles) === 0)
                <div class="empty--data">
                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="{{translate('public')}}">
                    <h5>
                        {{translate('No data found')}}
                    </h5>
                </div>
            @endif
        </div>
    </div>
@endsection
