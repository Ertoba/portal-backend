@extends('layouts.admin.app')

@section('title', translate('Create new provider'))

@push('css_or_js')
    <link rel="stylesheet" href="{{asset('Modules/Rental/public/assets/css/admin/provider-create.css')}}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header pb-20">
            <div class="d-flex justify-content-between flex-wrap gap-3">
                <div>
                    <h1 class="page-header-title text-break">
                        <span class="page-header-icon">
                            <img src="{{ asset('public/assets/admin/img/outline/provider-add.svg') }}" class="w--26" alt="">
                        </span>
                        <span>{{ translate('Create new provider') }}
                    </span> </h1>
                    <p class="page-header-desc">{{ translate('Add a rental provider, its owner\'s sign-in and the zone it will trade in.') }}</p>

                </div>
            </div>
        </div>

        @php($language = \App\CentralLogics\Helpers::get_business_settings('language', false) ?? null)
        <form action="#" method="post" enctype="multipart/form-data" id="providerFormSubmit">
            @csrf

            <div id="businessSetup">
                <div class="custom-timeline d-flex flex-wrap gap-40px text-title mb-2">
                    <h4 class="single"><span class="count">1</span>{{translate('Business basic setup')}}</h4>
                    <h4 class="single opacity-70"><span class="count2">2</span>{{translate('Business plan setup')}}</h4>
                </div>

                <div class="row g-2">
                    <div class="col-lg-12">
                        <div class="card mt-4">
                            <div class="card-header">
                                <div>
                                    <h5 class="text-title mb-1">
                                        {{ translate('General information') }}
                                    </h5>
                                    <p class="fs-12 mb-0">
                                        {{ translate('messages.Insert the basic information of the provider') }}
                                    </p>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-lg-6">
                                        <div class="card __bg-FAFAFA border-0">
                                            <div class="card-body">
                                                @if ($language)
                                                    <ul class="nav nav-tabs mb-4 flex-nowrap">
                                                        <li class="nav-item">
                                                            <a class="nav-link lang_link text-nowrap active" href="#"
                                                                id="default-link">{{ translate('Default') }}</a>
                                                        </li>
                                                        @foreach (json_decode($language) as $lang)
                                                            <li class="nav-item">
                                                                <a class="nav-link lang_link text-nowrap" href="#"
                                                                    id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                                @if ($language)
                                                    <div class="lang_form" id="default-form">
                                                        <div class="form-group">
                                                            <label class="input-label font-semibold"
                                                                for="default_name">{{ translate('Name') }}
                                                                ({{ translate('Default') }})
                                                            </label>
                                                            <input type="text" name="name[]" id="default_name"
                                                                class="form-control"
                                                                placeholder="{{ translate('messages.Provider name') }}" value="{{old('default_name')}}" required>
                                                        </div>
                                                        <input type="hidden" name="lang[]" value="default">
                                                        <div class="form-group mb-0">
                                                            <label class="input-label font-semibold"
                                                                for="exampleFormControlInput1">{{ translate('messages.Address') }}
                                                                ({{ translate('Default') }})</label>
                                                            <textarea type="text" name="address[]" id="default_address" placeholder="{{ translate('messages.Address') }}"
                                                                class="form-control min-h-90px ckeditor"></textarea>
                                                        </div>
                                                    </div>
                                                    @foreach (json_decode($language) as $lang)
                                                        <div class="d-none lang_form" id="{{ $lang }}-form">
                                                            <div class="form-group">
                                                                <label class="input-label font-semibold"
                                                                    for="{{ $lang }}_name">{{ translate('Name') }}
                                                                    ({{ strtoupper($lang) }})
                                                                </label>
                                                                <input type="text" name="name[]"
                                                                    id="{{ $lang }}_name" class="form-control"
                                                                    placeholder="{{ translate('messages.Provider name') }}">
                                                            </div>
                                                            <input type="hidden" name="lang[]" value="{{ $lang }}">
                                                            <div class="form-group mb-0">
                                                                <label class="input-label font-semibold"
                                                                    for="exampleFormControlInput1">{{ translate('messages.Address') }}
                                                                    ({{ strtoupper($lang) }})</label>
                                                                <textarea type="text" name="address[]" placeholder="{{ translate('messages.Address') }}"
                                                                    class="form-control min-h-90px ckeditor"></textarea>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @else
                                                    <div id="default-form">
                                                        <div class="form-group">
                                                            <label class="input-label font-semibold"
                                                                for="exampleFormControlInput1">{{ translate('Name') }}
                                                                ({{ translate('Default') }})</label>
                                                            <input type="text" name="name[]" class="form-control"
                                                                placeholder="{{ translate('messages.Provider name') }}" required>
                                                        </div>
                                                        <input type="hidden" name="lang[]" value="default">
                                                        <div class="form-group mb-0">
                                                            <label class="input-label font-semibold"
                                                                for="exampleFormControlInput1">{{ translate('messages.Address') }}
                                                            </label>
                                                            <textarea type="text" name="address[]" placeholder="{{ translate('messages.Address') }}"
                                                                class="form-control min-h-90px ckeditor"></textarea>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                    </div>
                                    <div class="col-lg-6">
                                        <div class="d-flex flex-column flex-sm-row gap-4">
                                            <div class="__custom-upload-img">
                                                <label class="form-label mb-1">
                                                    {{ translate('Logo') }}
                                                </label>
                                                <div class="mb-20">
                                                    <p class="fs-12 max-width-170px">{{ 'JPG, JPEG, PNG' . ' image, max ' . 2 . ' MB' }} <strong
                                                            class="font-semibold">(Ratio 1:1)</strong></p>
                                                </div>
                                                <label
                                                    class="position-relative d-inline-block image--border cursor-pointer w-100 h-165 max-w-165">
                                                    <img class="h-165 aspect-ratio-1 rounded-10 display-none"
                                                        id="logoImageViewer"
                                                        data-onerror-image="{{ asset('public/assets/admin/img/upload.png') }}"
                                                        src="{{ asset('public/assets/admin/img/upload-img.png') }}"
                                                        alt="logo image"/>
                                                    <div class="upload-file__textbox p-2 h-100">
                                                        <img width="34" height="34"
                                                            src="{{ asset('public/assets/admin/img/document-upload.png') }}"
                                                            alt="" class="svg">
                                                        <h6 class="mt-2 text-center font-semibold fs-12">
                                                            <span
                                                                class="text-info">{{ translate('messages.Click to upload') }}</span>
                                                            <br>
                                                            {{ translate('messages.or drag and drop') }}
                                                        </h6>
                                                    </div>
                                                    <div class="icon-file-group outside">

                                                        <input type="file" name="logo" id="customFileEg1"
                                                                class="custom-file-input"
                                                                accept=".webp, .jpg, .png, .jpeg|image/*">
                                                    </div>
                                                </label>
                                            </div>

                                            <div class="__custom-upload-img">
                                                <label class="form-label mb-1">
                                                    {{ translate('Cover') }}
                                                </label>
                                                <div class="mb-20">
                                                    <p class="fs-12">
                                                        {{ 'JPG, JPEG, PNG' . ' image, max ' . 2 . ' MB' }}
                                                        <br>
                                                        <strong class="font-semibold">(Ratio 2:1)</strong>
                                                    </p>
                                                </div>
                                                <label
                                                    class="position-relative d-inline-block image--border cursor-pointer w-100 h-165 min-w-330 min-w-100-mobile">
                                                    <img class="img--vertical-2 h-165 rounded-10 image--border display-none"
                                                        id="coverImageViewer"
                                                        data-onerror-image="{{ asset('public/assets/admin/img/upload-img.png') }}"
                                                        src="{{ asset('public/assets/admin/img/upload-img.png') }}"
                                                        alt="Fav icon" />
                                                    <div class="upload-file__textbox p-2 h-100">
                                                        <img width="34" height="34"
                                                            src="{{ asset('public/assets/admin/img/document-upload.png') }}"
                                                            alt="" class="svg">
                                                        <h6 class="mt-2 text-center font-semibold fs-12">
                                                            <span
                                                                class="text-info">{{ translate('messages.Click to upload') }}</span>
                                                            <br>
                                                            {{ translate('messages.or drag and drop') }}
                                                        </h6>
                                                    </div>
                                                    <div class="icon-file-group outside">

                                                        <input type="file" name="cover_photo" id="coverImageUpload"
                                                                class="custom-file-input"
                                                                accept=".webp, .jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*">
                                                    </div>
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
                                        {{ translate('Business information') }}
                                    </h5>
                                    <p class="fs-12 mb-0">
                                        {{ translate('messages.Insert the necessary information to operate the business') }}
                                    </p>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row g-3 my-0">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="input-label font-semibold"
                                                for="choice_zones">{{ translate('messages.Business zone') }}
                                                <span class="form-label-secondary" data-toggle="tooltip"
                                                    data-placement="right"
                                                    data-original-title="{{ translate('messages.Select the zone from where the business will be operated') }}">
                                                    <i class="tio-info text--title opacity-60"></i>
                                                </span>
                                            </label>
                                            <select name="zone_id" data-zone-coordinates-url="{{ route('admin.zone.get-coordinates', ['id' => 'PLACEHOLDER_ID']) }}" id="choice_zones" required
                                                class="form-control js-select2-custom"
                                                data-placeholder="{{ translate('Select zone') }}">
                                                <option value="" selected disabled>
                                                    {{ translate('Select zone') }}</option>
                                                @foreach ($zones as $zone)
                                                    @if (auth('admin')?->user()?->zone_id)
                                                        @if (auth('admin')->user()->zone_id == $zone->id)
                                                            <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                                                        @endif
                                                    @else
                                                        <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group mb-5 pickup-zone-tag">
                                            <label class="input-label font-semibold"
                                                for="pickup_zones">{{ translate('Pickup zone') }}<span
                                                    class="form-label-secondary" data-toggle="tooltip" data-placement="right"
                                                    data-original-title="{{ translate('messages.Select zones from where customer can choose their pickup locations for trip booking') }}">
                                                    <i class="tio-info text--title opacity-60"></i>
                                                </span></label>
                                            <select name="pickup_zones[]" id="pickup_zones"
                                                class="form-control  multiple-select2" multiple="multiple">
                                                @foreach ($zones as $zone)
                                                    @if (auth('admin')?->user()?->zone_id)
                                                        @if (auth('admin')->user()->zone_id == $zone->id)
                                                            <option value="{{ $zone->id }}" selected>{{ $zone->name }}
                                                            </option>
                                                        @endif
                                                    @else
                                                        <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group mb-4">
                                            <label class="input-label" for="latitude">{{ translate('messages.latitude') }}
                                                <span class="input-label-secondary"
                                                      title="{{ translate('messages.Click the map to set the provider latitude and longitude') }}"><img
                                                        src="{{ asset('/public/assets/admin/img/info-circle.svg') }}"
                                                        alt="{{ translate('messages.Click the map to set the provider latitude and longitude') }}"></span></label>
                                            <input type="text" id="latitude" name="latitude"
                                                   class="form-control __form-control"
                                                   placeholder="{{ translate('messages.Ex') }}: -94.22213"
                                                   value="{{ old('latitude') }}" required readonly>
                                        </div>
                                        <div class="form-group mb-4">
                                            <label class="input-label" for="longitude">{{ translate('messages.longitude') }}
                                                <span class="input-label-secondary"
                                                      title="{{ translate('messages.Click the map to set the provider latitude and longitude') }}"><img
                                                        src="{{ asset('/public/assets/admin/img/info-circle.svg') }}"
                                                        alt="{{ translate('messages.Click the map to set the provider latitude and longitude') }}"></span></label>
                                            <input type="text" name="longitude" class="form-control __form-control"
                                                   placeholder="{{ translate('messages.Ex') }}: 103.344322" id="longitude"
                                                   value="{{ old('longitude') }}" required readonly>
                                        </div>
                                        <div class="position-relative">
                                            <label class="input-label font-semibold"
                                                for="tax">{{ translate('Approximate pickup time') }}</label>
                                            <div class="custom-group-btn">
                                                <div class="item flex-sm-grow-1">
                                                    <label class="floating-label"
                                                        for="min">{{ translate('Min') }}:</label>
                                                    <input id="min" type="number" name="minimum_delivery_time"
                                                        value=""
                                                        class="form-control h--45px border-0"
                                                        placeholder="{{ translate('messages.Ex') }}: 20"
                                                        pattern="^[0-9]{2}$" required>
                                                </div>
                                                <div class="separator"></div>
                                                <div class="item flex-sm-grow-1">
                                                    <label class="floating-label"
                                                        for="max">{{ translate('Max') }}:</label>
                                                    <input id="max" type="number" name="maximum_delivery_time"
                                                        value=""
                                                        class="form-control h--45px border-0"
                                                        placeholder="{{ translate('messages.Ex') }}: 30" pattern="[0-9]{2}$"
                                                        required>
                                                </div>
                                                <div class="separator"></div>
                                                <div class="item flex-shrink-0">
                                                    <select name="delivery_time_type" id="delivery_time_type"
                                                        class="custom-select border-0">
                                                        <option value="min"  >
                                                            {{ translate('messages.minutes') }}
                                                        </option>
                                                        <option value="hours" >
                                                            {{ translate('messages.hours') }}
                                                        </option>
                                                        <option value="days" >
                                                            {{ translate('messages.days') }}
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <input id="pac-input1" class="controls rounded" data-toggle="tooltip"
                                            data-placement="right"
                                            data-original-title="{{ translate('Search your location') }}"
                                            type="text" placeholder="{{ translate('Search') }}" />
                                        <div id="map" class="min-h-100"></div>
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
                                        {{ translate('Owner information') }}
                                    </h5>
                                    <p class="fs-12 mb-0">
                                        {{ translate('messages.Add the information of the owner who operates the business') }}
                                    </p>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4 col-sm-6">
                                        <div class="form-group mb-0">
                                            <label class="input-label"
                                                for="f_name">{{ translate('First name') }}</label>
                                            <input type="text" name="f_name" class="form-control" id="f_name"
                                                placeholder="{{ translate('First name') }}" value="{{old('f_name')}}"
                                                required>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-sm-6">
                                        <div class="form-group mb-0">
                                            <label class="input-label"
                                                for="l_name">{{ translate('Last name') }}</label>
                                            <input type="text" name="l_name" class="form-control" id="l_name"
                                                placeholder="{{ translate('Last name') }}" value="{{old('l_name')}}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-sm-6">
                                        <div class="form-group mb-0">
                                            <label class="input-label"
                                                for="phone">{{ translate('Phone') }}</label>
                                            <input type="tel" id="phone" name="phone" class="form-control"
                                                placeholder="{{ translate('messages.Ex') }}: 017********" value="{{old('phone')}}"
                                                required>
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
                                        {{ translate('Account information') }}
                                    </h5>
                                    <p class="fs-12 mb-0">
                                        {{ translate('messages.Insert the necessary information to account information') }}
                                    </p>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4 col-sm-6">
                                        <div class="form-group mb-0">
                                            <label class="input-label"
                                                for="exampleFormControlInput1">{{ translate('messages.email') }}</label>
                                            <input type="email" name="email" class="form-control" id="email"
                                                placeholder="{{ translate('messages.Ex') }}: ex@example.com"
                                                value="{{old('email')}}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-sm-6">
                                        <div class="js-form-message form-group mb-0">
                                            <label class="input-label"
                                                for="signupSrPassword">{{ translate('password') }}<span
                                                    class="form-label-secondary" data-toggle="tooltip" data-placement="right"
                                                    data-original-title="{{ translate('Use at least one uppercase letter, one lowercase letter, one number and one symbol.') }} {{ translate('Minimum characters') }}: 8">
                                                    <i class="tio-info text--title opacity-60"></i>
                                                </span></label>

                                            <div class="input-group input-group-merge">
                                                <input type="password" class="js-toggle-password form-control"
                                                    name="password" id="signupSrPassword"
                                                    pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}"
                                                    title="{{ translate('Use at least one uppercase letter, one lowercase letter, one number and one symbol.') }} {{ translate('Minimum characters') }}: 8"
                                                    placeholder="{{ translate('Minimum characters') }}: 8+"
                                                    aria-label="8+ characters required"
                                                    data-msg="Your password is invalid. Please try again."
                                                    data-hs-toggle-password-options='{
                                                "target": [".js-toggle-password-target-1", ".js-toggle-password-target-2"],
                                                "defaultClass": "tio-hidden-outlined",
                                                "showClass": "tio-visible-outlined",
                                                "classChangeTarget": ".js-toggle-passowrd-show-icon-1"
                                                }'>
                                                <div class="js-toggle-password-target-1 input-group-append">
                                                    <a class="input-group-text" href="javascript:;">
                                                        <i class="js-toggle-passowrd-show-icon-1 tio-visible-outlined"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="password-feedback" class="pass password-feedback">
                                            {{ translate('messages.Password not matched') }}
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-sm-6">
                                        <div class="js-form-message form-group mb-0">
                                            <label class="input-label"
                                                for="signupSrConfirmPassword">{{ translate('Confirm password') }}</label>

                                            <div class="input-group input-group-merge">
                                                <input type="password" class="js-toggle-password form-control"
                                                    name="confirmPassword" id="signupSrConfirmPassword"
                                                    pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}"
                                                    title="{{ translate('Use at least one uppercase letter, one lowercase letter, one number and one symbol.') }} {{ translate('Minimum characters') }}: 8"
                                                    placeholder="{{ translate('Minimum characters') }}: 8+"
                                                    aria-label="8+ characters required"
                                                    data-msg="Password does not match the confirm password."
                                                    data-hs-toggle-password-options='{
                                                    "target": [".js-toggle-password-target-1", ".js-toggle-password-target-2"],
                                                    "defaultClass": "tio-hidden-outlined",
                                                    "showClass": "tio-visible-outlined",
                                                    "classChangeTarget": ".js-toggle-passowrd-show-icon-2"
                                                    }'>
                                                <div class="js-toggle-password-target-2 input-group-append">
                                                    <a class="input-group-text" href="javascript:;">
                                                        <i class="js-toggle-passowrd-show-icon-2 tio-visible-outlined"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="invalid-feedback" class="pass invalid-feedback">
                                            {{ translate('messages.Password not matched') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-12">
                        <div>
                            <div class="card p-20">
                                <div class="mb-20">
                                    <h3 class="mb-1">{{translate('Business TIN')}}</h3>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-8 col-xxl-9">
                                        <div class="bg--secondary rounded p-20 h-100">
                                            <div class="form-group">
                                                <label class="input-label mb-2 d-block title-clr fw-normal" for="exampleFormControlInput1">{{translate('Taxpayer identification Number(TIN)')}} </label>
                                                <input type="text" name="tin" placeholder="{{translate('Type your taxpayer identification Number(TIN)')}}" class="form-control"  >
                                            </div>
                                            <div class="form-group mb-0">
                                                <label class="input-label mb-2 d-block title-clr fw-normal" for="exampleFormControlInput1">{{translate('Expire date')}} </label>
                                                <input type="date" name="tin_expire_date" class="form-control"  >
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-xxl-3">
                                        <div class="bg--secondary rounded p-20 h-100 single-document-uploaderwrap">
                                            <div class="d-flex align-items-center gap-1 justify-content-between mb-20">
                                                <div>
                                                    <h4 class="mb-1 fz--14px">{{translate('TIN certificate')}}</h4>
                                                    <p class="fz-12px mb-0">pdf, doc, jpg. File size : max 2 MB</p>
                                                </div>
                                                <div class="d-flex gap-3 align-items-center">
                                                    <button type="button" id="doc_edit_btn" class="w-30px h-30 rounded d-flex align-items-center justify-content-center btn--primary btn px-3 icon-btn">
                                                        <i class="tio-edit"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div>
                                                <div id="file-assets"
                                                     data-picture-icon="{{ asset('public/assets/admin/img/picture.svg') }}"
                                                     data-document-icon="{{ asset('public/assets/admin/img/document.svg') }}"
                                                     data-blank-thumbnail="{{ asset('public/assets/admin/img/picture.svg') }}">
                                                </div>
                                                <div class="d-flex justify-content-center" id="pdf-container">
                                                    <div class="document-upload-wrapper" id="doc-upload-wrapper">
                                                        <input type="file" name="tin_certificate_image" class="document_input" accept=".doc, .pdf, .jpg, .png, .jpeg">
                                                        <div class="textbox">
                                                            <img width="40" height="40" class="svg"
                                                                 src="{{ asset('public/assets/admin/img/doc-uploaded.png') }}"
                                                                 alt="">
                                                            <p class="fs-12 mb-0">{{ translate('messages.Select a file or') }} <span class="font-semibold">{{ translate('Drag & drop') }}</span>
                                                                {{ translate('messages.Here.') }}</p>
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
                        <div class="btn--container justify-content-end mt-20">
                            <button type="reset" id="reset_btn"
                                class="btn btn--warning-light min-w-100px justify-content-center"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                            <button type="button"
                                class="btn btn--primary min-w-100px justify-content-center show-business-plan-div" id="nextStep"><i class="tio-arrow-forward"></i> {{ translate('messages.Next') }}</button>
                        </div>
                    </div>
                </div>
            </div>
            <div id="businessPlan" class="d-none">
                <div class="custom-timeline d-flex flex-wrap gap-40px text-title mb-2">
                    <h4 class="single text-primary checked"><span class="count-checked">1</span>{{ translate('Business basic setup') }}</h4>
                    <h4 class="single font-semibold"><span class="count btn-primary">2</span>{{ translate('Business plan setup') }}</h4>
                </div>
                <div class="row g-2">
                    <div class="col-lg-12">
                        <div class="card mt-3">
                            <div class="card-header">
                                <div>
                                    <h5 class="text-title mb-1">
                                        {{ translate('Choose business plan') }}
                                    </h5>
                                    <p class="fs-12 mb-0">
                                        {{ translate('messages.Pay per transaction or enjoy unlimited access with a subscription.') }}
                                    </p>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-lg-6">
                                        <label class="business-plan-card-wrapper">
                                            <input type="radio" name="business_plan" class="business-plan-radio" value="commission-base" checked/>
                                            <div class="business-plan-card">
                                                <h4 class="fs-16 title text-title mb-10px opacity-70">
                                                    {{ translate('Commission base') }}
                                                </h4>
                                                <p class="fs-14 text-title opacity-70 mb-0">
                                                    {{ translate('You have to give a certain percentage of commission to admin for every trip request.') }}
                                                </p>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="col-lg-6">
                                        <label class="business-plan-card-wrapper">
                                            <input type="radio" name="business_plan" class="business-plan-radio" value="subscription-base"/>
                                            <div class="business-plan-card">
                                                <h4 class="fs-16 title text-title mb-10px opacity-70">
                                                    {{ translate('Subscription base') }}
                                                </h4>
                                                <p class="fs-14 text-title opacity-70 mb-0">
                                                    {{ translate('messages.You have to pay certain amount in every month/year to admin as subscription fee.') }}
                                                </p>
                                            </div>
                                        </label>
                                    </div>

                                    <div class="col-lg-12 mt-20" id="subscription-plan">
                                        <div>
                                            <div class="text-center mb-20">
                                                <h3 class="modal-title fs-16 opacity-lg font-bold">
                                                    {{ translate('Choose subscription package') }}</h3>
                                            </div>
                                            <div class="plan-slider owl-theme owl-carousel owl-refresh">
                                                @forelse ($packages as $key=> $package)
                                                    <label class="__plan-item d-block hover {{ (count($packages) > 4 && $key == 2) || (count($packages) < 5 && $key == 1) ? 'active' : '' }}">
                                                        <input type="radio" name="package_id" id="package_id"
                                                               value="{{ $package->id }}" class="d-none" {{ (count($packages) > 4 && $key == 2) || (count($packages) < 5 && $key == 1) ? 'checked' : '' }}>
                                                        <div class="inner-div">
                                                            <div class="text-center">
                                                                <h3 class="title">{{ $package->package_name }}</h3>
                                                                <h2 class="price">{{ \App\CentralLogics\Helpers::format_currency($package->price) }}</h2>
                                                                <div class="day-count">{{ $package->validity }}
                                                                    {{ translate('messages.days') }}</div>
                                                            </div>
                                                            <ul class="info">

                                                                @if ($package->mobile_app)
                                                                    <li>
                                                                        <i class="tio-checkmark-circle"></i>
                                                                        <span>{{ translate('Mobile app') }}</span>
                                                                    </li>
                                                                @endif
                                                                @if ($package->chat)
                                                                    <li>
                                                                        <i class="tio-checkmark-circle"></i>
                                                                        <span>{{ translate('messages.Chatting options') }}</span>
                                                                    </li>
                                                                @endif
                                                                @if ($package->review)
                                                                    <li>
                                                                        <i class="tio-checkmark-circle"></i>
                                                                        <span>{{ translate('Review section') }}</span>
                                                                    </li>
                                                                @endif

                                                                @if ($package->max_order == 'unlimited')
                                                                    <li>
                                                                        <i class="tio-checkmark-circle"></i>
                                                                        <span>{{ translate('Unlimited trips') }}</span>
                                                                    </li>
                                                                @else
                                                                    <li>
                                                                        <i class="tio-checkmark-circle"></i>
                                                                        <span>{{ $package->max_order }} {{ translate('messages.Trips') }} </span>
                                                                    </li>
                                                                @endif
                                                                @if ($package->max_product == 'unlimited')
                                                                    <li>
                                                                        <i class="tio-checkmark-circle"></i>
                                                                        <span>{{ translate('messages.Unlimited uploads') }}</span>
                                                                    </li>
                                                                @else
                                                                    <li>
                                                                        <i class="tio-checkmark-circle"></i>
                                                                        <span>{{ $package->max_product }} {{ translate('messages.uploads') }}</span>
                                                                    </li>
                                                                @endif
                                                            </ul>
                                                        </div>
                                                    </label>
                                                @empty
                                                    <div class="text-center">
                                                        {{translate('No package found')}}
                                                    </div>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-12">
                        <div class="btn--container justify-content-end mt-20">
                            <button type="button" class="btn btn--reset min-w-100px justify-content-center" id="backBusinessSetup"><i class="tio-arrow-backward"></i> {{ translate('Back') }}</button>
                            <button type="submit" class="btn btn--primary min-w-100px justify-content-center" id="submit"><i class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Submit') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    @php($default_location = \App\Models\BusinessSetting::where('key', 'default_location')->first())
    @php($default_location = $default_location->value ? json_decode($default_location->value, true) : 0)

<div class="d-none" id="data-set"
    data-admin-zone-id="{{auth('admin')?->user()?->zone_id}}"

    data-lat="{{ $default_location ? $default_location['lat'] : '23.757989' }}"
    data-lng="{{ $default_location ? $default_location['lng'] : '90.360587' }}"
    data-logoImageViewer="{{ asset('public/assets/admin/img/upload.png') }}"

    data-get-all-modules-url="{{ route('restaurant.get-all-modules') }}"
    data-password-valid="{{ translate('Password is valid') }}"
    data-password-invalid="{{ translate('Password format is invalid') }}"
    data-password-matched="{{ translate('Passwords matched') }}"
    data-password-not-matched="{{ translate('Confirm password does not match') }}"

    data-store-logo-required="{{ translate('Store logo & cover photos are required') }}"
    data-store-name-required="{{ translate('Store name is required') }}"
    data-store-address-required="{{ translate('Store address is required') }}"
    data-select-zone="{{ translate('You must select a zone') }}"
    data-map-latlong-required="{{ translate('Click the map to set the latitude and longitude') }}"
    data-tax-required="{{ translate('Tax is required') }}"
    data-pickup-zone-required="{{ translate('You must select a pickup zone') }}"
    data-min-delivery-time-required="{{ translate('Minimum delivery time is required') }}"
    data-max-delivery-time-required="{{ translate('Max delivery time is required') }}"
    data-first-name-required="{{ translate('First name is required') }}"
    data-last-name-required="{{ translate('Last name is required') }}"
    data-phone-required="{{ translate('Valid phone number is required') }}"
    data-email-required="{{ translate('Email is required') }}"
    data-password-required="{{ translate('The password is required') }}"
    data-confirm-password-mismatch="{{ translate('Confirm password does not match') }}"
    data-select_pickup_zone="{{ translate('Select pickup zone') }}"

></div>

@endsection

@push('script_2')

    <script src="{{ asset('public/assets/admin/js/file-preview/pdf.min.js') }}"></script>
    <script src="{{ asset('public/assets/admin/js/file-preview/pdf-worker.min.js') }}"></script>
    <script src="{{ asset('public/assets/admin/js/file-preview/add-multiple-document-upload.js') }}"></script>

    <script src="{{ asset('public/assets/admin/js/spartan-multi-image-picker.js') }}"></script>
    <script
        src="https://maps.googleapis.com/maps/api/js?key={{ \App\CentralLogics\Helpers::get_business_settings('map_api_key', false) }}&libraries=drawing,places&v=3.45.8">
    </script>
    <script src="{{asset('Modules/Rental/public/assets/js/admin/view-pages/provider-create.js')}}"></script>

@endpush
