@extends('layouts.vendor.app')

@section('title', translate('Update driver'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="d-flex justify-content-between flex-wrap gap-3">
                <div>
                    <h1 class="page-header-title text-break">
                        <span class="page-header-icon">
                            <img src="{{ asset('public/assets/admin/img/outline/car.svg') }}" alt="">
                        </span>
                        <span>{{ translate('Update driver') }}
                    </h1>
                    <p class="page-header-desc">{{ translate('Change this driver\'s details, licence or account state.') }}</p>
                </div>
            </div>
        </div>

        <form action="" method="post" enctype="multipart/form-data">
            @csrf

            <div class="row g-3">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="text-title mb-1">
                                {{ translate('User information') }}
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label class="input-label"
                                               for="">
                                            {{ translate('First name') }}
                                        </label>
                                        <input type="text" name="first_name" id=""
                                               class="form-control"
                                               value="{{ $driver->first_name }}"
                                               placeholder="{{ translate('messages.Type your first name') }}" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="input-label"
                                               for="">
                                            {{ translate('Last name') }}
                                        </label>
                                        <input type="text" name="last_name" id=""
                                               class="form-control"
                                               value="{{ $driver->last_name }}"
                                               placeholder="{{ translate('messages.Type your last name') }}" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="input-label"
                                               for="">
                                            {{ translate('messages.email') }}
                                        </label>
                                        <input type="email" name="email" id=""
                                               class="form-control"
                                               value="{{ $driver->email }}"
                                               placeholder="{{ translate('messages.Type your email address') }}" required>
                                    </div>
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                               for="phone">{{ translate('Phone') }}</label>
                                        <input type="tel" id="phone" name="phone" class="form-control"
                                               placeholder="{{ translate('messages.Ex') }}: 017********" value="{{ $driver->phone }}"
                                               required>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="text-center">
                                        <label class="text--title fs-16 font-semibold mb-1">
                                            {{ translate('Profile image') }}
                                        </label>
                                        <div class="mb-20">
                                            <p class="fs-12">
                                                {{ 'JPG, JPEG, PNG' . ' image, max ' . 1 . ' MB' }} <strong class="font-semibold">(Ratio 1:1)</strong>
                                            </p>
                                        </div>
                                        <div class="upload-file image-general d-inline-block w-auto">
                                            <a href="javascript:void(0);" class="remove-btn opacity-0 z-index-99">
                                                <i class="tio-clear"></i>
                                            </a>
                                            <input type="file" name="image" class="upload-file__input single_file_input"
                                                accept=".webp, .jpg, .jpeg, .png" data-max-size="1" value="{{ $driver['image_full_url'] ?? '' }}">
                                            <label
                                                class="upload-file-wrapper w--180px">
                                                <div class="upload-file-textbox text-center">
                                                    <img width="34" height="34" src="{{ asset('public/assets/admin/img/document-upload.svg') }}" alt="">
                                                    <h6 class="mt-2 font-semibold text-center">
                                                        <span>{{ translate('Click to upload') }}</span>
                                                        <br>
                                                        {{ translate('or drag and drop') }}
                                                    </h6>
                                                </div>
                                                <img class="upload-file-img d-none" height="180" width="180" loading="lazy" src="{{ $driver['image_full_url'] ?? '' }}" data-driver-image="{{ $driver['image_full_url'] ?? '' }}">
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
                            <h5 class="text-title mb-1">
                                {{ translate('Identity information') }}
                            </h5>
                        </div>
                        <div class="card-body pb-2">
                            <div class="row g-3">
                                <div class="col-lg-6">
                                    <div class="form-group mb-0">
                                        <label class="input-label" for="">{{ translate('Identity type') }}</label>
                                        <select name="identity_type" class="form-control js-select2-custom" required>
                                            <option value="" readonly="true" hidden="true"  > {{ translate('messages.Select identity type') }}</option>
                                            <option value="passport" {{ $driver->identity_type == 'passport' ? 'selected' : '' }}>{{ translate('Passport') }}</option>
                                            <option value="driving_license" {{ $driver->identity_type == 'driving_license' ? 'selected' : '' }}>{{ translate('messages.Driving license') }} </option>
                                            <option value="nid" {{ $driver->identity_type == 'nid' ? 'selected' : '' }}>NID</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                               for="">{{ translate('Identity number') }}</label>
                                        <input type="text" id="" name="identity_number" class="form-control"
                                               placeholder="Ex: 123654789512364" value="{{ $driver->identity_number }}"
                                               required>
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <div class="">
                                        <div>
                                            <label class="form-label font-semibold mb-1">
                                                {{ translate('Identity image') }}
                                            </label>
                                            <p class="fs-12 mb-0">
                                                {{'JPG, JPEG, PNG' . ' image, max ' . 1 . ' MB'}}
                                                <strong class="font-semibold">(Ratio 2:1)</strong>
                                            </p>
                                        </div>
                                        <div class="d-flex pt-20 pb-2 overflow-x-auto">
                                            <div class="d-flex gap-3 flex-shrink-0" id="image_container">
                                                <div class="upload-file text-wrapper h--100px w--200px flex-shrink-0" id="image_upload_wrapper">
                                                    <input type="file" name="identity_image[]" class="upload-file__input multiple_image_input" accept=".webp, .jpg,.jpeg,.png" multiple>
                                                    <div class="upload-file__img d-flex gap-0 justify-content-center align-items-center h-100 max-w-300px p-0">
                                                        <div class="upload-file__textbox">
                                                            <img width="34" height="34" src="{{ asset('public/assets/admin/img/document-upload.png') }}" alt="" class="svg">
                                                            <h6 class="mt-2 font-semibold">
                                                                <span class="text-info">{{ translate('Click to upload') }}</span><br>
                                                                {{ translate('or drag and drop') }}
                                                            </h6>
                                                        </div>
                                                    </div>
                                                </div>
                                                @foreach($driver['identity_image_full_url'] as $img)
                                                    <div class="image-single h-100 max-w-200px p-0" data-existing="true" data-url="{{ $img }}">
                                                        <a href="javascript:void(0);" class="remove-btn" data-file-name="{{ $img }}">
                                                            <i class="tio-clear"></i>
                                                        </a>
                                                        <img class="img--vertical-2 rounded-10" width="200" height="100" loading="lazy" src="{{ $img }}" alt="">
                                                    </div>
                                                @endforeach
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
                                class="btn btn--reset min-w-120px shadow-none">{{ translate('messages.Reset') }}</button>
                        <button type="submit"
                                class="btn btn--primary min-w-120px shadow-none">{{ translate('messages.Submit') }}</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <input type="hidden" id="file-type-toast" value="{{ translate('Please upload a file in a supported format') . ': PNG, JPG' }}">
    <input type="hidden" id="file-size-toast" value="{{ translate('File size too big') }}">
    <input type="hidden" id="file-max-toast" value="{{ translate('Maximum files') }}: 5">

@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin/js/spartan-multi-image-picker.js') }}"></script>
    <script src="{{ asset('Modules/Rental/public/assets/js/view-pages/provider/driver-edit.js') }}"></script>
@endpush
