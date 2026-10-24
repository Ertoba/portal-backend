@extends('layouts.vendor.app')

@section('title',translate('Add new coupon'))

@section('content')
    @php($store_data = \App\CentralLogics\Helpers::get_store_data())
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title"><i class="tio-add-circle-outlined"></i> {{translate('messages.Add new coupon')}}</h1>
                    <p class="page-header-desc">{{ translate('A code customers type at checkout for money off one of your rentals.') }}</p>
                </div>
            </div>
        </div>
        @php($language = \App\CentralLogics\Helpers::get_business_settings('language', false) ?? null)
        <div class="card mb-3">
            <div class="card-body">
                <form action="" method="post">
                    @csrf
                    <div class="row">
                        <div class="col-12">
                            @if ($language)
                                <ul class="nav nav-tabs mb-3 border-0">
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
                                        <label class="input-label"
                                               for="default_title">{{ translate('messages.Title') }}
                                            ({{ translate('Default') }})
                                        </label>
                                        <input type="text" name="title[]" id="default_title"
                                               class="form-control" placeholder="{{ translate('messages.New coupon') }}"
                                               required
                                        >
                                    </div>
                                    <input type="hidden" name="lang[]" value="default">
                                </div>
                                @foreach (json_decode($language) as $lang)
                                    <div class="d-none lang_form"
                                         id="{{ $lang }}-form">
                                        <div class="form-group">
                                            <label class="input-label"
                                                   for="{{ $lang }}_title">{{ translate('messages.Title') }}
                                                ({{ strtoupper($lang) }})
                                            </label>
                                            <input type="text" name="title[]" id="{{ $lang }}_title"
                                                   class="form-control" placeholder="{{ translate('messages.New coupon') }}"
                                            >
                                        </div>
                                        <input type="hidden" name="lang[]" value="{{ $lang }}">
                                    </div>
                                @endforeach
                            @else
                                <div id="default-form">
                                    <div class="form-group">
                                        <label class="input-label"
                                               for="title">{{ translate('messages.Title') }} ({{ translate('Default') }})</label>
                                        <input type="text" id="title" name="title[]" class="form-control"
                                               placeholder="{{ translate('messages.New coupon') }}">
                                    </div>
                                    <input type="hidden" name="lang[]" value="default">
                                </div>
                            @endif
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="form-group">
                                <label class="input-label" for="coupon_type">{{translate('Coupon type')}}</label>
                                <select id="coupon_type" name="coupon_type" class="form-control" >
                                    <option value="default">{{translate('Default')}}</option>
                                </select>
                            </div>
                        </div>



                        <div class="col-lg-3 col-sm-6">
                            <div class="form-group">
                                <label class="input-label" for="coupon_code">{{translate('messages.code')}}</label>
                                <input id="coupon_code" type="text" name="code" class="form-control"
                                       placeholder="{{\Illuminate\Support\Str::random(8)}}" required maxlength="100">
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="form-group">
                                <label class="input-label" for="coupon_limit">{{translate('Limit for same user')}}</label>
                                <input type="number" name="limit" id="coupon_limit" class="form-control" placeholder="{{ translate('messages.Ex') }}: 10" max="100">
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="form-group">
                                <label class="input-label" for="date_from">{{translate('Start date')}}</label>
                                <input type="date" name="start_date" class="form-control" id="date_from" required>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="form-group">
                                <label class="input-label" for="date_to">{{translate('messages.Expire date')}}</label>
                                <input type="date" name="expire_date" class="form-control" id="date_to" required>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="form-group">
                                <label class="input-label" for="min_purchase">{{translate('Min trip amount')}}</label>
                                <input id="min_purchase" type="number" step="0.01" name="min_purchase" value="0" min="0" max="999999999999.99" class="form-control"
                                       placeholder="100">
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="form-group">
                                <label class="input-label" for="discount_type">{{translate('Discount type')}}</label>
                                <select name="discount_type" class="form-control" id="discount_type">
                                    <option value="amount">
                                        {{ translate('Amount').' ('.\App\CentralLogics\Helpers::currency_symbol().')'  }}
                                    </option>
                                    <option value="percent" > {{ translate('Percent').' (%)' }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="form-group">
                                <label class="input-label" for="discount">{{translate('Discount')}} </label>
                                <input type="number" step="0.01" min="1" max="999999999999.99" name="discount" id="discount" class="form-control" placeholder="{{ translate('messages.Ex') }}: 100" required>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="form-group">
                                <label class="input-label" for="max_discount">{{translate('messages.Max discount')}}</label>
                                <input type="number" step="0.01" min="0" value="0" max="999999999999.99" name="max_discount" id="max_discount" class="form-control" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="btn--container justify-content-end">
                        <button id="reset_btn" type="button" class="btn btn--reset">{{translate('messages.Reset')}}</button>
                        <button type="submit" class="btn btn--primary">{{translate('messages.Submit')}}</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header py-2">
                <div class="search--button-wrapper">
                    <h5 class="card-title">{{translate('Coupon list')}}<span class="badge badge-soft-dark ml-2" id="itemCount">{{$coupons->total()}}</span></h5>
                    <form method="get">

                        <div class="input--group input-group input-group-merge input-group-flush">
                            <input id="datatableSearch" type="search" value="{{request()?->search ?? ''}}" name="search" class="form-control" placeholder="{{ translate('messages.Ex') . ' : ' . translate('messages.Search by title or code') }}" aria-label="{{translate('Search')}}">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="table-responsive datatable-custom" id="table-div">
                <table id="columnSearchDatatable"
                       class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                       data-hs-datatables-options='{
                        "order": [],
                        "orderCellsTop": true,

                        "entries": "#datatableEntries",
                        "isResponsive": false,
                        "isShowPaging": false,
                        "paging":false,
                        }'>
                    <thead class="thead-light">
                    <tr>
                        <th>{{ translate('messages.SL') }}</th>
                        <th>{{translate('messages.Title')}}</th>
                        <th>{{translate('messages.code')}}</th>
                        <th>{{translate('Type')}}</th>
                        <th>{{translate('messages.Total uses')}}</th>
                        <th>{{translate('Min trip amount')}}</th>
                        <th>{{translate('messages.Max discount')}}</th>
                        <th>
                            <div class="text-center">
                                {{translate('Discount')}}
                            </div>
                        </th>
                        <th>{{translate('Discount type')}}</th>
                        <th>{{translate('Start date')}}</th>
                        <th>{{translate('messages.Expire date')}}</th>

                        <th>{{translate('messages.Status')}}</th>
                        <th class="text-center">{{translate('messages.Action')}}</th>
                    </tr>
                    </thead>

                    <tbody id="set-rows">
                    @foreach($coupons as $key=>$coupon)
                        <tr>
                            <td>{{$key+$coupons->firstItem()}}</td>
                            <td>
                            <span class="d-block font-size-sm text-body">
                                {{Str::limit($coupon['title'],15,'...')}}
                            </span>
                            </td>
                            <td>{{$coupon['code']}}</td>
                            <td>{{translate('messages.'.$coupon->coupon_type)}}</td>
                            <td>{{$coupon->total_uses}}</td>
                            <td>
                                <div class="text-right mw-87px">
                                    {{\App\CentralLogics\Helpers::format_currency($coupon['min_purchase'])}}
                                </div>
                            </td>
                            <td>
                                <div class="text-right mw-87px">
                                    {{\App\CentralLogics\Helpers::format_currency($coupon['max_discount'])}}
                                </div>
                            </td>
                            <td>
                                <div class="text-center">
                                    {{$coupon['discount']}}
                                </div>
                            </td>
                            @if ($coupon['discount_type'] == 'percent')
                                <td>{{ translate('Percent')}}</td>
                            @elseif ($coupon['discount_type'] == 'amount')
                                <td>{{ translate('Amount')}}</td>
                            @else
                                <td>{{$coupon['discount_type']}}</td>
                            @endif

                            <td>{{$coupon['start_date']}}</td>
                            <td>{{$coupon['expire_date']}}</td>

                            <td>
                                <label class="toggle-switch toggle-switch-sm" for="couponCheckbox{{$coupon->id}}">
                                    <input type="checkbox"
                                           data-url="{{route('vendor.rental_coupon.status',[$coupon['id']])}}"
                                           class="toggle-switch-input redirect-url" id="couponCheckbox{{$coupon->id}}" {{$coupon->status?'checked':''}}>
                                    <span class="toggle-switch-label">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>
                            </td>
                            <td>
                                <div class="btn--container justify-content-center">
                                    <a class="btn btn-sm action-btn action-btn--edit" href="{{route('vendor.rental_coupon.edit',[$coupon['id']])}}" title="{{translate('Edit coupon')}}"><i class="tio-edit"></i>
                                    </a>
                                    <a class="btn btn-sm action-btn action-btn--delete form-alert"
                                       data-id="coupon-{{$coupon['id']}}"
                                       data-message="{{ translate('Want to delete this coupon?') }}"
                                       href="javascript:" title="{{translate('messages.Delete coupon')}}"><i class="tio-delete-outlined"></i>
                                    </a>
                                    <form action="{{route('vendor.rental_coupon.delete',[$coupon['id']])}}"
                                          method="post" id="coupon-{{$coupon['id']}}">
                                        @csrf @method('delete')
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if(count($coupons) === 0)
                    <div class="empty--data">
                        <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                        <h5>
                            {{translate('No data found')}}
                        </h5>
                    </div>
                @endif
                <div class="page-area px-4 pb-3">
                    <div class="d-flex align-items-center justify-content-end">
                        <div>
                            {!! $coupons->links() !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" id="min-purchase-toast" value="{{ translate('messages.Discount amount cannot be greater than minimum purchase amount') }}">
@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin/js/view-pages/vendor-coupon.js')}}"></script>
@endpush
