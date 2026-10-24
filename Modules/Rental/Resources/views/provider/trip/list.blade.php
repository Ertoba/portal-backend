@extends('layouts.vendor.app')

@php
    $status_labels = [
        'scheduled' => translate('Scheduled'),
        'instant' => translate('messages.Instant'),
        'pending' => translate('Pending'),
        'confirmed' => translate('messages.confirmed'),
        'ongoing' => translate('Ongoing'),
        'completed' => translate('Completed'),
        'canceled' => translate('Canceled'),
        'payment_failed' => translate('Payment failed'),
    ];
    $status_badges = [
        'pending' => 'badge-soft-info',
        'confirmed' => 'badge-soft-primary',
        'ongoing' => 'badge-soft-warning',
        'completed' => 'badge-soft-success',
        'canceled' => 'badge-soft-danger',
        'payment_failed' => 'badge-soft-danger',
    ];
    $trip_type_labels = [
        'hourly' => translate('Hourly'),
        'distance_wise' => translate('Distance wise'),
        'day_wise' => translate('messages.day_wise'),
    ];
    $payment_status_labels = [
        'paid' => translate('messages.paid'),
        'unpaid' => translate('messages.unpaid'),
        'partially_paid' => translate('Partially paid'),
    ];
    $page_title = isset($status_labels[$status])
        ? translate('messages.Trips') . ': ' . $status_labels[$status]
        : translate('All trips');
    $max_avatars = 3;
@endphp

@section('title', $page_title)

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('Modules/Rental/public/assets/css/provider/trip-list.css') }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title text-break">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/outline/zone.svg') }}" class="w--26" alt="">
                </span>
                <span>{{ $page_title }}
                    <span class="badge badge-soft-dark ml-2" id="itemCount">{{ $total }}</span>
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Your trips at this stage, with the customer and driver on each.') }}</p>
        </div>

        <div class="card">
            <div class="card-header border-0 py-2">
                <div class="search--button-wrapper">
                    @include('partials._table-head', [
                        'subtitle' => translate('messages.Newest schedule first. Open a trip to assign drivers, update its status or print the invoice.'),
                    ])
                    <form action="" method="get" class="search-form">
                        <input type="hidden" value="{{ $status }}" name="status">
                        <div class="input-group input--group">
                            <input type="search" value="{{ request('search') }}" name="search" class="form-control"
                                   placeholder="{{ translate('Search by trip ID, customer name, email') }}"
                                   aria-label="{{ translate('Search by trip ID, customer name, email') }}">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>
                    @if (request('search'))
                        <button type="button" class="btn btn--reset location-reload-to-base" data-url="{{ url()->full() }}">
                            <i class="tio-refresh"></i> {{ translate('messages.Reset') }}
                        </button>
                    @endif
                    <div class="hs-unfold">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle h--40px" href="javascript:;"
                           data-hs-unfold-options='{"target": "#usersExportDropdown", "type": "css-animation"}'>
                            <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
                        </a>
                        <div id="usersExportDropdown" class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                            <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                            <a id="export-excel" class="dropdown-item" href="{{ route('vendor.trip.export', ['type' => 'excel', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{ asset('public/assets/admin/svg/components/excel.svg') }}" alt="">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item" href="{{ route('vendor.trip.export', ['type' => 'csv', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2" src="{{ asset('public/assets/admin/svg/components/placeholder-csv-format.svg') }}" alt="">
                                CSV
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th class="border-0">{{ translate('messages.Trip') }}</th>
                            <th class="border-0">{{ translate('messages.Schedule') }}</th>
                            <th class="border-0">{{ translate('messages.Customer') }}</th>
                            <th class="border-0">{{ translate('messages.Driver & vehicles') }}</th>
                            <th class="border-0">{{ translate('Type') }}</th>
                            <th class="border-0 col--numeric">{{ translate('messages.Amount') }}</th>
                            <th class="border-0 text-center">{{ translate('messages.Status') }}</th>
                            <th class="border-0 text-center">{{ translate('messages.Action') }}</th>
                        </tr>
                    </thead>

                    <tbody id="set-rows">
                        @foreach ($trips as $trip)
                            @php($drivers = $trip->assignedDriver->filter(fn ($assigned) => $assigned->driver))
                            @php($driver_count = $drivers->count())
                            @php($vehicle_count = $trip->assignedVehicle->count())
                            @php($customer_name = $trip->customer ? $trip->customer->fullName : data_get($trip->user_info, 'contact_person_name'))
                            @php($customer_contact = $trip->customer ? ($trip->customer->phone ?: $trip->customer->email) : (data_get($trip->user_info, 'contact_person_number') ?: data_get($trip->user_info, 'contact_person_email')))
                            <tr>
                                <td>
                                    <span class="d-flex align-items-center gap-2">
                                        <a class="font-weight-bold" href="{{ route('vendor.trip.details', $trip->id) }}">#{{ $trip->id }}</a>
                                        <span class="cell-chips">
                                            <span class="cell-chip">{{ $trip->scheduled ? translate('Scheduled') : translate('messages.Instant') }}</span>
                                            @if ($trip->edited)
                                                <span class="cell-chip">{{ translate('messages.Edited') }}</span>
                                            @endif
                                        </span>
                                    </span>
                                    <span class="d-block fs-12 text-muted">{{ translate('messages.booked') }}: {{ \App\CentralLogics\Helpers::date_format($trip->created_at) }}</span>
                                </td>
                                <td>
                                    @if ($trip->schedule_at)
                                        <span class="table-when">
                                            <span class="table-when__day">{{ \App\CentralLogics\Helpers::date_format($trip->schedule_at) }}</span>
                                            <span class="table-when__ago text-uppercase">{{ \App\CentralLogics\Helpers::time_format($trip->schedule_at) }}</span>
                                        </span>
                                    @else
                                        <span class="text-muted">&mdash;</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($customer_name)
                                        <span class="d-block text--title line--limit-1 max-w-200px">{{ $customer_name }}</span>
                                        @if ($customer_contact)
                                            <span class="d-block fs-12 text-muted">{{ $customer_contact }}</span>
                                        @endif
                                    @else
                                        <span class="text-muted">{{ translate('messages.Guest user') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($driver_count > 1)
                                        <div class="d-flex align-items-center gap-2" data-html="true" data-toggle="tooltip"
                                             title="<div class='d-flex flex-column p-2'>
                                                @foreach ($drivers as $tooltip_driver)
                                                    <div class='media gap-3 {{ !$loop->last ? 'border-bottom mb-2 pb-2' : '' }}'>
                                                        <img height='40' src='{{ data_get($tooltip_driver->driver, 'imageFullUrl') }}' class='rounded ratio-1-1' width='40' alt=''>
                                                        <div class='media-body'>
                                                            <h5 class='text-white mb-0'>{{ Str::limit(e($tooltip_driver->driver->fullName), 16, '...') }}</h5>
                                                            <div class='fs-10'>{{ Str::limit(e($tooltip_driver->driver->email), 18, '...') }}</div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                             </div>">
                                            <span class="d-flex">
                                                @foreach ($drivers->take($max_avatars) as $index => $assigned)
                                                    <img width="28" class="rounded-circle aspect-1-1 border border-white shadow-sm {{ $loop->first ? '' : 'ml-n2' }}" src="{{ data_get($assigned->driver, 'imageFullUrl') }}" alt="">
                                                @endforeach
                                            </span>
                                            <span class="fs-12 text--title">{{ translate('messages.drivers') }}: {{ $driver_count }}</span>
                                        </div>
                                    @elseif ($driver_count === 1)
                                        <a class="d-block text--title line--limit-1 max-w-200px" href="{{ route('vendor.driver.details', $drivers->first()->driver->id) }}">{{ $drivers->first()->driver->fullName }}</a>
                                    @else
                                        <span class="d-block text-muted">{{ translate('messages.Unassigned') }}</span>
                                    @endif

                                    @if ($vehicle_count > 0)
                                        <span class="d-inline-block fs-12 text-primary text-underline mt-1" data-html="true" data-toggle="tooltip"
                                              title="<div class='d-flex flex-column p-2'>
                                                @foreach ($trip->trip_details as $detail)
                                                    <div class='media gap-3 {{ !$loop->last ? 'border-bottom mb-2 pb-2' : '' }}'>
                                                        <img src='{{ data_get($detail->vehicle, 'thumbnailFullUrl', asset('public/assets/admin/img/160x160/img2.jpg')) }}' class='rounded ratio-1-1' width='40' alt=''>
                                                        <div class='media-body'>
                                                            <h5 class='text-white mb-0'>{{ Str::limit(e(data_get($detail->vehicle_details, 'name', '')), 16, '...') }}</h5>
                                                            @if ($detail->vehicle)
                                                                <div class='fs-10'>{{ translate('Car assigned') }}: {{ $detail->tripVehicleDetails->count() }}</div>
                                                            @else
                                                                <div class='fs-10 text-danger'>{{ translate('No data found') }}</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                              </div>">{{ translate('messages.vehicles') }}: {{ $vehicle_count }}</span>
                                    @else
                                        <span class="d-block fs-12 text--warning mt-1">{{ translate('messages.No vehicle assigned') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="d-block text--title">{{ $trip_type_labels[$trip->trip_type] ?? ucfirst(str_replace('_', ' ', $trip->trip_type)) }}</span>
                                    @if ($trip->distance_label)
                                        <span class="d-block fs-12 text-muted">{{ $trip->distance_label }}</span>
                                    @endif
                                </td>
                                <td class="col--numeric">
                                    <span class="d-block text--title font-weight-bold">{{ \App\CentralLogics\Helpers::format_currency($trip->trip_amount) }}</span>
                                    <span class="d-block fs-12 {{ $trip->payment_status === 'paid' ? 'text--success' : 'text--danger' }}">{{ $payment_status_labels[$trip->payment_status] ?? ucfirst(str_replace('_', ' ', $trip->payment_status)) }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $status_badges[$trip->trip_status] ?? 'badge-soft-secondary' }}">{{ $status_labels[$trip->trip_status] ?? ucfirst(str_replace('_', ' ', $trip->trip_status)) }}</span>
                                </td>
                                <td>
                                    <div class="btn--container justify-content-center">
                                        <a class="btn action-btn action-btn--view" href="{{ route('vendor.trip.details', $trip->id) }}"
                                           title="{{ translate('View details') }}"><i class="tio-visible-outlined"></i></a>
                                        <a class="btn action-btn action-btn--print" href="{{ route('vendor.trip.generate-invoice', ['id' => $trip->id]) }}"
                                           title="{{ translate('messages.Download invoice') }}"><i class="tio-download-to"></i></a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if (count($trips) === 0)
                <div class="empty--data">
                    <img src="{{ asset('public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
                    @if (request('search'))
                        <h5>{{ translate('messages.No trip matches your search.') }}</h5>
                        <p class="text-muted font-size-sm">{{ translate('Try a different trip ID, customer name or email.') }}</p>
                    @else
                        <h5>{{ translate('messages.No trips here yet.') }}</h5>
                        <p class="text-muted font-size-sm">{{ translate('Trips appear here as customers book your vehicles.') }}</p>
                    @endif
                </div>
            @else
                <div class="page-area px-4 pb-3">
                    {!! $trips->withQueryString()->links() !!}
                </div>
            @endif
        </div>
    </div>
@endsection
