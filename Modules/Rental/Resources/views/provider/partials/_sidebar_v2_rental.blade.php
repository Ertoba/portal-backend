
@php
    use App\CentralLogics\Helpers;

    $store_data = Helpers::get_store_data();
    $store_id   = Helpers::get_store_id();
    $vendor_user = Helpers::get_loggedin_user();

    $req = request()->path();
    $is = function($pat) use ($req) { return \Illuminate\Support\Str::is($pat, $req); };

    $can_trip      = Helpers::employee_module_permission_check('trip');
    $can_vehicle   = Helpers::employee_module_permission_check('vehicle');
    $can_v_cat     = Helpers::employee_module_permission_check('vehicle');
    $can_v_brand   = Helpers::employee_module_permission_check('vehicle');
    $can_driver    = Helpers::employee_module_permission_check('driver');
    $can_coupon    = Helpers::employee_module_permission_check('marketing');
    $can_banner    = Helpers::employee_module_permission_check('marketing');
    $can_wallet    = Helpers::employee_module_permission_check('wallet');
    $can_wal_method= Helpers::employee_module_permission_check('wallet_method');
    $can_role      = Helpers::employee_module_permission_check('employee');
    $can_employee  = Helpers::employee_module_permission_check('employee');
    $can_report    = Helpers::employee_module_permission_check('report');
    $can_exp_rep   = Helpers::employee_module_permission_check('expense_report');
    $can_disb_rep  = Helpers::employee_module_permission_check('disbursement_report');
    $can_store_setup = Helpers::employee_module_permission_check('store_setup');
    $can_notif_setup = Helpers::employee_module_permission_check('notification_setup');
    $can_my_shop     = Helpers::employee_module_permission_check('my_shop');
    $can_subscription= Helpers::employee_module_permission_check('business_plan');
    $can_reviews     = Helpers::employee_module_permission_check('reviews');
    $can_chat        = Helpers::employee_module_permission_check('chat');

    $reels_enabled = addon_published_status('ReelsModule')
        && Helpers::employee_module_permission_check('reels')
        && \Modules\ReelsModule\Support\ReelModuleConfig::isAllowedType('rental');

    $tc = $tripCount ?? null;
    if (!$tc) {
        $base = \Modules\Rental\Entities\Trips::where('provider_id', $store_id);
        $tc = [
            'total_trips'          => (clone $base)->count(),
            'scheduled_trips'      => (clone $base)->Scheduled()->count(),
            'pending_trips'        => (clone $base)->Pending()->count(),
            'confirmed_trips'      => (clone $base)->Confirmed()->count(),
            'ongoing_trips'        => (clone $base)->Ongoing()->count(),
            'completed_trips'      => (clone $base)->Completed()->count(),
            'canceled_trips'       => (clone $base)->Canceled()->count(),
            'payment_failed_trips' => (clone $base)->PaymentFailed()->count(),
        ];
    }

    $active_section = 'dashboard';
    if     ($is('vendor-panel/provider-dashboard*'))                                                                                                $active_section = 'dashboard';
    elseif ($is('vendor-panel/trip*'))                                                                                                              $active_section = 'sales';
    elseif ($is('vendor-panel/vehicle*') || $is('vendor-panel/vehicle-category*') || $is('vendor-panel/vehicle-brand*') || $is('vendor-panel/driver*')) $active_section = 'fleet';
    elseif ($is('vendor-panel/rental-coupon*') || $is('vendor-panel/rental-banner*') || $is('vendor-panel/reels*'))                                 $active_section = 'marketing';
    elseif ($is('vendor-panel/wallet*') || $is('vendor-panel/withdraw-method*') || $is('vendor-panel/wallet-method*'))                              $active_section = 'finance';
    elseif ($is('vendor-panel/custom-role*') || $is('vendor-panel/employee*'))                                                                       $active_section = 'team';
    elseif ($is('vendor-panel/report*'))                                                                                                             $active_section = 'reports';
    elseif ($is('vendor-panel/business-settings*') || $is('vendor-panel/store/*') || $is('vendor-panel/subscription*') || $is('vendor-panel/rental-reviews*') || $is('vendor-panel/message*')) $active_section = 'settings';
@endphp

<aside id="v2-shell" class="v2-shell" data-workspace="vendor::rental" data-active-section="{{ $active_section }}">
    <div id="v2-rail" class="v2-rail v2-rail--module" role="navigation" aria-label="Sections">
        <div class="v2-rail-scope d-none">RENTAL</div>
        <div class="v2-rail-btns">
            <button class="v2-rail-btn {{ $active_section==='dashboard' ? 'is-active' : '' }}" data-section="dashboard" data-label="{{ translate('messages.dashboard') }}" aria-label="{{ translate('messages.dashboard') }}">
                <i data-lucide="layout-dashboard"></i><span class="v2-pin-dot"></span>
            </button>
            @if($can_trip)
            <button class="v2-rail-btn {{ $active_section==='sales' ? 'is-active' : '' }}" data-section="sales" data-label="{{ translate('messages.Trips') }}" aria-label="{{ translate('messages.Trips') }}">
                <i data-lucide="navigation"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_vehicle || $can_v_cat || $can_v_brand || $can_driver)
            <button class="v2-rail-btn {{ $active_section==='fleet' ? 'is-active' : '' }}" data-section="fleet" data-label="{{ translate('messages.vehicle_management') }}" aria-label="{{ translate('messages.vehicle_management') }}">
                <i data-lucide="car"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_coupon || $can_banner || $reels_enabled)
            <button class="v2-rail-btn {{ $active_section==='marketing' ? 'is-active' : '' }}" data-section="marketing" data-label="{{ translate('Marketing') }}" aria-label="{{ translate('Marketing') }}">
                <i data-lucide="megaphone"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_wallet || $can_wal_method)
            <button class="v2-rail-btn {{ $active_section==='finance' ? 'is-active' : '' }}" data-section="finance" data-label="{{ translate('messages.Wallet Management') }}" aria-label="{{ translate('messages.Wallet Management') }}">
                <i data-lucide="wallet"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_role || $can_employee)
            <button class="v2-rail-btn {{ $active_section==='team' ? 'is-active' : '' }}" data-section="team" data-label="{{ translate('messages.employee_section') }}" aria-label="{{ translate('messages.employee_section') }}">
                <i data-lucide="users"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_report || $can_exp_rep || $can_disb_rep)
            <button class="v2-rail-btn {{ $active_section==='reports' ? 'is-active' : '' }}" data-section="reports" data-label="{{ translate('messages.Report_section') }}" aria-label="{{ translate('messages.Report_section') }}">
                <i data-lucide="bar-chart-3"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
            @if($can_store_setup || $can_notif_setup || $can_my_shop || $can_subscription || $can_reviews || $can_chat)
            <button class="v2-rail-btn {{ $active_section==='settings' ? 'is-active' : '' }}" data-section="settings" data-label="{{ translate('messages.business_section') }}" aria-label="{{ translate('messages.business_section') }}">
                <i data-lucide="settings-2"></i><span class="v2-pin-dot"></span>
            </button>
            @endif
        </div>
        <div class="v2-rail-bottom">
            <button class="v2-rail-btn v2-rail-profile" id="v2-rail-profile" aria-haspopup="menu" aria-expanded="false" aria-label="{{ $vendor_user->f_name ?? 'Provider' }}">
                <span class="v2-avatar">{{ strtoupper(substr($vendor_user->f_name ?? 'P', 0, 1) . substr($vendor_user->l_name ?? '', 0, 1)) }}</span>
            </button>
        </div>
    </div>

    <aside id="v2-panel" class="v2-panel" aria-label="{{ translate('Section navigation') }}">
        
        <div class="v2-panel-content" data-panel="dashboard" @if($active_section!=='dashboard') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ $store_data->name ?? translate('messages.dashboard') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Live overview of your rental operations') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::rental-dashboard'])
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="rdh-over"><span>{{ translate('messages.overview') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('vendor-panel/provider-dashboard*') ? 'is-active' : '' }}" href="{{ route('vendor.providerDashboard') }}" data-id="rdh-home">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.dashboard') }}</span>
                            <button type="button" class="v2-pin" data-pin="rdh-home" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        @if($can_trip)
        <div class="v2-panel-content" data-panel="sales" @if($active_section!=='sales') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('messages.Trips') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('All trip statuses for your rental store') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::rental-sales'])
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="rt-list"><span>{{ translate('messages.Trips') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        @php
                            $statuses = [
                                ['key'=>'all',           'label'=>translate('messages.all'),            'count'=>$tc['total_trips'],          'dot'=>'blue'],
                                ['key'=>'scheduled',     'label'=>translate('messages.scheduled'),      'count'=>$tc['scheduled_trips'],      'dot'=>'amber'],
                                ['key'=>'pending',       'label'=>translate('messages.pending'),        'count'=>$tc['pending_trips'],        'dot'=>'amber'],
                                ['key'=>'confirmed',     'label'=>translate('messages.confirmed'),      'count'=>$tc['confirmed_trips'],      'dot'=>'green'],
                                ['key'=>'ongoing',       'label'=>translate('messages.Ongoing'),        'count'=>$tc['ongoing_trips'],        'dot'=>'violet'],
                                ['key'=>'completed',     'label'=>translate('messages.Completed'),      'count'=>$tc['completed_trips'],      'dot'=>'green'],
                                ['key'=>'canceled',      'label'=>translate('messages.canceled'),       'count'=>$tc['canceled_trips'],       'dot'=>'rose'],
                                ['key'=>'payment_failed','label'=>translate('messages.payment_failed'), 'count'=>$tc['payment_failed_trips'], 'dot'=>'rose'],
                            ];
                            $current_status = request()->status;
                        @endphp
                        @foreach($statuses as $st)
                            <a class="v2-nav-item {{ $is('vendor-panel/trip*') && $current_status === $st['key'] ? 'is-active' : '' }}" href="{{ route('vendor.trip.list') }}?status={{ $st['key'] }}" data-id="rt-{{ $st['key'] }}">
                                <span class="v2-dot v2-dot--{{ $st['dot'] }}"></span><span class="v2-label">{{ $st['label'] }}</span>
                                <span class="v2-count">{{ $st['count'] }}</span>
                                <button type="button" class="v2-pin" data-pin="rt-{{ $st['key'] }}" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($can_vehicle || $can_v_cat || $can_v_brand || $can_driver)
        <div class="v2-panel-content" data-panel="fleet" @if($active_section!=='fleet') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('messages.vehicle_management') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Vehicles, categories, brands and drivers') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::rental-fleet'])

                @if($can_vehicle)
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="rf-veh"><span>{{ translate('Vehicle Setup') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('vendor-panel/vehicle/create') ? 'is-active' : '' }}" href="{{ route('vendor.vehicle.create') }}" data-id="rf-vc">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.create_new') }}</span>
                            <button type="button" class="v2-pin" data-pin="rf-vc" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ ($is('vendor-panel/vehicle/list') || $is('vendor-panel/vehicle/details/*') || $is('vendor-panel/vehicle/update/*')) ? 'is-active' : '' }}" href="{{ route('vendor.vehicle.list') }}" data-id="rf-vl">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.list') }}</span>
                            <button type="button" class="v2-pin" data-pin="rf-vl" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('vendor-panel/vehicle/bulk-import*') ? 'is-active' : '' }}" href="{{ route('vendor.vehicle.bulk_import') }}" data-id="rf-vi">
                            <span class="v2-dot v2-dot--gray"></span><span class="v2-label">{{ translate('messages.bulk_import') }}</span>
                        </a>
                        <a class="v2-nav-item {{ $is('vendor-panel/vehicle/bulk-export*') ? 'is-active' : '' }}" href="{{ route('vendor.vehicle.bulk-export-index') }}" data-id="rf-ve">
                            <span class="v2-dot v2-dot--gray"></span><span class="v2-label">{{ translate('messages.bulk_export') }}</span>
                        </a>
                    </div>
                </div>
                @endif

                @if($can_v_cat || $can_v_brand)
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="rf-meta"><span>{{ translate('Catalog metadata') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        @if($can_v_cat)
                        <a class="v2-nav-item {{ $is('vendor-panel/vehicle-category*') ? 'is-active' : '' }}" href="{{ route('vendor.vehicle_category.list') }}" data-id="rf-cat">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('messages.categories') }}</span>
                            <button type="button" class="v2-pin" data-pin="rf-cat" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_v_brand)
                        <a class="v2-nav-item {{ $is('vendor-panel/vehicle-brand*') ? 'is-active' : '' }}" href="{{ route('vendor.vehicle_brand.list') }}" data-id="rf-brn">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.Brand list') }}</span>
                            <button type="button" class="v2-pin" data-pin="rf-brn" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                @if($can_driver)
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="rf-drv"><span>{{ translate('messages.driver') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('vendor-panel/driver/create') ? 'is-active' : '' }}" href="{{ route('vendor.driver.create') }}" data-id="rf-dc">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.create_new') }}</span>
                            <button type="button" class="v2-pin" data-pin="rf-dc" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ ($is('vendor-panel/driver/list') || $is('vendor-panel/driver/details/*') || $is('vendor-panel/driver/update/*')) ? 'is-active' : '' }}" href="{{ route('vendor.driver.list') }}" data-id="rf-dl">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.driver') }}</span>
                            <button type="button" class="v2-pin" data-pin="rf-dl" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        @if($can_coupon || $can_banner || $reels_enabled)
        <div class="v2-panel-content" data-panel="marketing" @if($active_section!=='marketing') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('Marketing') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Coupons, banners and reels') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::rental-marketing'])

                @if($can_coupon || $can_banner)
                <div class="v2-group">
                    <div class="v2-group-items">
                        @if($can_coupon)
                        <a class="v2-nav-item {{ $is('vendor-panel/rental-coupon*') ? 'is-active' : '' }}" href="{{ route('vendor.rental_coupon.list') }}" data-id="rm-coup">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.coupons') }}</span>
                            <button type="button" class="v2-pin" data-pin="rm-coup" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_banner)
                        <a class="v2-nav-item {{ $is('vendor-panel/rental-banner*') ? 'is-active' : '' }}" href="{{ route('vendor.rental_banner.list') }}" data-id="rm-ban">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.banners') }}</span>
                            <button type="button" class="v2-pin" data-pin="rm-ban" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                @if($reels_enabled)
                @php
                    $vrl_create_active = $is('vendor-panel/reels/create*');
                    $vrl_list_active = $is('vendor-panel/reels*') && !$vrl_create_active;
                @endphp
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="rm-reels"><span>{{ translate('messages.Reels_Management') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $vrl_create_active ? 'is-active' : '' }}" href="{{ route('vendor.reels.create') }}" data-id="rm-rlc">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('messages.Create_Reels') }}</span>
                            <button type="button" class="v2-pin" data-pin="rm-rlc" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $vrl_list_active ? 'is-active' : '' }}" href="{{ route('vendor.reels.index') }}" data-id="rm-rll">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.Reels_List') }}</span>
                            <button type="button" class="v2-pin" data-pin="rm-rll" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        @if($can_wallet || $can_wal_method)
        <div class="v2-panel-content" data-panel="finance" @if($active_section!=='finance') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('messages.Wallet Management') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Wallet balance and disbursement methods') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::rental-finance'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        @if($can_wallet)
                        <a class="v2-nav-item {{ $is('vendor-panel/wallet') ? 'is-active' : '' }}" href="{{ route('vendor.wallet.index') }}" data-id="rfn-wal">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.my_wallet') }}</span>
                            <button type="button" class="v2-pin" data-pin="rfn-wal" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_wal_method)
                        <a class="v2-nav-item {{ ($is('vendor-panel/withdraw-method*') || $is('vendor-panel/wallet-method*')) ? 'is-active' : '' }}" href="{{ route('vendor.wallet-method.index') }}" data-id="rfn-wmt">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.disbursement_method') }}</span>
                            <button type="button" class="v2-pin" data-pin="rfn-wmt" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($can_role || $can_employee)
        <div class="v2-panel-content" data-panel="team" @if($active_section!=='team') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('messages.employee_section') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Roles and employees') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::rental-team'])
                @if($can_role)
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="rtm-role"><span>{{ translate('messages.employee_Role') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('vendor-panel/custom-role*') ? 'is-active' : '' }}" href="{{ route('vendor.custom-role.list') }}" data-id="rtm-role">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('messages.employee_Role') }}</span>
                            <button type="button" class="v2-pin" data-pin="rtm-role" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif
                @if($can_employee)
                <div class="v2-group">
                    <button type="button" class="v2-group-header" data-group-toggle="rtm-emp"><span>{{ translate('messages.employees') }}</span><i data-lucide="chevron-down" class="v2-chev"></i></button>
                    <div class="v2-group-items">
                        <a class="v2-nav-item {{ $is('vendor-panel/employee/add-new*') ? 'is-active' : '' }}" href="{{ route('vendor.employee.add-new') }}" data-id="rtm-empa">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.add_new_Employee') }}</span>
                            <button type="button" class="v2-pin" data-pin="rtm-empa" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ ($is('vendor-panel/employee/list*') || $is('vendor-panel/employee/edit/*')) ? 'is-active' : '' }}" href="{{ route('vendor.employee.list') }}" data-id="rtm-empl">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.Employee_list') }}</span>
                            <button type="button" class="v2-pin" data-pin="rtm-empl" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        @if($can_report || $can_exp_rep || $can_disb_rep)
        <div class="v2-panel-content" data-panel="reports" @if($active_section!=='reports') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('messages.Report_section') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Earnings, expenses, disbursements, trips and tax') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::rental-reports'])
                <div class="v2-group">
                    <div class="v2-group-items">
                        @if($can_exp_rep)
                        <a class="v2-nav-item {{ $is('vendor-panel/report/expense-report*') ? 'is-active' : '' }}" href="{{ route('vendor.report.expense-report') }}" data-id="rr-exp">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('messages.expense_report') }}</span>
                            <button type="button" class="v2-pin" data-pin="rr-exp" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_disb_rep)
                        <a class="v2-nav-item {{ $is('vendor-panel/report/disbursement-report*') ? 'is-active' : '' }}" href="{{ route('vendor.report.disbursement-report') }}" data-id="rr-dis">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.disbursement_report') }}</span>
                            <button type="button" class="v2-pin" data-pin="rr-dis" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_report)
                        <a class="v2-nav-item {{ $is('vendor-panel/report/earning-report*') ? 'is-active' : '' }}" href="{{ route('vendor.report.earning-report') }}" data-id="rr-ern">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('Earning Report') }}</span>
                            <button type="button" class="v2-pin" data-pin="rr-ern" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        <a class="v2-nav-item {{ $is('vendor-panel/report/trip-report*') ? 'is-active' : '' }}" href="{{ route('vendor.report.trip-report') }}" data-id="rr-trip">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('messages.trip_report') }}</span>
                            <button type="button" class="v2-pin" data-pin="rr-trip" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @if(Route::has('vendor.report.providerTax'))
                        <a class="v2-nav-item {{ ($is('vendor-panel/report/provider-tax*') || $is('vendor-panel/report/providerTax*') || $is('vendor-panel/report/tax*')) ? 'is-active' : '' }}" href="{{ route('vendor.report.providerTax') }}" data-id="rr-vat">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('messages.Vat_Report') }}</span>
                            <button type="button" class="v2-pin" data-pin="rr-vat" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($can_store_setup || $can_notif_setup || $can_my_shop || $can_subscription || $can_reviews || $can_chat)
        <div class="v2-panel-content" data-panel="settings" @if($active_section!=='settings') hidden @endif>
            <div class="v2-panel-header">
                <div class="v2-panel-title"><span class="name">{{ translate('messages.business_section') }}</span></div>
                <div class="v2-panel-subtitle">{{ translate('Store profile, notifications, subscription and chat') }}</div>
            </div>
            <div class="v2-panel-body">
                @include('layouts.admin.partials._v2_pinned_card', ['key' => 'vendor::rental-settings'])

                <div class="v2-group">
                    <div class="v2-group-items">
                        @if($can_my_shop)
                        <a class="v2-nav-item {{ $is('vendor-panel/store/*') ? 'is-active' : '' }}" href="{{ route('vendor.shop.view') }}" data-id="rst-shop">
                            <span class="v2-dot v2-dot--green"></span><span class="v2-label">{{ translate('messages.my_shop') }}</span>
                            <button type="button" class="v2-pin" data-pin="rst-shop" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_store_setup)
                        <a class="v2-nav-item {{ $is('vendor-panel/business-settings/store-setup*') ? 'is-active' : '' }}" href="{{ route('vendor.business-settings.store-setup') }}" data-id="rst-cfg">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.storeConfig') }}</span>
                            <button type="button" class="v2-pin" data-pin="rst-cfg" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_notif_setup)
                        <a class="v2-nav-item {{ $is('vendor-panel/business-settings/notification-setup*') ? 'is-active' : '' }}" href="{{ route('vendor.business-settings.notification-setup') }}" data-id="rst-not">
                            <span class="v2-dot v2-dot--violet"></span><span class="v2-label">{{ translate('messages.notification_setup') }}</span>
                            <button type="button" class="v2-pin" data-pin="rst-not" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_subscription)
                        <a class="v2-nav-item {{ $is('vendor-panel/subscription*') ? 'is-active' : '' }}" href="{{ route('vendor.subscriptionackage.subscriberDetail') }}" data-id="rst-sub">
                            <span class="v2-dot v2-dot--amber"></span><span class="v2-label">{{ translate('messages.My_Business_Plan') }}</span>
                            <button type="button" class="v2-pin" data-pin="rst-sub" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_reviews)
                        <a class="v2-nav-item {{ $is('vendor-panel/rental-reviews*') ? 'is-active' : '' }}" href="{{ route('vendor.rental.reviews') }}" data-id="rst-rev">
                            <span class="v2-dot v2-dot--rose"></span><span class="v2-label">{{ translate('messages.reviews') }}</span>
                            <button type="button" class="v2-pin" data-pin="rst-rev" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                        @if($can_chat)
                        <a class="v2-nav-item {{ $is('vendor-panel/message*') ? 'is-active' : '' }}" href="{{ route('vendor.message.list') }}" data-id="rst-chat">
                            <span class="v2-dot v2-dot--blue"></span><span class="v2-label">{{ translate('messages.Chat') }}</span>
                            <button type="button" class="v2-pin" data-pin="rst-chat" title="{{ translate('Pin') }}">@include('layouts.admin.partials._v2_pin_icon')</button>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endif
    </aside>
</aside>

@include('layouts.vendor.partials._v2_profile_pop')
@include('layouts.admin.partials._v2_sidebar_script')
