<?php

namespace App\Http\Middleware;

use App\Services\MiliEntitlementService;
use Closure;
use Illuminate\Http\Request;

class ActivationCheckMiddleware
{
    public function __construct(
        private MiliEntitlementService $entitlements
    ) {
    }

    public function handle(Request $request, Closure $next, $area = null): mixed
    {
        if (!$this->entitlements->enabled($area)) {
            if ($request->is('api/v1/*')) {
                return response()->json([
                    'code' => 503,
                    'message' => 'Mili feature is disabled: ' . str_replace('_', ' ', (string) $area),
                ], 503);
            }

            abort(503, 'This Mili feature is currently disabled.');
        }

        return $next($request);
    }
}
