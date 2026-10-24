<div id="sidebarMain" class="d-none">
    <aside class="js-navbar-vertical-aside navbar navbar-vertical-aside navbar-vertical navbar-vertical-fixed navbar-expand-xl navbar-bordered  ">
        <div class="navbar-vertical-container">
            <div class="navbar-brand-wrapper justify-content-between">
                <a class="navbar-brand" href="{{ route('admin.dashboard') }}" aria-label="Front">
                       <img class="navbar-brand-logo initial--36 onerror-image onerror-image" data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                    src="{{\App\CentralLogics\Helpers::logoFullUrl()}}"
                    alt="Logo">
                    <img class="navbar-brand-logo-mini initial--36 onerror-image onerror-image" data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                    src="{{\App\CentralLogics\Helpers::logoFullUrl()}}"
                    alt="Logo">
                </a>

                <button type="button" class="js-navbar-vertical-aside-toggle-invoker navbar-vertical-aside-toggle btn btn-icon btn-xs btn-ghost-dark">
                    <i class="tio-clear tio-lg"></i>
                </button>

                <div class="navbar-nav-wrap-content-left">
                    <button type="button" class="js-navbar-vertical-aside-toggle-invoker close">
                        <i class="tio-first-page navbar-vertical-aside-toggle-short-align" data-toggle="tooltip"
                        data-placement="right" title="Collapse"></i>
                        <i class="tio-last-page navbar-vertical-aside-toggle-full-align"
                        data-template='<div class="tooltip d-none d-sm-block" role="tooltip"><div class="arrow"></div><div class="tooltip-inner"></div></div>'></i>
                    </button>
                </div>

            </div>

            <div class="navbar-vertical-content bg--005555" id="navbar-vertical-content">
                <form autocomplete="off"   class="sidebar--search-form">
                    <div class="search--form-group">
                        <button type="button" class="btn"><i class="tio-search"></i></button>
                        <input  autocomplete="false" name="qq" type="text" class="form-control form--control" placeholder="{{ translate('Search menu') }}" id="search">

                        <div id="search-suggestions" class="flex-wrap mt-1"></div>
                    </div>
                </form>

                <ul class="navbar-nav navbar-nav-lg nav-tabs">
                    <li class="navbar-vertical-aside-has-menu @yield('dashboard') {{ Request::is('admin') ? 'show active' : '' }}">
                        <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.dashboard') }}?module_id={{Config::get('module.current_module_id')}}" title="{{ translate('Dashboard') }}">
                            <i class="tio-home-vs-1-outlined nav-icon"></i>
                            <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                {{ translate('Dashboard') }}
                            </span>
                        </a>
                    </li>
                    @if (\App\CentralLogics\Helpers::module_permission_check('trip'))
                        <li class="nav-item">
                            <small class="nav-subtitle">{{ translate('messages.Trip management') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>

                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/rental/trip*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('messages.Trips') }}">
                                <i class="tio-taxi nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                    {{ translate('messages.Trips') }}
                                </span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="display:{{ Request::is('admin/rental/trip*') ? 'block' : 'none' }}">
                                <li class="nav-item {{ request()->status == 'all' ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('admin.rental.trip.list') }}?status=all" title="{{ translate('All trips') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                            {{ translate('All') }}
                                            <span class="badge badge-soft-info badge-pill ml-1">
                                                {{ \Modules\Rental\Entities\Trips::count() }}
                                            </span>
                                        </span>
                                    </a>
                                </li>
                                <li class="nav-item {{ request()->status == 'scheduled' ? 'active' : '' }} @yield('scheduled')">
                                    <a class="nav-link" href="{{ route('admin.rental.trip.list') }}?status=scheduled" title="{{ translate('messages.Scheduled trips') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                            {{ translate('Scheduled') }}
                                            <span class="badge badge-soft-info badge-pill ml-1">
                                                {{ \Modules\Rental\Entities\Trips::Scheduled()->count() }}
                                            </span>
                                        </span>
                                    </a>
                                </li>
                                <li class="nav-item {{ request()->status == 'pending' ? 'active' : '' }} @yield('pending')">
                                    <a class="nav-link " href="{{ route('admin.rental.trip.list') }}?status=pending" title="{{ translate('messages.Pending trips') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                            {{ translate('Pending') }}
                                            <span class="badge badge-soft-info badge-pill ml-1">
                                                {{ \Modules\Rental\Entities\Trips::Pending()->count() }}
                                            </span>
                                        </span>
                                    </a>
                                </li>

                                <li class="nav-item {{ request()->status == 'confirmed' ? 'active' : '' }} @yield('confirmed')">
                                    <a class="nav-link " href="{{ route('admin.rental.trip.list') }}?status=confirmed" title="{{ translate('messages.Confirmed trips') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                            {{ translate('messages.confirmed') }}
                                            <span class="badge badge-soft-info badge-pill ml-1">
                                                {{ \Modules\Rental\Entities\Trips::Confirmed()->count() }}
                                            </span>
                                        </span>
                                    </a>
                                </li>
                                <li class="nav-item {{ request()->status == 'ongoing' ? 'active' : '' }} @yield('ongoing')">
                                    <a class="nav-link " href="{{ route('admin.rental.trip.list') }}?status=ongoing" title="{{ translate('messages.Ongoing trips') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                            {{ translate('Ongoing') }}
                                            <span class="badge badge-soft-info badge-pill ml-1">
                                                {{ \Modules\Rental\Entities\Trips::Ongoing()->count() }}
                                            </span>
                                        </span>
                                    </a>
                                </li>
                                <li class="nav-item {{ request()->status == 'completed' ? 'active' : '' }} @yield('completed')">
                                    <a class="nav-link text-capitalize" href="{{ route('admin.rental.trip.list') }}?status=completed" title="{{ translate('messages.Completed trips') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                            {{ translate('messages.Completed') }}
                                            <span class="badge badge-soft-success badge-pill ml-1">
                                                {{ \Modules\Rental\Entities\Trips::Completed()->count() }}
                                            </span>
                                        </span>
                                    </a>
                                </li>
                                <li class="nav-item {{ request()->status == 'canceled' ? 'active' : '' }} @yield('canceled')">
                                    <a class="nav-link " href="{{ route('admin.rental.trip.list') }}?status=canceled" title="{{ translate('messages.Canceled trips') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container">
                                            {{ translate('Canceled') }}
                                            <span class="badge badge-soft-danger  badge-pill ml-1">
                                                {{ \Modules\Rental\Entities\Trips::Canceled()->count() }}
                                            </span>
                                        </span>
                                    </a>
                                </li>
                                <li class="nav-item {{ request()->status == 'payment_failed' ? 'active' : '' }} @yield('payment_failed')">
                                    <a class="nav-link " href="{{ route('admin.rental.trip.list') }}?status=payment_failed" title="{{ translate('messages.Payment failed trips') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate sidebar--badge-container text-capitalize">
                                            {{ translate('Payment failed') }}
                                            <span class="badge badge-soft-danger  badge-pill ml-1">
                                                {{ \Modules\Rental\Entities\Trips::PaymentFailed()->count() }}
                                            </span>
                                        </span>
                                    </a>
                                </li>

                            </ul>
                        </li>
                    @endif

                    @if (\App\CentralLogics\Helpers::module_permission_check('promotion'))
                        <li class="nav-item">
                            <small class="nav-subtitle" title="{{ translate('Promotion management') }}">{{ translate('Promotion management') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>

                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/rental/banner*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.rental.banner.add-new') }}" title="{{ translate('messages.banners') }}">
                                <i class="tio-image nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.banners') }}</span>
                            </a>
                        </li>
                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/rental/coupon*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.rental.coupon.add-new') }}" title="{{ translate('messages.coupons') }}">
                                <i class="tio-gift nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.coupons') }}</span>
                            </a>
                        </li>
                         <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/rental/cashback*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.rental.cashback.list') }}" title="{{ translate('messages.CashBack') }}">
                                <i class="tio-settings-back nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('messages.CashBack') }}</span>
                            </a>
                        </li>
                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/rental/notification*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.rental.notification.list') }}" title="{{ translate('Push notification') }}">
                                <i class="tio-notifications nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                    {{ translate('Push notification') }}
                                </span>
                            </a>
                        </li>
                    @endif

                    @if (
                        addon_published_status('ReelsModule')
                        && \App\CentralLogics\Helpers::module_permission_check('reels')
                        && \Modules\ReelsModule\Support\ReelModuleConfig::isAllowedType(config('module.current_module_type'))
                    )
                        <li class="nav-item">
                            <small class="nav-subtitle">{{ translate('Reels management') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>
                        <li class="navbar-vertical-aside-has-menu @yield('reels_create')">
                            <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.reels.create') }}"
                                title="{{ translate('Create reels') }}">
                                <i class="tio-video-camera-outlined nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Create reels') }}</span>
                            </a>
                        </li>
                        <li class="navbar-vertical-aside-has-menu @yield('reels_list')">
                            <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.reels.index') }}"
                                title="{{ translate('Reels list') }}">
                                <i class="tio-format-bullets nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">{{ translate('Reels list') }}</span>
                            </a>
                        </li>
                    @endif

                    @if (\App\CentralLogics\Helpers::module_permission_check('vehicle'))
                        <li class="nav-item">
                            <small class="nav-subtitle" title="{{ translate('messages.Vehicle section') }}">{{ translate('Vehicle management') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>
                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/rental/category/list') || Request::is('admin/rental/category/edit*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.rental.category.list') }}" title="{{ translate('messages.Category') }}">
                                <i class="tio-category nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                    {{ translate('messages.Category') }}
                                </span>
                            </a>
                        </li>
                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/rental/brand/list') || Request::is('admin/rental/brand/edit*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.rental.brand.list') }}" title="{{ translate('messages.Brands') }}">
                                <i class="tio-medal nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                    {{ translate('messages.Brands') }}
                                </span>
                            </a>
                        </li>
                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/rental/provider/vehicle*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link nav-link-toggle" href="javascript:" title="{{ translate('Vehicle setup') }}">
                                <i class="tio-car nav-icon"></i>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate text-capitalize">{{ translate('Vehicle setup') }}</span>
                            </a>
                            <ul class="js-navbar-vertical-aside-submenu nav nav-sub" style="display:{{ Request::is('admin/rental/provider/vehicle*') ? 'block' : 'none' }}">
                                <li class="nav-item {{ Request::is('admin/rental/provider/vehicle/create') || Request::is('admin/rental/provider/vehicle/edit/*')  ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('admin.rental.provider.vehicle.create') }}" title="{{ translate('messages.Create new') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('messages.Create new') }}</span>
                                    </a>
                                </li>
                                <li class="nav-item {{ Request::is('admin/rental/provider/vehicle/list')  ||Request::is('admin/rental/provider/vehicle/update/*') ||Request::is('admin/rental/provider/vehicle/details/*') || Request::is('admin/rental/provider/vehicle/edit/*')  ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('admin.rental.provider.vehicle.list') }}" title="{{ translate('Vehicle list') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('messages.list') }}</span>
                                    </a>
                                </li>
                                <li class="nav-item {{ Request::is('admin/rental/provider/vehicle/review-list') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('admin.rental.provider.vehicle.reviews') }}" title="{{ translate('Review list') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate">{{ translate('messages.review') }}</span>
                                    </a>
                                </li>
                                <li class="nav-item {{ Request::is('admin/rental/provider/vehicle/bulk-import') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('admin.rental.provider.vehicle.bulk_import') }}" title="{{ translate('Bulk import') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate text-capitalize">{{ translate('Bulk import') }}</span>
                                    </a>
                                </li>
                                <li class="nav-item {{ Request::is('admin/rental/provider/vehicle/bulk-export') ? 'active' : '' }}">
                                    <a class="nav-link " href="{{ route('admin.rental.provider.vehicle.bulk-export-index') }}" title="{{ translate('Bulk export') }}">
                                        <span class="tio-circle nav-indicator-icon"></span>
                                        <span class="text-truncate text-capitalize">{{ translate('Bulk export') }}</span>
                                    </a>
                                </li>
                            </ul>
                        </li>
                    @endif

                    @if (\App\CentralLogics\Helpers::module_permission_check('provider'))
                        <li class="nav-item">
                            <small class="nav-subtitle" title="{{ translate('messages.Provider section') }}">{{ translate('messages.Provider management') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>

                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/rental/provider/new-requests') || Request::is('admin/rental/provider/new-requests-details/*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.rental.provider.new-requests') }}?request_type=pending_provider" title="{{ translate('messages.New providers request') }}">
                                <span class="tio-calendar-note nav-icon"></span>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                    {{ translate('messages.New providers request') }}
                                    @php($new_str = \App\Models\Store::whereHas('vendor', function($query){
                                        return $query->where('status', null);
                                    })->module(Config::get('module.current_module_id'))->get())
                                    @if (count($new_str)>0)

                                    <span class="btn-status btn-status-danger border-0 size-8px"></span>
                                    @endif
                                </span>
                            </a>
                        </li>
                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/rental/provider/create') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.rental.provider.create') }}" title="{{ translate('Add new provider') }}">
                                <span class="tio-add-circle nav-icon"></span>
                                <span class="navbar-vertical-aside-mini-mode-hidden-elements text-truncate">
                                    {{ translate('Add new provider') }}
                                </span>
                            </a>
                        </li>
                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/rental/provider/list') ||  Request::is('admin/rental/provider/details/*') ||  Request::is('admin/rental/provider/driver/*') ||  Request::is('admin/rental/provider/edit*') ||  Request::is('admin/store/withdraw-view*') ? 'active' : '' }}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link" href="{{ route('admin.rental.provider.list') }}" title="{{ translate('Providers list') }}">
                                <span class="tio-layout nav-icon"></span>
                                <span class="text-truncate">{{ translate('Providers list') }}</span>
                            </a>
                        </li>
                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/rental/provider/bulk-import') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.rental.provider.bulk_import') }}" title="{{ translate('Bulk import') }}">
                                <span class="tio-publish nav-icon"></span>
                                <span class="text-truncate text-capitalize">{{ translate('Bulk import') }}</span>
                            </a>
                        </li>
                        <li class="navbar-vertical-aside-has-menu {{ Request::is('admin/rental/provider/bulk-export') ? 'active' : '' }}">
                            <a class="nav-link " href="{{ route('admin.rental.provider.bulk_export_index') }}" title="{{ translate('Bulk export') }}">
                                <span class="tio-download-to nav-icon"></span>
                                <span class="text-truncate text-capitalize">{{ translate('Bulk export') }}</span>
                            </a>
                        </li>
                   @endif

                    @if (\App\CentralLogics\Helpers::module_permission_check('download_app'))
                        <li class="nav-item">
                            <small class="nav-subtitle" title="{{ translate('Download apps') }}">{{ translate('Download apps') }}</small>
                            <small class="tio-more-horizontal nav-subtitle-replacer"></small>
                        </li>
                        <li class="navbar-vertical-aside-has-menu {{Request::is('admin/rental/settings*')?'active':''}}">
                            <a class="js-navbar-vertical-aside-menu-link nav-link " href="{{route('admin.rental.settings.down_app')}}" title="{{translate('Download apps')}}">
                                <i class="tio-shopping-basket-outlined nav-icon"></i>
                                <span class="text-truncate">{{translate('Download apps')}}</span>
                            </a>
                        </li>
                    @endif

                <li class="nav-item py-5">

                </li>

                    @includeIf('layouts.admin.partials._logout_modal')

                </ul>
            </div>
        </div>
    </aside>
</div>

<div id="sidebarCompact" class="d-none">

</div>


@push('script_2')

<script src="{{ asset('Modules/Rental/public/assets/js/admin/view-pages/rental-sidebar.js') }}"></script>

@endpush
