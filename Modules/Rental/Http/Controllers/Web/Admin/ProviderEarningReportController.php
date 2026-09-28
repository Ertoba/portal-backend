<?php

namespace Modules\Rental\Http\Controllers\Web\Admin;

use App\Exports\StoreEarningTransactionExport;
use App\Http\Controllers\Controller;
use App\Models\Store;
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

    private function resolveProviderId(Request $request): string
    {
        return $request->query('store_id', 'all');
    }

    public function getProviderEarningSummary(Request $request)
    {
        [$filter, $from, $to] = $this->resolveDateFilter($request);
        $summary = $this->buildRentalProviderSummary(
            $this->resolveProviderId($request),
            $filter,
            $from,
            $to,
            $this->resolveModuleId($request)
        );

        return response()->json([
            'view' => view('rental::provider.report.earning-report.partials.render._earning-summary', compact('summary'))->render(),
        ]);
    }

    public function getProviderEarningBreakdown(Request $request)
    {
        [$filter, $from, $to] = $this->resolveDateFilter($request);
        $breakdown = $this->buildRentalProviderEarningBreakdown(
            $this->resolveProviderId($request),
            $filter,
            $from,
            $to,
            $this->resolveModuleId($request)
        );

        return response()->json([
            'view' => view('rental::provider.report.earning-report.partials.render._earning-breakdown', compact('breakdown'))->render(),
        ]);
    }

    public function getProviderExpenseBreakdown(Request $request)
    {
        [$filter, $from, $to] = $this->resolveDateFilter($request);
        $expenses = $this->buildRentalProviderExpenseBreakdown(
            $this->resolveProviderId($request),
            $filter,
            $from,
            $to,
            $this->resolveModuleId($request)
        );

        return response()->json([
            'view' => view('rental::provider.report.earning-report.partials.render._expense-breakdown', compact('expenses'))->render(),
        ]);
    }

    public function getProviderEarningTrend(Request $request)
    {
        [$filter, $from, $to] = $this->resolveDateFilter($request);

        return response()->json(
            $this->getRentalProviderTrendData(
                $this->resolveProviderId($request),
                $filter,
                $from,
                $to,
                $this->resolveModuleId($request)
            )
        );
    }

    public function getProviderEarningTransactions(Request $request)
    {
        [$filter, $from, $to] = $this->resolveDateFilter($request);
        $providerId = $this->resolveProviderId($request);
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

        return response()->json([
            'transactions' => $transactions,
            'view' => view($view, [
                'transactions' => $transactions,
                'trip_details_route_name' => 'admin.transactions.rental.trip.details',
            ])->render(),
        ]);
    }

    public function exportProviderEarningTransactions(Request $request)
    {
        [$filter, $from, $to] = $this->resolveDateFilter($request);
        $providerId = $this->resolveProviderId($request);
        $moduleId = $this->resolveModuleId($request);
        $type = $this->rental_provider_transaction_type($request->query('type', 'order'));
        $exportType = $request->query('export_type', 'excel');
        $storeName = is_numeric($providerId) ? (Store::find($providerId)?->name ?? 'All') : 'All';

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
            'store_name' => $storeName,
            'type' => $type === 'order' ? 'order' : $type,
        ];

        if ($exportType === 'csv') {
            return Excel::download(new StoreEarningTransactionExport($data), $title . '.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download(new StoreEarningTransactionExport($data), $title . '.xlsx', \Maatwebsite\Excel\Excel::XLSX);
    }
}
