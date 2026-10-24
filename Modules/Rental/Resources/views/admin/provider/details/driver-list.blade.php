@extends('layouts.admin.app')

@section('title', translate('Driver list'))



@section('content')
    <div class="content container-fluid">
        @include('rental::admin.provider.details.partials._header',['store'=>$store])
        <div class="tab-content">
            <div class="tab-pane fade show active" id="trip">
                <div class="row g-2 mb-20">
                    <div class="col-sm-6 col-lg-4">
                        <a class="order--card h-100" href="javascript:">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">
                                    <span>{{ translate('All') }}</span>
                                </h6>
                                <span class="card-title text-title">
                                    {{ $totalDrivers }}
                                </span>
                            </div>
                        </a>
                    </div>

                    <div class="col-sm-6 col-lg-4">
                        <a class="order--card h-100" href="javascript:">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">
                                    <span>{{ translate('messages.Active') }}</span>
                                </h6>
                                <span class="card-title text--success">
                                    {{ $activeDrivers }}
                                </span>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-4">
                        <a class="order--card h-100" href="javascript:">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="card-subtitle m-0">
                                    <span>{{ translate('messages.Inactive') }}</span>
                                </h6>
                                <span class="card-title text--info">
                                    {{ $inactiveDrivers }}
                                </span>
                            </div>
                        </a>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header py-2">
                        <div class="search--button-wrapper">
                            <h5 class="card-title text--title flex-grow-1">{{ translate('Total drivers') }}</h5>
                            <form class="search-form flex-grow-1 max-w-353px">
                                <div class="input-group input--group">
                                    <input id="datatableSearch_" type="search" value="{{ request()?->search ?? null }}"
                                           name="search" class="form-control"
                                           placeholder="{{ translate('Search by name') }}"
                                           aria-label="{{ translate('messages.Search by name') }}">
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
                                       href="{{ route('admin.rental.provider.driver.export', ['type' => 'excel', request()->getQueryString()]) }}">
                                        <img class="avatar avatar-xss avatar-4by3 mr-2"
                                             src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                             alt="Image Description">
                                        Excel
                                    </a>
                                    <a id="export-csv" class="dropdown-item"
                                       href="{{ route('admin.rental.provider.driver.export', ['type' => 'csv', request()->getQueryString()]) }}">
                                        <img class="avatar avatar-xss avatar-4by3 mr-2"
                                             src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                             alt="Image Description">
                                        CSV
                                    </a>

                                </div>
                            </div>
                            <a class="btn btn--primary font-weight-bold float-right mr-2 mb-0"
                               href="{{ route('admin.rental.provider.driver.create', request()->id) }}"><i class="tio-add-circle"></i> {{ translate('messages.New driver') }}</a>
                        </div>
                    </div>

                    <div class="table-responsive datatable-custom">
                        <table id="columnSearchDatatable"
                               class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table text--title font-semibold"
                               data-hs-datatables-options='{
                                "order": [],
                                "orderCellsTop": true,
                                "paging":false

                            }'>
                            <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{ translate('SL') }}</th>
                                <th class="border-0">{{ translate('Driver information') }}</th>
                                <th class="border-0">{{ translate('Total trip') }}</th>
                                <th class="border-0">{{ translate('Completed') }}</th>
                                <th class="border-0">{{ translate('Cancel trip') }}</th>
                                <th class="text-center border-0">{{ translate('Driver status') }}</th>
                                <th class="text-center border-0">{{ translate('messages.Action') }}</th>
                            </tr>
                            </thead>

                            <tbody id="set-rows">
                            @foreach($drivers as $key => $driver)
                            <tr>
                                <td>{{ $key+$drivers->firstItem() }}</td>
                                <td>
                                    <div class="text--title">
                                        <a href="{{ route('admin.rental.provider.driver.details', $driver->id) }}" target="_blank" rel="noopener noreferrer">


                                            <div class="font-medium">
                                                {{ Str::limit($driver->fullName, 20,'...') }}
                                            </div>
                                        </a>
                                        <div class="opacity-lg font-regular">
                                            {{ $driver->phone }}
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    {{ $driver->trips_count }}
                                </td>
                                <td>
                                    {{ $driver->completed_trips_count }}
                                </td>
                                <td>
                                    {{ $driver->canceled_trips_count }}
                                </td>
                                <td>
                                    <label class="toggle-switch toggle-switch-sm" for="stocksCheckbox{{$driver->id}}">
                                        <input type="checkbox" data-url="{{route('admin.rental.provider.driver.status',[$driver['id'],$driver->status?0:1])}}" class="toggle-switch-input redirect-url" id="stocksCheckbox{{$driver->id}}" {{$driver->status?'checked':''}}>
                                        <span class="toggle-switch-label mx-auto">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </label>
                                </td>
                                <td>
                                    <div class="btn--container justify-content-center">
                                        <a class="btn action-btn action-btn--view" href="{{ route('admin.rental.provider.driver.details', $driver->id)}}"
                                           title="{{ translate('Edit store') }}"><i class="tio-visible-outlined"></i>
                                        </a>
                                        <a class="btn action-btn action-btn--edit" href="{{ route('admin.rental.provider.driver.edit', $driver->id)}}"
                                        title="{{ translate('Edit store') }}"><i class="tio-edit"></i>
                                        </a>
                                        <a class="btn action-btn action-btn--delete form-alert" href="javascript:"
                                           data-id="brand-{{$driver['id']}}" data-message="{{ translate('Want to delete this driver?') }}" title="{{translate('messages.Delete driver')}}"><i class="tio-delete-outlined"></i>
                                        </a>
                                        <form action="{{route('admin.rental.provider.driver.delete',[$driver['id']])}}" method="post" id="brand-{{$driver['id']}}">
                                            @csrf @method('delete')
                                        </form>

                                    </div>
                                </td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>

                    </div>
                    @if(count($drivers) !== 0)
                        <hr>
                    @endif
                    <div class="page-area">
                        {!! $drivers->appends($_GET)->links() !!}
                    </div>
                    @if(count($drivers) === 0)
                        <div class="empty--data">
                            <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                            <h5>
                                {{translate('No data found')}}
                            </h5>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

