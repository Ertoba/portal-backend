@extends('layouts.admin.app')

@section('title',translate('messages.Add new category'))

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/outline/category.svg')}}" class="w--26" alt="">
                </span>
                <span>
                    {{translate('Add new category')}}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('The groups customers browse vehicles by, such as sedan, SUV or van.') }}</p>
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
                            @if ($language)
                            <div class="form-group lang_form" id="default-form">
                                <label class="input-label" for="exampleFormControlInput1">{{translate('Name')}} ({{ translate('Default') }})
                                    <span class="form-label-secondary text-danger"
                                    data-toggle="tooltip" data-placement="right"
                                    data-original-title="{{ translate('messages.Required.')}}"> *
                                    </span>

                                </label>
                                <input type="text" name="name[]"  value="{{ old('name.0') }}" class="form-control" placeholder="{{translate('messages.New category')}}" maxlength="191" required>
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                                @foreach($language as $key=> $lang)
                                    <div class="form-group d-none lang_form" id="{{$lang}}-form">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('Name')}} ({{strtoupper($lang)}})</label>
                                        <input type="text" name="name[]"  value="{{ old('name.'.$key+1) }}" class="form-control" placeholder="{{translate('messages.New category')}}" maxlength="191">
                                    </div>
                                    <input type="hidden" name="lang[]" value="{{$lang}}">
                                @endforeach
                            @else
                                <div class="form-group">
                                    <label class="input-label" for="exampleFormControlInput1">{{translate('Name')}}</label>
                                    <input type="text" name="name" class="form-control" placeholder="{{translate('messages.New category')}}" value="{{old('name')}}" maxlength="191">
                                </div>
                                <input type="hidden" name="lang[]" value="default">
                            @endif
                            <input name="position" value="0" class="initial-hidden">
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
                                            'imageFormat' => IMAGE_FORMAT,
                                            'textPosition' => 'top',
                                        ])
                                    </div>
                        </div>
                        <div class="col-12 mt-4">
                            <div class="btn--container justify-content-end mt-20">
                                <button type="reset" id="reset_btn" data-image="{{asset('public/assets/admin/img/upload-img.png')}}" class="btn btn--reset"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                                <button type="submit" class="btn btn--primary"><i class="{{ isset($category) ? 'tio-save' : 'tio-add-circle' }}"></i> {{isset($category)?translate('Update'):translate('Add')}}</button>
                            </div>
                        </div>
                    </div>

                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header py-2 border-0">
                <div class="search--button-wrapper">
                    <h5 class="card-title">{{translate('Category list')}}<span class="badge badge-soft-dark ml-2" id="itemCount">{{$categories->total()}}</span></h5>

                    <form class="search-form">
                        <div class="input-group input--group">
                            <input type="search" name="search" value="{{ request()?->search ?? null }}" class="form-control min-height-45" placeholder="{{translate('messages.Search categories')}}" aria-label="{{ translate('Ex') }}: Categories">
                            <input type="hidden" name="position" value="0">
                            <button type="submit" class="btn btn--secondary min-height-45"><i class="tio-search"></i></button>
                        </div>
                    </form>
                    @if(request()->input('search'))
                    <button type="reset" class="btn btn--primary ml-2 location-reload-to-category" data-url="{{url()->full()}}"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                    @endif
                    <div class="hs-unfold mr-2">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40" href="javascript:;"
                            data-hs-unfold-options='{
                                    "target": "#usersExportDropdown",
                                    "type": "css-animation"
                                }'>
                            <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
                        </a>

                        <div id="usersExportDropdown"
                            class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">

                            <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                            <a id="export-excel" class="dropdown-item" href="{{ route('admin.rental.category.export-categories', ['type' => 'excel', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                    alt="Image Description">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item" href="{{ route('admin.rental.category.export-categories', ['type' => 'csv', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                    alt="Image Description">
                                CSV
                            </a>

                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive datatable-custom">
                    <table id="columnSearchDatatable"
                        class="table table-borderless table-thead-bordered table-align-middle"
                        data-hs-datatables-options='{
                            "isResponsive": false,
                            "isShowPaging": false,
                            "paging":false,
                        }'>
                        <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{translate('SL')}}</th>
                                <th class="border-0">ID</th>
                                <th class="border-0 w--1">{{translate('Name')}}</th>
                                <th class="border-0 text-center">{{translate('messages.Status')}}</th>
                                <th class="border-0 text-center">{{translate('messages.Action')}}</th>
                            </tr>
                        </thead>

                        <tbody id="table-div">
                        @foreach($categories as $key=>$category)
                            <tr>
                                <td>{{$key+$categories->firstItem()}}</td>
                                <td>{{$category->id}}</td>
                                <td>
                                    <span class="d-block font-size-sm text-body">
                                        {{Str::limit($category['name'], 20,'...')}}
                                    </span>
                                </td>
                                <td>
                                    <label class="toggle-switch toggle-switch-sm" for="stocksCheckbox{{$category->id}}">
                                        <input type="checkbox" data-url="{{route('admin.rental.category.status',[$category['id'],$category->status?0:1])}}" class="toggle-switch-input redirect-url" id="stocksCheckbox{{$category->id}}" {{$category->status?'checked':''}}>
                                        <span class="toggle-switch-label mx-auto">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </label>
                                </td>
                                <td>
                                    <div class="btn--container justify-content-center">
                                        <a class="btn action-btn action-btn--edit"
                                            href="{{route('admin.rental.category.edit',[$category['id']])}}" title="{{translate('Edit category')}}"><i class="tio-edit"></i>
                                        </a>
                                        <a class="btn action-btn action-btn--delete form-alert" href="javascript:"
                                        data-id="category-{{$category['id']}}" data-message="{{ translate('Want to delete this category?') }}" title="{{translate('messages.Delete category')}}"><i class="tio-delete-outlined"></i>
                                        </a>
                                        <form action="{{route('admin.rental.category.delete',[$category['id']])}}" method="post" id="category-{{$category['id']}}">
                                            @csrf @method('delete')
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @if(count($categories) !== 0)
            <hr>
            @endif
            <div class="page-area">
                {!! $categories->appends($_GET)->links() !!}
            </div>
            @if(count($categories) === 0)
            <div class="empty--data">
                <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                <h5>
                    {{translate('No data found')}}
                </h5>
            </div>
            @endif
        </div>

    </div>

@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin/js/view-pages/category-index.js')}}"></script>
@endpush
