@extends('layouts.vendor.app')

@section('title', translate('Earning report'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div>
                <h1 class="page-header-title text-capitalize">
                    <span class="page-header-icon">
                        <img src="{{ asset('public/assets/admin/img/outline/report.svg') }}" class="w--26" alt="">
                    </span>
                    <span>
                        {{ translate('Earning report') }}
                    </span>
                </h1>
                <p class="page-header-desc">{{ translate('What you earned, and what was taken from it in commission.') }}</p>
            </div>
        </div>

        @include('rental::provider.report.earning-report.content', [
            'report_url' => route('vendor.report.earning-report'),
            'summary_url' => route('vendor.report.earning-summary'),
            'breakdown_url' => route('vendor.report.earning-breakdown'),
            'expense_url' => route('vendor.report.expense-breakdown'),
            'trend_url' => route('vendor.report.earning-trend'),
            'reset_url' => route('vendor.report.earning-report'),
            'transactions_export_url' => route('vendor.report.earning-export'),
            'transactions_url' => route('vendor.report.earning-transactions'),
            'show_store_select' => false,
            'store' => $store,
            'store_id' => $store_id,
            'module_id' => $module_id,
        ])
    </div>
@endsection
