<?php

namespace Modules\Rental\Http\Resources\Vendor\Report;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class TaxReportResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'total_size' => $this->resource['orders']->total(),
            'limit' => $this->resource['orders']->perPage(),
            'offset' => $this->resource['orders']->currentPage(),
            'taxSummary' => $this->resource['taxSummary'],
            'totalOrders' => $this->resource['totalOrders'],
            'totalOrderAmount' => $this->resource['totalOrderAmount'],
            'totalTax' => $this->resource['totalTax'],
            'orders' => TaxReportOrderResource::collection($this->resource['orders']->items()),
        ]);
    }
}
