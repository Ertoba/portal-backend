@extends('layouts.vendor.app')

@section('title', translate('Add new vehicle'))

@php($openai_enabled = data_get(\App\CentralLogics\Helpers::get_business_settings('openai_config'), 'status') == 1)
@php($aiRoutePrefix = 'vendor.vehicle.ai')
@php($aiRequestType = 'vendor')

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('Modules/Rental/public/assets/css/provider/vehicle.css') }}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/custom.css')}}">
    @if ($openai_enabled)
        <link rel="stylesheet" href="{{ asset('public/assets/admin/css/AI/animation/product/ai-sidebar.css') }}">
    @endif
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header pb-20">
            <div class="d-flex justify-content-between flex-wrap gap-3">
                <div>
                    <h1 class="page-header-title text-break">
                        <span class="page-header-icon">
                            <img src="{{ asset('public/assets/admin/img/outline/car.svg') }}" alt="">
                        </span>
                        <span>{{ translate('Add new vehicle') }}
                    </h1>
                    <p class="page-header-desc">{{ translate('List a vehicle with its photographs, seats, fuel and the rate it goes out at.') }}</p>
                </div>
            </div>
        </div>
        @php($language = \App\CentralLogics\Helpers::get_business_settings('language', false) ?? null)

        <form action="" method="post" enctype="multipart/form-data">
            @csrf

            <div class="row g-3">
                <div class="col-lg-12">
                    <div class="card mt-4">
                        <div class="card-header">
                            <div>
                                <h5 class="text-title mb-1">
                                    {{ translate('General information') }}
                                </h5>
                                <p class="fs-12 mb-0">
                                    {{ translate('messages.Insert the basic information of the vehicle') }}
                                </p>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-lg-6">
                                    <div class="card __bg-FAFAFA border-0">
                                        <div class="card-body">
                                            @if ($language)
                                                <ul class="nav nav-tabs border-0 mb-4">
                                                    <li class="nav-item">
                                                        <a class="nav-link lang_link active" href="#"
                                                           id="default-link">{{ translate('Default') }}</a>
                                                    </li>
                                                    @foreach (json_decode($language) as $lang)
                                                        <li class="nav-item">
                                                            <a class="nav-link lang_link" href="#"
                                                               id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                            @if ($language)
                                                <div class="lang_form" id="default-form">
                                                    <div class="form-group mb-20">
                                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-1">
                                                            <label class="input-label font-semibold mb-0"
                                                                   for="default_name">{{ translate('Vehicle name') }}
                                                                ({{ translate('Default') }})
                                                            </label>
                                                            @include('rental::admin.vehicle.partials._ai_generate_btn', ['section' => 'title'])
                                                        </div>
                                                        @if ($openai_enabled)<div class="outline-wrapper">@endif
                                                        <input type="text" name="name[]" id="default_name"
                                                               class="form-control"
                                                               value=""
                                                               placeholder="{{ translate('messages.Type vehicle name') }}"
                                                               required>
                                                        @if ($openai_enabled)</div>@endif
                                                    </div>
                                                    <input type="hidden" name="lang[]" value="default">
                                                    <div class="form-group mb-0">
                                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-1">
                                                            <label class="input-label font-semibold mb-0"
                                                                   for="exampleFormControlInput1">{{ translate('Short description') }}
                                                                ({{ translate('Default') }})</label>
                                                            @include('rental::admin.vehicle.partials._ai_generate_btn', ['section' => 'generate-description'])
                                                        </div>
                                                        @if ($openai_enabled)<div class="outline-wrapper">@endif
                                                        <textarea type="text" name="description[]" id="default_description" placeholder="{{ translate('messages.Type business address') }}"
                                                                  class="form-control min-h-90px ckeditor"></textarea>
                                                        @if ($openai_enabled)</div>@endif
                                                    </div>
                                                </div>
                                                @foreach (json_decode($language) as $lang)
                                                    <div class="d-none lang_form" id="{{ $lang }}-form">
                                                        <div class="form-group mb-0">
                                                            <label class="input-label font-semibold"
                                                                   for="{{ $lang }}_name">{{ translate('Vehicle name') }}
                                                                ({{ strtoupper($lang) }})
                                                            </label>
                                                            <input type="text" name="name[]"
                                                                   id="{{ $lang }}_name" class="form-control"
                                                                   placeholder="{{ translate('Store name') }}">
                                                        </div>
                                                        <input type="hidden" name="lang[]" value="{{ $lang }}">
                                                        <div class="form-group mb-0">
                                                            <label class="input-label font-semibold"
                                                                   for="exampleFormControlInput1">{{ translate('Short description') }}
                                                                ({{ strtoupper($lang) }})</label>
                                                            <textarea type="text" name="description[]" placeholder="{{ translate('messages.Store') }}"
                                                                      class="form-control min-h-90px ckeditor"></textarea>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            @else
                                                <div id="default-form">
                                                    <div class="form-group mb-0">
                                                        <label class="input-label font-semibold"
                                                               for="exampleFormControlInput1">{{ translate('Vehicle name') }}
                                                            ({{ translate('Default') }})</label>
                                                        <input type="text" name="name[]" class="form-control"
                                                               placeholder="{{ translate('Store name') }}" required>
                                                    </div>
                                                    <input type="hidden" name="lang[]" value="default">
                                                    <div class="form-group mb-0">
                                                        <label class="input-label font-semibold"
                                                               for="exampleFormControlInput1">{{ translate('Short description') }}
                                                        </label>
                                                        <textarea type="text" name="description[]" placeholder="{{ translate('messages.Store') }}"
                                                                  class="form-control min-h-90px ckeditor"></textarea>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                </div>
                                <div class="col-lg-6">
                                    <div class="text-center">
                                        <label class="text--title fs-16 font-semibold mb-1">
                                            {{ translate('Vehicle thumbnail') }}
                                        </label>
                                        <div class="mb-20">
                                            <p class="fs-12">
                                                {{ 'JPG, JPEG, PNG' . ' image, max ' . 1 . ' MB' }} <strong class="font-semibold">(Ratio 2:1)</strong>
                                            </p>
                                        </div>
                                        <div class="upload-file image-general d-inline-block w-auto">
                                            <a href="javascript:void(0);" class="remove-btn opacity-0 z-index-99">
                                                <i class="tio-clear"></i>
                                            </a>
                                            <input type="file" name="thumbnail"
                                                   data-type="{{ translate('Please upload a file in a supported format') . ': PNG, JPG' }}"
                                                   data-size="{{ translate('File size too big') }}"
                                                   class="upload-file__input single_file_input"
                                                accept=".webp, .jpg, .jpeg, .png" data-max-size="1" value="" required>
                                            <label
                                                class="upload-file-wrapper height-150px max-w-300px aspect-2-1">
                                                <div class="upload-file-textbox text-center w-100">
                                                    <img width="34" height="34" src="{{ asset('public/assets/admin/img/document-upload.svg') }}" alt="">
                                                    <h6 class="mt-2 font-semibold text-center">
                                                        <span>{{ translate('Click to upload') }}</span>
                                                        <br>
                                                        {{ translate('or drag and drop') }}
                                                    </h6>
                                                </div>
                                                <img class="upload-file-img ratio-2 display-none" width="300" height="150" loading="lazy" src="" alt="">
                                            </label>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h5 class="text-title mb-1">
                                    {{ translate('messages.Images') }}
                                </h5>
                                <p class="fs-12 mb-0">
                                    {{ 'JPG, JPEG, PNG' . ' image, max ' . 1 . ' MB' }}
                                    <span class="font-semibold"> ({{ translate('Ratio') }} 2:1)</span>
                                </p>
                            </div>
                        </div>
                        <div class="card-body py-1">
                            <div class="d-flex pt-20 pb-2 overflow-x-auto">
                               <div class="d-flex gap-3 flex-shrink-0" id="image_container">
                                   <div class="upload-file text-wrapper h--100px w--200px flex-shrink-0"
                                        id="image_upload_wrapper">
                                       <input type="file" name="images[]"
                                              class="upload-file__input multiple_image_input"
                                              data-maximum="{{ translate('You can upload a maximum of') }} "
                                              data-content=" {{ translate('Files') }}"
                                              data-size="{{ translate('File size too big') }}"
                                              data-type="{{ translate('Please upload a file in a supported format') . ': PNG, JPG' }}"
                                              accept=".webp, .jpg,.jpeg,.png" multiple required>
                                       <div
                                           class="upload-file__img d-flex gap-0 justify-content-center align-items-center h-100 max-w-300px p-0">
                                           <div class="upload-file__textbox">
                                               <img width="34" height="34"
                                                    src="{{ asset('public/assets/admin/img/document-upload.png') }}"
                                                    alt="" class="svg">
                                               <h6 class="mt-2 font-semibold">
                                                   <span class="text-info">{{ translate('Click to upload') }}</span><br>
                                                   {{ translate('or drag and drop') }}
                                               </h6>
                                           </div>
                                       </div>
                                   </div>
                               </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <h5 class="text-title mb-1">
                                    {{ translate('Vehicle information') }}
                                </h5>
                                <p class="fs-12 mb-0">
                                    {{ translate('Insert the vehicle\'s general informations') }}
                                </p>
                            </div>
                            @include('rental::admin.vehicle.partials._ai_generate_btn', ['section' => 'vehicle-info'])
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-lg-4">
                                    <div class="form-group mb-0">
                                        <label class="input-label" for="choice_brand">{{ translate('messages.Brand') }}
                                        </label>
                                        <select name="brand_id" id="choice_brand" class="form-control js-select2-custom"
                                                data-placeholder="{{ translate('messages.Select vehicle brand') }}" required>
                                            <option value="" selected disabled>{{ translate('messages.Select vehicle brand') }}</option>
                                            @foreach($brands as $brand)
                                                <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                               for="">{{ translate('messages.Model') }}
                                        </label>
                                        <input type="text" name="model" class="form-control" placeholder="Model Name"
                                               value="" required>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                               for="choice_category">{{ translate('messages.Category') }}
                                        </label>
                                        <select name="category_id" id="choice_category" class="form-control js-select2-custom" data-placeholder="{{ translate('messages.Select vehicle category') }}" required>
                                            <option value="" selected disabled>{{ translate('messages.Select vehicle category') }}</option>
                                            @foreach($categories as $category)
                                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group mb-0">
                                        <label class="input-label" for="choice_type">{{ translate('Type') }}
                                        </label>
                                        <select name="type" id="choice_type" class="form-control js-select2-custom"
                                                data-placeholder="{{ translate('messages.Select vehicle type') }}" required>
                                            <option value="" selected disabled>
                                                {{ translate('messages.Select vehicle type') }}</option>
                                            <option value="family">{{ translate('messages.family') }}</option>
                                            <option value="luxury">{{ translate('messages.Luxury') }}</option>
                                            <option value="affordable">{{ translate('messages.Affordable') }}</option>
                                            <option value="executives">{{ translate('messages.Executives') }}</option>
                                            <option value="compact">{{ translate('messages.Compact') }}</option>
                                            <option value="full_size">{{ translate('messages.Full-Size') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                               for="">{{ translate('Engine capacity (cc)') }}
                                        </label>
                                        <input type="number" name="engine_capacity" class="form-control" placeholder="Ex: 450"
                                               value="" required>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                               for="">{{ translate('Engine power (hp)') }}
                                        </label>
                                        <input type="number" name="engine_power" class="form-control" placeholder="Ex: 100"
                                               value="" required>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                               for="">{{ translate('Seating capacity') }}
                                        </label>
                                        <input type="number" name="seating_capacity" class="form-control"
                                               placeholder="Input how many person can seat" value="" required>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                               for="">{{ translate('Air condition') }}
                                        </label>
                                        <div class="resturant-type-group border">
                                            <label class="form-check form--check mr-2 mr-md-4">
                                                <input class="form-check-input" type="radio" value="yes"
                                                       name="air_condition" id="order_confirmation_model"
                                                       checked="">
                                                <span class="form-check-label">
                                                    {{ translate('messages.Yes') }}
                                                </span>
                                            </label>
                                            <label class="form-check form--check mr-2 mr-md-4">
                                                <input class="form-check-input" type="radio" value="0"
                                                       name="air_condition" id="order_confirmation_model2">
                                                <span class="form-check-label">
                                                    {{ translate('messages.No') }}
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                               for="choice_fuel_type">{{ translate('Fuel type') }}
                                        </label>
                                        <select name="fuel_type" id="choice_fuel_type"
                                                class="form-control js-select2-custom"
                                                data-placeholder="{{ translate('messages.Select fuel type') }}" required>
                                            <option value="" selected disabled>
                                                {{ translate('messages.Select vehicle fuel type') }}</option>
                                            <option value="octan" >{{ translate('Octane') }}</option>
                                            <option value="diesel" >{{ translate('messages.diesel') }}</option>
                                            <option value="CNG" >CNG</option>
                                            <option value="petrol" >{{ translate('messages.Petrol') }}</option>
                                            <option value="electric" >{{ translate('messages.Electric') }}</option>
                                            <option value="jet_fuel" >{{ translate('Jet fuel') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                               for="choice_transmission_type">{{ translate('messages.Transmission type') }}
                                        </label>
                                        <select name="transmission_type" id="choice_transmission_type"
                                                class="form-control js-select2-custom"
                                                data-placeholder="{{ translate('messages.Select vehicle transmission') }}" required>
                                            <option value="" selected disabled>
                                                {{ translate('messages.Select vehicle transmission') }}</option>
                                            <option value="automatic">{{ translate('Automatic') }}</option>
                                            <option value="manual">{{ translate('Manual') }}</option>
                                            <option value="continuously_variable">{{ translate('Continuously variable') }}</option>
                                            <option value="dual_clutch">{{ translate('Dual-Clutch') }}</option>
                                            <option value="semi_automatic">{{ translate('Semi-Automatic') }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header flex-wrap gap-3">
                            <div class="flex-grow-1">
                                <h5 class="text-title mb-1">
                                    {{ translate('Vehicle identity') }}
                                </h5>
                                <p class="fs-12 mb-0">
                                    {{ translate('Insert the vehicle\'s unique informations') }}
                                </p>
                            </div>
                            <label class="d-flex align-items-center gap-2">
                                <span class="text--title">
                                    {{ translate('Same model multiple vehicles') }}
                                </span>
                                <input class="form-check-input single-select position-relative m-0" type="checkbox" name="multiple_vehicles">
                            </label>
                        </div>
                        <div class="card-body d-flex flex-column gap-20px">
                            <div class="d-flex gap-20px flex-column flex-md-row equal-width" id="input-container">
                                <div class="form-group mb-0">
                                    <label class="input-label"
                                           for="">{{ translate('VIN number') }}</label>
                                    <input type="text" name="vehicle[vin_number][]" class="form-control"
                                           placeholder="Type your business name" value="" required>
                                </div>
                                <div class="form-group mb-0">
                                    <label class="input-label"
                                           for="">{{ translate('License plate number') }}</label>
                                    <input type="text" name="vehicle[license_plate_number][]" class="form-control"
                                           placeholder="Type your license plate number" value="" required>
                                </div>
                                <button type="button"
                                        data-vin="{{ translate('VIN number') }}"
                                        data-license="{{ translate('License plate number') }}"
                                        class="btn plus-btn shadow-none text--primary p-0 fs-32 lh--1 text-left mt-md-4 add-btn">
                                    <i class="tio-add-circle-outlined"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <h5 class="text-title mb-1">
                                    {{ translate('Pricing & discounts') }}
                                </h5>
                                <p class="fs-12 mb-0">
                                    {{ translate('Insert the pricing & discount information') }}
                                </p>
                            </div>
                            @include('rental::admin.vehicle.partials._ai_generate_btn', ['section' => 'pricing'])
                        </div>
                            <div class="card-body">

                    <div class="bg--secondary rounded p-20 mobile-space-0">
                         <div class="mb-3">
                        <h6 class="fz--14px mb-1">
                            {{ translate('Trip type') }}
                        </h6>
                        <p class="fs-12 mb-0">
                            {{ translate('messages.Choose the trip type you prefer.') }}
                        </p>
                    </div>
                        <div class="bg-white rounded p-15 border">
                            <div class="row g-3">

                                <div class="col-md-6 col-lg-4">
                                    <div class="form-group mb-0">
                                        <div class="p-0 resturant-type-group">
                                            <label class="d-flex mb-0 form-check item">
                                                <input class="form-check-input single-select" type="checkbox" name="trip_hourly"
                                                        value="hourly" {{ old('trip_hourly') == 'hourly' ? 'checked' : '' }}>
                                                <span class="form-check-label ml-2 mt-1">
                                                    <span class="title-clr d-block fz--14px">{{translate('Hourly')}}</span>
                                                    <p class="fz-12px mb-0 ">{{translate('Set your hourly rental price.')}}</p>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 col-lg-4">
                                    <div class="form-group mb-0">
                                        <div class="p-0 resturant-type-group">
                                            <label class="d-flex mb-0 form-check item">
                                                <input class="form-check-input single-select" type="checkbox" name="trip_day_wise" value="trip_day_wise">
                                                <span class="form-check-label ml-2 mt-1">
                                                    <span class="title-clr d-block fz--14px">{{ translate('Per day') }}</span>
                                                    <p class="fz-12px mb-0 ">{{translate('Set your per day rental price.')}}</p>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                   <div class="col-md-6 col-lg-4">
                                    <div class="form-group mb-0">
                                        <div class="p-0 resturant-type-group">
                                            <label class="d-flex mb-0 form-check item">
                                                <input class="form-check-input single-select" type="checkbox" name="trip_distance"
                                                        value="distance_wise" {{ old('trip_distance') == 'distance_wise' ? 'checked' : '' }}>
                                                <span class="form-check-label ml-2 mt-1">
                                                    <span class="title-clr d-block fz--14px">{{translate('Distance wise')}}</span>
                                                    <p class="fz-12px mb-0 ">{{translate('Set your distance wise rental price.')}}</p>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row g-3 mt-2">

                            <div class="col-hide">
                                <div class="form-group mb-0">
                                    <label class="input-label" for="">{{ translate('Hourly wise price') }} ($/per hour)<span class="text-danger">*</span></label>
                                    <input type="number" name="hourly_price" class="form-control"
                                            placeholder="Ex: 35.25" min="0.01" step="0.01" value="{{ old('hourly_price') }}" required>
                                </div>
                            </div>

                            <div class="col-hide">
                                <div class="form-group mb-0">
                                    <label class="input-label" for="">{{ translate('Per day price') }} ($/per day)<span class="text-danger">*</span></label>
                                    <input type="number" name="day_wise_price" class="form-control"
                                            placeholder="Ex: 35.25" min="0.01" step="0.01" value="{{ old('day_wise_price') }}" required>
                                </div>
                            </div>
                            <div class="col-hide">
                                <div class="form-group mb-0">
                                    <label class="input-label" for="">{{ translate('Distance wise price') }} ($/{{ $distanceUnitLabel }})<span class="text-danger">*</span></label>
                                    <input type="number" name="distance_price" class="form-control"
                                            placeholder="Ex: 35.25" min="0.01" step="0.01" value="{{ old('distance_price') }}" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-20 bg--secondary rounded p-20 mobile-space-0">
                        <div class="row g-3">
                            <div class="col-xxl-3 col-md-4">
                                <div class="mb-0">
                                    <h6 class="fz--14px mb-1">
                                        {{ translate('Give discount') }}
                                    </h6>
                                    <p class="fz-12px mb-0">
                                        {{ translate('messages.Set a discount that applies to all pricing types—hourly, daily, and distance-based') }}
                                    </p>
                                </div>
                            </div>
                            <div class="col-xxl-9 col-md-8">
                                <div class="bg-white rounded p-20 mobile-space-0">
                                    <div class="form-group mb-0">
                                        <div class="custom-group-btn border">
                                            <div class="flex-sm-grow-1">
                                                <input id="discount_input" type="number" name="discount_price" class="form-control h--45px border-0 pl-unset"
                                                        placeholder="Ex: 10" min="0" step="0.001" value="{{ old('discount_price') }}">
                                            </div>
                                            <div class="flex-shrink-0">
                                                <select name="discount_type" id="discount_type" class="custom-select ltr border-0">
                                                    <option value="percent" {{ old('discount_type') == 'percent' ? 'selected' : '' }}>%</option>
                                                    <option value="amount" {{ old('discount_type') == 'amount' ? 'selected' : '' }}>
                                                        {{ \App\CentralLogics\Helpers::currency_symbol() }}
                                                    </option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
                </div>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <h5 class="text-title mb-1">
                                    {{ translate('Search tags') }}
                                </h5>
                                <p class="fs-12 mb-0">
                                    {{ translate('Insert the tags for appear in user\'s search list') }}
                                </p>
                            </div>
                            @include('rental::admin.vehicle.partials._ai_generate_btn', ['section' => 'tags'])
                        </div>
                        <div class="card-body">
                            <div class="form-group mb-0 pickup-zone-tag">
                                <select name="tag[]" id="pickup_zones12"
                                        class="form-control js-select2-custom select2-hidden-accessible" multiple="multiple">
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h5 class="text-title mb-1">
                                    {{ translate('Vehicle documents') }}
                                </h5>
                                <p class="fs-12 mb-0">
                                    {{ translate('Upload vehicle\'s important documents') }}
                                </p>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="d-flex py-3 overflow-x-auto">
                                <div class="d-flex gap-3 flex-shrink-0" id="pdf-container">
                                    <div class="upload-file text-wrapper document-wrapper" id="upload-wrapper">
                                        <input type="file" name="documents[]"
                                            class="upload-file__input multiple_document_input" accept="*"
                                            multiple>
                                        <div
                                            class="upload-file__img d-flex justify-content-center align-items-center h-100 max-w-300px p-0">
                                            <div class="upload-file__textbox pdf">
                                                <img width="34" height="34"
                                                    src="{{ asset('public/assets/admin/img/document-upload.png') }}"
                                                    alt="" class="svg">
                                                <h6 class="font-semibold">
                                                    <span class="text-info">{{ translate('Click to upload') }}</span><br>
                                                    {{ translate('or drag and drop') }}
                                                </h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-20" id="vehicle-meta-section">
                        @if ($openai_enabled)
                            @include('rental::admin.vehicle.partials._ai_generate_btn', ['section' => 'seo'])
                        @endif
                        @include('admin-views.business-settings.landing-page-settings.partial._meta_data',['submit' => false])
                    </div>

                </div>
                <div class="col-lg-12">
                    <div class="btn--container justify-content-end mt-20">
                        <button type="reset" id="reset_btn"
                                class="btn btn--reset min-w-120px">{{ translate('messages.Reset') }}</button>
                        <button type="submit"
                                class="btn btn--primary min-w-120px">{{ translate('messages.Submit') }}</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <input type="hidden" id="file_size_error_text" value="{{ translate('File size too big') }}">
    <input type="hidden" id="file_type_error_text" value="{{ translate('Please upload a file in a supported format') . ': PNG, JPG' }}">
    <input type="hidden" id="max_file_upload_limit_error_text" value="{{ translate('Maximum file upload limit is') }}">
    <div id="file-assets"
        data-picture-icon="{{ asset('public/assets/admin/img/picture.svg') }}"
        data-document-icon="{{ asset('public/assets/admin/img/document.svg') }}"
        data-blank-thumbnail="{{ asset('public/assets/admin/img/blank2.png') }}">
    </div>

    @if ($openai_enabled)
        @include('rental::admin.vehicle.partials._ai_assistant')
    @endif

@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin/js/spartan-multi-image-picker.js') }}"></script>
    <script src="{{ asset('Modules/Rental/public/assets/js/view-pages/provider/pdf.min.js') }}"></script>
    <script src="{{ asset('Modules/Rental/public/assets/js/view-pages/provider/vehicle-create.js') }}"></script>
    <script src="{{ asset('Modules/Rental/public/assets/js/view-pages/provider/multiple-upload.js') }}"></script>
    @if ($openai_enabled)
        <script src="{{ asset('Modules/Rental/public/assets/js/admin/view-pages/vehicle-ai.js') }}"></script>
    @endif

@endpush
