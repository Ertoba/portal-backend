@extends('layouts.admin.app')

@section('title', translate('Update provider basic setup'))

@push('css_or_js')
  <link rel="stylesheet" href="{{asset('Modules/Rental/public/assets/css/admin/provider-edit.css')}}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header pb-20">
            <div class="d-flex justify-content-between flex-wrap gap-3">
                <div>
                    <h1 class="page-header-title text-break">
                        <span class="page-header-icon">
                            <img src="{{ asset('/public/assets/admin/img/outline/provider-add.svg') }}" class="w--26" alt="">
                        </span>
                        <span>{{ $store->name}}
                    </h1>
                    <p class="page-header-desc">{{ translate('Change this provider\'s name, owner, contact details and zone.') }}</p></span>
                    </h1>
                </div>
            </div>
        </div>
        @php
            $delivery_time_start = preg_match('([0-9]+[\-][0-9]+\s[min|hours|days])', $store->delivery_time ?? '')
                ? explode('-', $store->delivery_time)[0]
                : 10;
            $delivery_time_end = preg_match('([0-9]+[\-][0-9]+\s[min|hours|days])', $store->delivery_time ?? '')
                ? explode(' ', explode('-', $store->delivery_time)[1])[0]
                : 30;
            $delivery_time_type = preg_match('([0-9]+[\-][0-9]+\s[min|hours|days])', $store->delivery_time ?? '')
                ? explode(' ', explode('-', $store->delivery_time)[1])[1]
                : 'min';
        @endphp
        @php($language = \App\CentralLogics\Helpers::get_business_settings('language', false) ?? null)

        <form action="" method="post" enctype="multipart/form-data" id="providerFormSubmit">
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
                                        {{ translate('messages.Update the basic information of the provider') }}
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
                                                        <div class="lang_form"
                                                             id="default-form">
                                                            <div class="form-group">
                                                                <label class="input-label"
                                                                       for="default_name">{{ translate('Name') }}
                                                                    ({{ translate('Default') }})
                                                                </label>
                                                                <input type="text" name="name[]" id="default_name"
                                                                       class="form-control" placeholder="{{ translate('messages.Provider name') }}" value="{{$store->getRawOriginal('name')}}"
                                                                       required
                                                                >
                                                            </div>
                                                            <input type="hidden" name="lang[]" value="default">
                                                            <div class="form-group mb-0">
                                                                <label class="input-label"
                                                                       for="exampleFormControlInput1">{{ translate('messages.Address') }} ({{ translate('Default') }})</label>
                                                                <textarea type="text" name="address[]" placeholder="{{translate('Provider address')}}" class="form-control min-h-90px ckeditor">{{$store->getRawOriginal('address')}}</textarea>
                                                            </div>
                                                        </div>
                                                        @foreach (json_decode($language) as $lang)
                                                                <?php
                                                                if(count($store['translations'])){
                                                                    $translate = [];
                                                                    foreach($store['translations'] as $t)
                                                                    {
                                                                        if($t->locale == $lang && $t->key=="name"){
                                                                            $translate[$lang]['name'] = $t->value;
                                                                        }
                                                                        if($t->locale == $lang && $t->key=="address"){
                                                                            $translate[$lang]['address'] = $t->value;
                                                                        }
                                                                    }
                                                                }
                                                                ?>
                                                            <div class="d-none lang_form"
                                                                 id="{{ $lang }}-form">
                                                                <div class="form-group">
                                                                    <label class="input-label"
                                                                           for="{{ $lang }}_name">{{ translate('Name') }}
                                                                        ({{ strtoupper($lang) }})
                                                                    </label>
                                                                    <input type="text" name="name[]" id="{{ $lang }}_name"
                                                                           class="form-control" value="{{ $translate[$lang]['name']??'' }}" placeholder="{{ translate('messages.Provider name') }}"
                                                                    >
                                                                </div>
                                                                <input type="hidden" name="lang[]" value="{{ $lang }}">
                                                                <div class="form-group mb-0">
                                                                    <label class="input-label"
                                                                           for="exampleFormControlInput1">{{ translate('messages.Address') }} ({{ strtoupper($lang) }})</label>
                                                                    <textarea type="text" name="address[]" placeholder="{{translate('Provider address')}}" class="form-control min-h-90px ckeditor">{{ $translate[$lang]['address']??'' }}</textarea>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    @else
                                                        <div id="default-form">
                                                            <div class="form-group">
                                                                <label class="input-label"
                                                                       for="exampleFormControlInput1">{{ translate('Name') }} ({{ translate('Default') }})</label>
                                                                <input type="text" name="name[]" class="form-control"
                                                                       placeholder="{{ translate('messages.Provider name') }}" required>
                                                            </div>
                                                            <input type="hidden" name="lang[]" value="default">
                                                            <div class="form-group mb-0">
                                                                <label class="input-label"
                                                                       for="exampleFormControlInput1">{{ translate('messages.Address') }}
                                                                </label>
                                                                <textarea type="text" name="address[]" placeholder="{{translate('Provider address')}}" class="form-control min-h-90px ckeditor"></textarea>
                                                            </div>
                                                        </div>
                                                    @endif
                                            </div>
                                        </div>

                                    </div>
                                    <div class="col-lg-6">
                                        <div class="d-flex flex-column flex-sm-row gap-4">
                                            <div class="__custom-upload-img mr-lg-5">
                                                <label class="form-label mb-1">
                                                    {{ translate('Logo') }}
                                                </label>
                                                <div class="mb-20">
                                                    <p class="fs-12 max-width-170px">{{ 'JPG, JPEG, PNG' . ' image, max ' . 2 . ' MB' }} <strong
                                                            class="font-semibold">(Ratio 1:1)</strong></p>
                                                </div>
                                                <label class="text-center position-relative">
                                                    <img class="img--110 min-height-170px min-width-170px onerror-image image--border" id="logoImageViewer"
                                                         data-onerror-image="{{ asset('public/assets/admin/img/upload-img.png') }}"
                                                         src="{{ $store->logo_full_url ?? asset('public/assets/admin/img/upload-img.png') }}"
                                                         alt="logo image" />
                                                    <div class="icon-file-group">
                                                        <div class="icon-file">
                                                            <i class="tio-edit"></i>
                                                            <input type="file" name="logo" id="customFileEg1" class="custom-file-input"
                                                                   accept=".webp, .jpg, .png, .jpeg|image/*">
                                                        </div>
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
                                                <label class="text-center position-relative">
                                                    <img class="img--vertical min-height-170px min-width-170px onerror-image image--border" id="coverImageViewer"
                                                         data-onerror-image="{{ asset('public/assets/admin/img/upload-img.png') }}"
                                                         src="{{ $store->cover_photo_full_url ?? asset('public/assets/admin/img/upload-img.png') }}"
                                                         alt="Fav icon" />
                                                    <div class="icon-file-group">
                                                        <div class="icon-file">
                                                            <i class="tio-edit"></i>
                                                            <input type="file" name="cover_photo" id="coverImageUpload"  class="custom-file-input"
                                                                   accept=".webp, .jpg, .png, .jpeg|image/*">
                                                        </div>
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
                                        {{ translate('messages.Update the necessary information to operate the business') }}
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
                                            <select name="zone_id" id="choice_zones" data-zone-coordinates-url="{{ route('admin.zone.get-coordinates', ['id' => 'PLACEHOLDER_ID']) }}" required
                                                    class="form-control js-select2-custom"
                                                    data-placeholder="{{ translate('Select zone') }}">
                                                <option value="" selected disabled>{{ translate('Select zone') }}</option>
                                                @foreach(\App\CentralLogics\Helpers::zones_dropdown(activeOnly: true) as $zone)
                                                    @if(auth('admin')?->user()?->zone_id)
                                                        @if(auth('admin')->user()->zone_id == $zone->id)
                                                            <option value="{{$zone->id}}" {{$store->zone_id == $zone->id? 'selected': ''}}>{{$zone->name}}</option>
                                                        @endif
                                                    @else
                                                        <option value="{{$zone->id}}" {{$store->zone_id == $zone->id? 'selected': ''}}>{{$zone->name}}</option>
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
                                            <select name="pickup_zones[]" id="pickup_zones" class="form-control basic-multiple-select2" multiple="multiple">
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
                                        <div class="form-group">
                                            <label class="input-label" for="latitude">{{translate('messages.latitude')}}
                                                <span
                                                    class="form-label-secondary" data-toggle="tooltip" data-placement="right"
                                                    data-original-title="{{translate('messages.Click the map to set the provider latitude and longitude')}}">
                                                <img src="{{asset('/public/assets/admin/img/info-circle.svg')}}" alt="{{translate('messages.Click the map to set the provider latitude and longitude')}}">
                                            </span>
                                            </label>
                                            <input type="text" id="latitude"
                                                   name="latitude" class="form-control"
                                                   placeholder="{{ translate('messages.Ex') }}: -94.22213" value="{{$store->latitude}}" required readonly>
                                        </div>
                                        <div class="form-group mb-5">
                                            <label class="input-label" for="longitude">{{translate('messages.longitude')}}
                                                <span
                                                    class="form-label-secondary" data-toggle="tooltip" data-placement="right"
                                                    data-original-title="{{translate('messages.Click the map to set the provider latitude and longitude')}}">
                                                <img src="{{asset('/public/assets/admin/img/info-circle.svg')}}" alt="{{translate('messages.Click the map to set the provider latitude and longitude')}}">
                                            </span>
                                            </label>
                                            <input type="text"
                                                   name="longitude" class="form-control"
                                                   placeholder="{{ translate('messages.Ex') }}: 103.344322" id="longitude" value="{{$store->longitude}}" required readonly>
                                        </div>
                                        <div class="position-relative">
                                            <label class="input-label font-semibold"
                                                   for="tax">{{ translate('Approximate pickup time') }}</label>
                                            <div class="custom-group-btn">
                                                <div class="item flex-sm-grow-1">
                                                    <label class="floating-label"
                                                           for="min">{{ translate('Min') }}:</label>
                                                    <input id="min" type="number" name="minimum_delivery_time"
                                                           value="{{ $delivery_time_start }}"
                                                           class="form-control h--45px border-0"
                                                           placeholder="{{ translate('messages.Ex') }}: 20"
                                                           pattern="^[0-9]{2}$" required>
                                                </div>
                                                <div class="separator"></div>
                                                <div class="item flex-sm-grow-1">
                                                    <label class="floating-label"
                                                           for="max">{{ translate('Max') }}:</label>
                                                    <input id="max" type="number" name="maximum_delivery_time"
                                                           value="{{ $delivery_time_end }}"
                                                           class="form-control h--45px border-0"
                                                           placeholder="{{ translate('messages.Ex') }}: 30" pattern="[0-9]{2}$"
                                                           required>
                                                </div>
                                                <div class="separator"></div>
                                                <div class="item flex-shrink-0">
                                                    <select name="delivery_time_type" id="delivery_time_type"
                                                            class="custom-select border-0">
                                                        <option value="min"
                                                            {{ $delivery_time_type == 'min' ? 'selected' : '' }}>
                                                            {{ translate('messages.minutes') }}
                                                        </option>
                                                        <option value="hours"
                                                            {{ $delivery_time_type == 'hours' ? 'selected' : '' }}>
                                                            {{ translate('messages.hours') }}
                                                        </option>
                                                        <option value="days"
                                                            {{ $delivery_time_type == 'days' ? 'selected' : '' }}>
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
                                        {{ translate('Update the information of the owner who operate the business') }}
                                    </p>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4 col-sm-6">
                                        <div class="form-group mb-0">
                                            <label class="input-label" for="f_name">{{translate('First name')}}</label>
                                            <input type="text" name="f_name" class="form-control" placeholder="{{translate('First name')}}"
                                                   value="{{$store->vendor->f_name}}"  required>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-sm-6">
                                        <div class="form-group mb-0">
                                            <label class="input-label" for="l_name">{{translate('Last name')}}</label>
                                            <input type="text" name="l_name" class="form-control" placeholder="{{translate('Last name')}}"
                                                   value="{{$store->vendor->l_name}}"  required>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-sm-6">
                                        <div class="form-group mb-0">
                                            <label class="input-label" for="phone">{{translate('Phone')}}</label>
                                            <input type="tel" id="phone" name="phone" class="form-control"
                                                   placeholder="{{ translate('messages.Ex') }}: 017********" value="{{$store->vendor->phone}}"
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
                                        {{ translate('messages.Update the necessary information to account information') }}
                                    </p>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4 col-sm-6">
                                        <div class="form-group mb-0">
                                            <label class="input-label"
                                                   for="exampleFormControlInput1">{{ translate('messages.email') }}</label>
                                            <input type="email" name="email" class="form-control"
                                                   placeholder="{{ translate('messages.Ex') }}: ex@example.com"
                                                   value="{{$store->email}}" required>
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
                                                <input type="text" name="tin" placeholder="{{translate('Type your taxpayer identification Number(TIN)')}}" class="form-control"
                                                       value="{{$store->tin}}"  >
                                            </div>
                                            <div class="form-group mb-0">
                                                <label class="input-label mb-2 d-block title-clr fw-normal" for="exampleFormControlInput1">{{translate('Expire date')}} </label>
                                                <input type="date" name="tin_expire_date" class="form-control" value="{{$store->tin_expire_date}}" >
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
                                                    <div class="document-upload-wrapper d-none" id="doc-upload-wrapper">
                                                        <input type="file" name="tin_certificate_image" class="document_input" accept=".doc, .pdf, .jpg, .png, .jpeg">
                                                        <div class="textbox">
                                                            <img width="40" height="40" class="svg"
                                                                 src="{{ asset('public/assets/admin/img/doc-uploaded.png') }}"
                                                                 alt="">
                                                            <p class="fs-12 mb-0">{{ translate('messages.Select a file or') }} <span class="font-semibold">{{ translate('Drag & drop') }}</span>
                                                                {{ translate('messages.Here.') }}</p>
                                                        </div>
                                                    </div>
                                                    <div class="pdf-single" data-file-name="${file.name}" data-file-url="{{ $store->tin_certificate_image_full_url ?? asset('public/assets/admin/img/upload-cloud.png') }}">
                                                    <div class="pdf-frame">
                                                        @php($imgPath =  $store->tin_certificate_image_full_url ?? asset('public/assets/admin/img/upload-cloud.png'))
                                                        @if(Str::endsWith($imgPath, ['.pdf', '.doc', '.docx']))
                                                            @php($imgPath =  asset('public/assets/admin/img/document.svg'))
                                                        @endif
                                                        <img class="pdf-thumbnail-alt" src="{{ $imgPath }}" alt="File Thumbnail">
                                                    </div>
                                                        <div class="overlay">
                                                            <div class="pdf-info">
                                                                @if(Str::endsWith($imgPath, ['.pdf', '.doc', '.docx']))
                                                                    <img src="{{ asset('public/assets/admin/img/document.svg') }}" width="34" alt="File Type Logo">
                                                                @else
                                                                    <img src="{{ asset('public/assets/admin/img/picture.svg') }}" width="34" alt="File Type Logo">
                                                                @endif
                                                                <div class="file-name-wrapper">
                                                                    <span class="file-name js-filename-truncate">{{ $store->tin_certificate_image }}</span>
                                                                    <span class="opacity-50">{{ translate('Click to view the file') }}</span>
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
                        </div>
                    </div>
                    <div class="col-lg-12">
                        <div class="btn--container justify-content-end mt-20">
                            <button type="reset" id="reset_btn"
                                    class="btn btn--warning-light min-w-100px justify-content-center"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                            <button type="submit"
                                    class="btn btn--primary min-w-100px justify-content-center"><i class="tio-save"></i> {{ translate('Update & next') }}</button>
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
            data-store-lat="{{ $store->latitude }}"
            data-store-lng="{{ $store->longitude }}"
            data-store-name="{{ $store->name }}"
            data-store-logo="{{ $store->logo_full_url }}"
            data-store-cover-photo="{{ $store->cover_photo_full_url }}"
            data-store-zone-id="{{ $store->zone_id }}"

            data-password-valid="{{ translate('Password is valid') }}"
            data-password-invalid="{{ translate('Password format is invalid') }}"
            data-password-matched="{{ translate('Passwords matched') }}"
            data-confirm-password-mismatch="{{ translate('Confirm password does not match') }}"

    ></div>
@endsection

@push('script_2')

    <script src="{{ asset('public/assets/admin/js/file-preview/pdf.min.js') }}"></script>
    <script src="{{ asset('public/assets/admin/js/file-preview/pdf-worker.min.js') }}"></script>
    <script src="{{ asset('public/assets/admin/js/file-preview/edit-multiple-document-upload.js') }}"></script>

    <script src="{{ asset('public/assets/admin/js/spartan-multi-image-picker.js') }}"></script>
    <script
        src="https://maps.googleapis.com/maps/api/js?key={{ \App\CentralLogics\Helpers::get_business_settings('map_api_key', false) }}&libraries=drawing,places&v=3.45.8">
    </script>
    <script src="{{asset('Modules/Rental/public/assets/js/admin/view-pages/provider-edit.js')}}"></script>

@endpush
