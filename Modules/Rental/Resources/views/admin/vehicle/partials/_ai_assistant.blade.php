<div class="modal fade p-0" id="vehicleAiModal" tabindex="-1" aria-labelledby="vehicleAiModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-slideInRight modal-dialog-scrollable modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2 aiAssistantModalLabel" id="vehicleAiModalLabel">
                    <span class="square-div">
                        <span class="ai-btn-animation"><span class="gradientCirc"></span></span>
                        <img class="position-relative z-1" width="15" height="12" src="{{ asset('public/assets/admin/img/svg/blink-right.svg') }}" alt="">
                    </span>
                    <span>{{ translate('AI_Assistant') }}</span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('Close') }}">
                    <span aria-hidden="true" class="tio-clear"></span>
                </button>
            </div>
            <div class="modal-body">

                <div id="vehicleAiMain" class="ai-modal-content">
                    <div class="text-center mb-4">
                        <div class="ai-avatar mb-3">
                            <div class="avatar-circle mx-auto">
                                <span class="ai-btn-animation"><span class="gradientCirc"></span></span>
                                <img class="position-relative z-1" width="40" height="34" src="{{ asset('public/assets/admin/img/svg/blink-right.svg') }}" alt="">
                            </div>
                        </div>
                        <div class="ai-greeting mb-5">
                            <h4 class="text-title">{{ translate('Hi_There') }},</h4>
                            <h2 class="mb-2">{{ translate('I_am_here_to_help_you') }}</h2>
                            <p class="text-muted">
                                {{ translate('Generate_your_vehicle_name_and_description_from_a_keyword_or_a_photo.') }}
                            </p>
                        </div>
                        <div class="ai-actions d-grid gap-3">
                            <button type="button" class="btn btn-outline-secondary bg-transparent btn-block d-flex gap-2 mb-3 vehicle-ai-action-btn" data-action="keyword">
                                <img width="18" height="18" src="{{ asset('public/assets/admin/img/svg/text-generate.svg') }}" alt="">
                                <span class="text-title">{{ translate('Generate_by_Keyword') }}</span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary bg-transparent btn-block d-flex gap-2 vehicle-ai-action-btn" data-action="image">
                                <img width="18" height="18" src="{{ asset('public/assets/admin/img/svg/picture.svg') }}" alt="">
                                <span class="text-title">{{ translate('Generate_from_Image') }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div id="vehicleAiKeyword" class="ai-modal-content" style="display: none;">
                    <div class="mb-4">
                        <h5 class="mb-3 fs-16 font-bold">{{ translate('great!') }}</h5>
                        <p class="mb-3">{{ translate('Tell_me_which_vehicle_you_want_to_add._Just_type_it_simply,_like:') }}</p>
                        <ul class="mb-3 pl-4">
                            <li>{{ translate('a_2023_toyota_corolla_sedan') }}</li>
                            <li>{{ translate('a_luxury_bmw_suv') }}</li>
                            <li>{{ translate('a_yamaha_sports_bike') }}</li>
                        </ul>
                        <div class="generate-text-input-group">
                            <input type="text" class="form-control" id="vehicleAiKeywords"
                                   placeholder="{{ translate('Tell_me_about_your_vehicle') }}">
                            <button type="button" class="btn btn-primary border-0" id="vehicleAiGenerateTitles"
                                    data-route="{{ route($aiRoutePrefix . '.generate-titles') }}">
                                <span class="ai-loader-animation z-2 d-none">
                                    <span class="loader-circle"></span>
                                    <img width="15" height="15" class="position-relative h-100" src="{{ asset('public/assets/admin/img/svg/blink-left.svg') }}" alt="">
                                </span>
                                <span class="position-relative z-1"><i class="tio-arrow-forward"></i></span>
                            </button>
                        </div>
                    </div>
                    <div id="vehicleAiTitlesWrapper" style="display: none;">
                        <div class="text-primary generate_btn_wrapper vehicle-ai-generating d-none mb-3">
                            <div class="btn-svg-wrapper">
                                <img width="18" height="18" src="{{ asset('public/assets/admin/img/svg/blink-right-small.svg') }}" alt="">
                            </div>
                            <span class="ai-text-animation ai-text-animation-visible">{{ translate('Just_a_second') }}</span>
                        </div>
                        <h4 class="mb-2 vehicle-ai-titles-heading d-none">{{ translate('Suggested_Vehicle_Names') }}</h4>
                        <div id="vehicleAiTitles" class="list-group"></div>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-outline-secondary btn-sm vehicle-ai-back-btn">
                            <i class="tio-chevron-left"></i> {{ translate('Back') }}
                        </button>
                    </div>
                </div>

                <div id="vehicleAiImage" class="ai-modal-content" style="display: none;">
                    <div class="mt-10">
                        <div class="mb-4">
                            <h5 class="mb-3 fs-16 font-bold">{{ translate('upload_a_clear_photo_of_the_vehicle') }}</h5>
                            <ul class="mb-4 pl-4">
                                <li>{{ translate('try_to_use_a_clean_&_avoid_blur_image') }}</li>
                                <li>{{ translate('use_a_photo_that_clearly_shows_the_vehicle') }}</li>
                            </ul>
                        </div>
                        <div class="text-center mb-4">
                            <label class="upload-zone w-100 mx-auto" id="vehicleAiChooseImage">
                                <input type="file" id="vehicleAiImageInput" hidden accept="image/*">
                                <div class="text-box mx-auto">
                                    <div class="w-100 d-flex flex-column gap-2 justify-content-center align-items-center py-4">
                                        <img width="40" height="40" src="{{ asset('public/assets/admin/img/svg/image-upload.svg') }}" alt="">
                                        <div class="d-flex gap-2 align-items-center justify-content-center fs-14">
                                            <span class="text-dark">{{ translate('drag_&_drop_your_image') }}</span>
                                            <span class="text-lowercase">{{ translate('or') }}</span>
                                            <span class="text-primary font-semibold fs-12 text-underline">
                                                <i class="fi fi-rr-cloud-upload-alt"></i> {{ translate('Browse_Image') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div id="vehicleAiImagePreview" class="mx-auto position-relative" style="display: none;">
                                    <img id="vehicleAiPreviewImg" src="" alt="{{ translate('Preview') }}" class="upload-zone_img" style="max-height: 200px;">
                                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                                        <button type="button" class="btn btn-danger p-0 square-div z-2" id="vehicleAiRemoveImage" data-toggle="tooltip" title="{{ translate('Remove_image') }}">
                                            <i class="tio-clear"></i>
                                        </button>
                                    </div>
                                </div>
                            </label>
                            <div class="mt-4 text-center">
                                <button type="button" class="btn btn-primary mb-3 d-flex align-items-center gap-2 opacity-1 border-0 mx-auto"
                                        id="vehicleAiAnalyze" data-route="{{ route($aiRoutePrefix . '.analyze-image') }}" disabled>
                                    <span class="ai-btn-animation d-none"><span class="gradientRect"></span></span>
                                    <span class="position-relative z-1 d-flex gap-2 align-items-center">
                                        <span class="d-flex align-items-center btn-text">{{ translate('Generate_Vehicle_Info') }}</span>
                                        <img width="17" height="15" src="{{ asset('public/assets/admin/img/svg/blink-left.svg') }}" alt="">
                                    </span>
                                </button>
                            </div>
                        </div>
                        <div class="mt-1">
                            <button type="button" class="btn btn-outline-secondary btn-sm vehicle-ai-back-btn">
                                <i class="tio-chevron-left"></i> {{ translate('Back') }}
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<div class="floating-ai-button">
    <button type="button" class="btn btn-lg rounded-circle shadow-lg" data-toggle="modal" data-target="#vehicleAiModal" title="{{ translate('AI_Assistant') }}">
        <span class="ai-btn-animation"><span class="gradientCirc"></span></span>
        <span class="position-relative z-1 text-white d-flex flex-column gap-1 align-items-center">
            <img width="16" height="17" src="{{ asset('public/assets/admin/img/svg/hexa-ai.svg') }}" alt="">
            <span class="fs-12 font-semibold">{{ translate('Use_AI') }}</span>
        </span>
    </button>
    <div class="ai-tooltip"><span>{{ translate('AI_Assistant') }}</span></div>
</div>

<input type="hidden" id="vehicle_ai_lang" value="{{ \App\CentralLogics\Helpers::system_default_language() }}">
<input type="hidden" id="vehicle_ai_request_type" value="{{ $aiRequestType ?? 'admin' }}">
<input type="hidden" id="vehicle_ai_generate_desc_route" value="{{ route($aiRoutePrefix . '.generate-description') }}">
