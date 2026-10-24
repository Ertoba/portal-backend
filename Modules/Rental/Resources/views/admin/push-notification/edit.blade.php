@extends('layouts.admin.app')

@section('title',translate('messages.Update notification'))

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/notification.png')}}" class="w--26" alt="">
                </span>
                <span>
                    {{translate('messages.Notification update')}}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Change this notification\'s wording, artwork or who it goes to.') }}</p>
        </div>
        <div class="card">
            <div class="card-body">
                <form action="{{route('admin.rental.notification.update',[$notification['id']])}}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="row g-2">
                                <div class="col-12">
                                    <div class="form-group mb-0">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('messages.Title')}}</label>
                                        <input type="text" value="{{$notification['title']}}" name="notification_title" class="form-control" placeholder="{{translate('New notification')}}" required maxlength="191">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group mb-0">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('messages.Zone')}}</label>
                                        <select name="zone" id="zone" class="form-control js-select2-custom" >
                                            <option value="all" {{isset($notification->zone_id)?'':'selected'}}>{{translate('All zone')}}</option>
                                            @foreach($zones as $zone)
                                                <option value="{{$zone['id']}}"  {{$notification->zone_id==$zone['id']?'selected':''}}>{{$zone['name']}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group mb-0">
                                        <label class="input-label" for="tergat">{{translate('messages.Send to')}}</label>

                                        <select name="tergat" class="form-control" id="tergat" data-placeholder="{{translate('messages.Select target')}}" required>
                                            <option value="customer" {{$notification->tergat=='customer'?'selected':''}}>{{translate('messages.Customer')}}</option>
                                            <option value="store" {{$notification->tergat=='provider'?'selected':''}}>{{translate('messages.Provider')}}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group mb-0">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('messages.Description')}}</label>
                                        <textarea name="description" class="form-control" maxlength="1000" required>{{$notification['description']}}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="h-100 d-flex flex-column">
                                <label class="d-block text-center mt-auto mb-0">
                                    {{translate('messages.Image')}}
                                    <small class="text-danger">* ( {{translate('messages.Ratio')}} 900x300 )</small>
                                </label>
                                <div class="text-center py-3 my-auto">
                                    <img class="img--vertical onerror-image" id="viewer"
                                    src="{{ $notification['image_full_url'] }}" data-src="{{ $notification['image_full_url'] }}"
                                    data-onerror-image="{{asset('public/assets/admin/img/900x400/img1.jpg')}}" alt="image"/>
                                </div>
                                <div class="custom-file">
                                    <input type="file" name="image" id="customFileEg1" class="custom-file-input"
                                        accept=".webp, .jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*">
                                    <label class="custom-file-label" for="customFileEg1">{{translate('Choose file')}}</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="btn--container mt-4 justify-content-end">
                        <button type="reset" id="reset_btn" class="btn btn--reset"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                        <button type="submit" class="btn btn--primary"><i class="tio-send"></i> {{translate('messages.Send again')}}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<input type="hidden" id="default_zone_id" value="{{ $notification['zone_id'] }}">
@endsection

@push('script_2')
    <script src="{{asset('Modules/Rental/public/assets/js/admin/view-pages/push-notification-edit.js')}}"></script>
@endpush
