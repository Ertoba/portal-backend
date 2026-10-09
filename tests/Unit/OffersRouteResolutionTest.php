<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\V1\Customer\Item\ItemController;
use Illuminate\Http\Request;
use Tests\TestCase;

class OffersRouteResolutionTest extends TestCase
{
    /**
     * Legacy V4 aliases must not shadow the real item/store offer queries.
     * Resolving routes here performs no DB writes or live API calls.
     */
    public function test_offer_items_resolves_real_v42_controller(): void
    {
        $route = app('router')->getRoutes()->match(Request::create('/api/v1/offers/items', 'GET'));

        $this->assertSame(ItemController::class . '@offerItems', $route->getActionName());
    }

    public function test_offer_stores_resolves_real_v42_controller(): void
    {
        $route = app('router')->getRoutes()->match(Request::create('/api/v1/offers/stores', 'GET'));

        $this->assertSame(ItemController::class . '@offerStores', $route->getActionName());
    }
}
