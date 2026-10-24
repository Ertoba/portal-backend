<?php

namespace Modules\Rental\Http\Controllers\Api\V1\Vendor\Report;

use App\Http\Controllers\Api\BaseApiController;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Modules\Rental\Http\Requests\Vendor\Report\TaxReportRequest;
use Modules\Rental\Http\Resources\Vendor\Report\TaxReportResource;
use Modules\Rental\Services\Trip\TripService;

class TaxReportController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly TripService $tripService
    ) {
    }

    public function index(TaxReportRequest $request): JsonResponse
    {
        $report = $this->tripService->taxReport(
            $request->filters(),
            $this->pageParams($request)
        );

        return $this->responseFormatter(config('response.default_200'), new TaxReportResource($report));
    }
}
