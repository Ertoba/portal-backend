{{--
    v2 Rental module sidebar.
    Sections: Dashboard / Sales (Trips) / Catalog (Vehicles) / Vendors (Providers) / Marketing / Apps.
    Mirrors the prototype's module-workspace structure but with rental-specific routes.
--}}
@php
    use App\CentralLogics\Helpers;

    $current_module_id = Config::get('module.current_module_id');

    $can_market = Helpers::module_permission_check('promotion');

    $reels_enabled = addon_published_status('ReelsModule')
        && Helpers::module_permission_check('reels')
        && (\Modules\ReelsModule\Support\ReelModuleConfig::isAllowedType(config('module.current_module_type')) ?? false);

    // Trip counts (rental-specific)
    $count_all       = \Modules\Rental\Entities\Trips::count();
    $count_scheduled = \Modules\Rental\Entities\Trips::Scheduled()->count();
    $count_pending   = \Modules\Rental\Entities\Trips::Pending()->count();
    $count_confirmed = \Modules\Rental\Entities\Trips::Confirmed()->count();
    $count_ongoing   = \Modules\Rental\Entities\Trips::Ongoing()->count();
    $count_completed = \Modules\Rental\Entities\Trips::Completed()->count();
    $count_canceled  = \Modules\Rental\Entities\Trips::Canceled()->count();
    $count_failed    = \Modules\Rental\Entities\Trips::PaymentFailed()->count();

    $count_new_providers = \App\Models\Store::whereHas('vendor', function($q){ return $q->where('status', null); })->module($current_module_id)->count();

    $req = request()->path();
    $is = function($pat) use ($req) { return \Illuminate\Support\Str::is($pat, $req); };

    $active_section = 'dashboard';
    if ($is('admin/rental/trip*'))                                                              $active_section = 'sales';
    elseif ($is('admin/rental/category*') || $is('admin/rental/brand*') || $is('admin/rental/provider/vehicle*')) $active_section = 'catalog';
    elseif ($is('admin/rental/provider/new-requests*') || $is('admin/rental/provider/create*') || $is('admin/rental/provider/list*') || $is('admin/rental/provider/bulk*') || $is('admin/rental/provider/details*') || $is('admin/rental/provider/edit*') || $is('admin/rental/provider/driver*')) $active_section = 'vendors';
    elseif ($is('admin/rental/banner*') || $is('admin/rental/coupon*') || $is('admin/rental/cashback*') || $is('admin/rental/notification*') || $is('admin/reels*')) $active_section = 'marketing';
    elseif ($is('admin/rental/settings*'))                                                      $active_section = 'apps';
@endphp

<aside id="v2-shell" class="v2-shell" data-workspace="module" data-active-section="{{ $active_section }}">
    <div id="v2-rail" class="v2-rail v2-rail--module" role="navigation" aria-label="Sections">
        <div class="v2-rail-scope v2-rail-scope--module d-none">MODULE</div>
        <div class="v2-rail-btns">
            <button class="v2-rail-btn {{ $active_section==='dashboard' ? 'is-active module' : '' }}" data-section="dashboard" data-label="{{ translate('messages.dashboard') }}" aria-label="{{ translate('messages.dashboard') }}">
                <i data-lucide="gauge"></i><span class="v2-pin-dot"></span>
            </button>
            @if(Helpers::module_permission_check('trip'))
                <button class="v2-rail-btn {{ $active_section==='sales' ? 'is-active module' : '' }}" data-section="sales" data-label="{{ translate('messages.Trips') }}" aria-label="{{ translate('messages.Trips') }}">
                    <i data-lucide="shopping-bag"></i><span class="v2-pin-dot"></span>
                </button>
            @endif
            @if(Helpers::module_permission_check('vehicle'))
                <button class="v2-rail-btn {{ $active_section==='catalog' ? 'is-active module' : '' }}" data-section="catalog" data-label="{{ translate('messages.vehicle_management') }}" aria-label="{{ translate('messages.vehicle_management') }}">
                    <i data-lucide="car"></i><span class="v2-pin-dot"></span>
                </button>
            @endif
            @if(Helpers::module_permission_check('provider'))
                <button class="v2-rail-btn {{ $active_section==='vendors' ? 'is-active module' : '' }}" data-section="vendors" data-label="{{ translate('messages.provider_management') }}" aria-label="{{ translate('messages.provider_management') }}">
                    <i data-lucide="store"></i><span class="v2-pin-dot"></span>
                </button>
            @endif
            @if($can_market || $reels_enabled)
                <button class="v2-rail-btn {{ $active_section==='marketing' ? 'is-active module' : '' }}" data-section="marketing" data-label="{{ translate('Marketing') }}" aria-label="{{ translate('Marketing') }}">
                    <i data-lucide="megaphone"></i><span class="v2-pin-dot"></span>
                </button>
            @endif
            @if(Helpers::module_permission_check('download_app'))
                <button class="v2-rail-btn {{ $active_section==='apps' ? 'is-active module' : '' }}" data-section="apps" data-label="{{ translate('Download_Apps') }}" aria-label="{{ translate('Download_Apps') }}">
                    <i data-lucide="smartphone"></i><span class="v2-pin-dot"></span>
                </button>
            @endif
        </div>
        <div class="v2-rail-bottom">
            <button class="v2-rail-btn v2-rail-profile" id="v2-rail-profile" aria-haspopup="menu" aria-expanded="false" aria-label="{{ auth('admin')->user()->f_name ?? 'Admin' }}">
                <span class="v2-avatar">{{ strtoupper(substr(auth('admin')->user()->f_name ?? 'A', 0, 1) . substr(auth('admin')->user()->l_name ?? '', 0, 1)) }}</span>
            </button>
        </div>
    </div>

    <aside id="v2-panel" class="v2-panel" aria-label="{{ translate('Section navigation') }}">
        {{-- Dashboard --}}
        <div class="v2-panel-content" data-panel="dashboard" @if($active_section!=='dashboard') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title">
                    <span class="name">{{ translate('messages.dashboard') }}</span>
                    <span class="v2-module-tag"><i data-lucide="layout-grid"></i>{{ \App\Models\Module::find($current_module_id)?->module_name ?? translate('Rental') }}</span>
                </div>
                <div class="v2-panel-subtitle">{{ translate('Module overview, key metrics, and quick links') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'module::dashboard'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/rental') ? 'is-active' : '' }}" href="{{ route('admin.rental.dashboard') }}" data-id="dash-overview">
                            <span class="v2-dot v2-dot--blue"></span>
                            <span class="v2-label">{{ translate('Module overview') }}</span>
                            <button type="button" class="v2-pin" data-pin="dash-overview" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sales / Trips --}}
        @if(Helpers::module_permission_check('trip'))
        <div class="v2-panel-content" data-panel="sales" @if($active_section!=='sales') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title">
                    <span class="name">{{ translate('messages.Trip_management') }}</span>
                    <span class="v2-module-tag"><i data-lucide="layout-grid"></i>{{ \App\Models\Module::find($current_module_id)?->module_name ?? translate('Rental') }}</span>
                </div>
                <div class="v2-panel-subtitle">{{ translate('Trip operations and statuses') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'module::sales'])
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="rn-trips"><span>{{ translate('messages.Trips') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        @php
                            $trip_items = [
                                ['key' => 'tr-all',  'status' => 'all',           'label' => translate('messages.all'),            'count' => $count_all,       'dot' => 'blue'],
                                ['key' => 'tr-sch',  'status' => 'scheduled',     'label' => translate('messages.scheduled'),      'count' => $count_scheduled, 'dot' => 'violet'],
                                ['key' => 'tr-pen',  'status' => 'pending',       'label' => translate('messages.pending'),        'count' => $count_pending,   'dot' => 'amber'],
                                ['key' => 'tr-con',  'status' => 'confirmed',     'label' => translate('messages.confirmed'),      'count' => $count_confirmed, 'dot' => 'blue'],
                                ['key' => 'tr-ong',  'status' => 'ongoing',       'label' => translate('messages.Ongoing'),        'count' => $count_ongoing,   'dot' => 'violet'],
                                ['key' => 'tr-com',  'status' => 'completed',     'label' => translate('messages.Completed'),      'count' => $count_completed, 'dot' => 'green'],
                                ['key' => 'tr-can',  'status' => 'canceled',      'label' => translate('messages.canceled'),       'count' => $count_canceled,  'dot' => 'rose'],
                                ['key' => 'tr-fail', 'status' => 'payment_failed','label' => translate('messages.payment_failed'), 'count' => $count_failed,    'dot' => 'rose'],
                            ];
                        @endphp
                        @foreach($trip_items as $ti)
                            <a class="v2-nav-item {{ request()->status === $ti['status'] ? 'is-active' : '' }}" href="{{ route('admin.rental.trip.list') }}?status={{ $ti['status'] }}" data-id="{{ $ti['key'] }}">
                                <span class="v2-dot v2-dot--{{ $ti['dot'] }}"></span>
                                <span class="v2-label">{{ $ti['label'] }}</span>
                                <span class="v2-count">{{ $ti['count'] }}</span>
                                <button type="button" class="v2-pin" data-pin="{{ $ti['key'] }}" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Catalog / Vehicles --}}
        @if(Helpers::module_permission_check('vehicle'))
        <div class="v2-panel-content" data-panel="catalog" @if($active_section!=='catalog') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title">
                    <span class="name">{{ translate('messages.vehicle_management') }}</span>
                    <span class="v2-module-tag"><i data-lucide="layout-grid"></i>{{ \App\Models\Module::find($current_module_id)?->module_name ?? translate('Rental') }}</span>
                </div>
                <div class="v2-panel-subtitle">{{ translate('Categories, brands, and vehicle setup') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'module::catalog'])

                @if(Helpers::module_permission_check('rental_vehicle_setup'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="rn-setup"><span>{{ translate('Setup') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/rental/category/list') || $is('admin/rental/category/edit*') ? 'is-active' : '' }}" href="{{ route('admin.rental.category.list') }}" data-id="rn-cat">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.category') }}</span>
                            <button type="button" class="v2-pin" data-pin="rn-cat" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/rental/brand/list') || $is('admin/rental/brand/edit*') ? 'is-active' : '' }}" href="{{ route('admin.rental.brand.list') }}" data-id="rn-brand">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('messages.brands') }}</span>
                            <button type="button" class="v2-pin" data-pin="rn-brand" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif

                @if(Helpers::module_permission_check('vehicle'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="rn-veh"><span>{{ translate('Vehicle Setup') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/rental/provider/vehicle/create') ? 'is-active' : '' }}" href="{{ route('admin.rental.provider.vehicle.create') }}" data-id="rn-veh-add">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.create_new') }}</span>
                            <button type="button" class="v2-pin" data-pin="rn-veh-add" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/rental/provider/vehicle/list') || $is('admin/rental/provider/vehicle/update/*') || $is('admin/rental/provider/vehicle/details/*') ? 'is-active' : '' }}" href="{{ route('admin.rental.provider.vehicle.list') }}" data-id="rn-veh-list">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.list') }}</span>
                            <button type="button" class="v2-pin" data-pin="rn-veh-list" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/rental/provider/vehicle/review-list*') ? 'is-active' : '' }}" href="{{ route('admin.rental.provider.vehicle.reviews') }}" data-id="rn-veh-rev">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('messages.review') }}</span>
                            <button type="button" class="v2-pin" data-pin="rn-veh-rev" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/rental/provider/vehicle/bulk-import*') ? 'is-active' : '' }}" href="{{ route('admin.rental.provider.vehicle.bulk_import') }}" data-id="rn-veh-imp">
                            <span class="v2-dot v2-dot--gray"></span><span class="v2-label">{{ translate('messages.bulk_import') }}</span>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/rental/provider/vehicle/bulk-export*') ? 'is-active' : '' }}" href="{{ route('admin.rental.provider.vehicle.bulk-export-index') }}" data-id="rn-veh-exp">
                            <span class="v2-dot v2-dot--gray"></span><span class="v2-label">{{ translate('messages.bulk_export') }}</span>
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Vendors / Providers --}}
        @if(Helpers::module_permission_check('provider'))
        <div class="v2-panel-content" data-panel="vendors" @if($active_section!=='vendors') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title">
                    <span class="name">{{ translate('messages.provider_management') }}</span>
                    <span class="v2-module-tag"><i data-lucide="layout-grid"></i>{{ \App\Models\Module::find($current_module_id)?->module_name ?? translate('Rental') }}</span>
                </div>
                <div class="v2-panel-subtitle">{{ translate('Providers directory, onboarding, and bulk tools') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'module::vendors'])

                @if(Helpers::module_permission_check('provider'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="rn-prov-dir"><span>{{ translate('Directory') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/rental/provider/list') || $is('admin/rental/provider/details/*') || $is('admin/rental/provider/driver/*') || $is('admin/rental/provider/edit*') ? 'is-active' : '' }}" href="{{ route('admin.rental.provider.list') }}" data-id="rn-prov-list">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('providers list') }}</span>
                            <button type="button" class="v2-pin" data-pin="rn-prov-list" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/rental/provider/create') ? 'is-active' : '' }}" href="{{ route('admin.rental.provider.create') }}" data-id="rn-prov-add">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('add new provider') }}</span>
                            <button type="button" class="v2-pin" data-pin="rn-prov-add" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/rental/provider/new-requests*') || $is('admin/rental/provider/new-requests-details/*') ? 'is-active' : '' }}" href="{{ route('admin.rental.provider.new-requests') }}?request_type=pending_provider" data-id="rn-prov-new">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('messages.new_providers_request') }}</span>
                            @if($count_new_providers > 0)<span class="v2-count">{{ $count_new_providers }}</span>@endif
                            <button type="button" class="v2-pin" data-pin="rn-prov-new" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif

                @if(Helpers::module_permission_check('rental_provider_bulk'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="rn-prov-bulk"><span>{{ translate('Bulk') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/rental/provider/bulk-import*') ? 'is-active' : '' }}" href="{{ route('admin.rental.provider.bulk_import') }}" data-id="rn-prov-imp">
                            <span class="v2-dot v2-dot--gray"></span><span class="v2-label">{{ translate('messages.bulk_import') }}</span>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/rental/provider/bulk-export*') ? 'is-active' : '' }}" href="{{ route('admin.rental.provider.bulk_export_index') }}" data-id="rn-prov-exp">
                            <span class="v2-dot v2-dot--gray"></span><span class="v2-label">{{ translate('messages.bulk_export') }}</span>
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Marketing --}}
        @if($can_market || $reels_enabled)
        <div class="v2-panel-content" data-panel="marketing" @if($active_section!=='marketing') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title">
                    <span class="name">{{ translate('Marketing') }}</span>
                    <span class="v2-module-tag"><i data-lucide="layout-grid"></i>{{ \App\Models\Module::find($current_module_id)?->module_name ?? translate('Rental') }}</span>
                </div>
                <div class="v2-panel-subtitle">{{ translate('Coupons, banners, and notifications for rentals') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'module::marketing'])

                @if(Helpers::module_permission_check('promotion'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="rn-promo"><span>{{ translate('Promotions') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/rental/coupon*') ? 'is-active' : '' }}" href="{{ route('admin.rental.coupon.add-new') }}" data-id="rn-coup">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.coupons') }}</span>
                            <button type="button" class="v2-pin" data-pin="rn-coup" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('admin/rental/cashback*') ? 'is-active' : '' }}" href="{{ route('admin.rental.cashback.list') }}" data-id="rn-cash">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('messages.cashback') }}</span>
                            <button type="button" class="v2-pin" data-pin="rn-cash" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif

                @if(Helpers::module_permission_check('rental_banners'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="rn-ban"><span>{{ translate('Banners') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/rental/banner*') ? 'is-active' : '' }}" href="{{ route('admin.rental.banner.add-new') }}" data-id="rn-bn">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.banners') }}</span>
                            <button type="button" class="v2-pin" data-pin="rn-bn" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif

                @if(Helpers::module_permission_check('rental_communication'))
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="rn-comm"><span>{{ translate('Communication') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/rental/notification*') ? 'is-active' : '' }}" href="{{ route('admin.rental.notification.list') }}" data-id="rn-pn">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('messages.push_notification') }}</span>
                            <button type="button" class="v2-pin" data-pin="rn-pn" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif

                @if($reels_enabled)
                @php
                    $reel_create_active = $is('admin/reels/create*');
                    $reel_list_active = $is('admin/reels*') && !$reel_create_active;
                @endphp
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="rn-reels"><span>{{ translate('messages.Reels_Management') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $reel_create_active ? 'is-active' : '' }}" href="{{ route('admin.reels.create') }}" data-id="rn-rl-cr">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('messages.Create_Reels') }}</span>
                            <button type="button" class="v2-pin" data-pin="rn-rl-cr" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $reel_list_active ? 'is-active' : '' }}" href="{{ route('admin.reels.index') }}" data-id="rn-rl-ls">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.Reels_List') }}</span>
                            <button type="button" class="v2-pin" data-pin="rn-rl-ls" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Apps --}}
        @if(Helpers::module_permission_check('download_app'))
        <div class="v2-panel-content" data-panel="apps" @if($active_section!=='apps') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title">
                    <span class="name">{{ translate('Download_Apps') }}</span>
                    <span class="v2-module-tag"><i data-lucide="layout-grid"></i>{{ \App\Models\Module::find($current_module_id)?->module_name ?? translate('Rental') }}</span>
                </div>
                <div class="v2-panel-subtitle">{{ translate('Provider and customer app downloads') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'module::apps'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('admin/rental/settings*') ? 'is-active' : '' }}" href="{{ route('admin.rental.settings.down_app') }}" data-id="rn-apps">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('Download_Apps') }}</span>
                            <button type="button" class="v2-pin" data-pin="rn-apps" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </aside>
</aside>

@include('layouts.admin.partials._v2_profile_pop')
@include('layouts.admin.partials._v2_sidebar_script')
