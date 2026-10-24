<?php

namespace Modules\Rental\Http\Controllers\Api\V1\Vendor\Report;

use App\Http\Controllers\Api\BaseApiController;
use App\Traits\Api\ApiRequestContextTrait;
use App\Traits\Report\ReportGeneratorTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Rental\Http\Resources\Vendor\Report\EarningReportResource;
use Modules\Rental\Services\Report\ProviderReportService;

class EarningReportController extends BaseApiController
{
    use ApiRequestContextTrait, ReportGeneratorTrait;

    public function __construct(
        private readonly ProviderReportService $providerReportService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $report = $this->providerReportService->earningReport(
            $this->reportFilters($request),
            $this->pageParams($request)
        );

        return $this->responseFormatter(config('response.default_200'), new EarningReportResource($report));
    }

    private function reportFilters(Request $request): array
    {
        [$filter, $from, $to] = $this->resolveDateFilter($request);

        return [
            'provider_id' => $this->vendorStoreId($request),
            'filter' => $filter,
            'from' => $from,
            'to' => $to,
            'module_id' => $request->query('module_id', 'all'),
            'type' => $request->query('type', 'earning'),
            'search' => $request->query('search'),
        ];
    }
}
