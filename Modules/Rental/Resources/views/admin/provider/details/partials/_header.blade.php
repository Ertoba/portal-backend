    <!-- Page Header -->
    @php
        $verified_seller_badge = \App\CentralLogics\Helpers::get_business_settings('verified_seller_badge');
    @endphp
    <div class="page-header pb-0">
        <div class="page-header">
            <div class="d-flex justify-content-between flex-wrap gap-3">
                <div>
                    <h1 class="page-header-title text-break">
                        <span class="page-header-icon">
                            <img src="{{ asset('public/assets/admin/img/store.png') }}" class="w--22" alt="">
                        </span>
                        <span>{{ translate('messages.Provider_Details') }}
                    </h1></span>
                    </h1>
                </div>
                @if(!request()->tab)
                    <div class="d-flex align-items-start flex-wrap gap-2">
                        @if ($store->status == 1 && $store->vendor->status == 1 && $verified_seller_badge == 1)
                            @php
                                $verified_seller = $store->storeConfig?->verified_seller;
                            @endphp
                            <a href="javascript:"
                               class="btn fs-14 h--45px d-center  {{ $verified_seller ? 'btn-secondary bg--EDEDED border-0 title-clr' : 'btn-primary' }}"
                               data-toggle="modal"
                               data-target="{{ $verified_seller ? '#remove-badge-btn' : '#badge-modal-btn' }}">
                                {{ $verified_seller ? translate('messages.Removed Verified badge') : translate('messages.Give Verified Badge') }}
                            </a>
                        @endif
                        <a href="javascript:" class="btn btn--reset d-flex justify-content-between align-items-center gap-4 lh--1 h--45px">
                            {{ translate('messages.status') }}
                            <label class="toggle-switch toggle-switch-sm" for="stocksCheckbox{{$store->id}}">
                                <input type="checkbox" data-url="{{route('admin.store.status',[$store['id'],$store->status?0:1])}}"
                                       class="toggle-switch-input redirect-url" id="stocksCheckbox{{$store->id}}" {{$store->status?'checked':''}}>
                                <span class="toggle-switch-label">
                                    <span class="toggle-switch-indicator"></span>
                                </span>
                            </label>
                        </a>
                        <a href="{{ route('admin.rental.provider.edit-basic-setup', $store->id)}}" class="btn h--45px d-center btn--primary font-weight-bold float-right mr-2 mb-0">
                            <i class="tio-edit"></i> {{ translate('messages.edit_provider') }}
                        </a>
                    </div>
                @endif
            </div>
        </div>
        @if($store->vendor->status)
        <!-- Nav Scroller -->
        <div class="js-nav-scroller hs-nav-scroller-horizontal">
            <span class="hs-nav-scroller-arrow-prev d-none">
                <a class="hs-nav-scroller-arrow-link" href="javascript:;">
                    <i class="tio-chevron-left"></i>
            </a>
            </span>

            <span class="hs-nav-scroller-arrow-next d-none">
                <a class="hs-nav-scroller-arrow-link" href="javascript:;">
                    <i class="tio-chevron-right"></i>
                </a>
            </span>

            <!-- Nav -->
            <ul class="nav nav-tabs page-header-tabs mb-2">
                <li class="nav-item">
                    <a class="nav-link {{request('tab')==null?'active':''}}" href="{{route('admin.rental.provider.details', $store->id)}}">{{translate('messages.overview')}}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{request('tab')=='order'?'active':''}}" href="{{route('admin.rental.provider.details', ['id'=>$store->id, 'tab'=> 'order'])}}"  aria-disabled="true">{{translate('messages.Trip List')}}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{request('tab')=='driver'?'active':''}}" href="{{route('admin.rental.provider.details', ['id'=>$store->id, 'tab'=> 'driver'])}}"  aria-disabled="true">{{translate('messages.driver list')}}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{request('tab')=='vehicle'?'active':''}}" href="{{route('admin.rental.provider.details', ['id'=>$store->id, 'tab'=> 'vehicle'])}}"  aria-disabled="true">{{translate('messages.Vehicles')}}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{request('tab')=='reviews'?'active':''}}" href="{{route('admin.rental.provider.details', ['id'=>$store->id, 'tab'=> 'reviews'])}}"  aria-disabled="true">{{translate('messages.reviews')}}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{request('tab')=='discount'?'active':''}}" href="{{route('admin.rental.provider.details', ['id'=>$store->id, 'tab'=> 'discount'])}}"  aria-disabled="true">{{translate('messages.discounts')}}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{request('tab')=='transaction'?'active':''}}" href="{{route('admin.rental.provider.details', ['id'=>$store->id, 'tab'=> 'transaction'])}}"  aria-disabled="true">{{translate('messages.transactions')}}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{request('tab')=='settings'?'active':''}}" href="{{route('admin.rental.provider.details', ['id'=>$store->id, 'tab'=> 'settings'])}}"  aria-disabled="true">{{translate('messages.settings')}}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{request('tab')=='conversations'?'active':''}}" href="{{route('admin.rental.provider.details', ['id'=>$store->id, 'tab'=> 'conversations'])}}"  aria-disabled="true">{{translate('Conversations')}}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{request('tab')=='meta-data'?'active':''}}" href="{{route('admin.rental.provider.details', ['id'=>$store->id, 'tab'=> 'meta-data'])}}"  aria-disabled="true">{{translate('meta_data')}}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link  {{request('tab')=='disbursements' ?'active':''}}" href="{{route('admin.rental.provider.details', ['id'=>$store->id, 'tab'=> 'disbursements'])}}"  aria-disabled="true">{{translate('messages.disbursements')}}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link  {{request('tab')=='business_plan' ?'active':''}}" href="{{route('admin.rental.provider.details', ['id'=>$store->id, 'tab'=> 'business_plan'])}}"  aria-disabled="true">{{translate('messages.business_plan')}}</a>
                </li>
            </ul>
            <!-- End Nav -->
        </div>
        <!-- End Nav Scroller -->
        @endif
    </div>
    <!-- End Page Header -->

    <div class="modal shedule-modal fade" id="badge-modal-btn" tabindex="-1" aria-labelledby="exampleModalLabel"
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
                        <img src="{{asset('public/assets/admin/img/badge-big.png')}}" alt="icon" class="mb-3">
                        <h3 class="mb-2">{{ translate('Give Verified Badge to this provider?') }}</h3>
                        <p class="mb-0">{{ translate('This will mark the provider as verified, and a trusted badge will be displayed on the provider details for customers') }}</p>
                    </div>
                </div>
                <div class="modal-footer justify-content-center border-0 pt-0 gap-2">
                    <button type="button" class="btn min-w-120px btn--reset" data-dismiss="modal">{{ translate('messages.Cancel') }}</button>
                    <a href="{{ route('admin.rental.provider.verified-seller', [$store->id]) }}" class="btn min-w-120px btn--primary">{{ translate('messages.Yes') }}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal shedule-modal fade" id="remove-badge-btn" tabindex="-1" aria-labelledby="exampleModalLabel"
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
                        <img src="{{asset('public/assets/admin/img/remove-badge.png')}}" alt="icon" class="mb-3">
                        <h3 class="mb-2">{{ translate('Want to remove the Verified Badge from this provider?') }}</h3>
                        <p class="mb-0">{{ translate('The provider will lose its verified status, but you can reassign the badge at any time') }}</p>
                    </div>
                </div>
                <div class="modal-footer justify-content-center border-0 pt-0 gap-2">
                    <button type="button" class="btn min-w-120px btn--reset" data-dismiss="modal">{{ translate('messages.Cancel') }}</button>
                    <a href="{{ route('admin.rental.provider.verified-seller', [$store->id]) }}" class="btn min-w-120px btn--danger">{{ translate('messages.Yes') }}</a>
                </div>
            </div>
        </div>
    </div>
