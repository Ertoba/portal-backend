<div class="card card-body mb-20">
    <h3 class="mb-20">{{ translate('messages.Filter_Data') }}</h3>
    <form method="GET" action="{{ route('admin.transactions.report.admin-earning-report') }}">
        <input type="hidden" name="tab" value="rental">
        <input type="hidden" name="module_id" value="all">
        <div class="__bg-F8F9FC-card">
            <div class="row g-3 date-filter-wrapper">
                <div class="col-lg-4 col-sm-6">
                    <label class="input-label text-capitalize">{{ translate('messages.Date_Range') }}</label>
                    <select name="filter" id="rental_admin_filter" class="form-control custom-select date-type-select">
                        <option value="all_time" {{ request('filter', 'all_time') === 'all_time' ? 'selected' : '' }}>{{ translate('messages.All_Time') }}</option>
                        <option value="this_week" {{ request('filter') === 'this_week' ? 'selected' : '' }}>{{ translate('messages.This_Week') }}</option>
                        <option value="this_month" {{ request('filter') === 'this_month' ? 'selected' : '' }}>{{ translate('messages.This_Month') }}</option>
                        <option value="this_year" {{ request('filter') === 'this_year' ? 'selected' : '' }}>{{ translate('messages.This_Year') }}</option>
                        <option value="previous_year" {{ request('filter') === 'previous_year' ? 'selected' : '' }}>{{ translate('messages.Previous_Year') }}</option>
                        <option value="custom" {{ request('filter') === 'custom' ? 'selected' : '' }}>{{ translate('messages.Custom_Range') }}</option>
                    </select>
                </div>
                <div class="col-lg-4 col-sm-6 custom-date-div {{ request('filter') === 'custom' ? '' : 'd--none' }}">
                    <label class="input-label text-capitalize">{{ translate('messages.Start_Date') }} <span class="text-danger">*</span></label>
                    <input type="date" id="rental_admin_start_date" name="from" value="{{ request('from') }}" class="form-control">
                </div>
                <div class="col-lg-4 col-sm-6 custom-date-div {{ request('filter') === 'custom' ? '' : 'd--none' }}">
                    <label class="input-label text-capitalize">{{ translate('messages.End_Date') }} <span class="text-danger">*</span></label>
                    <input type="date" id="rental_admin_end_date" name="to" value="{{ request('to') }}" class="form-control">
                </div>
            </div>
        </div>

        <div class="btn--container mt-4 justify-content-end">
            <a href="{{ $resetUrl }}" class="btn btn--reset">{{ translate('messages.reset') }}</a>
            <button type="submit" class="btn btn--primary">{{ translate('messages.filter') }}</button>
        </div>
    </form>
</div>
