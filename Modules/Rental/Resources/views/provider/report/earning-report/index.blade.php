@extends('layouts.vendor.app')

@section('title', translate('Earning Report'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header pb-0">
            <div>
                <h1 class="page-header-title text-capitalize">
                    {{ translate('Earning Report') }}
                </h1>
                <p>{{ translate('messages.Comprehensive_financial_overview_and_analytics') }}</p>
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
