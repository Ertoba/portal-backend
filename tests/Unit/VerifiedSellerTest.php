<?php

namespace Tests\Unit;

use App\CentralLogics\Helpers;
use App\Models\Store;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class VerifiedSellerTest extends TestCase
{
    protected function tearDown(): void
    {
        Config::forget('verified_seller_badge_conf');

        parent::tearDown();
    }

    public function test_badge_requires_global_and_store_flags(): void
    {
        $storeConfig = (object) ['verified_seller' => true];

        Config::set('verified_seller_badge_conf', ['value' => 0]);
        $this->assertSame(0, Helpers::get_verified_seller_status(storeConfig: $storeConfig));

        Config::set('verified_seller_badge_conf', ['value' => 1]);
        $this->assertSame(1, Helpers::get_verified_seller_status(storeConfig: $storeConfig));

        $storeConfig->verified_seller = false;
        $this->assertSame(0, Helpers::get_verified_seller_status(storeConfig: $storeConfig));
    }

    public function test_store_accessor_uses_the_same_global_gate(): void
    {
        Config::set('verified_seller_badge_conf', ['value' => 1]);

        $store = new Store();
        $store->setRelation('storeConfig', (object) ['verified_seller' => true]);

        $this->assertSame(1, $store->verified_seller);
    }

    public function test_admin_update_route_is_post_only(): void
    {
        $route = app('router')->getRoutes()->getByName('admin.store.verified-seller');

        $this->assertNotNull($route);
        $this->assertSame(['POST'], $route->methods());
    }
}
