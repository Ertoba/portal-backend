<div class="card card-body mb-20">
    <h3 class="mb-20">{{ translate('Filter data') }}</h3>
    <form method="GET" action="{{ route('admin.transactions.report.admin-earning-report') }}">
        <input type="hidden" name="tab" value="rental">
        <input type="hidden" name="module_id" value="all">
        <div class="__bg-F8F9FC-card">
            <div class="row g-3 date-filter-wrapper">
                <div class="col-lg-4 col-sm-6">
                    <label class="input-label text-capitalize">{{ translate('Date range') }}</label>
                    <select name="filter" id="rental_admin_filter" class="form-control custom-select date-type-select">
                        <option value="all_time" {{ request('filter', 'all_time') === 'all_time' ? 'selected' : '' }}>{{ translate('All time') }}</option>
                        <option value="this_week" {{ request('filter') === 'this_week' ? 'selected' : '' }}>{{ translate('This week') }}</option>
                        <option value="this_month" {{ request('filter') === 'this_month' ? 'selected' : '' }}>{{ translate('This month') }}</option>
                        <option value="this_year" {{ request('filter') === 'this_year' ? 'selected' : '' }}>{{ translate('This year') }}</option>
                        <option value="previous_year" {{ request('filter') === 'previous_year' ? 'selected' : '' }}>{{ translate('Previous year') }}</option>
                        <option value="custom" {{ request('filter') === 'custom' ? 'selected' : '' }}>{{ translate('Custom range') }}</option>
                    </select>
                </div>
                <div class="col-lg-4 col-sm-6 custom-date-div {{ request('filter') === 'custom' ? '' : 'd--none' }}">
                    <label class="input-label text-capitalize">{{ translate('Start date') }} <span class="text-danger">*</span></label>
                    <input type="date" id="rental_admin_start_date" name="from" value="{{ request('from') }}" class="form-control">
                </div>
                <div class="col-lg-4 col-sm-6 custom-date-div {{ request('filter') === 'custom' ? '' : 'd--none' }}">
                    <label class="input-label text-capitalize">{{ translate('End date') }} <span class="text-danger">*</span></label>
                    <input type="date" id="rental_admin_end_date" name="to" value="{{ request('to') }}" class="form-control">
                </div>
            </div>
        </div>

        <div class="btn--container mt-4 justify-content-end">
            <a href="{{ $resetUrl }}" class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</a>
            <button type="submit" class="btn btn--primary"><i class="tio-filter-list"></i> {{ translate('messages.Filter') }}</button>
        </div>
    </form>
</div>
