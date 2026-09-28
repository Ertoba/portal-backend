<?php

namespace Modules\Rental\Http\Controllers\Web\Admin;

use App\Exports\AdminEarningTransactionExport;
use App\Http\Controllers\Controller;
use App\Traits\ReportGeneratorTrait;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Rental\Traits\RentalReportGeneratorTrait;

class AdminEarningReportController extends Controller
{
    use ReportGeneratorTrait, RentalReportGeneratorTrait;

    private function resolveModuleId(Request $request): string
    {
        return $request->query('module_id', 'all');
    }

    public function getAdminEarningSummary(Request $request)
    {
        [$filter, $from, $to] = $this->resolveDateFilter($request);
        $module_id = $this->resolveModuleId($request);
        $summary = $this->buildRentalAdminEarningSummary($filter, $from, $to, $module_id);

        return response()->json([
            'view' => view('rental::admin.report.earning-report.partials.render._earning-summary', compact('summary'))->render(),
        ]);
    }

    public function getAdminEarningBreakdown(Request $request)
    {
        [$filter, $from, $to] = $this->resolveDateFilter($request);
        $module_id = $this->resolveModuleId($request);
        $earnings = $this->buildRentalAdminEarningBreakdown($filter, $from, $to, $module_id);

        return response()->json([
            'view' => view('rental::admin.report.earning-report.partials.render._earning-breakdown', compact('earnings'))->render(),
            'earnings' => $earnings,
        ]);
    }

    public function getAdminExpenseBreakdown(Request $request)
    {
        [$filter, $from, $to] = $this->resolveDateFilter($request);
        $module_id = $this->resolveModuleId($request);
        $expenses = $this->buildRentalAdminExpenseBreakdown($filter, $from, $to, $module_id);

        return response()->json([
            'view' => view('rental::admin.report.earning-report.partials.render._expense-breakdown', compact('expenses'))->render(),
        ]);
    }

    public function getMonthlyEarningsReport(Request $request)
    {
        [$filter, $from, $to] = $this->resolveDateFilter($request);
        $module_id = $this->resolveModuleId($request);

        return response()->json($this->getRentalAdminTrendData($filter, $from, $to, $module_id));
    }

    public function getTopEarningProviders(Request $request)
    {
        [$filter, $from, $to] = $this->resolveDateFilter($request);
        $module_id = $this->resolveModuleId($request);
        $topProviders = $this->getRentalAdminTopEarningProviders($filter, $from, $to, $module_id);

        return response()->json([
            'view' => view('rental::admin.report.earning-report.partials.render._top-earning-provider', compact('topProviders'))->render(),
        ]);
    }

    public function getZoneWiseEarnings(Request $request)
    {
        [$filter, $from, $to] = $this->resolveDateFilter($request);
        $module_id = $this->resolveModuleId($request);
        $topZones = $this->getRentalAdminZoneWiseEarnings($filter, $from, $to, $module_id);

        return response()->json([
            'view' => view('rental::admin.report.earning-report.partials.render._zone-wise-earnings', compact('topZones'))->render(),
        ]);
    }

    public function getEarningTransactions(Request $request)
    {
        [$filter, $from, $to] = $this->resolveDateFilter($request);
        $module_id = $this->resolveModuleId($request);
        $type = $request->query('type', 'order');

        if ($type === 'expense') {
            $transactions = $this->getRentalAdminExpenseTransactions($request, $filter, $from, $to, false, $module_id);
            $view = 'rental::admin.report.earning-report.partials.render._expense-transaction-table';
        } elseif ($type === 'subscription') {
            $transactions = $this->getRentalAdminSubscriptionTransactions($request, $filter, $from, $to, false, $module_id);
            $view = 'rental::admin.report.earning-report.partials.render._subscription-transaction-table';
        } else {
            $transactions = $this->getRentalAdminEarningTransactions($request, $filter, $from, $to, false, $module_id);
            $view = 'rental::admin.report.earning-report.partials.render._earning-transaction-table';
        }

        return response()->json([
            'transactions' => $transactions,
            'view' => view($view, compact('transactions'))->render(),
        ]);
    }

    public function exportEarningTransactions(Request $request)
    {
        [$filter, $from, $to] = $this->resolveDateFilter($request);
        $module_id = $this->resolveModuleId($request);
        $type = $request->query('type', 'order');
        $export_type = $request->query('export_type', 'excel');

        if ($type === 'expense') {
            $transactions = $this->getRentalAdminExpenseTransactions($request, $filter, $from, $to, true, $module_id);
            $title = 'Rental_Admin_Expense_Report';
        } elseif ($type === 'subscription') {
            $transactions = $this->getRentalAdminSubscriptionTransactions($request, $filter, $from, $to, true, $module_id);
            $title = 'Rental_Admin_Subscription_Report';
        } else {
            $transactions = $this->getRentalAdminEarningTransactions($request, $filter, $from, $to, true, $module_id);
            $title = 'Rental_Admin_Earning_Report';
        }

        $data = [
            'transactions' => $transactions,
            'filter' => $filter,
            'from' => $from,
            'to' => $to,
            'search' => $request->query('search'),
            'title' => $title,
            'type' => $type,
        ];

        if ($export_type === 'csv') {
            return Excel::download(new AdminEarningTransactionExport($data), $title . '.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download(new AdminEarningTransactionExport($data), $title . '.xlsx', \Maatwebsite\Excel\Excel::XLSX);
    }
}
