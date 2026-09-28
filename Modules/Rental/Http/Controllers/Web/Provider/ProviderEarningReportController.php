<?php

namespace Modules\Rental\Http\Controllers\Web\Provider;

use App\CentralLogics\Helpers;
use App\Exports\StoreEarningTransactionExport;
use App\Http\Controllers\Controller;
use App\Traits\ReportGeneratorTrait;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Rental\Traits\RentalReportGeneratorTrait;

class ProviderEarningReportController extends Controller
{
    use ReportGeneratorTrait, RentalReportGeneratorTrait;

    private function resolveModuleId(Request $request): string
    {
        return $request->query('module_id', 'all');
    }

    private function resolveProviderId(): string
    {
        return (string) (Helpers::get_store_id() ?: 'all');
    }

    public function index(Request $request)
    {
        $store = Helpers::get_store_data();
        $store_id = $store?->id ?? 'all';
        $module_id = $this->resolveModuleId($request);

        return view('rental::provider.report.earning-report.index', compact('store', 'store_id', 'module_id'));
    }

    public function getEarningSummary(Request $request)
    {
        [$filter, $from, $to] = $this->resolveDateFilter($request);
        $summary = $this->buildRentalProviderSummary(
            $this->resolveProviderId(),
            $filter,
            $from,
            $to,
            $this->resolveModuleId($request)
        );

        return response()->json([
            'view' => view('rental::provider.report.earning-report.partials.render._earning-summary', compact('summary'))->render(),
        ]);
    }

    public function getEarningBreakdown(Request $request)
    {
        [$filter, $from, $to] = $this->resolveDateFilter($request);
        $breakdown = $this->buildRentalProviderEarningBreakdown(
            $this->resolveProviderId(),
            $filter,
            $from,
            $to,
            $this->resolveModuleId($request)
        );

        return response()->json([
            'view' => view('rental::provider.report.earning-report.partials.render._earning-breakdown', compact('breakdown'))->render(),
        ]);
    }

    public function getExpenseBreakdown(Request $request)
    {
        [$filter, $from, $to] = $this->resolveDateFilter($request);
        $expenses = $this->buildRentalProviderExpenseBreakdown(
            $this->resolveProviderId(),
            $filter,
            $from,
            $to,
            $this->resolveModuleId($request)
        );

        return response()->json([
            'view' => view('rental::provider.report.earning-report.partials.render._expense-breakdown', compact('expenses'))->render(),
        ]);
    }

    public function getEarningTrend(Request $request)
    {
        [$filter, $from, $to] = $this->resolveDateFilter($request);

        return response()->json(
            $this->getRentalProviderTrendData(
                $this->resolveProviderId(),
                $filter,
                $from,
                $to,
                $this->resolveModuleId($request)
            )
        );
    }

    public function getEarningTransactions(Request $request)
    {
        [$filter, $from, $to] = $this->resolveDateFilter($request);
        $providerId = $this->resolveProviderId();
        $moduleId = $this->resolveModuleId($request);
        $type = $this->rental_provider_transaction_type($request->query('type', 'order'));

        if ($type === 'expense') {
            $transactions = $this->getRentalProviderExpenseTransactions($request, $providerId, $filter, $from, $to, false, null, null, $moduleId);
            $view = 'rental::provider.report.earning-report.partials.render._expense-transaction-table';
        } elseif ($type === 'subscription') {
            $transactions = $this->getRentalProviderSubscriptionTransactions($request, $providerId, $filter, $from, $to, false, null, null, $moduleId);
            $view = 'rental::provider.report.earning-report.partials.render._subscription-transaction-table';
        } else {
            $transactions = $this->getRentalProviderEarningTransactions($request, $providerId, $filter, $from, $to, false, null, null, $moduleId);
            $view = 'rental::provider.report.earning-report.partials.render._earning-transaction-table';
        }

        $hide_source_column = true;

        return response()->json([
            'transactions' => $transactions,
            'view' => view($view, [
                'transactions' => $transactions,
                'trip_details_route_name' => 'vendor.trip.details',
                'hide_source_column' => $hide_source_column,
            ])->render(),
        ]);
    }

    public function exportEarningTransactions(Request $request)
    {
        [$filter, $from, $to] = $this->resolveDateFilter($request);
        $providerId = $this->resolveProviderId();
        $moduleId = $this->resolveModuleId($request);
        $type = $this->rental_provider_transaction_type($request->query('type', 'order'));
        $exportType = $request->query('export_type', 'excel');
        $store = Helpers::get_store_data();

        if ($type === 'expense') {
            $transactions = $this->getRentalProviderExpenseTransactions($request, $providerId, $filter, $from, $to, true, null, null, $moduleId);
            $title = 'Rental_Provider_Expense_Report';
        } elseif ($type === 'subscription') {
            $transactions = $this->getRentalProviderSubscriptionTransactions($request, $providerId, $filter, $from, $to, true, null, null, $moduleId);
            $title = 'Rental_Provider_Subscription_Report';
        } else {
            $transactions = $this->getRentalProviderEarningTransactions($request, $providerId, $filter, $from, $to, true, null, null, $moduleId);
            $title = 'Rental_Provider_Earning_Report';
        }

        $data = [
            'transactions' => $transactions,
            'filter' => $filter,
            'from' => $from,
            'to' => $to,
            'search' => $request->query('search'),
            'title' => $title,
            'store_name' => $store?->name ?? 'Provider',
            'type' => $type === 'order' ? 'order' : $type,
        ];

        if ($exportType === 'csv') {
            return Excel::download(new StoreEarningTransactionExport($data), $title . '.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download(new StoreEarningTransactionExport($data), $title . '.xlsx', \Maatwebsite\Excel\Excel::XLSX);
    }
}
