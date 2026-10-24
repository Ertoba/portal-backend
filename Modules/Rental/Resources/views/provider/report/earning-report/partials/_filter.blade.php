<div class="card card-body mb-20">
    <h3 class="mb-20">{{ translate('Filter data') }}</h3>
    <form method="GET" action="{{ $report_url }}">
        @if(!empty($tab))
            <input type="hidden" name="tab" value="{{ $tab }}">
        @endif
        <div class="__bg-F8F9FC-card">
            <div class="row g-3 date-filter-wrapper">
                @if(!empty($show_store_select))
                    <div class="col-lg-4 col-sm-6">
                        <label class="input-label text-capitalize">{{ translate('Select provider') }}</label>
                        <select name="store_id" id="rental_provider_store_id" data-get-provider-url="{{ route('admin.store.get-providers') }}" data-placeholder="{{ translate('Select store') }}" class="js-data-example-ajax form-control custom-select">
                            @if (isset($store))
                                <option value="{{ $store->id }}" data-verified="{{ (int) $store->verified_seller }}" selected>{{ $store->name }}</option>
                            @else
                                <option value="all" selected>{{ translate('All provider') }}</option>
                            @endif
                        </select>
                    </div>
                @else
                    <input type="hidden" name="store_id" id="rental_provider_store_id" value="{{ $store_id ?? 'all' }}">
                @endif
                <div class="col-lg-{{ !empty($show_store_select) ? '4' : '8' }} col-sm-6">
                    <label class="input-label text-capitalize">{{ translate('Date range') }}</label>
                    <select name="filter" id="rental_provider_filter" class="form-control custom-select date-type-select">
                        <option value="all_time" {{ request('filter', 'all_time') === 'all_time' ? 'selected' : '' }}>{{ translate('All time') }}</option>
                        <option value="this_week" {{ request('filter') === 'this_week' ? 'selected' : '' }}>{{ translate('This week') }}</option>
                        <option value="this_month" {{ request('filter') === 'this_month' ? 'selected' : '' }}>{{ translate('This month') }}</option>
                        <option value="this_year" {{ request('filter') === 'this_year' ? 'selected' : '' }}>{{ translate('This year') }}</option>
                        <option value="previous_year" {{ request('filter') === 'previous_year' ? 'selected' : '' }}>{{ translate('Previous year') }}</option>
                        <option value="custom" {{ request('filter') === 'custom' ? 'selected' : '' }}>{{ translate('Custom range') }}</option>
                    </select>
                </div>
                <div class="col-lg-6 custom-date-div {{ request('filter') === 'custom' ? '' : 'd--none' }}">
                    <label class="input-label text-capitalize">{{ translate('Start date') }} <span class="text-danger">*</span></label>
                    <input type="date" name="from" id="rental_provider_start_date" value="{{ request('from') }}" class="form-control">
                </div>
                <div class="col-lg-6 custom-date-div {{ request('filter') === 'custom' ? '' : 'd--none' }}">
                    <label class="input-label text-capitalize">{{ translate('End date') }} <span class="text-danger">*</span></label>
                    <input type="date" name="to" id="rental_provider_end_date" value="{{ request('to') }}" class="form-control">
                </div>
            </div>
        </div>
        <div class="btn--container mt-4 justify-content-end">
            <a href="{{ $reset_url }}" class="btn btn--reset">{{ translate('messages.Reset') }}</a>
            <button type="submit" class="btn btn--primary">{{ translate('messages.Filter') }}</button>
        </div>
    </form>
</div>
