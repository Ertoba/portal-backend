@extends('layouts.admin.app')

@section('title',translate('Provider List'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        @php
            $verified_seller_badge = \App\CentralLogics\Helpers::get_business_settings('verified_seller_badge');
            $recommended_store_list = $verified_seller_badge ? \App\CentralLogics\Helpers::get_verified_seller_eligible_providers(countOnly: false , moduleId: config('module.current_module_id')) : [];
            $recommended_stores = count($recommended_store_list);
        @endphp
        <!-- Page Header -->
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <div>
                <h1 class="page-header-title">
                    <span class="page-header-icon">
                        <img src="{{ asset('public/assets/admin/img/rental/provider.png') }}" class="w--22" alt="">
                    </span>
                    <span>
                        {{translate('Provider')}}
                    </span>
                </h1>
                <div class="page-header-select-wrapper">
                </div>
            </div>
            @if ($recommended_stores??0 > 0)
                <div class="d-flex align-items-center gap-2 bg-success bg-opacity-10 flex-wrap rounded py-1 px-2">
                    <div class="fs-12 mb-0 d-flex align-items-center gap-2">
                        <img src="{{ asset('public/assets/admin/img/badge-rounded-circle.svg') }}" alt="" class="rounded-0 w-auto h-auto object-contain">
                        {{ translate('Recommended') }} <strong class="title-clr">{{$recommended_stores}}</strong> {{ translate('providers for verification') }}
                    </div>

                    <button class="btn btn--primary bg-theme2 border-0 py-1 px-3 fs-12 fw-500 mb-0 offcanvas-trigger  " data-target="#offcanvas__customBtn3" data-id="0"  data-url="" type="button">
                        {{ translate('messages.View') }}
                    </button>
                </div>
            @endif
        </div>
        <!-- End Page Header -->


        <!-- Provider Card Wrapper -->
        <div class="row g-3 mb-3">
            <div class="col-xl-3 col-sm-6">
                <div class="resturant-card card--bg-1">
                    @php
                        $total_store = \App\Models\Store::whereHas('vendor', function($query){
                            return $query->where('status', 1);
                        })->where('module_id', Config::get('module.current_module_id'))->count();
                        $total_store = isset($total_store) ? $total_store : 0;
                    @endphp
                    <h4 class="title">{{$total_store}}</h4>
                    <span class="subtitle">{{translate('messages.total_providers')}}</span>
                    <img class="resturant-icon" src="{{asset('/public/assets/admin/img/total_provider.png')}}" alt="store">
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="resturant-card card--bg-3">
                    @php
                        $active_stores = \App\Models\Store::whereHas('vendor', function($query){
                            return $query->where('status', 1);
                        })->where(['status'=>1])->where('module_id', Config::get('module.current_module_id'))->count();
                        $active_stores = isset($active_stores) ? $active_stores : 0;
                    @endphp
                    <h4 class="title">{{$active_stores}}</h4>
                    <span class="subtitle">{{translate('messages.active_providers')}}</span>
                    <img class="resturant-icon" src="{{asset('/public/assets/admin/img/active_provider.png')}}" alt="store">
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="resturant-card card--bg-4">
                    @php
                        $inactive_stores = \App\Models\Store::whereHas('vendor', function($query){
                            return $query->where('status', 1);
                        })->where(['status'=>0])->where('module_id', Config::get('module.current_module_id'))->count();
                        $inactive_stores = isset($inactive_stores) ? $inactive_stores : 0;
                    @endphp
                    <h4 class="title">{{$inactive_stores}}</h4>
                    <span class="subtitle">{{translate('messages.inactive_providers')}}</span>
                    <img class="resturant-icon" src="{{asset('/public/assets/admin/img/inactive_providers.png')}}" alt="store">
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="resturant-card card--bg-2">
                    @php
                        $data = \App\Models\Store::whereHas('vendor', function($query){
                            return $query->where('status', 1);
                        })->where('created_at', '>=', now()->subDays(30)->toDateTimeString())->where('module_id', Config::get('module.current_module_id'))->count();
                    @endphp
                    <h4 class="title">{{$data}}</h4>
                    <span class="subtitle">{{translate('messages.newly_joined_providers')}}</span>
                    <img class="resturant-icon" src="{{asset('/public/assets/admin/img/new_provider.png')}}" alt="{{translate('provider')}}">
                </div>
            </div>
        </div>

        <ul class="transaction--information text-uppercase">
            <li class="text--info">
                <i class="tio-document-text-outlined"></i>
                <div>
                    <span>{{translate('messages.total_transactions')}}</span> <strong>{{$totalTransaction}}</strong>
                </div>
            </li>
            <li class="seperator"></li>
            <li class="text--success">
                <i class="tio-checkmark-circle-outlined success--icon"></i>
                <div>
                    <span>{{translate('messages.commission_earned')}}</span> <strong>{{\App\CentralLogics\Helpers::format_currency($comissionEarned)}}</strong>
                </div>
            </li>
            <li class="seperator"></li>
            <li class="text--danger">
                <i class="tio-atm"></i>
                <div>
                    <span>{{translate('messages.total_provider_withdraws')}}</span> <strong>{{\App\CentralLogics\Helpers::format_currency($storeWithdraws)}}</strong>
                </div>
            </li>
        </ul>

        <!-- Card -->
        <div class="card">
            <!-- Header -->
            <div class="card-header py-2">
                <div class="search--button-wrapper">
                    <h5 class="card-title">{{translate('messages.providers_list')}} <span class="badge badge-soft-dark ml-2" id="itemCount">{{$stores->total()}}</span></h5>

                @if(!auth('admin')?->user()?->zone_id)
                <div class="select-item min--280">
                    <select name="zone_id" class="form-control js-select2-custom set-filter" data-url="{{url()->full()}}" data-filter="zone_id">
                        <option value="" {{!request('zone_id')?'selected':''}}>{{ translate('messages.All_Zones') }}</option>
                        @foreach(\App\Models\Zone::orderBy('name')->get() as $z)
                            <option
                                value="{{$z['id']}}" {{isset($zone) && $zone->id == $z['id']?'selected':''}}>
                                {{$z['name']}}
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif
                    <form class="search-form">
                                    <!-- Search -->
                        <div class="input-group input--group">
                            <input id="datatableSearch_" type="search" value="{{ request()?->search ?? null }}" name="search" class="form-control"
                                    placeholder="{{translate('ex_:_Search_provider_Name')}}" aria-label="{{translate('messages.search')}}" >
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>

                        </div>
                        <!-- End Search -->
                    </form>
                    @if(request()->input('search'))
                    <button type="reset" class="btn btn--primary ml-2 location-reload-to-base" data-url="{{url()->full()}}">{{translate('messages.reset')}}</button>
                    @endif


                    <!-- Unfold -->
                    <div class="hs-unfold mr-2">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40" href="javascript:;"
                            data-hs-unfold-options='{
                                    "target": "#usersExportDropdown",
                                    "type": "css-animation"
                                }'>
                            <i class="tio-download-to mr-1"></i> {{ translate('messages.export') }}
                        </a>

                        <div id="usersExportDropdown"
                            class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">

                            <span class="dropdown-header">{{ translate('messages.download_options') }}</span>
                            <a id="export-excel" class="dropdown-item" href="{{route('admin.store.export', ['is_rental'=>1,'type'=>'excel',request()->getQueryString()])}}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                    alt="Image Description">
                                {{ translate('messages.excel') }}
                            </a>
                            <a id="export-csv" class="dropdown-item" href="{{route('admin.store.export', ['type'=>'csv',request()->getQueryString()])}}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                    alt="Image Description">
                                {{ translate('messages.csv') }}
                            </a>

                        </div>
                    </div>
                    <a href="{{  route('admin.rental.provider.create') }}" type="button" target="_blank" class="btn btn--primary ml-2 location-reload-to-base" rel="noopener noreferrer">{{translate('messages.New_Provider')}}</a>


                    <!-- End Unfold -->
                </div>
            </div>
            <!-- End Header -->

            <!-- Table -->
            <div class="table-responsive datatable-custom">
                <table id="columnSearchDatatable"
                        class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                        data-hs-datatables-options='{
                            "order": [],
                            "orderCellsTop": true,
                            "paging":false

                        }'>
                    <thead class="thead-light">
                    <tr>
                        <th class="border-0">{{translate('sl')}}</th>
                        <th class="border-0">{{translate('messages.provider')}}</th>
                        <th class="border-0">{{translate('messages.owner_info')}}</th>
                        <th class="border-0">{{translate('messages.Total_vehicle')}}</th>
                        <th class="text-uppercase border-0">{{translate('messages.total_trip')}}</th>
                        <th class="text-uppercase border-0">{{translate('messages.status')}}</th>
                        <th class="text-center border-0">{{translate('messages.action')}}</th>
                    </tr>
                    </thead>

                    <tbody id="set-rows">
                    @foreach($stores as $key=>$store)
                        <tr>
                            <td>{{$key+$stores->firstItem()}}</td>
                            <td>
                                <div>
                                    <a href="{{route('admin.rental.provider.details', $store->id)}}" class="table-rest-info" alt="{{translate('view provider')}}">
                                    <img class="img--60 circle onerror-image" data-onerror-image="{{asset('public/assets/admin/img/160x160/img1.jpg')}}"

                                            src="{{ $store['logo_full_url'] ?? asset('public/assets/admin/img/160x160/img1.jpg') }}"

                                            >
                                        <div class="info max-w-200px"><div title="{{ $store?->name }}" class="text--title">
                                            {{Str::limit($store->name,20,'...')}}
                                            @include('partials._verified_store_badge', ['store' => $store])
                                        </div>
                                            <div class="font-light">
                                                {{translate('messages.id')}}:{{$store->id}}
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </td>

                            <td>
                                <span title="{{ $store?->vendor?->f_name.' '.$store?->vendor?->l_name }}" class="d-block font-size-sm text-body">
                                    {{Str::limit($store->vendor->f_name.' '.$store->vendor->l_name,20,'...')}}
                                </span>
                                <div>
                                    <a href="tel:{{ $store['phone'] }}">
                                        {{$store['phone']}}
                                    </a>
                                </div>
                            </td>
                            <td>
                                {{$store->vehicles->count()}}
                            </td>
                            <td>
                                <span class="form-label-secondary cursor-pointer" data-toggle="tooltip" data-placement="bottom" data-html="true"

                                      data-original-title="<div class='text-left p-3'>
                                <div class='d-flex gap-2'><div class='w--100px'>{{translate('Complete')}}</div> : {{ $store->trips()->Completed()->count() }}</div>
                              <div class='d-flex gap-2'><div class='w--100px'>{{translate('Ongoing')}}</div> : {{ $store->trips()->Ongoing()->count() }}</div>
                              <div class='d-flex gap-2'><div class='w--100px'>{{translate('Canceled')}}</div> : {{ $store->trips()->Canceled()->count() }}</div>
                              <div class='text-danger font-bold d-flex gap-2'><div class='w--100px'>{{translate('Cancelation Rate')}}</div> : {{ number_format($store->trips()->Canceled()->count() > 0 ? ($store->trips()->Canceled()->count() / $store->trips->count()) * 100 : 0) }}%</div>
                            </div>">
                                      {{ $store->trips->count() }} <i class="tio-info"></i>
                                </span>
                            </td>

                            <td>
                                @if(isset($store->vendor->status))
                                    @if($store->vendor->status)
                                    <label class="toggle-switch toggle-switch-sm" for="stocksCheckbox{{$store->id}}">
                                        <input type="checkbox" data-url="{{route('admin.rental.provider.status-by-store',[$store->id])}}" data-message="{{translate('messages.you_want_to_change_this_provider_status')}}" class="toggle-switch-input status_change_alert" id="stocksCheckbox{{$store->id}}" {{$store->status?'checked':''}}>
                                        <span class="toggle-switch-label">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </label>
                                    @else
                                    <span class="badge badge-soft-danger">{{translate('messages.denied')}}</span>
                                    @endif
                                @else
                                    <span class="badge badge-soft-danger">{{translate('messages.pending')}}</span>
                                @endif
                            </td>

                            <td>
                                <div class="btn--container justify-content-center">
                                    <a class="btn action-btn btn--warning btn-outline-warning"
                                            href="{{route('admin.rental.provider.details', $store->id)}}"
                                            title="{{ translate('messages.details') }}"><i
                                                class="tio-visible-outlined"></i>
                                        </a>
                                    <a class="btn action-btn btn--primary btn-outline-primary"
                                    href="{{route('admin.rental.provider.edit-basic-setup',[$store['id']])}}" title="{{translate('messages.edit_provider')}}"><i class="tio-edit"></i>
                                    </a>
                                    <a class="btn action-btn btn--danger btn-outline-danger form-alert" href="javascript:"
                                    data-id="vendor-{{$store['id']}}" data-message="{{translate('You want to remove this provider')}}" title="{{translate('messages.delete_provider')}}"><i class="tio-delete-outlined"></i>
                                    </a>
                                    <form action="{{route('admin.store.delete',[$store['id']])}}" method="post" id="vendor-{{$store['id']}}">
                                        @csrf @method('delete')
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

            </div>
                @if(count($stores) !== 0)
                <hr>
                @endif
                <div class="page-area">
                    {!! $stores->withQueryString()->links() !!}
                </div>
                @if(count($stores) === 0)
                <div class="empty--data">
                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                    <h5>
                        {{translate('no_data_found')}}
                    </h5>
                </div>
                @endif
            <!-- End Table -->
        </div>
        <!-- End Card -->
    </div>

    <div id="offcanvas__customBtn3" class="custom-offcanvas d-flex flex-column justify-content-between">
        <div class="d-flex flex-column flex-grow-1">
            <div class="custom-offcanvas-header bg-white d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
                <h3 class="mb-0 fs-18 text-title fw-semibold">{{ translate('Verification Recommendations') }}</h3>
                <button type="button"
                    class="btn-close w-25px h-25px border rounded-circle d-center bg-white text-dark offcanvas-close fz-15px p-0"
                    aria-label="Close">&times;</button>
            </div>
            <div class="custom-offcanvas-body p-4 d-flex flex-column gap-3">
                <p class="fs-14 lh-base color-5d6167 mb-0">
                    {{ translate('We have detected that') }} <strong>{{ number_format($recommended_stores) }}</strong>
                    {{ translate('providers have GOOD performance based on their overall activity. You can give them a Verified badge, which will appear next to the provider name to build customer trust.') }}
                </p>

                <div class="bg--secondary rounded p-4">
                    <h4 class="mb-3 fs-18 text-title fw-semibold">{{ translate('They qualified the criteria') }}
                        
                    </h4>

                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex align-items-start gap-2">
                            <span class="d-center flex-shrink-0 mt-1 w-18px h-18px rounded-circle bg-success">
                                <i class="tio-done text-white fz-10px"></i>
                            </span>
                            <span class="fs-14 color-5d6167">
                                <strong class="text-title">{{ config('verified_seller.providers.minimum_total_trips', 10) }}+</strong> {{ translate('Trips completed') }}
                            </span>
                        </div>
                        <div class="d-flex align-items-start gap-2">
                            <span class="d-center flex-shrink-0 mt-1 w-18px h-18px rounded-circle bg-success">
                                <i class="tio-done text-white fz-10px"></i>
                            </span>
                            <span class="fs-14 color-5d6167">
                                {{ translate('Trip completion rate above') }} <strong class="text-title">{{ config('verified_seller.providers.minimum_success_rate', 40) }}%</strong>
                            </span>
                        </div>
                        <div class="d-flex align-items-start gap-2">
                            <span class="d-center flex-shrink-0 mt-1 w-18px h-18px rounded-circle bg-success">
                                <i class="tio-done text-white fz-10px"></i>
                            </span>
                            <span class="fs-14 color-5d6167">
                                <strong class="text-title">{{ config('verified_seller.providers.minimum_account_age_months', 3) }}+</strong> {{ translate('months since account creation') }}
                            </span>
                        </div>
                        <div class="d-flex align-items-start gap-2">
                            <span class="d-center flex-shrink-0 mt-1 w-18px h-18px rounded-circle bg-success">
                                <i class="tio-done text-white fz-10px"></i>
                            </span>
                            <span class="fs-14 color-5d6167">
                                <strong class="text-title">{{ translate('Positive') }}</strong> {{ translate('customer feedback trend') }}
                            </span>
                        </div>
                        <div class="d-flex align-items-start gap-2">
                            <span class="d-center flex-shrink-0 mt-1 w-18px h-18px rounded-circle bg-success">
                                <i class="tio-done text-white fz-10px"></i>
                            </span>
                            <span class="fs-14 color-5d6167">
                                <strong class="text-title">{{ config('verified_seller.providers.minimum_avg_rating', 2) }}+</strong> {{ translate('Rating') }}/5.00
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="align-items-center bg-white bottom-0 d-flex gap-3 justify-content-center mt-auto offcanvas-footer p-3 position-sticky border-top">
            <button type="button" id="open-eligible-store-list" class="btn w-100 btn--reset h--40px">{{ translate('View List') }}</button>
            <button type="button" id="verify-all-summary" class="btn w-100 btn--primary h--40px">{{ translate('Verify All') }}</button>
        </div>
    </div>
    <div id="offcanvas__eligibleStores" class="custom-offcanvas d-flex flex-column justify-content-between">
        <div class="d-flex flex-column flex-grow-1">
            <div class="custom-offcanvas-header bg-white d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <h3 class="mb-0 fs-18 text-title fw-semibold d-flex align-items-center gap-2">
                        <span id="back-to-verification" class="d-inline-flex align-items-center justify-content-center w-20px h-20px rounded-circle bg-light">
                            <i class="tio-arrow-backward fs-12"></i>
                        </span>
                        {{ translate('Providers List') }}
                    </h3>
                    <span class="badge badge-soft-dark">{{ $recommended_stores }}</span>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <label class="d-flex align-items-center gap-2 mb-0 cursor-pointer">
                        <input type="checkbox" id="select-all-eligible-stores" class="form-check-input mt-0">
                        <span class="fs-14 text-title">{{ translate('Select All') }}</span>
                    </label>
                    <button type="button" class="btn-close w-25px h-25px border rounded-circle d-center bg-white text-dark offcanvas-close fz-15px p-0" aria-label="Close">&times;</button>
                </div>
            </div>
            <div class="custom-offcanvas-body p-3 p-md-4 d-flex flex-column gap-3" id="eligible-store-list">
                @foreach ($recommended_store_list as $store)
                    <div class="bg-white rounded-10 p-3 eligible-store-item">
                        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                            <div class="d-flex align-items-center gap-3">
                                <div class="flex-shrink-0">
                                    <img class="w-50px h-50px rounded-circle border object-fit-cover onerror-image"
                                         data-onerror-image="{{ asset('public/assets/admin/img/160x160/img1.jpg') }}"
                                         src="{{$store['logo_full_url']}}">
                                </div>
                                <div>
                                    <h4 class="mb-1 fs-16 text-title fw-semibold">{{ $store['name'] }}</h4>
                                    <div class="d-flex flex-wrap gap-2 fs-13 color-6c757d">
                                        <span>{{ translate('messages.Rating') }} {{ number_format($store['avg_rating'], 1) }}/5</span>
                                        <span>{{ translate('messages.Trips') }} {{ number_format($store['total_trips']) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="eligible-action-wrapper d-flex align-items-center justify-content-end min-w-110px">
                                <a href="{{ route('admin.store.verified-seller', [$store['id']]) }}" class="btn btn--primary btn-sm min-w-110px eligible-give-btn">
                                    <i class="tio-done mr-1"></i>{{ translate('Give Badge') }}
                                </a>
                                <label class="form-check m-0 d-none eligible-store-check">
                                    <input type="checkbox" class="form-check-input mt-0" checked disabled>
                                </label>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div id="eligible-store-footer" class=" d-none">
            <div class="align-items-center bg-white bottom-0 d-flex gap-3 justify-content-center mt-auto offcanvas-footer p-3 position-sticky border-top">
                <div class="d-flex gap-3 w-100 justify-content-center">
                    <button type="button" class="btn w-100 btn--reset offcanvas-close h--40px">{{ translate('Cancel') }}</button>
                    <button type="button" id="verify-all-stores" class="btn w-100 btn--primary h--40px">{{ translate('Verify All') }}</button>
                </div>
            </div>
        </div>
    </div>
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>
    <div class="modal shedule-modal fade" id="verify-all-modal" tabindex="-1" aria-labelledby="verifyAllModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content pb-2 max-w-500">
                <div class="modal-header">
                    <button type="button"
                        class="close bg-modal-btn w-30px h-30 rounded-circle position-absolute right-0 top-0 m-2 z-2"
                        data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <img src="{{ asset('public/assets/admin/img/badge-big.png') }}" alt="icon" class="mb-3">
                        <h3 class="mb-2">{{ translate('Verify all qualified providers?') }}</h3>
                        <p class="mb-0">{{ translate('This will give a verified badge to every provider that matches the verification criteria.') }}</p>
                    </div>
                </div>
                <div class="modal-footer justify-content-center border-0 pt-0 gap-2">
                    <button type="button" class="btn min-w-120px btn--reset" data-dismiss="modal">{{ translate('messages.Cancel') }}</button>
                    <a href="{{ route('admin.store.verified-seller-all') }}" class="btn min-w-120px btn--primary">{{ translate('messages.Yes') }}</a>
                </div>
            </div>
        </div>
    </div>


    <div class="d-none" id="data-set"
        data-translate-are-you-sure="{{ translate('Are_you_sure?') }}"
        data-translate-no="{{ translate('no') }}"
        data-translate-yes="{{ translate('yes') }}"
    ></div>


@endsection

@push('script_2')
<script src="{{asset('Modules/Rental/public/assets/js/admin/view-pages/provider-list.js')}}"></script>
@endpush
