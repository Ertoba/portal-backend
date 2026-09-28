<?php

namespace Modules\Rental\Http\Controllers\Api\Provider;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Traits\ReportGeneratorTrait;
use Illuminate\Http\Request;
use Modules\Rental\Traits\RentalReportGeneratorTrait;

class ProviderEarningReportController extends Controller
{
    use ReportGeneratorTrait, RentalReportGeneratorTrait;

    public function getEarningReport(Request $request)
    {
        $store = Helpers::get_store_data() ?? $request->vendor?->stores?->first();

        if (!$store) {
            return response()->json([
                'message' => translate('messages.unauthorized'),
            ], 401);
        }

        [$filter, $from, $to] = $this->resolveDateFilter($request);
        $limit = $request->query('limit', config('default_pagination', 25));
        $offset = $request->query('offset', 1);
        $type = $request->query('type', 'earning');
        $moduleId = $request->query('module_id', 'all');

        return response()->json(
            $this->buildRentalProviderApiReportPayload(
                $request,
                $store->id,
                $filter,
                $from,
                $to,
                $moduleId,
                $type,
                $limit,
                $offset
            ),
            200
        );
    }
}
