@php
    $filter = request()->filter;
    $showComparison = in_array($filter, ['this_week', 'this_month', 'this_year', 'custom', 'previous_year']);
    $comparisonText = translate('messages.vs last period');
    if ($filter == 'this_week') {
        $comparisonText = translate('messages.vs last week');
    } elseif ($filter == 'this_month') {
        $comparisonText = translate('messages.vs last month');
    } elseif ($filter == 'this_year') {
        $comparisonText = translate('messages.vs last year');
    } elseif ($filter == 'previous_year') {
        $comparisonText = translate('messages.vs two years ago');
    }
@endphp

<div class="row g-3 mb-20">
    <div class="col-lg-4 col-md-6">
        <div class="card-shape-in d-flex position-relative bg-success-gradient text-white rounded-10 p-3 p-xxl-20 gap-2 justify-content-between align-items-start overflow-hidden z-2 h-100 cursor-pointer">
            <div class="flex-grow-1 d-flex flex-column h-100">
                <div>
                    <div class="opacity-lg fs-14 mb-2">{{ translate('Total Earning with Admin Commission') }}</div>
                    @if ($showComparison)
                        <div class="opacity-lg fs-14 mb-2">
                            {{ $summary['total_earning_percentage'] == 0 ? '' : ($summary['total_earning_positive'] ? '↑' : '↓') }}
                            {{ (float) $summary['total_earning_percentage'] }}%
                            {{ $comparisonText }}
                        </div>
                    @endif
                </div>
                <h2 class="font-medium fs-32 fs-18-mobile text-white mt-auto mb-0">
                    {{ \App\CentralLogics\Helpers::format_currency($summary['total_earning'] ?? 0) }}
                </h2>
            </div>
            <div class="mark_badge fs-24 flex-shrink-0 rounded-10 w-48 ratio--1 d-flex justify-content-center align-items-center">
                <img width="24" src="{{ asset('public/assets/admin/img/report/new/earning.png') }}" alt="earning">
            </div>
        </div>
    </div>

    <div class="col-lg-4 col-md-6">
        <div class="card-shape-in d-flex position-relative bg-warning-gradient text-white rounded-10 p-3 p-xxl-20 gap-2 justify-content-between align-items-start overflow-hidden z-2 h-100 cursor-pointer">
            <div class="flex-grow-1 d-flex flex-column h-100">
                <div>
                    <div class="opacity-lg fs-14 mb-2">{{ translate('messages.Total_Expenses') }}</div>
                    @if ($showComparison)
                        <div class="opacity-lg fs-14 mb-2">
                            {{ $summary['total_expense_percentage'] == 0 ? '' : ($summary['total_expense_positive'] ? '↑' : '↓') }}
                            {{ (float) $summary['total_expense_percentage'] }}%
                            {{ $comparisonText }}
                        </div>
                    @endif
                </div>
                <h2 class="font-medium fs-32 fs-18-mobile text-white mt-auto mb-0">
                    {{ \App\CentralLogics\Helpers::format_currency($summary['total_expense'] ?? 0) }}
                </h2>
            </div>
            <div class="mark_badge fs-24 flex-shrink-0 rounded-10 w-48 ratio--1 d-flex justify-content-center align-items-center">
                <img width="24" src="{{ asset('public/assets/admin/img/report/new/earning.png') }}" alt="expense">
            </div>
        </div>
    </div>

    <div class="col-lg-4 col-md-6">
        <div class="card-shape-in d-flex position-relative bg-info-gradient text-white rounded-10 p-3 p-xxl-20 gap-2 justify-content-between align-items-start overflow-hidden z-2 h-100 cursor-pointer">
            <div class="flex-grow-1 d-flex flex-column h-100">
                <div>
                    <div class="opacity-lg fs-14 mb-2">{{ translate('Net Profit') }}
                        <span data-toggle="tooltip" data-placement="right"
                            data-original-title="{{ translate('Net profit shows the amount a store keeps after total earnings are reduced by total expenses.') }}"
                            class="text-white tio-info fs-16 m-0"></span>
                    </div>
                    @if ($showComparison)
                        <div class="opacity-lg fs-14 mb-2">
                            {{ $summary['net_income_percentage'] == 0 ? '' : ($summary['net_income_positive'] ? '↑' : '↓') }}
                            {{ (float) $summary['net_income_percentage'] }}%
                            {{ $comparisonText }}
                        </div>
                    @endif
                </div>
                <h2 class="font-medium fs-32 fs-18-mobile text-white mt-auto mb-0">
                    {{ \App\CentralLogics\Helpers::format_currency($summary['net_income'] ?? 0) }}
                </h2>
            </div>
            <div class="mark_badge fs-24 flex-shrink-0 rounded-10 w-48 ratio--1 d-flex justify-content-center align-items-center">
                <img width="24" src="{{ asset('public/assets/admin/img/report/new/wallet.png') }}" alt="net-income">
            </div>
        </div>
    </div>
</div>
