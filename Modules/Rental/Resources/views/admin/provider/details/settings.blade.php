@extends('layouts.admin.app')

@section('title',$store->name."'s ".translate('messages.settings'))

@push('css_or_js')
    <!-- Custom styles for this page -->
    <link href="{{asset('public/assets/admin/css/croppie.css')}}" rel="stylesheet">

@endpush

@section('content')
<div class="content container-fluid">
    @include('rental::admin.provider.details.partials._header',['store'=>$store])
    <!-- Page Heading -->
    <div class="tab-content">
        <div class="tab-pane fade show active" id="vendor">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <span class="card-header-icon">
                            <img class="w--22" src="{{asset('public/assets/admin/img/store.png')}}" alt="">
                        </span>
                        <span class="p-md-1"> {{translate('messages.vendor_settings')}}</span>
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{route('admin.rental.provider.update_settings',[$store['id']])}}" method="post"
                        enctype="multipart/form-data">
                        @csrf
                        <div class="row g-3">
                        @if ($store->store_business_model == 'commission')
                            <div class="col-sm-6 col-lg-4">
                                <div class="form-group mb-0">
                                    <label class="toggle-switch toggle-switch-sm d-flex justify-content-between border border-secondary rounded px-4 form-control" for="reviews_section">
                                    <span class="pr-2">{{translate('messages.Show_Reviews_In_vendor_Panel')}}<span class="input-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{translate('When_enabled,_vendor_owners_can_see_customer_feedback_in_the_vendor_panel_&_vendor_app.')}}"><img src="{{asset('/public/assets/admin/img/info-circle.svg')}}" alt="{{translate('messages.show_hide_food_menu')}}"></span> </span>
                                        <input type="checkbox"
                                               data-id="reviews_section"
                                               data-type="toggle"
                                               data-image-on="{{ asset('/public/assets/admin/img/status-ons.png') }}"
                                               data-image-off="{{ asset('/public/assets/admin/img/off-danger.png') }}"
                                               data-title-on="{{ translate('Are you want to turn on ?') }}"
                                               data-title-off="{{ translate('Are you want to turn off ?') }}"
                                               data-text-on="<p>{{ translate('This will show customer reviews in the vendor panel and vendor app.') }}</p>"
                                               data-text-off="<p>{{ translate('This will hide customer reviews from the vendor panel and vendor app.') }}</p>"
                                               class="toggle-switch-input dynamic-checkbox-toggle"
                                               name="reviews_section" id="reviews_section" value="1" {{$store->reviews_section?'checked':''}}>
                                        <span class="toggle-switch-label text">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        @endif

                        <div class="col-sm-6 col-lg-4">
                            <div class="form-group mb-0">
                                <label class="toggle-switch toggle-switch-sm d-flex justify-content-between border border-secondary rounded px-4 form-control" for="schedule_order">
                                <span class="pr-2">{{translate('messages.scheduled_trip')}}<span class="input-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{translate('When_enabled,_vendor_owner_can_take_scheduled_trips_from_customers.')}}"><img src="{{asset('/public/assets/admin/img/info-circle.svg')}}" alt="{{translate('messages.scheduled_trip_hint')}}"></span></span>
                                    <input type="checkbox"
                                           data-id="schedule_order"
                                           data-type="toggle"
                                           data-image-on="{{ asset('/public/assets/admin/img/status-ons.png') }}"
                                           data-image-off="{{ asset('/public/assets/admin/img/off-danger.png') }}"
                                           data-title-on="{{ translate('Are you want to turn on ?') }}"
                                           data-title-off="{{ translate('Are you want to turn off ?') }}"
                                           data-text-on="<p>{{ translate('This will allow the vendor to take scheduled trips from customers.') }}</p>"
                                           data-text-off="<p>{{ translate('This will stop the vendor from taking scheduled trips from customers.') }}</p>"
                                           class="toggle-switch-input dynamic-checkbox-toggle"
                                           name="schedule_order" id="schedule_order" value="1" {{$store->schedule_order?'checked':''}}>
                                    <span class="toggle-switch-label">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>
                            </div>
                        </div>
                        </div>
                        <div class="row g-3 mt-3">
                            <div class="row">
                                <div class="form-group col-sm-6 col-lg-4">
                                    <label class="input-label text-capitalize" for="maximum_delivery_time">{{translate('messages.approx_pickup_time')}}<span class="input-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{translate('Set_the_total_time_to_deliver_products.')}}"><img src="{{asset('/public/assets/admin/img/info-circle.svg')}}" alt="{{translate('Set_the_total_time_to_deliver_products.')}}"></span></label>
                                    <div class="input-group">
                                        <input type="number" name="minimum_pickup_time" class="form-control" placeholder="Min: 10" value="{{explode('-',$store->delivery_time)[0]}}" data-toggle="tooltip" data-placement="top" data-original-title="{{translate('messages.minimum_delivery_time')}}">
                                        <input type="number" name="maximum_pickup_time" class="form-control" placeholder="Max: 20" value="{{explode(' ',explode('-',$store->delivery_time)[1])[0]}}" data-toggle="tooltip" data-placement="top" data-original-title="{{translate('messages.maximum_delivery_time')}}">
                                        <select name="pickup_time_type" class="form-control text-capitalize" id="" required>
                                            <option value="min" {{explode(' ',explode('-',$store->delivery_time)[1])[1]=='min'?'selected':''}}>{{translate('messages.minutes')}}</option>
                                            <option value="hours" {{explode(' ',explode('-',$store->delivery_time)[1])[1]=='hours'?'selected':''}}>{{translate('messages.hours')}}</option>
                                            <option value="days" {{explode(' ',explode('-',$store->delivery_time)[1])[1]=='days'?'selected':''}}>{{translate('messages.days')}}</option>
                                        </select>
                                    </div>
                                </div>
                               


                                <div class="col-12">
                                    <div class="justify-content-end btn--container">
                                        <button type="reset" class="btn btn--reset">{{translate('messages.reset')}}</button>
                                        <button type="submit" class="btn btn--primary">{{translate('save_changes')}}</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
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
                                                <form action="{{route('admin.store.website-builder-status',[$store->id,$store->storeConfig?->website_builder_status?0:1])}}"  method="get"  id="website_builder_status_form"></form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
            @endif
            @if (!config('module.'.$store->module_type)['always_open'])
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="card-title">
                            <span class="card-header-icon"><i class="tio-clock"></i></span>
                            <span class="p-md-1">{{translate('messages.Daily time schedule')}}</span>
                        </h5>
                    </div>
                    <div class="card-body" id="schedule">
                        @include('rental::admin.provider.details.partials._schedule', $store)
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Create schedule modal -->

<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" data-message="{{translate('messages.Create Schedule For ') }} ">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">{{translate('messages.Create Schedule')}}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form action="javascript:" method="post" id="add-schedule" data-route="{{route('admin.store.add-schedule')}}">
                    @csrf
                    <input type="hidden" name="day" id="day_id_input">
                    <input type="hidden" name="store_id" value="{{$store->id}}">
                    <div class="form-group">
                        <label for="recipient-name" class="col-form-label">{{translate('messages.Start time')}}:</label>
                        <input type="time" class="form-control" name="start_time" required>
                    </div>
                    <div class="form-group">
                        <label for="message-text" class="col-form-label">{{translate('messages.End time')}}:</label>
                        <input type="time" class="form-control" name="end_time" required>
                    </div>
                    <button type="submit" class="btn btn-primary">{{translate('messages.Submit')}}</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="title" data-title="{{ translate('Want_to_delete_this_schedule?') }}"></div>
<div id="subTitle" data-sub-title="{{ translate('If_you_select_Yes,_the_time_schedule_will_be_deleted') }}"></div>
<div id="buttonNo" data-no="{{ translate('no') }}"></div>
<div id="buttonYes" data-yes="{{ translate('yes') }}"></div>
<div id="removed" data-removed="{{ translate('messages.Schedule removed successfully') }}"></div>
<div id="added" data-added="{{ translate('messages.Schedule added successfully') }}"></div>
<div id="notFound" data-not-found="{{ translate('Schedule not found') }}"></div>

@endsection

@push('script_2')
    <script src="{{asset('Modules/Rental/public/assets/js/admin/view-pages/provider-setting.js')}}"></script>
@endpush
