@extends('layouts.vendor.app')

@section('title', translate('Add new driver'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="d-flex justify-content-between flex-wrap gap-3">
                <div>
                    <h1 class="page-header-title text-break">
                        <span class="page-header-icon">
                            <img src="{{ asset('public/assets/admin/img/outline/car.svg') }}" alt="{{translate('Image')}}">
                        </span>
                        <span>{{ translate('Add new driver') }}
                    </h1>
                    <p class="page-header-desc">{{ translate('Add a driver and the licence details that let them take your vehicles out.') }}</p>
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
                                               value=""
                                               placeholder="{{ translate('messages.Type your first name') }}" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="input-label"
                                               for="">
                                            {{ translate('Last name') }}
                                        </label>
                                        <input type="text" name="last_name" id=""
                                               class="form-control"
                                               value=""
                                               placeholder="{{ translate('messages.Type your last name') }}" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="input-label"
                                               for="">
                                            {{ translate('messages.email') }}
                                        </label>
                                        <input type="email" name="email" id=""
                                               class="form-control"
                                               value=""
                                               placeholder="{{ translate('messages.Type your email address') }}" required>
                                    </div>
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                               for="phone">{{ translate('Phone') }}</label>
                                        <input type="tel" id="phone" name="phone" class="form-control"
                                               placeholder="{{ translate('messages.Ex') }}: 017********"
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
                                                accept=".webp, .jpg, .jpeg, .png" data-max-size="1" required>
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
                                                <img class="upload-file-img d-none" height="180" width="180" loading="lazy" src="" alt="">
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
                                        <label class="input-label"
                                               for="">{{ translate('Identity type') }}</label>
                                        <select name="identity_type" class="form-control js-select2-custom" required>
                                            <option value="" readonly="true" hidden="true"  > {{ translate('messages.Select identity type') }}</option>
                                            <option value="passport">{{ translate('Passport') }}</option>
                                            <option value="driving_license">{{ translate('messages.Driving license') }} </option>
                                            <option value="nid">NID</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                               for="">{{ translate('Identity number') }}</label>
                                        <input type="text" id="" name="identity_number" class="form-control"
                                               placeholder="{{translate('Ex') . ': 123654789512364'}}" value=""
                                               required>
                                    </div>
                                </div>
                                <div class="col-lg-12">
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
                                           <div class="upload-file text-wrapper h--100px w--200px flex-shrink-0"
                                                id="image_upload_wrapper">
                                               <input type="file" name="identity_image[]"
                                                      class="upload-file__input multiple_image_input" accept=".webp, .jpg,.jpeg,.png" multiple required>
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
    <script src="{{ asset('Modules/Rental/public/assets/js/view-pages/provider/driver-create.js') }}"></script>
@endpush
