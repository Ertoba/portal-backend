<div class="card card-body recent-transactions-card">
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap border-0 recent-transaction-header">
        <div>
            <h3 class="mb-20">{{ translate('messages.Recent_Transactions') }}</h3>
            <div class="js-nav-scroller hs-nav-scroller-horizontal">
                <ul class="nav nav-tabs border-0 nav--tabs nav--pills">
                    <li class="nav-item">
                        <a class="nav-link active rental-admin-transaction-tab" data-type="order" href="#rental-admin-earning-tab" data-toggle="pill">
                            {{ translate('messages.Earnings') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link rental-admin-transaction-tab" data-type="expense" href="#rental-admin-expense-tab" data-toggle="pill">
                            {{ translate('messages.Expenses') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link rental-admin-transaction-tab" data-type="subscription" href="#rental-admin-subscription-tab" data-toggle="pill">
                            {{ translate('messages.Subscription') }}
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="search--button-wrapper justify-content-end">
            <form id="rental-admin-transaction-search-form" class="">
                <div class="input--group input-group input-group-merge input-group-flush">
                    <input id="rental-admin-transaction-search" type="search" name="report_search" class="form-control" value=""
                        placeholder="{{ translate('messages.Search_by_Provider') }}" aria-label="Search">
                    <button type="submit" class="btn btn--secondary">
                        <i class="tio-search"></i>
                    </button>
                </div>
            </form>
            <div class="d-flex flex-wrap gpa-3 justify-content-sm-end align-items-sm-center ml-0 mr-0 flex-grow-0">
                <div class="hs-unfold ml-3">
                    <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle btn export-btn font--sm"
                        href="javascript:;"
                        data-hs-unfold-options='{"target": "#rentalAdminUsersExportDropdown", "type": "css-animation","boundary": "viewport"}'>
                        <i class="tio-download-to mr-1"></i> {{ translate('export') }}
                    </a>
                    <div id="rentalAdminUsersExportDropdown" class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                        <span class="dropdown-header">{{ translate('download_options') }}</span>
                        <a id="rental-admin-export-excel" class="dropdown-item" href="javascript:;">
                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                alt="excel">
                            {{ translate('messages.excel') }}
                        </a>
                        <a id="rental-admin-export-csv" class="dropdown-item" href="javascript:;">
                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                alt="csv">
                            .{{ translate('messages.csv') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-content mt-4">
        <div class="tab-pane fade show active" id="rental-admin-earning-tab">
            @include('rental::admin.report.earning-report.partials.recent-transactions._earning')
        </div>
        <div class="tab-pane fade" id="rental-admin-expense-tab">
            @include('rental::admin.report.earning-report.partials.recent-transactions._expense')
        </div>
        <div class="tab-pane fade" id="rental-admin-subscription-tab">
            @include('rental::admin.report.earning-report.partials.recent-transactions._subscription')
        </div>
    </div>
</div>
