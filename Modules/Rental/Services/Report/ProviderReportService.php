<?php

namespace Modules\Rental\Services\Report;

use App\Services\BaseService;
use App\Traits\Report\ReportGeneratorTrait;
use Modules\Rental\Traits\RentalReportGeneratorTrait;

class ProviderReportService extends BaseService
{
    use ReportGeneratorTrait, RentalReportGeneratorTrait;

    public function earningReport(array $filters = [], array $paginate = []): array
    {
        $providerId = $filters['provider_id'] ?? null;
        $filter = $filters['filter'] ?? 'all_time';
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $moduleId = $filters['module_id'] ?? 'all';
        $search = $filters['search'] ?? null;
        $type = $this->rental_provider_transaction_type($filters['type'] ?? null);
        $limit = $this->pageSize($paginate);
        $offset = $this->pageNumber($paginate);

        $earning = $type === 'order'
            ? $this->getRentalProviderEarningTransactions($search, $providerId, $filter, $from, $to, false, $limit, $offset, $moduleId)
            : null;
        $expense = $type === 'expense'
            ? $this->getRentalProviderExpenseTransactions($search, $providerId, $filter, $from, $to, false, $limit, $offset, $moduleId)
            : null;
        $subscription = $type === 'subscription'
            ? $this->getRentalProviderSubscriptionTransactions($search, $providerId, $filter, $from, $to, false, $limit, $offset, $moduleId)
            : null;

        return [
            'summary' => $this->buildRentalProviderSummary($providerId, $filter, $from, $to, $moduleId),
            'earning_breakdown' => $this->buildRentalProviderEarningBreakdown($providerId, $filter, $from, $to, $moduleId),
            'expense_breakdown' => $this->buildRentalProviderExpenseBreakdown($providerId, $filter, $from, $to, $moduleId),
            'trend' => $this->getRentalProviderTrendData($providerId, $filter, $from, $to, $moduleId),
            'recent_transactions' => [
                'active_type' => $type === 'order' ? 'earning' : $type,
                'earning' => $earning ? $this->rental_provider_format_paginated_transactions($earning, $limit, $offset) : null,
                'expense' => $expense ? $this->rental_provider_format_paginated_transactions($expense, $limit, $offset) : null,
                'subscription' => $subscription ? $this->rental_provider_format_paginated_transactions($subscription, $limit, $offset) : null,
            ],
            'total_size' => match ($type) {
                'order' => $earning?->total() ?? 0,
                'expense' => $expense?->total() ?? 0,
                'subscription' => $subscription?->total() ?? 0,
                default => 0,
            },
            'limit' => $limit,
            'offset' => $offset,
        ];
    }
}
