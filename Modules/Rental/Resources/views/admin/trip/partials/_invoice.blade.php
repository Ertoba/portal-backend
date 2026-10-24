<div class="content container-fluid invoice-page initial-38">
    <div id="printableArea">
        <div>
            <div class="text-center">
                <input type="button" class="btn btn-primary mt-3 non-printable print-Div"
                       value="{{ translate('Proceed, if the thermal printer is ready.') }}" />
                <a href="{{ url()->previous() }}"
                   class="btn btn-danger non-printable mt-3"><i class="tio-arrow-backward"></i> {{ translate('Back') }}</a>
            </div>

            <hr class="non-printable">

            <div class="print--invoice initial-38-1">
                @if ($trip?->provider)
                    <div class="text-center pt-4 mb-3">
                        <img class="invoice-logo" src="{{ asset('/public/assets/admin/img/car_icon.svg') }}"
                             alt="">
                        <div class="top-info">
                            <h2 class="store-name">
                                 {{ $trip?->provider?->name }}
                            </h2>
                            <div>
                                <img src="{{ asset('/public/assets/admin/img/location_icon.svg') }}" alt="">
                                {{ $trip?->provider?->address }}
                            </div>
                            <div class="mt-1 d-flex justify-content-center">
                                <span><img src="{{ asset('/public/assets/admin/img/phone_icon.svg') }}" alt=""></span>&nbsp;
                                <span>{{ $trip?->provider?->phone }}</span>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="img-wrap">
                    <div class="top-info">

                        <img src="{{ asset('/public/assets/admin/img/line_icon.svg') }}" alt="" class="w-100">
                    </div>
                    <div class="order-info-id text-center">
                        <div class="d-flex justify-content-center mb-2 fs-12">
                            <span class="fw-medium">{{ translate('Trip ID') }}</span>
                            <span>:</span>
                            <span class="fw-medium">{{ $trip?->id }}</span>
                        </div>
                        <div>
                            {{ \App\CentralLogics\Helpers::time_date_format($trip?->schedule_at) }}
                        </div>
                        <div>
                            @if ($trip->provider?->gst_status)
                                <span>{{ translate('GST no') }}</span> <span>:</span> <span>{{ $trip?->provider?->gst_code }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="order-info-details">
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="mb-1">
                                    <span class="opacity-70">{{ translate('Customer name') }}</span> <span>:</span>
                                    <span>{{ $trip?->customer ? $trip?->customer?->fullName : $trip?->user_info['contact_person_name'] }}</span>
                                </div>
                                <div class="mb-1">
                                    <span class="opacity-70">{{ translate('Phone') }}</span> <span>:</span>
                                    <span>{{ $trip?->customer ? $trip?->customer?->phone : $trip?->user_info['contact_person_number'] }}</span>
                                </div>
                                <div class="text-break mb-1">
                                    <span class="opacity-70">{{ translate('Pickup location') }}</span> <span>:</span>
                                    <span>{{ $trip?->pickup_location['location_name'] }}</span>
                                </div>
                                <div class="text-break mb-1">
                                    <span class="opacity-70">{{ translate('messages.Destination location') }}</span> <span>:</span>
                                    <span>{{ $trip?->destination_location['location_name'] }}</span>
                                </div>
                            </div>
                        </div>

                        <div><img src="{{ asset('/public/assets/admin/img/line_icon.svg') }}" alt="" class="w-100"></div>

                        <div>
                            <table class="table invoice--table text-black mb-1">
                                <thead class="border-0">
                                <tr class="border-0">
                                    <th>{{ translate('Vehicle list') }}</th>
                                    <th class="w-10p"></th>
                                    <th>{{ translate('messages.price') }}</th>
                                </tr>
                                </thead>

                                <tbody>
                                @php($sub_total = 0)
                                @php($total_tax = 0)
                                @foreach($trip->trip_details as $details)
                                    <tr>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <div>{{ $loop->iteration }}.</div>
                                                <div class="opacity-70">
                                                    <strong class="d-block mb-1">{{ $details?->vehicle_details['name'] }}</strong>
                                                    <span class="fs-9">
                                                        {{ \App\CentralLogics\Helpers::format_currency($details['price']) }}/{{ translate($details?->rental_type) }},
                                                        {{ $details->quantity }} {{ translate('Vehicle') }},
                                                        <?php
                                                           if( $details->rental_type == 'hourly'){
                                                            $getTime= $details->estimated_hours .' Hours';
                                                        } elseif( $details->rental_type == 'day_wise'){
                                                            $getTime=( (int) round($details->estimated_hours/ 24)  ) .'Days'; ;
                                                        } else{
                                                            $getTime = $details->distance_label ?? '';
                                                        }
                                                        ?>
                                                        {{ $getTime }}
                                                    </span><br>
                                                    @php($licensePlates = $details?->tripVehicleDetails->map(function($vehicleDetails) {
                                                            return $vehicleDetails?->vehicle_identity_data?->license_plate_number ?? translate('No data found');
                                                        })->filter()->implode(', ') ?? translate('Vehicle not assigned'))

                                                    <span class="fs-9">@if($licensePlates) {{translate('vehicles')}}: {{ $licensePlates }} @endif </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td></td>
                                        <td>
                                            @php($amount = $details['price'] * $details['quantity'])
                                            {{ \App\CentralLogics\Helpers::format_currency($amount) }}
                                        </td>
                                    </tr>
                                    @php($sub_total += $amount)
                                    @php($total_tax += $details['tax_amount'] * $details['quantity'])
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div><img src="{{ asset('/public/assets/admin/img/line_icon.svg') }}" alt="" class="w-100"></div>

                        <div class="checkout--info">
                            <dl class="row text-right">
                                <dt class="col-6 opacity-70">{{ translate('messages.subtotal') }}
                                    @if ($trip->tax_status == 'included' )
                                        ({{ translate('TAX included') }})
                                    @endif
                                    :</dt>
                                <dd class="col-6"> {{ \App\CentralLogics\Helpers::format_currency($sub_total) }} </dd>

                                <dt class="col-6 opacity-70">{{ translate('Discount') }}:</dt>
                                <dd class="col-6">  - {{ \App\CentralLogics\Helpers::format_currency($trip['discount_on_trip'])}}</dd>

                                <dt class="col-6 opacity-70">{{ translate('Coupon discount') }}:</dt>
                                <dd class="col-6"> - {{ \App\CentralLogics\Helpers::format_currency($trip['coupon_discount_amount']) }}</dd>

                                @if (($trip->proDiscount?->amount_saved ?? 0) > 0)
                                <dt class="col-6 opacity-70">{{ translate('messages.Pro discount') }}:</dt>
                                <dd class="col-6"> - {{ \App\CentralLogics\Helpers::format_currency($trip->proDiscount?->amount_saved) }}</dd>
                                @endif

                                <dt class="col-6 opacity-70">{{ translate('messages.tax') }}:</dt>
                                <dd class="col-6"> +{{ \App\CentralLogics\Helpers::format_currency($trip['tax_amount']) }}</dd>

                                <dt class="col-6 total">{{ translate('messages.Total') }}:</dt>
                                <dd class="col-6 total"> {{ \App\CentralLogics\Helpers::format_currency($trip->trip_amount) }}</dd>
                            </dl>
                        </div>
                    </div>

                    <div class="top-info">
                        <img src="{{ asset('/public/assets/admin/img/line_icon.svg') }}" alt="" class="w-100">
                        <div>{{ translate('Thank you') }}</div>
                        <img src="{{ asset('/public/assets/admin/img/line_icon.svg') }}" alt="" class="w-100">

                        <div class="copyright">
                            &copy; {{ \App\CentralLogics\Helpers::get_business_settings('business_name', false) }}.
                            <span class="d-none d-sm-inline-block">{{ \App\CentralLogics\Helpers::get_business_settings('footer_text', false) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('script_2')
    <script src="{{asset('Modules/Rental/public/assets/js/admin/view-pages/invoice.js')}}"></script>
@endpush
