@extends('layouts.admin.app')

@section('title',translate('messages.Update brand'))

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/edit.png')}}" class="w--20" alt="">
                </span>
            </h1>
        </div>
        <!-- End Page Header -->
        <div class="card">
            <div class="card-body">
                <form action="" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-3">
                        <div class="col-lg-6">
                            @if($language)
                                <ul class="nav nav-tabs mb-4 border-0">
                                    <li class="nav-item">
                                        <a class="nav-link lang_link active"
                                        href="#"
                                        id="default-link">{{translate('messages.default')}}</a>
                                    </li>
                                    @foreach ($language as $lang)
                                        <li class="nav-item">
                                            <a class="nav-link lang_link"
                                                href="#"
                                                id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @if($language)
                                <div class="form-group lang_form" id="default-form">
                                    <label class="input-label" for="exampleFormControlInput1">{{translate('messages.name')}} ({{ translate('messages.default') }}) <span class="form-label-secondary text-danger"
                                        data-toggle="tooltip" data-placement="right"
                                        data-original-title="{{ translate('messages.Required.')}}"> *
                                        </span>
                                    </label>
                                    <input type="text" name="name[]" class="form-control" placeholder="{{translate('messages.new_brand')}}" maxlength="191" value="{{$brand?->getRawOriginal('name')}}"  >
                                </div>
                                <input type="hidden" name="lang[]" value="default">
                                @foreach($language as $lang)
                                        <?php
                                            if(count($brand?->translations ?? [])){
                                                $translate = [];
                                                foreach($brand['translations'] as $t)
                                                {
                                                    if($t->locale == $lang && $t->key=="name"){
                                                        $translate[$lang]['name'] = $t->value;
                                                    }
                                                }
                                            }
                                        ?>
                                    <div class="form-group d-none lang_form" id="{{$lang}}-form">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('messages.name')}} ({{strtoupper($lang)}})</label>
                                        <input type="text" name="name[]" class="form-control" placeholder="{{translate('messages.new_brand')}}" maxlength="191" value="{{$translate[$lang]['name']??''}}"  >
                                    </div>
                                    <input type="hidden" name="lang[]" value="{{$lang}}">
                                @endforeach
                            @else
                                <div class="form-group">
                                    <label class="input-label" for="exampleFormControlInput1">{{translate('messages.name')}}</label>
                                    <input type="text" name="name" class="form-control" placeholder="{{translate('messages.new_brand')}}" value="{{$brand['name']}}" maxlength="191">
                                </div>
                                <input type="hidden" name="lang[]" value="{{$lang}}">
                            @endif


                        </div>
                        <div class="col-lg-6">
                            <div class="text-center">
                                        <label class="text--title fs-16 font-semibold mb-1">
                                            {{ translate('Image') }}<span class="text-danger">*</span>
                                        </label>
                                        @include('rental::admin.partials._image-uploader', [
                                            'name' => 'image',
                                            'ratio' => '1:1',
                                            'imageExtension' => IMAGE_EXTENSION,
                                            'maxSize' => MAX_FILE_SIZE,
                                            'isRequired' => true,
                                            'existingImage' => $brand['image_full_url'] ?? '',
                                            'imageFormat' => IMAGE_FORMAT,
                                            'textPosition' => 'top',
                                        ])
                                    </div>
                        </div>
                        <div class="col-12 mt-4">
                            <div class="btn--container justify-content-end mt-20">
                                <button type="reset" id="reset_btn" data-image="{{ $brand['image_full_url'] }}" class="btn btn--reset">{{translate('messages.reset')}}</button>
                                <button type="submit" class="btn btn--primary">{{translate('messages.update')}}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <!-- End Table -->
        </div>
    </div>

@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin')}}/js/view-pages/category-index.js"></script>
@endpush
