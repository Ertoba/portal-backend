@extends('layouts.admin.app')

@section('title',translate('messages.Update category'))

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/edit.png')}}" class="w--20" alt="">
                </span>
                <span>
                    {{$category->position?translate('messages.sub').' ':''}}{{translate('messages.Category update')}}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Rename this category or change the languages it is shown in.') }}</p>
        </div>
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
                                        id="default-link">{{translate('Default')}}</a>
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
                                    <label class="input-label" for="exampleFormControlInput1">{{translate('Name')}} ({{ translate('Default') }}) <span class="form-label-secondary text-danger"
                                        data-toggle="tooltip" data-placement="right"
                                        data-original-title="{{ translate('messages.Required.')}}"> *
                                        </span>
                                    </label>
                                    <input type="text" name="name[]" class="form-control" placeholder="{{translate('messages.New category')}}" maxlength="191" value="{{$category?->getRawOriginal('name')}}"  >
                                </div>
                                <input type="hidden" name="lang[]" value="default">
                                @foreach($language as $lang)
                                        <?php
                                        if(count($category?->translations ?? [])){
                                            $translate = [];
                                            foreach($category['translations'] as $t)
                                            {
                                                if($t->locale == $lang && $t->key=="name"){
                                                    $translate[$lang]['name'] = $t->value;
                                                }
                                            }
                                        }
                                    ?>
                                    <div class="form-group d-none lang_form" id="{{$lang}}-form">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('Name')}} ({{strtoupper($lang)}})</label>
                                        <input type="text" name="name[]" class="form-control" placeholder="{{translate('messages.New category')}}" maxlength="191" value="{{$translate[$lang]['name']??''}}"  >
                                    </div>
                                    <input type="hidden" name="lang[]" value="{{$lang}}">
                                @endforeach
                            @else
                                <div class="form-group">
                                    <label class="input-label" for="exampleFormControlInput1">{{translate('Name')}}</label>
                                    <input type="text" name="name" class="form-control" placeholder="{{translate('messages.New category')}}" value="{{$category['name']}}" maxlength="191">
                                </div>
                                <input type="hidden" name="lang[]" value="{{$lang}}">
                            @endif


                        </div>
                        <div class="col-lg-6">
                            @if ($category->position == 0)
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
                                            'existingImage' => $category['image_full_url'] ?? '',
                                            'imageFormat' => IMAGE_FORMAT,
                                            'textPosition' => 'top',
                                        ])
                                    </div>
                            @endif
                        </div>
                        <div class="col-12 mt-4">
                            <div class="btn--container justify-content-end mt-20">
                                <button type="reset" data-image="{{ $category['image_full_url'] }}" id="reset_btn" class="btn btn--reset"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                                <button type="submit" class="btn btn--primary"><i class="tio-save"></i> {{translate('Update')}}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin')}}/js/view-pages/category-index.js"></script>
@endpush
