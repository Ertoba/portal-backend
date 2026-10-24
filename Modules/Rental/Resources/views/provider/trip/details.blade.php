@extends('layouts.vendor.app')
@section('title', translate('Trip details'))

@section($trip->trip_status)
active
@endsection

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/order-details.css') }}">
    <link rel="stylesheet" href="{{ asset('Modules/Rental/public/assets/css/provider/trip-details.css') }}">
@endpush

@section('content')
    @php
        $tripStatusLabels = [
            'pending' => translate('Pending'),
            'confirmed' => translate('confirmed'),
            'ongoing' => translate('Ongoing'),
            'completed' => translate('Completed'),
            'canceled' => translate('Canceled'),
            'payment_failed' => translate('Payment failed'),
        ];
        $tripStatusTones = [
            'pending' => 'accent',
            'confirmed' => 'info',
            'ongoing' => 'warn',
            'completed' => 'ok',
            'canceled' => 'danger',
            'payment_failed' => 'danger',
        ];
        $tripStatusLabel = $tripStatusLabels[$trip->trip_status] ?? ucfirst(str_replace('_', ' ', $trip->trip_status));
        $tripStatusTone = $tripStatusTones[$trip->trip_status] ?? 'neutral';

        $paymentStatusLabels = [
            'paid' => translate('paid'),
            'unpaid' => translate('unpaid'),
        ];
        $paymentStatusLabel = $paymentStatusLabels[$trip->payment_status] ?? ucfirst(str_replace('_', ' ', $trip->payment_status));

        $tripTypeLabels = [
            'hourly' => translate('Hourly'),
            'day_wise' => translate('day_wise'),
            'distance_wise' => translate('Distance wise'),
        ];
        $tripTypeLabel = $tripTypeLabels[$trip->trip_type] ?? ucfirst(str_replace('_', ' ', $trip->trip_type));

        if ($trip->trip_type == 'hourly') {
            $tripSpanLabel = translate('Duration');
            $tripSpanValue = $trip->estimated_hours . ' ' . translate('hours');
        } elseif ($trip->trip_type == 'day_wise') {
            $tripSpanLabel = translate('Duration');
            $tripSpanValue = (int) round($trip->estimated_hours / 24) . ' ' . translate('days');
        } else {
            $tripSpanLabel = translate('Distance');
            $tripSpanValue = $trip->distance_label ?? translate('N/A');
        }

        $tripStepOrder = ['pending', 'confirmed', 'ongoing', 'completed'];
        $tripHalted = in_array($trip->trip_status, ['canceled', 'payment_failed']);
        $tripCurrentStep = array_search($trip->trip_status, $tripStepOrder, true);
        $tripCurrentStep = $tripCurrentStep === false ? -1 : $tripCurrentStep;
        $tripSteps = [];
        foreach ($tripStepOrder as $index => $step) {
            $stepTime = $step == 'pending' ? $trip->created_at : $trip->getAttribute($step);
            if ($tripHalted) {
                if (!$stepTime) {
                    continue;
                }
                $stepState = 'done';
            } else {
                $stepState = $index < $tripCurrentStep ? 'done' : ($index == $tripCurrentStep ? 'current' : 'todo');
            }
            $tripSteps[] = [
                'label' => $tripStatusLabels[$step],
                'state' => $stepState,
                'time' => $stepState != 'todo' && $stepTime ? \App\CentralLogics\Helpers::time_date_format($stepTime) : null,
            ];
        }
        if ($tripHalted) {
            $haltedTime = $trip->getAttribute($trip->trip_status);
            $tripSteps[] = [
                'label' => $tripStatusLabel,
                'state' => 'halted',
                'time' => $haltedTime ? \App\CentralLogics\Helpers::time_date_format($haltedTime) : null,
            ];
        }

        $subtotal = $trip->trip_details->sum('calculated_price');
        $tripVehicleCount = $trip->trip_details->sum('quantity');
        $tripDrivers = $trip->vehicle_identity->whereNotNull('vehicle_driver_id');
        $driverCount = $tripDrivers->count();
        $assignedVehicleCount = $trip->vehicle_identity->whereNotNull('vehicle_identity_id')->count();
        $canAssignVehicle = !in_array($trip->trip_status, ['ongoing', 'pending', 'completed', 'canceled']);
        $additionalChargeName = \App\CentralLogics\Helpers::get_business_settings('additional_charge_name') ?? translate('Additional charge');

        $pickup = $trip->pickup_location ?? [];
        $destination = $trip->destination_location ?? [];
        $routeUrl = 'https://www.google.com/maps/dir/?api=1&origin=' . data_get($pickup, 'lat') . ',' . data_get($pickup, 'lng')
            . '&destination=' . data_get($destination, 'lat') . ',' . data_get($destination, 'lng');

        $fallbackVehicleImage = asset('public/assets/admin/img/160x160/img2.jpg');
        $fallbackUserImage = asset('public/assets/admin/img/160x160/img1.jpg');
    @endphp

    <div class="content container-fluid odv tdv">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">
                        <span class="page-header-icon">
                            <img src="{{ asset('/public/assets/admin/img/outline/car.svg') }}" class="w--26" alt="">
                        </span>
                        <span>{{ translate('Trip details') }}</span>
                    </h1>
                    <p class="page-header-desc">{{ translate('Everything on this trip, from the route and vehicle to the payment and the driver.') }}</p>
                </div>
            </div>
        </div>

        <div class="row flex-xl-nowrap" id="printableArea">
            <div class="col-lg-8 order-print-area-left">
                <div class="card mb-3 mb-lg-5">
                    <div class="card-header odv-head">
                        <div class="odv-head__top">
                            <div class="odv-head__ident">
                                <h2 class="odv-head__order">
                                    {{ translate('Trip') }} <span class="odv-hash">#</span>{{ $trip->id }}
                                </h2>
                                <span class="odv-pill odv-pill--{{ $tripStatusTone }}">{{ $tripStatusLabel }}</span>
                                @if ($trip->edited)
                                    <span class="odv-flag">{{ translate('Edited') }}</span>
                                @endif
                            </div>

                            <div class="odv-head__actions">
                                @if ($trip->trip_status == 'pending' && $is_deleted != 1)
                                    <button type="button" class="btn btn-outline-primary" data-toggle="modal"
                                            data-target="#editTripModal">
                                        <i class="tio-edit"></i> <span>{{ translate('Edit trip') }}</span>
                                    </button>
                                @endif
                                <a class="btn btn--primary"
                                   href="{{ route('vendor.trip.generate-invoice', ['id' => $trip->id]) }}">
                                    <i class="tio-print"></i> <span>{{ translate('Print invoice') }}</span>
                                </a>
                            </div>
                        </div>

                        <div class="odv-head__facts">
                            <span class="odv-fact">
                                <i class="tio-date-range"></i>
                                {{ translate('Placed on') }}
                                <strong>{{ \App\CentralLogics\Helpers::time_date_format($trip->created_at) }}</strong>
                            </span>
                            @if ($trip->scheduled)
                                <span class="odv-fact odv-fact--warn">
                                    <i class="tio-time"></i>
                                    {{ translate('Scheduled at') }}
                                    <strong>{{ \App\CentralLogics\Helpers::time_date_format($trip->schedule_at) }}</strong>
                                </span>
                            @endif
                            @if ($trip->estimated_trip_end_time)
                                <span class="odv-fact">
                                    <i class="tio-flag-outlined"></i>
                                    {{ translate('Estimated trip end') }}
                                    <strong>{{ \App\CentralLogics\Helpers::time_date_format($trip->estimated_trip_end_time) }}</strong>
                                </span>
                            @endif
                            <button type="button" class="odv-fact" data-toggle="modal"
                                    data-target="#providerLocationModal" title="{{ translate('View map') }}">
                                <i class="tio-poi"></i>
                                {{ translate('Provider') }}
                                <strong>{{ $trip->provider?->name }}</strong>
                            </button>
                        </div>
                    </div>

                    <ol class="tdv-steps">
                        @foreach ($tripSteps as $step)
                            <li class="tdv-step tdv-step--{{ $step['state'] }}">
                                <span class="tdv-step__dot">
                                    @if ($step['state'] == 'done')
                                        <i class="tio-done"></i>
                                    @elseif ($step['state'] == 'halted')
                                        <i class="tio-clear"></i>
                                    @endif
                                </span>
                                <span class="tdv-step__label">{{ $step['label'] }}</span>
                                @if ($step['time'])
                                    <span class="tdv-step__time">{{ $step['time'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>

                    <div class="odv-meta">
                        <div class="odv-meta__item">
                            <span class="odv-meta__label">{{ translate('Trip type') }}</span>
                            <span class="odv-meta__value">
                                {{ $tripTypeLabel }}
                                <span class="tdv-muted">({{ $trip->scheduled ? translate('Scheduled') : translate('Instant') }})</span>
                            </span>
                        </div>

                        <div class="odv-meta__item">
                            <span class="odv-meta__label">{{ $tripSpanLabel }}</span>
                            <span class="odv-meta__value tdv-nocase">{{ $tripSpanValue }}</span>
                        </div>

                        <div class="odv-meta__item">
                            <span class="odv-meta__label">{{ translate('Total vehicles') }}</span>
                            <span class="odv-meta__value">{{ $tripVehicleCount }}</span>
                        </div>

                        @if ($trip->payment_method)
                            <div class="odv-meta__item">
                                <span class="odv-meta__label">{{ translate('Payment method') }}</span>
                                <span class="odv-meta__value">{{ payment_method_label($trip->payment_method) }}</span>
                            </div>
                        @endif

                        <div class="odv-meta__item">
                            <span class="odv-meta__label">{{ translate('Payment status') }}</span>
                            <span class="odv-meta__value">
                                <span class="odv-pill odv-pill--{{ $trip->payment_status == 'paid' ? 'ok' : 'danger' }}">{{ $paymentStatusLabel }}</span>
                            </span>
                        </div>
                    </div>

                    @if (($trip->trip_status == 'canceled' && $trip->cancellation_reason) || $trip->trip_note)
                        <div class="odv-notes">
                            @if ($trip->trip_status == 'canceled' && $trip->cancellation_reason)
                                <div class="odv-note odv-note--danger">
                                    <i class="tio-clear-circle-outlined"></i>
                                    <div class="odv-note__body">
                                        <span class="odv-note__label">{{ translate('Cancel reason') }}</span>
                                        {{ $trip->cancellation_reason }}
                                    </div>
                                </div>
                            @endif
                            @if ($trip->trip_note)
                                <div class="odv-note">
                                    <i class="tio-comment-text-outlined"></i>
                                    <div class="odv-note__body">
                                        <span class="odv-note__label">{{ translate('Note') }}</span>
                                        {{ $trip->trip_note }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="card-body px-0">
                        <div class="odv-section-head">
                            <h2 class="odv-section-head__title">{{ translate('Vehicle details') }}</h2>
                            <span class="odv-count">{{ $trip->trip_details->count() }}</span>
                        </div>
                        <div class="table-responsive datatable-custom pb-0">
                            <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table dataTable no-footer mb-0 odv-items">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="border-0">#</th>
                                        <th class="border-0">{{ translate('Vehicle') }}</th>
                                        <th class="border-0">{{ translate('Unit fare') }}</th>
                                        <th class="border-0 text-center">{{ translate('QTY') }}</th>
                                        <th class="border-0 text-center">{{ $tripSpanLabel }}</th>
                                        <th class="border-0 text-right">{{ translate('Fare') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($trip->trip_details as $detail)
                                        @php
                                            if ($detail->rental_type == 'hourly') {
                                                $unitPrice = $detail->vehicle_details['hourly_price'] ?? 0;
                                                $unitLabel = translate('Hour');
                                            } elseif ($detail->rental_type == 'day_wise') {
                                                $unitPrice = $detail->vehicle_details['day_wise_price'] ?? 0;
                                                $unitLabel = translate('Day');
                                            } else {
                                                $unitPrice = $detail->vehicle_details['distance_price'] ?? 0;
                                                $unitLabel = $distanceUnitLabel;
                                            }
                                            $vehicleName = $detail->vehicle_details['name'] ?? '';
                                            $vehicleImage = data_get($detail->vehicle, 'thumbnailFullUrl', $fallbackVehicleImage);
                                            $plates = $detail->tripVehicleDetails
                                                ->map(fn ($row) => $row->vehicle_identity_data?->license_plate_number)
                                                ->filter();
                                        @endphp
                                        <tr>
                                            <td class="odv-items__sl">{{ $loop->iteration }}</td>
                                            <td>
                                                <div class="odv-item">
                                                    @if ($detail->vehicle)
                                                        <a class="odv-item__thumb"
                                                           href="{{ route('vendor.vehicle.details', $detail->vehicle_id) }}">
                                                            <img class="onerror-image" src="{{ $vehicleImage }}"
                                                                 data-onerror-image="{{ $fallbackVehicleImage }}"
                                                                 alt="{{ $vehicleName }}">
                                                        </a>
                                                    @else
                                                        <span class="odv-item__thumb">
                                                            <img src="{{ $fallbackVehicleImage }}" alt="{{ $vehicleName }}">
                                                        </span>
                                                    @endif
                                                    <div class="odv-item__body">
                                                        <strong class="odv-item__name">{{ $vehicleName }}</strong>
                                                        @if ($detail->vehicle)
                                                            <div class="tdv-item-meta">
                                                                @if ($detail->vehicle->category)
                                                                    <span>{{ $detail->vehicle->category->name }}</span>
                                                                @endif
                                                                @if ($detail->vehicle->brand)
                                                                    <span>{{ $detail->vehicle->brand->name }}</span>
                                                                @endif
                                                            </div>
                                                        @else
                                                            <div class="tdv-item-meta tdv-item-meta--missing">{{ translate('No data found') }}</div>
                                                        @endif

                                                        @if ($detail->tripVehicleDetails->isEmpty())
                                                            @if ($canAssignVehicle && $is_deleted != 1)
                                                                <button type="button"
                                                                        class="btn btn-outline-primary tdv-assign assign-vehicle-btn"
                                                                        data-toggle="modal"
                                                                        data-target="#assignVehicleModal"
                                                                        data-details_id="{{ $detail->id }}"
                                                                        data-trip_id="{{ $detail->trip_id }}"
                                                                        data-vehicle_id="{{ $detail->vehicle_id }}"
                                                                        data-quantity="{{ $detail->quantity }}"
                                                                        data-img="{{ $vehicleImage }}"
                                                                        data-name="{{ $vehicleName }}"
                                                                        data-vendor="{{ $trip->provider?->name }}"
                                                                        data-category="{{ $detail->vehicle?->category?->name }}"
                                                                        data-brand="{{ $detail->vehicle?->brand?->name }}"
                                                                        data-list="{{ json_encode($detail->vehicle?->vehicleIdentities) }}"
                                                                        data-trip_vehicle_details="{{ json_encode($detail->tripVehicleDetails->map(fn ($row) => $row->attributesToArray())) }}">
                                                                    <i class="tio-add-circle"></i> <span>{{ translate('Assign vehicle') }}</span>
                                                                </button>
                                                            @endif
                                                        @else
                                                            <div class="tdv-plates">
                                                                <span class="tdv-plates__label">{{ translate('Assigned vehicle') }}</span>
                                                                @forelse ($plates as $plate)
                                                                    <span class="tdv-plate">{{ $plate }}</span>
                                                                @empty
                                                                    <span class="tdv-plate">{{ translate('N/A') }}</span>
                                                                @endforelse
                                                                @if ($canAssignVehicle)
                                                                    <button type="button"
                                                                            class="tdv-plates__edit assign-vehicle-btn"
                                                                            title="{{ translate('Edit') }}"
                                                                            aria-label="{{ translate('Edit') }}"
                                                                            data-toggle="modal"
                                                                            data-target="#assignVehicleModal"
                                                                            data-details_id="{{ $detail->id }}"
                                                                            data-trip_id="{{ $detail->trip_id }}"
                                                                            data-vehicle_id="{{ $detail->vehicle_id }}"
                                                                            data-quantity="{{ $detail->quantity }}"
                                                                            data-img="{{ $vehicleImage }}"
                                                                            data-name="{{ $vehicleName }}"
                                                                            data-vendor="{{ $trip->provider?->name }}"
                                                                            data-category="{{ $detail->vehicle?->category?->name }}"
                                                                            data-brand="{{ $detail->vehicle?->brand?->name }}"
                                                                            data-list="{{ json_encode($detail->vehicle?->vehicleIdentities) }}"
                                                                            data-trip_vehicle_details="{{ json_encode($detail->tripVehicleDetails->map(fn ($row) => $row->attributesToArray())) }}">
                                                                        <i class="tio-edit"></i>
                                                                    </button>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="tdv-rate">
                                                {{ \App\CentralLogics\Helpers::format_currency($unitPrice) }}
                                                <small>/ {{ $unitLabel }}</small>
                                            </td>
                                            <td class="odv-qty">{{ $detail->quantity }}</td>
                                            <td class="odv-qty">{{ $tripSpanValue }}</td>
                                            <td class="odv-line-total">{{ \App\CentralLogics\Helpers::format_currency($detail->calculated_price) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="odv-summary-wrap">
                            <dl class="odv-summary">
                                <dt class="col-6">{{ translate('Trip fare') }}</dt>
                                <dd class="col-6">{{ \App\CentralLogics\Helpers::format_currency($subtotal) }}</dd>

                                <dt class="col-6">{{ translate('Discount') }}</dt>
                                <dd class="col-6">- {{ \App\CentralLogics\Helpers::format_currency($trip->discount_on_trip) }}</dd>

                                <dt class="col-6">{{ translate('Coupon discount') }}
                                    @if ($trip->proDiscount && $trip->proDiscount->benefit_type === 'coupon')
                                        <i class="tio-info-outined" data-toggle="tooltip"
                                           title="{{ translate('Pro customer coupon applied.') }}"></i>
                                    @endif
                                </dt>
                                <dd class="col-6">- {{ \App\CentralLogics\Helpers::format_currency($trip->coupon_discount_amount) }}</dd>

                                @if ($trip->ref_bonus_amount > 0)
                                    <dt class="col-6">{{ translate('Referral discount') }}</dt>
                                    <dd class="col-6">- {{ \App\CentralLogics\Helpers::format_currency($trip->ref_bonus_amount) }}</dd>
                                @endif

                                @if (($trip->proDiscount?->amount_saved ?? 0) > 0)
                                    <dt class="col-6">{{ translate('Pro discount') }}</dt>
                                    <dd class="col-6">- {{ \App\CentralLogics\Helpers::format_currency($trip->proDiscount->amount_saved) }}</dd>
                                @endif

                                @if ($trip->tax_status == 'excluded' && $trip->tax_amount > 0)
                                    <dt class="col-6">{{ translate('VAT/tax') }}</dt>
                                    <dd class="col-6">+ {{ \App\CentralLogics\Helpers::format_currency($trip->tax_amount) }}</dd>
                                @endif

                                <dt class="col-6">{{ $additionalChargeName }}</dt>
                                <dd class="col-6">+ {{ \App\CentralLogics\Helpers::format_currency($trip->additional_charge) }}</dd>

                                <div class="odv-summary__rule"></div>
                                <dt class="col-6 odv-summary__total-label">
                                    {{ translate('Total') }}
                                    @if ($trip->tax_status == 'included')
                                        <span class="tdv-muted">({{ translate('TAX included') }})</span>
                                    @endif
                                </dt>
                                <dd class="col-6 odv-summary__total-value">{{ \App\CentralLogics\Helpers::format_currency($trip->trip_amount) }}</dd>
                            </dl>
                        </div>
                        @if ($trip->edited)
                            <div class="odv-summary__note">
                                <div class="odv-note odv-note--warn">
                                    <i class="tio-info"></i>
                                    <div class="odv-note__body">
                                        {{ translate('Total bill has been updated after the edits.') }}
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4 order-print-area-right odv-side">
                @if ($trip->trip_status != 'completed' || $trip->payment_status != 'paid')
                    <div class="card">
                        <div class="card-body">
                            <div class="odv-sec__head">
                                <span class="odv-sec__icon"><i class="tio-settings-outlined"></i></span>
                                <h2 class="odv-sec__title">{{ translate('Trip setup') }}</h2>
                            </div>

                            @if ($trip->trip_status != 'completed')
                                <div class="odv-status-select">
                                    <label for="trip-status-toggle">{{ translate('Trip status') }}</label>
                                    <div class="dropdown">
                                        <button id="trip-status-toggle" type="button"
                                                class="form-control dropdown-toggle d-flex justify-content-between align-items-center w-100"
                                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            {{ $tripStatusLabel }}
                                        </button>
                                        <div class="dropdown-menu" aria-labelledby="trip-status-toggle">
                                            @foreach (['pending', 'confirmed', 'ongoing', 'completed', 'canceled'] as $status)
                                                @if ($status !== strtolower($trip->trip_status))
                                                    <a class="dropdown-item route-alert" href="javascript:"
                                                       data-url="{{ route('vendor.trip.status', ['id' => $trip->id, 'status' => $status]) }}"
                                                       data-message="{{ translate('Change status') }}: {{ $tripStatusLabels[$status] }}?">
                                                        {{ $tripStatusLabels[$status] }}
                                                    </a>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="odv-status-select">
                                <label for="payment-status-toggle">{{ translate('Payment status') }}</label>
                                <div class="dropdown">
                                    <button id="payment-status-toggle" type="button"
                                            class="form-control dropdown-toggle d-flex justify-content-between align-items-center w-100"
                                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        {{ $paymentStatusLabel }}
                                    </button>
                                    <div class="dropdown-menu" aria-labelledby="payment-status-toggle">
                                        @foreach ($paymentStatusLabels as $status => $label)
                                            @if ($status !== strtolower($trip->payment_status))
                                                <a class="dropdown-item route-alert" href="javascript:"
                                                   data-url="{{ route('vendor.trip.payment.status', ['id' => $trip->id, 'status' => $status]) }}"
                                                   data-message="{{ translate('Change status') }}: {{ $label }}?">
                                                    {{ $label }}
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            @if ($driverCount <= 0 && $assignedVehicleCount > 0)
                                <button type="button" class="btn btn--primary w-100 odv-assign-btn"
                                        data-toggle="modal" data-target="#assignDriverModal">
                                    <i class="tio-user-add"></i> <span>{{ translate('Assign driver') }}</span>
                                </button>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($driverCount > 0)
                    <div class="card">
                        <div class="card-body">
                            <div class="odv-sec__head">
                                <span class="odv-sec__icon"><i class="tio-user-big-outlined"></i></span>
                                <h2 class="odv-sec__title">{{ translate('Driver list') }}</h2>
                                <span class="odv-count">{{ $driverCount }}</span>
                                @if (!in_array($trip->trip_status, ['pending', 'completed', 'canceled']))
                                    <button type="button" class="btn btn-outline-primary btn-sm odv-sec__aside assign-driver-modal">
                                        <i class="tio-edit"></i> <span>{{ translate('Edit') }}</span>
                                    </button>
                                @endif
                            </div>

                            <div class="tdv-drivers">
                                @foreach ($tripDrivers as $driverDetails)
                                    <div class="odv-party">
                                        <div class="odv-party__avatar">
                                            <img class="onerror-image"
                                                 src="{{ $driverDetails->driver?->image_full_url ?? $fallbackUserImage }}"
                                                 data-onerror-image="{{ $fallbackUserImage }}"
                                                 alt="{{ $driverDetails->driver?->fullName }}">
                                        </div>
                                        <div class="odv-party__body">
                                            <span class="odv-party__name">{{ $driverDetails->driver?->fullName ?? translate('No driver assigned') }}</span>
                                            <div class="odv-party__meta">
                                                @if ($driverDetails->driver?->phone)
                                                    <span><i class="tio-call-talking-quiet"></i>{{ $driverDetails->driver->phone }}</span>
                                                @endif
                                                <span><i class="tio-car"></i>{{ $driverDetails->vehicle_identity_data?->license_plate_number ?? translate('N/A') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                <div class="card">
                    <div class="card-body">
                        <div class="odv-sec__head">
                            <span class="odv-sec__icon"><i class="tio-route"></i></span>
                            <h2 class="odv-sec__title">{{ translate('Trip route') }}</h2>
                            <a class="odv-sec__aside" href="{{ $routeUrl }}" target="_blank" rel="noopener noreferrer">
                                <i class="tio-open-in-new"></i> <span>{{ translate('Open in Google Maps') }}</span>
                            </a>
                        </div>

                        <div class="tdv-map">
                            <div class="custom_map_canvas" id="custom_route_line_map_canvas"></div>
                        </div>

                        <ol class="tdv-route">
                            <li class="tdv-route__stop tdv-route__stop--pickup">
                                <span class="tdv-route__label">{{ translate('Pickup') }}</span>
                                <span class="tdv-route__place">{{ data_get($pickup, 'location_name') ?? translate('N/A') }}</span>
                            </li>
                            <li class="tdv-route__stop tdv-route__stop--drop">
                                <span class="tdv-route__label">{{ translate('Destination') }}</span>
                                <span class="tdv-route__place">{{ data_get($destination, 'location_name') ?? translate('N/A') }}</span>
                            </li>
                        </ol>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="odv-sec__head">
                            <span class="odv-sec__icon"><i class="tio-user"></i></span>
                            <h2 class="odv-sec__title">{{ translate('Customer information') }}</h2>
                            @if ($trip->is_guest == 1)
                                <span class="odv-flag odv-sec__aside">{{ translate('Guest user') }}</span>
                            @endif
                        </div>

                        @if ($trip->customer)
                            <div class="odv-party">
                                @include('partials._user-avatar', [
                                    'imageUrl' => $trip->customer->image_full_url,
                                    'proStatus' => $trip->customer->pro_status ?? false,
                                    'size' => 44,
                                    'wrapperClass' => 'odv-party__avatar',
                                    'alt' => $trip->customer->fullName,
                                ])
                                <div class="odv-party__body">
                                    <span class="odv-party__name">{{ $trip->customer->fullName }}</span>
                                    <div class="odv-party__meta">
                                        <span><i class="tio-route"></i>{{ $trip->customer->trips_count ?? 0 }} {{ translate('Trips') }}</span>
                                        @if ($trip->customer->phone)
                                            <span><i class="tio-call-talking-quiet"></i>{{ $trip->customer->phone }}</span>
                                        @endif
                                        @if ($trip->customer->email)
                                            <span><i class="tio-email-outlined"></i>{{ $trip->customer->email }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @elseif (data_get($trip->user_info, 'contact_person_name'))
                            <div class="odv-party">
                                <div class="odv-party__avatar">
                                    <img src="{{ $fallbackUserImage }}" alt="{{ data_get($trip->user_info, 'contact_person_name') }}">
                                </div>
                                <div class="odv-party__body">
                                    <span class="odv-party__name">{{ data_get($trip->user_info, 'contact_person_name') }}</span>
                                    <div class="odv-party__meta">
                                        @if (data_get($trip->user_info, 'contact_person_number'))
                                            <span><i class="tio-call-talking-quiet"></i>{{ data_get($trip->user_info, 'contact_person_number') }}</span>
                                        @endif
                                        @if (data_get($trip->user_info, 'contact_person_email'))
                                            <span><i class="tio-email-outlined"></i>{{ data_get($trip->user_info, 'contact_person_email') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @elseif ($trip->is_guest == 1)
                            <div class="odv-empty">{{ translate('Guest user') }}</div>
                        @else
                            <div class="odv-empty">{{ translate('No data found') }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="assignDriverModal" tabindex="-1" role="dialog"
         aria-labelledby="assignDriverModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header p-2 pb-0 justify-content-end flex-shrink-0">
                    <button type="button" class="close p-0 m-0" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                </div>
                <form action="{{ route('vendor.trip.assign.driver') }}" method="post">
                    @csrf
                    <input type="hidden" name="trip_id" value="{{ $trip->id }}">
                    <div class="modal-body px-4 py-0">
                        <h5 class="font-bold" id="assignDriverModalLabel">{{ translate('Assign driver') }}</h5>
                        <div class="fs-12 mb-20">
                            <span id="vehicle-assign-count">
                                {{ count($trip->vehicle_identity->filter(fn($v) => !$v->vehicle_driver_id)) }}
                            </span>
                            {{ translate('Vehicle need to assign driver') }}
                        </div>
                        <div class="card shadow-none">
                            <div class="table-responsive">
                                <table
                                    class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table dataTable no-footer mb-0">
                                    <thead class="thead-light">
                                    <tr>
                                        <th class="border-0">{{ translate('Vehicle list') }}</th>
                                        <th class="border-0">{{ translate('Selected driver') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($trip->vehicle_identity as $vehicleDetails)
                                        <tr>
                                            <td>
                                                <div class="media media--sm">
                                                    <span class="mr-3">
                                                        <img width="60" height="40" class="img--ratio-2 onerror-image rounded h--40px"
                                                             src="{{ data_get($vehicleDetails?->vehicles, 'thumbnailFullUrl', $fallbackVehicleImage) }}"
                                                             data-onerror-image="{{ $fallbackVehicleImage }}"
                                                             alt="{{ $vehicleDetails?->vehicles?->name }}">
                                                    </span>
                                                    <div class="media-body">
                                                        <div class="fs-12 text--title">
                                                            <div class="font-bold">{{ ucwords($vehicleDetails?->vehicles?->name) }}</div>
                                                            <div class="font-semibold opacity-60">{{ translate('Car no') }}: {{ $vehicleDetails?->vehicle_identity_data?->license_plate_number }}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column w-100">
                                                    <select name="driver_ids[{{ $vehicleDetails->id }}]"
                                                            class="form-control js-select2-custom driver-select"
                                                            data-placeholder="{{ translate('Select vehicle driver') }}"
                                                            id="driver_{{ $vehicleDetails->id }}">
                                                        <option value="" selected disabled>{{ translate('Select vehicle driver') }}</option>
                                                        @foreach($trip?->provider?->vehicleDriver ?? [] as $providerDriver)
                                                            <option value="{{ $providerDriver->id }}"
                                                                    {{ $vehicleDetails->vehicle_driver_id == $providerDriver->id ? 'selected' : '' }}
                                                                    data-driver-id="{{ $providerDriver->id }}">
                                                                {{ $providerDriver?->fullName }} ({{ $providerDriver->phone }})
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 flex-shrink-0 px-4">
                        <div class="btn--container justify-content-end">
                            <button type="button" data-dismiss="modal" aria-label="Close"
                                    class="btn btn--warning-light min-w-120px">{{ translate('messages.Cancel') }}</button>
                            <button type="submit"
                                    class="btn btn--primary min-w-120px">{{ translate('messages.assign') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="assignVehicleModal" tabindex="-1" role="dialog"
         aria-labelledby="assignVehicleModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header p-2 pb-0 justify-content-end flex-shrink-0">
                    <button type="button" class="close p-0 m-0" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                </div>
                <form action="{{ route('vendor.trip.assign.vehicle') }}" method="post">
                    @csrf
                    <div class="modal-body px-4 py-0">
                        <div class="media media--sm flex-wrap mb-20">
                            <span class="mr-3">
                                <img id="vehicleImage" width="160" class="img-fluid rounded aspect-2-1 onerror-image"
                                     src="{{ asset('public/assets/admin/img/car-demo.png') }}"
                                     data-onerror-image="{{ asset('public/assets/admin/img/car-demo.png') }}"
                                     alt="">
                            </span>
                            <div class="media-body">
                                <div class="text--title">
                                    <div class="fs-20 font-semibold line--limit-1" id="vehicleName">{{ translate('Vehicle name') }}</div>
                                    <div class="mb-2"><span class="font-semibold"> {{ translate('vendor') }}:</span> <span id="vehicleVendor">{{ translate('Vehicle name') }}</span></div>
                                    <div class="d-flex flex-wrap gap-2 gap-sm-4">
                                        <div><span class="font-semibold"> {{ translate('Category') }}  :</span> <span id="vehicleCategory"> {{ translate('Category') }} </span></div>
                                        <div><span class="font-semibold"> {{ translate('Brand') }}  :</span> <span id="vehicleBrand"> {{ translate('Brand') }} </span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <h5 class="font-bold" id="assignVehicleModalLabel"> {{ translate('Vehicles list') }}<span class="fs-12 font-regular">({{ translate('Select any of') }} <span id="vehicleQuantity"></span> {{ translate('Vehicle') }})</span></h5>
                        <div class="card shadow-none">
                            <div class="table-responsive">
                                <table
                                    class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table dataTable no-footer mb-0">
                                    <thead class="thead-light">
                                    <tr>
                                        <th class="border-0"> {{ translate('SL') }}</th>
                                        <th class="border-0"> {{ translate('VIN number') }}</th>
                                        <th class="border-0"> {{ translate('License number') }}</th>
                                        <th class="border-0 text-center">{{ translate('Action') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 flex-shrink-0 px-4">
                        <div class="btn--container justify-content-end">
                            <button type="reset" data-dismiss="modal" aria-label="Close"
                                    class="btn btn--warning-light min-w-120px">{{ translate('messages.Cancel') }}</button>
                            <button type="submit"
                                    class="btn btn--primary min-w-120px">{{ translate('Add') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="providerLocationModal" tabindex="-1" role="dialog"
         aria-labelledby="providerLocationModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header pt-4 px-4">
                    <h4 class="modal-title" id="providerLocationModalLabel">{{ translate('messages.Trip ID') }} {{ $trip->id }}
                    </h4>
                    <button type="button" class="close p-0 m-0" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-12 modal_body_map">
                            <div class="location-map" id="location-map">
                                <div class="initial--25 rounded-8 custom_map_canvas" id="provider_map_canvas"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editTripModal" tabindex="-1" role="dialog" aria-labelledby="editTripModalLabel">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content modal-scroll">
                <div class="modal-header pt-4 px-4 flex-shrink-0">
                    <h4 class="modal-title" id="editTripModalLabel">{{ translate('messages.Trip ID') }}  {{ $trip->id }}</h4>
                    <button type="button" class="close p-0 m-0" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                </div>
                <form action="" method="post" id="updateForm">
                    @csrf
                    <input type="hidden" id="pickup-lat" name="pickup_lat">
                    <input type="hidden" id="pickup-lng" name="pickup_lng">
                    <input type="hidden" id="destination-lat" name="destination_lat">
                    <input type="hidden" id="destination-lng" name="destination_lng">
                    <input type="hidden" id="distance-input" name="distance">
                    <input type="hidden" name="trip_id" value="{{ $trip->id }}">

                    <div class="modal-body px-4 py-0">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group text-title">
                                    <label class="input-label font-semibold" for="pickup-input">{{ translate('Pickup location') }}</label>
                                    <div class="position-relative w-100 d-flex align-items-center">
                                        <input type="text" name="pickup_location" id="pickup-input" class="form-control pr-2"
                                               placeholder="{{ translate('Enter pickup location') }}"
                                               value="{{ data_get($pickup, 'location_name') }}">
                                        <div class="input-icon fs-20 opacity-60">
                                            <i class="tio-poi"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group text-title">
                                    <label class="input-label font-semibold" for="destination-input">{{ translate('Destination') }}</label>
                                    <div class="position-relative w-100 d-flex align-items-center">
                                        <input type="text" name="destination_location" id="destination-input" class="form-control pr-2"
                                               placeholder="{{ translate('Enter destination location') }}"
                                               value="{{ data_get($destination, 'location_name') }}">
                                        <div class="input-icon fs-20 opacity-60">
                                            <i class="tio-navigate-outlined rotate-45 d-block"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group text-title">
                                    <label class="input-label font-semibold" for="trip-type-display">{{ translate('Trip type') }}</label>
                                    <input type="text" class="form-control pr-2" name="trip_type" value="{{ $trip->trip_type }}" hidden>
                                    <input type="text" id="trip-type-display" class="form-control pr-2" value="{{ $tripTypeLabel }}" disabled>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group text-title">
                                    <label class="input-label font-semibold" for="trip-schedule">{{ translate('Trip schedule') }}</label>
                                    <div class="position-relative w-100 d-flex align-items-center">
                                        <input type="datetime-local" name="schedule_at" id="trip-schedule" readonly
                                               value="{{ $trip->schedule_at }}" class="form-control pr-2 opacity-lg">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card p-0">
                            <div class="card-body p-0 bg--F6F6F6">
                                <div class="table-responsive">
                                    <table
                                        class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table dataTable no-footer mb-0">
                                        <thead class="bg--EDEDED text--title">
                                        <tr>
                                            <th class="border-0">#</th>
                                            <th class="border-0">{{ translate('Vehicle details') }}</th>
                                            <th class="border-0">{{ translate('Unit fare') }}</th>
                                            <th class="border-0 text-center">{{ translate('quantity') }}</th>
                                            <th class="border-0 text-center">{{ $tripSpanLabel }}</th>
                                            <th class="border-0 text-center">{{ translate('Fare') }}</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($trip->trip_details as $editDetail)
                                            @php
                                                if ($editDetail->rental_type == 'hourly') {
                                                    $getPrice = $editDetail->vehicle_details['hourly_price'] ?? 0;
                                                    $getType = translate('Hour');
                                                } elseif ($editDetail->rental_type == 'day_wise') {
                                                    $getPrice = $editDetail->vehicle_details['day_wise_price'] ?? 0;
                                                    $getType = translate('Day');
                                                } else {
                                                    $getPrice = $editDetail->vehicle_details['distance_price'] ?? 0;
                                                    $getType = $distanceUnitLabel;
                                                }
                                            @endphp
                                            <tr>
                                                <td>
                                                    <div class="eta_amount">
                                                        {{ $loop->iteration }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="media media--sm eta_amount">
                                                        <a class="avatar avatar-xl mr-3" href="{{ route('vendor.vehicle.details', $editDetail->vehicle_id) }}">
                                                            <img class="img-fluid rounded aspect-ratio-1 onerror-image"
                                                                 src="{{ data_get($editDetail?->vehicle, 'thumbnailFullUrl', $fallbackVehicleImage) }}"
                                                                 data-onerror-image="{{ $fallbackVehicleImage }}"
                                                                 alt="{{ $editDetail?->vehicle_details['name'] ?? '' }}">
                                                        </a>
                                                        <div class="media-body">
                                                            <div class="fs-12 text--title">
                                                                <div class="fz-12 font-semibold line--limit-1">
                                                                    {{ $editDetail?->vehicle_details['name'] ?? '' }}</div>
                                                                <div><span class="font-semibold mr-2">{{ translate('Category') }} :</span>{{ $editDetail?->vehicle?->category?->name }}
                                                                </div>
                                                                <div><span class="font-semibold mr-2">{{ translate('Brand') }} :</span>{{ $editDetail?->vehicle?->brand?->name }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="fs-14 eta_amount text--title">
                                                        {{ \App\CentralLogics\Helpers::format_currency($getPrice) }}
                                                        /{{ $getType }}
                                                    </div>
                                                </td>

                                                <td class="text-center">
                                                    <div class="d-flex flex-column gap-1 align-items-center">
                                                        <span class="eta_amount  d-none"> </span>
                                                        <input type="number" name="quantity" class="form-control fs-14 text--title w--60px quantity-input text-center" min="1" max="{{ $editDetail->vehicle_variations_count }}"
                                                               data-max_quantity="{{ $editDetail->vehicle_variations_count }}"
                                                               data-max_original_quantity="{{ $editDetail->quantity }}"
                                                               data-id="{{ $editDetail->id }}" data-vehicle_id="{{ $editDetail->vehicle_id }}" value="{{ $editDetail->quantity }}" placeholder="{{ translate('Ex') }}: 5">
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex flex-column align-items-center">
                                                        <span class="eta_amount  d-none"> </span>
                                                        @if ($trip->trip_type == 'distance_wise')
                                                            <span class="distance-input">{{ $tripSpanValue }}</span>
                                                        @else
                                                            <span>{{ $tripSpanValue }}</span>
                                                        @endif
                                                    </div>
                                                </td>

                                                <td class="text-center">
                                                    <div class="d-flex flex-column gap-1 align-items-center">
                                                        <span class="eta_amount_mt d-none "> *{{ translate('System EST fare') }}:
                                                            <small id="est_{{ $editDetail->id }}" class=" text--warning"> </small>
                                                        </span>
                                                        <input type="text" name="price" min="1" max="999999999"
                                                               data-price="{{ $editDetail->price }}"
                                                               class="form-control w--120px text-center fs-14 text--title fare-total"
                                                               data-id="{{ $editDetail->id }}"
                                                               data-vehicle_id="{{ $editDetail->vehicle_id }}"
                                                               data-old-value="{{ $editDetail->original_price  * $editDetail->quantity}}"
                                                               data-quantity="{{ $editDetail->quantity }}"
                                                               value="{{ \App\CentralLogics\Helpers::format_currency($editDetail->calculated_price) }}"
                                                               placeholder="{{ translate('Fare') }}">
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mx-3">
                                    <hr>
                                </div>
                                <div class="row justify-content-md-end mb-3 mt-4 mx-0">
                                    <div class="col-md-9 col-lg-8">
                                        <dl class="row text-right text-title">
                                            <dt class="col-6 font-regular">{{ translate('Trip fare') }}</dt>
                                            <dd class="col-6 total_fare">
                                                {{ \App\CentralLogics\Helpers::format_currency($subtotal) }}
                                            </dd>

                                            <dt class="col-6">{{ translate('subtotal') }}</dt>
                                            <dd class="col-6 font-semibold subtotal">
                                                {{ \App\CentralLogics\Helpers::format_currency($subtotal) }}
                                            </dd>

                                            <dt class="col-6 font-regular ">{{ translate('Discount') }}</dt>
                                            <dd class="col-6 discount_amount">
                                                -{{ \App\CentralLogics\Helpers::format_currency($trip->discount_on_trip) }}
                                            </dd>

                                            <dt class="col-6 font-regular ">{{ translate('Coupon discount') }}
                                                @if ($trip->proDiscount && $trip->proDiscount->benefit_type === 'coupon')
                                                    <i class="tio-info-outined" data-toggle="tooltip"
                                                       title="{{ translate('Pro customer coupon applied.') }}"></i>
                                                @endif
                                            </dt>
                                            <dd class="col-6 coupon_discount_amount">
                                                -{{ \App\CentralLogics\Helpers::format_currency($trip->coupon_discount_amount) }}
                                            </dd>

                                            @if ($trip->ref_bonus_amount > 0)
                                                <dt class="col-6 font-regular">{{ translate('Referral discount') }}</dt>
                                                <dd class="col-6 ref_bonus_amount">
                                                    -{{ \App\CentralLogics\Helpers::format_currency($trip->ref_bonus_amount) }}
                                                </dd>
                                            @endif

                                            <dt class="col-6 font-regular">{{ translate('VAT/tax') }}
                                                <span id="tax_include_or_exclude"></span>
                                            </dt>
                                            <dd class="col-6 text-right tax_amount">
                                                + {{ \App\CentralLogics\Helpers::format_currency($trip->tax_amount) }}
                                            </dd>

                                            <dt class="col-6 font-regular ">{{ $additionalChargeName }}</dt>
                                            <dd class="col-6 text-right additional_charge">
                                                + {{ \App\CentralLogics\Helpers::format_currency($trip->additional_charge) }}</dd>

                                            <dt class="col-6 font-bold">{{ translate('Total') }}</dt>
                                            <dd class="col-6 font-bold grand-total">{{ \App\CentralLogics\Helpers::format_currency($trip->trip_amount) }}</dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 flex-shrink-0 px-4">
                        <div class="btn--container justify-content-end">
                            <button type="reset" id="reset_btn" class="btn btn--warning-light min-w-120px close-modal">{{ translate('messages.Cancel') }}</button>
                            <button id="edit-trip" type="button" class="btn btn--primary  min-w-120px">{{ translate('Update') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="mapModal" tabindex="-1" role="dialog" aria-labelledby="mapModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header pt-4 px-4 flex-shrink-0">
                    <h4 class="modal-title" id="mapModalLabel">{{ translate('messages.Trip ID') }}  {{ $trip->id }}</h4>
                    <button type="button" class="close p-0 m-0" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body px-4 py-4">
                    <input id="search-input" type="text" class="form-control mb-3" placeholder="{{ translate('Search location') }}">
                    <div id="map"></div>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" id="currency_symbol" value="{{ \App\CentralLogics\Helpers::currency_symbol() }}">
    <input type="hidden" id="tax_included" value="({{ translate('Included') }})">
    <div id="max_quantity_msg" data-max-quantity-msg="{{ translate('Maximum available quantity is') }}"></div>
    <div id="get_calculation_url" data-url="{{ route('vendor.trip.get-calculation') }}"></div>

    <div id="provider_latitude" data-provider-latitude="{{ $trip?->provider?->latitude ?? 0 }}"></div>
    <div id="provider_longitude" data-provider-longitude="{{ $trip?->provider?->longitude ?? 0 }}"></div>
    <div id="provider_name" data-provider-name="{{ Str::limit($trip?->provider?->name ?? '', 15, '...') }}"></div>
    <div id="map-marker-image" data-map-marker-image="{{ asset('public/assets/admin/img/icons/pickup.svg') }}"></div>
    <div id="provider_logo" data-provider-logo="{{ $trip?->provider?->logo_full_url ?? asset('public/assets/admin/img/100x100/1.png') }}"></div>
    <div id="provider_address" data-provider-address="{{ $trip?->provider?->address ?? '' }}"></div>
    <div id="pickup-location-lat" data-pickup-location-lat="{{ data_get($pickup, 'lat') }}"></div>
    <div id="pickup-location-lng" data-pickup-location-lng="{{ data_get($pickup, 'lng') }}"></div>
    <div id="destination-location-lat" data-destination-location-lat="{{ data_get($destination, 'lat') }}"></div>
    <div id="destination-location-lng" data-destination-location-lng="{{ data_get($destination, 'lng') }}"></div>

@endsection

@push('script_2')
    <script src="https://maps.googleapis.com/maps/api/js?key={{ \App\CentralLogics\Helpers::get_business_settings('map_api_key', false) }}&libraries=places&v=3.45.8"></script>
    <script src="{{asset('Modules/Rental/public/assets/js/view-pages/provider/trip-details.js')}}"></script>
@endpush
