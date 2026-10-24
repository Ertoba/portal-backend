@extends('layouts.vendor.app')

@section('title',translate('Edit role'))

@section('content')
<div class="content container-fluid">

    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{asset('public/assets/admin/img/edit.png')}}" class="w--26" alt="">
            </span>
            <span>
                {{translate('Edit role')}}
            </span>
        </h1>
        <p class="page-header-desc">{{ translate('Change what this role can reach. Employees holding it are updated at once.') }}</p>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{route('vendor.custom-role.update-role',[$role['id']])}}" method="post">
                @csrf
                @php($language = \App\CentralLogics\Helpers::get_business_settings('language', false) ?? null)
                @if($language)
                    <ul class="nav nav-tabs mb-4">
                        <li class="nav-item">
                            <a class="nav-link lang_link active"
                            href="#"
                            id="default-link">{{translate('Default')}}</a>
                        </li>
                        @foreach (json_decode($language) as $lang)
                            <li class="nav-item">
                                <a class="nav-link lang_link"
                                    href="#"
                                    id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                            </li>
                        @endforeach
                    </ul>
                    <div class="lang_form" id="default-form">
                        <div class="form-group">
                            <label class="input-label" for="default_title">{{translate('Role name')}} ({{translate('Default')}})</label>
                            <input type="text" name="name[]" id="default_title" class="form-control" placeholder="{{translate('Role name example')}}" value="{{$role?->getRawOriginal('name')}}"  >
                        </div>
                        <input type="hidden" name="lang[]" value="default">
                    </div>
                    @foreach(json_decode($language) as $lang)
                        <?php
                            if(count($role['translations'])){
                                $translate = [];
                                foreach($role['translations'] as $t)
                                {
                                    if($t->locale == $lang && $t->key=="name"){
                                        $translate[$lang]['name'] = $t->value;
                                    }
                                }
                            }
                        ?>
                        <div class="d-none lang_form" id="{{$lang}}-form">
                            <div class="form-group">
                                <label class="input-label" for="{{$lang}}_title">{{translate('Role name')}} ({{strtoupper($lang)}})</label>
                                <input type="text" name="name[]" id="{{$lang}}_title" class="form-control" placeholder="{{translate('Role name example')}}" value="{{$translate[$lang]['name']??''}}"  >
                            </div>
                            <input type="hidden" name="lang[]" value="{{$lang}}">
                        </div>
                    @endforeach
                @else
                <div id="default-form">
                    <div class="form-group">
                        <label class="input-label" for="name">{{translate('Role name')}} ({{ translate('Default') }})</label>
                        <input type="text" id="name" name="name[]" class="form-control" placeholder="{{translate('Role name example')}}" value="{{$role['name']}}" maxlength="100" required>
                    </div>
                    <input type="hidden" name="lang[]" value="default">
                </div>
                @endif

                <h5>{{translate('messages.Module permission')}} : </h5>
                <hr>
                <div class="check--item-wrapper mx-0">
                    <div class="check-item">
                        <div class="form-group form-check form--check">
                            <input type="checkbox" name="modules[]" value="trip" class="form-check-input"
                                   id="trip" {{in_array('trip',(array)json_decode($role['modules']))?'checked':''}}>
                            <label class="form-check-label input-label " for="trip">{{translate('messages.Trip')}}</label>
                        </div>
                    </div>
                    <div class="check-item">
                        <div class="form-group form-check form--check">
                            <input type="checkbox" name="modules[]" value="vehicle" class="form-check-input"
                                   id="vehicle" {{in_array('vehicle',(array)json_decode($role['modules']))?'checked':''}}>
                            <label class="form-check-label input-label " for="vehicle">{{translate('messages.Vehicle')}}</label>
                        </div>
                    </div>
                    <div class="check-item">
                        <div class="form-group form-check form--check">
                            <input type="checkbox" name="modules[]" value="driver" class="form-check-input"
                                   id="driver" {{in_array('driver',(array)json_decode($role['modules']))?'checked':''}}>
                            <label class="form-check-label input-label " for="driver">{{translate('messages.Driver')}}</label>
                        </div>
                    </div>
                    <div class="check-item">
                        <div class="form-group form-check form--check">
                            <input type="checkbox" name="modules[]" value="marketing" class="form-check-input"
                                   id="marketing" {{in_array('marketing',(array)json_decode($role['modules']))?'checked':''}}>
                            <label class="form-check-label input-label " for="marketing">{{translate('messages.Marketing')}}</label>
                        </div>
                    </div>
                    <div class="check-item">
                        <div class="form-group form-check form--check">
                            <input type="checkbox" name="modules[]" value="store_setup" class="form-check-input"
                                   id="store_setup" {{in_array('store_setup',(array)json_decode($role['modules']))?'checked':''}}>
                            <label class="form-check-label input-label " for="store_setup">{{translate('messages.Store setup')}}</label>
                        </div>
                    </div>
                    <div class="check-item">
                        <div class="form-group form-check form--check">
                            <input type="checkbox" name="modules[]" value="wallet" class="form-check-input"
                                   id="wallet" {{in_array('wallet',(array)json_decode($role['modules']))?'checked':''}}>
                            <label class="form-check-label input-label " for="wallet">{{translate('messages.My wallet')}}</label>
                        </div>
                    </div>
                    <div class="check-item">
                        <div class="form-group form-check form--check">
                            <input type="checkbox" name="modules[]" value="bank_info" class="form-check-input"
                                   id="bank_info" {{in_array('bank_info',(array)json_decode($role['modules']))?'checked':''}}>
                            <label class="form-check-label input-label " for="bank_info">{{translate('messages.Profile')}}</label>
                        </div>
                    </div>

                    <div class="check-item">
                        <div class="form-group form-check form--check">
                            <input type="checkbox" name="modules[]" value="employee" class="form-check-input"
                                   id="employee" {{in_array('employee',(array)json_decode($role['modules']))?'checked':''}}>
                            <label class="form-check-label input-label " for="employee">{{translate('Employees')}}</label>
                        </div>
                    </div>
                    <div class="check-item">
                        <div class="form-group form-check form--check">
                            <input type="checkbox" name="modules[]" value="my_shop" class="form-check-input"
                                   id="my_shop" {{in_array('my_shop',(array)json_decode($role['modules']))?'checked':''}}>
                            <label class="form-check-label input-label " for="my_shop">{{translate('messages.My shop')}}</label>
                        </div>
                    </div>
                    <div class="check-item">
                        <div class="form-group form-check form--check">
                            <input type="checkbox" name="modules[]" value="reviews" class="form-check-input"
                                   id="reviews" {{in_array('reviews',(array)json_decode($role['modules']))?'checked':''}}>
                            <label class="form-check-label input-label " for="reviews">{{translate('messages.Reviews')}}</label>
                        </div>
                    </div>
                    <div class="check-item">
                        <div class="form-group form-check form--check">
                            <input type="checkbox" name="modules[]" value="chat" class="form-check-input"
                                   id="chat" {{in_array('chat',(array)json_decode($role['modules']))?'checked':''}}>
                            <label class="form-check-label input-label " for="chat">{{translate('messages.Chat')}}</label>
                        </div>
                    </div>
                    <div class="check-item">
                        <div class="form-group form-check form--check">
                            <input type="checkbox" name="modules[]" value="report" class="form-check-input"
                                   id="report" {{in_array('report',(array)json_decode($role['modules']))?'checked':''}}>
                            <label class="form-check-label input-label " for="report">{{translate('messages.report')}}</label>
                        </div>
                    </div>
                </div>
                <div class="btn--container justify-content-end mt-4">
                    <button type="reset" class="btn btn--reset">{{translate('messages.Reset')}}</button>
                    <button type="submit" class="btn btn--primary">{{translate('Update')}}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
