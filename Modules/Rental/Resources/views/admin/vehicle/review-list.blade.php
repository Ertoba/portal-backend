@extends('layouts.admin.app')

@section('title',translate('Review list'))


@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/items.png')}}" class="w--22" alt="">
                </span>
                <span>
                    {{translate('messages.Vehicle reviews')}}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('What customers said about the vehicles they rented.') }}</p>
        </div>
        <div class="card">
            <div class="card-header border-0 py-2">
                <h5 class="card-title">
                    {{translate('Review list')}}<span class="badge badge-soft-dark ml-2" id="itemCount">{{$vehicleReview->total()}}</span></h5>
                <div class="search--button-wrapper justify-content-end">
                    <form  class="search-form">
                        <div class="input-group input--group">
                            <input id="datatableSearch" name="search" value="{{ request()?->search ?? null }}" type="search" class="form-control min-height-45" placeholder="{{translate('Ex') . ' : ' . translate('Search item name, customer name, rating')}}" aria-label="{{translate('Search')}}">
                            <button type="submit" class="btn btn--secondary min-height-45"><i class="tio-search"></i></button>
                        </div>
                    </form>

                    @if(request()->input('search'))
                    <button type="reset" class="btn btn--primary ml-2 location-reload-to-base" data-url="{{url()->full()}}"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                    @endif

                    <div class="hs-unfold mr-2">
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
                            <a id="export-excel" class="dropdown-item" href="{{ route('admin.rental.provider.vehicle.review.export', ['type' => 'excel', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                    alt="Image Description">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item" href="{{ route('admin.rental.provider.vehicle.review.export', ['type' => 'csv', request()->getQueryString()]) }}">
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
                <div class="table-responsive datatable-custom">
                    <table id="columnSearchDatatable"
                           class="table table-borderless table-thead-bordered table-nowrap card-table"
                           data-hs-datatables-options='{
                                    "order": [],
                                    "orderCellsTop": true,
                                    "paging": false
                                }'>
                        <thead class="thead-light">
                        <tr>
                            <th>{{ translate('messages.SL') }}</th>
                            <th>{{ translate('Review ID') }}</th>
                            <th class="w-10p">{{translate('messages.Vehicle')}}</th>
                            <th class="w-20p">{{translate('messages.Customer')}}</th>
                            <th class="w-30p">{{translate('messages.review')}}</th>
                            <th>{{translate('messages.Date')}}</th>
                            <th class="w-30p text-center">{{translate('Provider reply')}}</th>
                            <th>{{translate('messages.Action')}}</th>
                        </tr>
                        </thead>

                        <tbody>
                        @foreach($vehicleReview as $key=>$review)
                            <tr>
                                <td>{{$key+$vehicleReview->firstItem()}}</td>
                                <td>{{$review->review_id}}</td>

                                <td class="d-flex">
                                    @if ($review->vehicle)
                                        <a class="media align-items-center mb-1" href="{{route('admin.rental.provider.vehicle.details', $review->vehicle_id)}}">
                                            <img class="avatar avatar-lg mr-3 onerror-image"
                                                 src="{{ $review->vehicle['thumbnailFullUrl'] ?? asset('public/assets/admin/img/160x160/img2.jpg') }}"
                                                 data-onerror-image="{{asset('public/assets/admin/img/160x160/img2.jpg')}}"
                                                 alt="{{ $review->vehicle['name'] }} image">
                                        </a>
                                        <div class="py-2">
                                            <a class="media align-items-center mb-1" href="{{route('admin.rental.provider.vehicle.details', $review->vehicle_id)}}">
                                                <div class="media-body">
                                                    <h5 class="text-hover-primary mb-0">{{Str::limit($review->vehicle['name'],20,'...')}}</h5>
                                                </div>
                                            </a>
                                            <a class="mr-5 text-body" href="{{ route('admin.rental.trip.details', $review->trip_id) }}"> {{ translate('Trip ID') }}: {{ $review->trip_id }}</a>
                                        </div>
                                    @else
                                        {{translate('messages.Trip deleted!')}}
                                    @endif

                                </td>
                                <td>
                                    @if ($review->customer)
                                        <a href="{{ route('admin.users.customer.view', $review->user_id) }}">
                                            {{ $review?->customer?->fullName }}
                                        </a>
                                        <p>
                                            {{ $review?->customer?->phone }}
                                        </p>
                                    @else
                                        {{translate('No data found')}}
                                    @endif
                                </td>
                                <td>
                                    <label class="rating">
                                        {{$review->rating}} <i class="tio-star m-sm-auto"></i>
                                    </label>
                                    <p class="text-wrap" data-toggle="tooltip" data-placement="left"
                                       data-original-title="{{ $review?->comment }}">{!! $review->comment?Str::limit($review->comment, 30, '...'):'' !!}</p>
                                </td>
                                <td class="text-uppercase">
                                    <div>
                                        {{ \App\CentralLogics\Helpers::date_format($review->created_at)  }}

                                    </div>
                                    <div>
                                        {{ \App\CentralLogics\Helpers::time_format($review->created_at)  }}
                                    </div>
                                </td>
                                <td>
                                    <p class="text-wrap text-center" data-toggle="tooltip" data-placement="top"
                                       data-original-title="{{ $review?->reply }}">{!! $review->reply?Str::limit($review->reply, 50, '...'): translate('Not replied yet') !!}</p>
                                </td>
                                <td>
                                    <label class="toggle-switch toggle-switch-sm" for="reviewCheckbox{{$review->id}}">
                                        {{-- data-url-fixed: this endpoint flips whatever it finds, so its action ends in a
                                             record id and must never be rewritten by status-toggle.js --}}
                                        <input type="checkbox" data-url-fixed
                                               data-id="status-{{ $review['id'] }}" data-message="{{ $review->status ? translate('messages.You want to hide this review for customer') : translate('messages.You want to show this review for customer') }}"
                                               data-title="{{ translate('messages.Are you sure?') }}"
                                               data-yes-text="{{ translate('messages.Yes') }}"
                                               data-no-text="{{ translate('messages.No') }}"
                                               class="toggle-switch-input status_form_alert" id="reviewCheckbox{{ $review->id }}"
                                            {{ $review->status ? 'checked' : '' }}>
                                        <span class="toggle-switch-label">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                    </label>
                                    <form action="{{route('admin.rental.provider.vehicle.review.status', $review['id'])}}" method="get" id="status-{{$review['id']}}">
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    @if(count($vehicleReview) === 0)
                        <div class="empty--data">
                            <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                            <h5>
                                {{translate('No data found')}}
                            </h5>
                        </div>
                    @endif
                </div>
                <div class="page-area px-4 pb-3">
                    <div class="d-flex align-items-center justify-content-end">
                        <div>
                            {!! $vehicleReview->links() !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
<script src="{{ asset('Modules/Rental/public/assets/js/admin/view-pages/vehicle-review-list.js') }}"></script>
@endpush
