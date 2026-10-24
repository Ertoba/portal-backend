@extends('layouts.admin.app')

@section('title',$store->name."'s ".translate('messages.Transactions'))

@push('css_or_js')
    <link href="{{asset('public/assets/admin/css/croppie.css')}}" rel="stylesheet">
@endpush

@section('content')
<div class="content container-fluid">
    @include('rental::admin.provider.details.partials._header',['store'=>$store])
    @php
        $vendor = $store?->module_type;
        $title = $vendor == 'rental' ? 'Provider' : 'Store';
        $orderOrTrip = $vendor == 'rental' ? 'trip' : 'order';
    @endphp
    <div class="card">
        <div class="card-header border-0 py-2">
            <div class="search--button-wrapper">
                <ul class="nav nav-tabs mr-auto transaction--table-nav">
                    <li class="nav-item">
                        <a class="nav-link text-capitalize {{$sub_tab=='cash'?'active':''}}" href="{{route('admin.rental.provider.details', ['id'=>$store->id, 'tab'=> 'transaction', 'sub_tab'=>'cash'])}}"  aria-disabled="true">{{translate('Cash transaction')}} ({{$transactionCounts['cash']}})</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-capitalize {{$sub_tab=='digital'?'active':''}}" href="{{route('admin.rental.provider.details', ['id'=>$store->id, 'tab'=> 'transaction', 'sub_tab'=>'digital'])}}"  aria-disabled="true">{{translate($orderOrTrip.'_transactions')}} ({{$transactionCounts['digital']}})</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-capitalize {{$sub_tab=='withdraw'?'active':''}}" href="{{route('admin.rental.provider.details', ['id'=>$store->id, 'tab'=> 'transaction', 'sub_tab'=>'withdraw'])}}"  aria-disabled="true">{{translate('Withdraw transactions')}} ({{$transactionCounts['withdraw']}})</a>
                    </li>
                </ul>
                <div class="hs-unfold mr-2">
                    <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle h--40px" href="javascript:;"
                        data-hs-unfold-options='{
                            "target": "#usersExportDropdown",
                            "type": "css-animation"
                        }'>
                        <i class="tio-download-to mr-1"></i> {{translate('messages.Export')}}
                    </a>

                    <div id="usersExportDropdown"
                            class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                        <span class="dropdown-header">{{translate('messages.Download options')}}</span>
                        @if($sub_tab=='cash')
                        <a id="export-excel" class="dropdown-item" href="{{route('admin.store.cash_export', ['provider_id'=>request()->id, 'type'=>'excel', 'store_id'=>$store->id]) }}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{asset('public/assets/admin')}}/svg/components/excel.svg"
                                    alt="Image Description">
                            Excel
                        </a>
                        <a id="export-csv" class="dropdown-item" href="{{route('admin.store.cash_export', ['provider_id'=>request()->id, 'type'=>'csv', 'store_id'=>$store->id])}}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{asset('public/assets/admin')}}/svg/components/placeholder-csv-format.svg"
                                    alt="Image Description">
                            .csv
                        </a>
                        @elseif ($sub_tab=='digital')
                        <a id="export-excel" class="dropdown-item" href="{{route('admin.store.order_export', ['provider_id'=>request()->id, 'type'=>'excel', 'store_id'=>$store->id])}}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{asset('public/assets/admin')}}/svg/components/excel.svg"
                                    alt="Image Description">
                            Excel
                        </a>
                        <a id="export-csv" class="dropdown-item" href="{{route('admin.store.order_export', ['provider_id'=>request()->id, 'type'=>'csv', 'store_id'=>$store->id])}}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{asset('public/assets/admin')}}/svg/components/placeholder-csv-format.svg"
                                    alt="Image Description">
                            .csv
                        </a>
                        @elseif ($sub_tab=='withdraw')
                        <a id="export-excel" class="dropdown-item" href="{{route('admin.store.withdraw_trans_export', ['provider_id'=>request()->id, 'type'=>'excel', 'store_id'=>$store->id])}}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{asset('public/assets/admin')}}/svg/components/excel.svg"
                                    alt="Image Description">
                            Excel
                        </a>
                        <a id="export-csv" class="dropdown-item" href="{{route('admin.store.withdraw_trans_export', ['provider_id'=>request()->id, 'type'=>'csv', 'store_id'=>$store->id])}}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{asset('public/assets/admin')}}/svg/components/placeholder-csv-format.svg"
                                    alt="Image Description">
                            .csv
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">

        @if($sub_tab == 'cash')
            @include('rental::admin.provider.details.partials.cash_transaction')
        @elseif ($sub_tab == 'digital')
            @include('rental::admin.provider.details.partials.digital_transaction')
        @elseif ($sub_tab == 'withdraw')
            @include('rental::admin.provider.details.partials.withdraw_transaction')
        @endif
        </div>
    </div>
</div>
@endsection

@push('script_2')
    <script src="{{asset('Modules/Rental/public/assets/js/admin/view-pages/provider-transaction.js')}}"></script>
@endpush
