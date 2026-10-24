@extends('layouts.vendor.app')

@section('title',translate('Create role'))

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{asset('public/assets/admin/img/role.png')}}" class="w--26" alt="">
            </span>
            <span>
                {{translate('messages.Custom role')}}
            </span>
        </h1>
        <p class="page-header-desc">{{ translate('A role decides which parts of the panel an employee can open.') }}</p>
    </div>
    @php($language = \App\CentralLogics\Helpers::get_business_settings('language', false) ?? null)
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">
                <span class="card-header-icon">
                    <i class="tio-document-text-outlined"></i>
                </span>
                <span>{{translate('messages.Role form')}}</span>
            </h5>
        </div>
        <div class="card-body">
            <form action="{{route('vendor.custom-role.create')}}" method="post">
                @csrf
                @if ($language)
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
                            <div class="form-group lang_form" id="default-form">
                                <label class="input-label" for="name">{{translate('Role name')}} ({{ translate('Default') }})</label>
                                <input type="text" id="name" name="name[]" class="form-control" placeholder="{{translate('Role name example')}}" maxlength="191"  >
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                                @foreach(json_decode($language) as $lang)
                                    <div class="form-group d-none lang_form" id="{{$lang}}-form">
                                        <label class="input-label" for="name{{$lang}}">{{translate('Role name')}} ({{strtoupper($lang)}})</label>
                                        <input type="text" id="name{{$lang}}" name="name[]" class="form-control" placeholder="{{translate('Role name example')}}" maxlength="191"  >
                                    </div>
                                    <input type="hidden" name="lang[]" value="{{$lang}}">
                                @endforeach
                            @else
                                <div class="form-group">
                                    <label class="input-label" for="name">{{translate('Role name')}}</label>
                                    <input type="text" id="name" name="name" class="form-control" placeholder="{{translate('Role name example')}}" value="{{old('name')}}" required maxlength="191">
                                </div>
                                <input type="hidden" name="lang[]" value="default">
                            @endif

                <h5 class="text-capitalize">{{translate('messages.Module permission')}} : </h5>
                <hr>
                <div class="check--item-wrapper mx-0">
                    <div class="check-item">
                        <div class="form-group form-check form--check">
                            <input type="checkbox" name="modules[]" value="trip" class="form-check-input"
                                    id="trip">
                            <label class="form-check-label input-label " for="trip">{{translate('messages.Trip')}}</label>
                        </div>
                    </div>
                    <div class="check-item">
                        <div class="form-group form-check form--check">
                            <input type="checkbox" name="modules[]" value="vehicle" class="form-check-input"
                                    id="vehicle">
                            <label class="form-check-label input-label " for="vehicle">{{translate('messages.Vehicle')}}</label>
                        </div>
                    </div>
                    <div class="check-item">
                        <div class="form-group form-check form--check">
                            <input type="checkbox" name="modules[]" value="driver" class="form-check-input" id="driver">
                            <label class="form-check-label input-label " for="driver">{{translate('messages.Driver')}}</label>
                        </div>
                    </div>
                    <div class="check-item">
                        <div class="form-group form-check form--check">
                            <input type="checkbox" name="modules[]" value="marketing" class="form-check-input"
                                    id="marketing">
                            <label class="form-check-label input-label " for="marketing">{{translate('messages.Marketing')}}</label>
                        </div>
                    </div>
                    <div class="check-item">
                        <div class="form-group form-check form--check">
                            <input type="checkbox" name="modules[]" value="store_setup" class="form-check-input"
                                    id="store_setup">
                            <label class="form-check-label input-label " for="store_setup">{{translate('messages.Store setup')}}</label>
                        </div>
                    </div>
                    <div class="check-item">
                        <div class="form-group form-check form--check">
                            <input type="checkbox" name="modules[]" value="wallet" class="form-check-input"
                                    id="wallet">
                            <label class="form-check-label input-label " for="wallet">{{translate('messages.My wallet')}}</label>
                        </div>
                    </div>
                    <div class="check-item">
                        <div class="form-group form-check form--check">
                            <input type="checkbox" name="modules[]" value="bank_info" class="form-check-input"
                                    id="bank_info">
                            <label class="form-check-label input-label " for="bank_info">{{translate('messages.Profile')}}</label>
                        </div>
                    </div>

                    <div class="check-item">
                        <div class="form-group form-check form--check">
                            <input type="checkbox" name="modules[]" value="employee" class="form-check-input"
                                    id="employee">
                            <label class="form-check-label input-label " for="employee">{{translate('Employees')}}</label>
                        </div>
                    </div>
                    <div class="check-item">
                        <div class="form-group form-check form--check">
                            <input type="checkbox" name="modules[]" value="my_shop" class="form-check-input"
                                    id="my_shop">
                            <label class="form-check-label input-label " for="my_shop">{{translate('messages.My shop')}}</label>
                        </div>
                    </div>
                    <div class="check-item">
                        <div class="form-group form-check form--check">
                            <input type="checkbox" name="modules[]" value="reviews" class="form-check-input"
                                    id="reviews">
                            <label class="form-check-label input-label " for="reviews">{{translate('messages.Reviews')}}</label>
                        </div>
                    </div>
                    <div class="check-item">
                        <div class="form-group form-check form--check">
                            <input type="checkbox" name="modules[]" value="chat" class="form-check-input"
                                    id="chat">
                            <label class="form-check-label input-label " for="chat">{{translate('messages.Chat')}}</label>
                        </div>
                    </div>
                    <div class="check-item">
                        <div class="form-group form-check form--check">
                            <input type="checkbox" name="modules[]" value="report" class="form-check-input"
                                    id="report">
                            <label class="form-check-label input-label " for="report">{{translate('messages.report')}}</label>
                        </div>
                    </div>
                </div>
                <div class="btn--container justify-content-end mt-4">
                    <button type="reset" class="btn btn--reset">{{translate('messages.Reset')}}</button>
                    <button type="submit" class="btn btn--primary">{{translate('messages.Submit')}}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header border-0">
            <div class="search--button-wrapper">
                <h5 class="card-title">
                    <span class="card-header-icon">
                        <i class="tio-document-text-outlined"></i>
                    </span>
                    <span>
                        {{translate('messages.Roles table')}}<span class="badge badge-soft-dark ml-2" id="itemCount">{{$roles->total()}}</span>
                    </span>
                </h5>
                <form  class="search-form min--250">
                    <div class="input-group input--group">
                        <input  value="{{request()?->search ?? ''}}" type="search" name="search" class="form-control" placeholder="{{translate('messages.Search role')}}" aria-label="{{translate('messages.Search')}}">
                        <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive datatable-custom">
                <table id="columnSearchDatatable"
                        class="table table-borderless table-thead-bordered table-align-middle card-table"
                        data-hs-datatables-options='{
                            "order": [],
                            "orderCellsTop": true,
                            "paging":false
                        }'>
                    <thead class="thead-light">
                        <tr>
                            <th class="border-0 w-50px">{{translate('messages.SL')}}</th>
                            <th class="border-0 w-50px">{{translate('Role name')}}</th>
                            <th class="border-0 w-100px">{{translate('Modules')}}</th>
                            <th class="border-0 w-50px">{{translate('messages.Created at')}}</th>
                            <th class="border-0 w-50px text-center">{{translate('messages.Action')}}</th>
                        </tr>
                    </thead>
                    <tbody  id="set-rows">
                    @foreach($roles as $k=>$r)
                        <tr>
                            <td >{{$k+$roles->firstItem()}}</td>
                            <td>{{Str::limit($r['name'],20,'...')}}</td>
                            <td class="text-capitalize">
                                @if($r['modules']!=null)
                                    @foreach((array)json_decode($r['modules']) as $key=>$m)

                                    @if ($m == 'bank_info')
                                    {{translate('messages.Profile')}}
                                    @else
                                    {{translate(str_replace('_',' ',$m))}}
                                    @endif


                                    {{  !$loop->last ? ',' : '.'}}
                                    @endforeach
                                @endif
                            </td>
                            <td>{{date('d-M-y',strtotime($r['created_at']))}}</td>
                            <td>
                                @if (auth('vendor_employee')?->user()?->employee_role_id  != $r['id'])
                                    <div class="btn--container justify-content-center">
                                        <a class="btn action-btn action-btn--edit"
                                            href="{{route('vendor.custom-role.update-role',$r['id'])}}" title="{{translate('Edit role')}}"><i class="tio-edit"></i>
                                        </a>
                                        <a class="btn action-btn action-btn--delete form-alert" href="javascript:"
                                           data-id="role-{{$r['id']}}" data-message="{{translate('Want to delete this role?')}}"
                                             title="{{translate('messages.Delete role')}}"><i class="tio-delete-outlined"></i>
                                        </a>
                                    </div>
                                    <form action="{{route('vendor.custom-role.delete',[$r['id']])}}"
                                            method="post" id="role-{{$r['id']}}">
                                        @csrf @method('delete')
                                    </form>
                                @else
                                    <div class="btn--container justify-content-center">
                                        {{ translate('N/A') }}
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if(count($roles) !== 0)
                <hr>
                @endif
                <div class="page-area">
                    <table>
                        <tfoot>
                        {!! $roles->links() !!}
                        </tfoot>
                    </table>
                </div>
                @if(count($roles) === 0)
                <div class="empty--data">
                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                    <h5>
                        {{translate('No data found')}}
                    </h5>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

