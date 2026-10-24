<?php

namespace Modules\Rental\Http\Requests\Vendor\Report;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;
use Carbon\Carbon;

class TaxReportRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return [
            'limit' => 'nullable|integer|min:1',
            'offset' => 'nullable|integer|min:1',
            'from' => 'required|date_format:m/d/Y',
            'to' => 'required|date_format:m/d/Y',
            'search' => 'nullable|string|max:255',
        ];
    }

    public function filters(): array
    {
        return [
            'provider_id' => $this->vendorStoreId($this),
            'from' => Carbon::createFromFormat('m/d/Y', trim($this->input('from')))->startOfDay(),
            'to' => Carbon::createFromFormat('m/d/Y', trim($this->input('to')))->endOfDay(),
            'search' => $this->input('search'),
        ];
    }
}
