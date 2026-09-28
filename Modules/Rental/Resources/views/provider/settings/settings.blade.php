@extends('layouts.vendor.app')

@section('title',translate('messages.settings'))

@push('css_or_js')
<link href="{{asset('public/assets/admin/css/croppie.css')}}" rel="stylesheet">
<link rel="stylesheet" href="{{asset('public/assets/admin/css/custom.css')}}">

@endpush

@section('content')

    <div class="content container-fluid config-inline-remove-class">
        <!-- Page Heading -->
        <div class="page-header">
            <h1 class="page-header-title">
                <span>
                    {{translate('messages.Provider_Setup')}}
                </span>
            </h1>
        </div>
        <!-- Page Heading -->
        <div class="card mb-3">
            <div class="card-body py-3">
                <div class="row">
                    <div class="col-lg-6">
                        <div>

                            <p>{{ translate('To view a list of all active zones on your') }} <a target="_blank" href="{{ route('home') }}" class="text-underline text--info">{{ translate('Admin Landing') }}</a> {{ translate('Page, Enable the') }} <span class="font-semibold">'{{ translate('Available Zones') }}'</span> {{ translate('feature') }}</p>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="">
                            <label class="toggle-switch toggle-switch-sm d-flex justify-content-between border border-secondary rounded px-4 form-control" for="restaurant-open-status">
                                <span class="pr-2">{{translate('messages.Provider_temporarily_closed_title')}}</span>
                                <label class="switch toggle-switch-lg m-0">
                                    <input id="restaurant-open-status" type="checkbox" class="toggle-switch-input restaurant-open-status"
                                           data-title="{{translate('messages.are_you_sure')}}"
                                           data-text="{{$store->active ? translate('messages.you_want_to_temporarily_close_this_').($store->module->module_type == 'rental' ? translate('provider') : translate('store')) : translate('messages.you_want_to_open_this_').($store->module->module_type == 'rental' ? translate('provider') : translate('store')) }}"
                                           data-route="{{route('vendor.business-settings.update-active-status')}}"
                                           data-no="{{translate('messages.no')}}"
                                           data-yes="{{translate('messages.yes')}}"
                                        {{$store->active ?'':'checked'}}>
                                    <span class="toggle-switch-label">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <div>
                    <h5 class="text-title mb-1">
                        {{ translate('Basic Settings') }}
                    </h5>
                    <p class="fs-12 mb-0">
                        {{ translate('Vendor Settings') }}
                    </p>
                </div>
            </div>
            <form action="{{route('vendor.business-settings.update-setup',[$store['id']])}}" method="post"
                    enctype="multipart/form-data">
                    @csrf
            <div class="card-body">
                <div class="row g-4 align-items-end">
                    <div class="col-lg-4 col-sm-6">
                        <div class="form-group mb-0">
                            <label class="input-label font-semibold" for="schedule_order">
                                {{ translate('Scheduled Trip') }}
                            </label>
                            <label class="toggle-switch toggle-switch-sm d-flex justify-content-between border border-secondary rounded px-4 form-control" for="schedule_order">
                                <span class="pr-2">{{translate('messages.scheduled_order')}}<span class="input-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{translate('When_enabled,_store_owner_can_take_scheduled_orders_from_customers.')}}"><img src="{{asset('/public/assets/admin/img/info-circle.svg')}}" alt="{{translate('messages.scheduled_order_hint')}}"></span></span>
                                <input type="checkbox" value="1" class="toggle-switch-input " name="schedule_order" id="schedule_order" {{$store->schedule_order?'checked':''}}>
                                <span class="toggle-switch-label">
                                    <span class="toggle-switch-indicator"></span>
                                </span>
                            </label>
                        </div>
                    </div>
                    <div class="col-lg-4 col-sm-6">
                        <div class="">
                            <label class="d-flex justify-content-between switch toggle-switch-sm text-dark" for="gst_status">
                                <label class="input-label font-semibold mb-0">{{translate('messages.GST')}} <span class="form-label-secondary" data-toggle="tooltip" data-placement="right"
                                data-original-title="{{translate('messages.If GST is enable, GST number will show in invoice')}}"><i class="tio-info text--title opacity-60"></i></span></label>
                                <input type="checkbox" class="toggle-switch-input" name="gst_status" id="gst_status" value="1" {{$store->gst_status?'checked':''}}>
                                <span class="toggle-switch-label">
                                    <span class="toggle-switch-indicator"></span>
                                </span>
                            </label>
                            <input type="text" id="gst" name="gst" class="form-control" value="{{$store->gst_code}}" {{isset($store->gst_status)?'':'readonly'}}>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="position-relative">
                            <label class="input-label font-semibold"
                                for="tax">{{ translate('Approx. Pickup Time') }}</label>
                            <div class="custom-group-btn">
                                <div class="item flex-sm-grow-1">
                                    <input id="min" type="number" name="minimum_delivery_time"
                                        value="{{explode('-',$store->delivery_time)[0]}}"
                                        class="form-control h--45px border-0"
                                        placeholder="{{ translate('messages.Ex :') }} 20"
                                        pattern="^[0-9]{2}$" required>
                                </div>
                                <div class="separator"></div>
                                <div class="item flex-sm-grow-1">
                                    <input id="max" type="number" name="maximum_delivery_time"
                                        value="{{explode(' ',explode('-',$store->delivery_time)[1])[0]}}"
                                        class="form-control h--45px border-0"
                                        placeholder="{{ translate('messages.Ex :') }} 30" pattern="[0-9]{2}$"
                                        required>
                                </div>
                                <div class="separator"></div>
                                <div class="item flex-shrink-0">
                                    <select name="delivery_time_type" class="custom-select border-0"  required>
                                        <option value="min" {{explode(' ',explode('-',$store->delivery_time)[1])[1]=='min'?'selected':''}}>{{translate('messages.minutes')}}</option>
                                        <option value="hours" {{explode(' ',explode('-',$store->delivery_time)[1])[1]=='hours'?'selected':''}}>{{translate('messages.hours')}}</option>
                                        <option value="days" {{explode(' ',explode('-',$store->delivery_time)[1])[1]=='days'?'selected':''}}>{{translate('messages.days')}}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group mb-0 pickup-zone-tag">
                            <label class="input-label font-semibold"
                                for="pickup_zones">{{ translate('messages.pickup_zone') }}<span
                                    class="form-label-secondary" data-toggle="tooltip" data-placement="right"
                                    data-original-title="{{ translate('messages.Select zones from where customer can choose their pickup locations for trip booking') }}">
                                    <i class="tio-info text--title opacity-60"></i>
                                </span></label>
                            <select name="pickup_zones[]" id="pickup_zones"
                                class="form-control  multiple-select2" multiple="multiple">


                                @foreach ($zones as $zone)
                                <?php
                                    $pickupZoneIds = json_decode($store->pickup_zone_id) ?? [];
                                ?>

                                @if (in_array($zone->id, $pickupZoneIds))
                                    <option value="{{ $zone->id }}" selected>{{ $zone->name }}</option>
                                @else
                                    <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                                @endif
                            @endforeach

                            </select>


                        </div>
                    </div>
                    <div class="col-12">
                        <div class="btn--container mt-3 justify-content-end">
                            <button type="reset" class="btn btn--reset">{{translate('messages.reset')}}</button>
                            <button type="submit" class="btn btn--primary">{{translate('messages.update')}}</button>
                        </div>
                    </div>
                </div>
            </div>

            </form>
        </div>
        @if($admin_website_builder_status == 1)

            <div class="card mt-3" id="admin_website_builder_section">
                <div class="card-body">
                    <div class="mb-20">
                        <div class="row g-1 align-items-center">
                            <div class="col-xxl-9 col-lg-8 col-md-7 col-sm-6">
                                <div>
                                    <h4 class="mb-1">
                                        {{ translate('Vendor Website Builder') }}
                                    </h4>
                                    <p class="mb-0 fs-12">
                                        {{ translate('Enable this option to allow vendors to set up and manage their own website.') }}
                                    </p>
                                </div>
                            </div>
                            <div class="col-xxl-3 col-lg-4 col-md-5 col-sm-6">
                                <div class="">
                                    <div class="form-group mb-0">
                                        <label
                                            class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control">
                                            <span class="pr-1 d-flex align-items-center switch--label">
                                                <span class="line--limit-1">
                                                    {{translate('Status') }}
                                                </span>
                                            </span>
                                            <input type="checkbox"
                                                data-id="website_builder_status"
                                                data-type="toggle"
                                                data-image-on="{{ asset('/public/assets/admin/img/modal/store-reg-on.png') }}"
                                                data-image-off="{{ asset('/public/assets/admin/img/modal/store-reg-off.png') }}"
                                                data-title-on="<strong>{{translate('Are you sure to enable vendor Website setup?')}}</strong>"
                                                data-title-off="<strong>{{translate('Are you sure to disable vendor Website setup?')}}</strong>"
                                                data-text-on="<p>{{ translate('If enabled, vendors will have the freedom to create, edit, and manage their own websites independently.') }}</p>"
                                                data-text-off="<p>{{ translate('If disabled, vendors will not be able to create or manage their own websites.') }}</p>"
                                                class="status toggle-switch-input dynamic-checkbox"
                                                value="1"
                                                name="website_builder_status" id="website_builder_status"
                                                {{ $store->storeConfig?->website_builder_status == 1?'checked':'' }}>
                                            <span class="toggle-switch-label text">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                                </label>
                                                <form action="{{route('vendor.business-settings.website-builder-status',[$store->id,$store->storeConfig?->website_builder_status?0:1])}}"  method="get"  id="website_builder_status_form"></form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
        @endif
        <div class="card mb-3 mt-3">
            <div class="card-header">
                <div>
                    <h5 class="text-title mb-1">
                        {{translate('messages.Provider_Meta_Data')}}
                    </h5>
                    <p class="fs-12 mb-0">
                        {{ translate('Provider_Meta_Data_&_Image') }}
                    </p>
                </div>
            </div>
            <div class="card-body">
                <form action="{{route('vendor.business-settings.update-meta-data',[$store['id']])}}" method="post"
                enctype="multipart/form-data" class="col-12">
                @csrf
                @include('admin-views.business-settings.landing-page-settings.partial._meta_data', ['submit' => true])
                </form>
            </div>
        </div>

        @if (!config('module.'.$store->module->module_type)['always_open'])
        <div class="card mt-3">
            <div class="card-header">
                <div>
                    <h5 class="text-title mb-1">
                        {{translate('messages.Provder_Active_Time')}}
                    </h5>
                    <p class="fs-12 mb-0">
                        {{ translate('Set the time when Provder is active to show in app and website') }}
                    </p>
                </div>
            </div>
            <div class="card-body" id="schedule">
                @include('vendor-views.business-settings.partials._schedule', $store)
            </div>
        </div>
        @endif
    </div>

    <!-- Create schedule modal -->

    <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" data-title="{{ translate('messages.Create Schedule For ') }} ">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">{{translate('messages.Create Schedule For ')}}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="javascript:" method="post" id="add-schedule" data-route="{{route('vendor.business-settings.add-schedule')}}">
                        @csrf
                        <input type="hidden" name="day" id="day_id_input">
                        <div class=" ">
                            <label for="recipient-name" class="col-form-label">{{translate('messages.Start time')}}:</label>
                            <input type="time"  id="recipient-name" class="form-control" name="start_time" required>
                        </div>
                        <div class=" ">
                            <label for="message-text" class="col-form-label">{{translate('messages.End time')}}:</label>
                            <input type="time" id="message-text" class="form-control" name="end_time" required>
                        </div>
                        <div class="btn--container mt-4 justify-content-end">
                            <button type="reset" class="btn btn--reset">{{translate('messages.reset')}}</button>
                            <button type="submit" class="btn btn--primary">{{translate('messages.Submit')}}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="button-title" data-title="{{translate('Want_to_delete_this_schedule?')}}"></div>
    <div id="button-text" data-text="{{translate('If_you_select_Yes,_the_time_schedule_will_be_deleted.')}}"></div>
    <div id="button-cancel" data-no="{{ translate('no') }}"></div>
    <div id="button-accept" data-yes="{{ translate('yes') }}"></div>
    <div id="button-success" data-success="{{translate('messages.Schedule removed successfully')}}"></div>
    <div id="button-error" data-error="{{translate('messages.Schedule removed successfully')}}"></div>
    <div id="button-added" data-error="{{translate('messages.Schedule added successfully')}}"></div>
@endsection

@push('script_2')
    <script src="{{ asset('Modules/Rental/public/assets/js/view-pages/provider/setting.js') }}"></script>

@endpush
