<div class="d-flex flex-wrap justify-content-between align-items-center tabs-slide-wrap position-relative mb-2 __gap-12px">
    <div class="js-nav-scroller hs-nav-scroller-horizontal mt-2">
        <!-- Nav -->
        <ul class="nav nav-tabs tabs-inner border-0 nav--tabs nav--pills">
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/rental-email-setup/provider/registration') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.rental-email-setup', ['provider','registration']) }}">
                    {{translate('New Provider Registration')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/rental-email-setup/provider/approve') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.rental-email-setup', ['provider','approve']) }}">
                    {{translate('New_Provider_Approval')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/rental-email-setup/provider/deny') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.rental-email-setup', ['provider','deny']) }}">
                    {{translate('New_Provider_Rejection')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/rental-email-setup/provider/suspend') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.rental-email-setup', ['provider','suspend']) }}">
                    {{translate('Account_Suspend')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/rental-email-setup/provider/unsuspend') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.rental-email-setup', ['provider','unsuspend']) }}">
                    {{translate('Account_Unsuspend')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/rental-email-setup/provider/withdraw-approve') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.rental-email-setup', ['provider','withdraw-approve']) }}">
                    {{translate('Withdraw_Approval')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/rental-email-setup/provider/withdraw-deny') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.rental-email-setup', ['provider','withdraw-deny']) }}">
                    {{translate('Withdraw_Rejection')}}
                </a>
            </li>

            @if (\App\CentralLogics\Helpers::subscription_check())
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/rental-email-setup/provider/subscription-successful') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.rental-email-setup', ['provider','subscription-successful']) }}">
                    {{translate('Subscription_Successful')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/rental-email-setup/provider/subscription-renew') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.rental-email-setup', ['provider','subscription-renew']) }}">
                    {{translate('Subscription_Renew')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/rental-email-setup/provider/subscription-shift') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.rental-email-setup', ['provider','subscription-shift']) }}">
                    {{translate('Subscription_Shift')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/rental-email-setup/provider/subscription-cancel') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.rental-email-setup', ['provider','subscription-cancel']) }}">
                    {{translate('Subscription_Cancel')}}
                </a>
            </li>
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/rental-email-setup/provider/subscription-plan_upadte') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.rental-email-setup', ['provider','subscription-plan_upadte']) }}">
                    {{translate('Subscription_Plan_Upadte')}}
                </a>
            </li>
            @endif
        </ul>
        <!-- End Nav -->
    </div>
    <div class="arrow-area">
        <div class="button-prev align-items-center">
            <button type="button"
                class="btn btn-click-prev mr-auto border-0 btn-primary rounded-circle fs-12 p-2 d-center">
                <i class="tio-chevron-left fs-24"></i>
            </button>
        </div>
        <div class="button-next align-items-center">
            <button type="button"
                class="btn btn-click-next ml-auto border-0 btn-primary rounded-circle fs-12 p-2 d-center">
                <i class="tio-chevron-right fs-24"></i>
            </button>
        </div>
    </div>
</div>
