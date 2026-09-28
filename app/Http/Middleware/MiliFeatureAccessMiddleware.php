<?php

namespace App\Http\Middleware;

use App\Services\MiliEntitlementService;
use Closure;
use Illuminate\Http\Request;

class MiliFeatureAccessMiddleware
{
    public function __construct(
        private MiliEntitlementService $features
    ) {
    }

    public function handle(
        Request $request,
        Closure $next,
        ?string $area = null
    ): mixed {
        if (!$this->features->enabled($area)) {
            if ($request->is('api/v1/*')) {
                return response()->json([
                    'code' => 503,
                    'message' => 'Mili feature is disabled: ' .
                        str_replace('_', ' ', (string) $area),
                ], 503);
            }

            abort(503, 'This Mili feature is currently disabled.');
        }

        return $next($request);
    }
}
