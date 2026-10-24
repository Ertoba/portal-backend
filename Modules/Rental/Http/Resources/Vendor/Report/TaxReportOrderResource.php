<?php

namespace Modules\Rental\Http\Resources\Vendor\Report;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class TaxReportOrderResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource->id,
            'trip_amount' => $this->resource->trip_amount,
            'tax_amount' => $this->resource->tax_amount,
            'trip_type' => $this->resource->trip_type,
            'created_at' => $this->resource->created_at,
            'trip_status' => $this->resource->trip_status,
            'payment_status' => $this->resource->payment_status,
            'order_taxes' => $this->whenLoaded('orderTaxes'),
        ]);
    }
}
