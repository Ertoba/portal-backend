<div class="d-flex flex-wrap justify-content-between align-items-center tabs-slide-wrap position-relative mb-2 __gap-12px">
    <div class="js-nav-scroller hs-nav-scroller-horizontal mt-2">
        <!-- Nav -->
        <ul class="nav nav-tabs tabs-inner border-0 nav--tabs nav--pills">
            <li class="nav-item tabs-slide_items">
                <a class="nav-link {{ Request::is('admin/business-settings/rental-email-setup/user/new-order') ? 'active' : '' }}"
                href="{{ route('admin.business-settings.rental-email-setup', ['user','new-order']) }}">{{translate('Trip_Booking')}}</a>
            </li>
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
