<?php

namespace Modules\Rental\Http\Resources\Vendor\Report;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class EarningReportResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'summary' => $this->resource['summary'],
            'earning_breakdown' => $this->resource['earning_breakdown'],
            'expense_breakdown' => $this->resource['expense_breakdown'],
            'trend' => $this->resource['trend'],
            'recent_transactions' => $this->resource['recent_transactions'],
            'total_size' => $this->resource['total_size'],
            'limit' => $this->resource['limit'],
            'offset' => $this->resource['offset'],
        ]);
    }
}
