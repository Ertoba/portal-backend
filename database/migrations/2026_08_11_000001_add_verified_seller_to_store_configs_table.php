<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $storeConfigsTable = $this->storeConfigsTable();
        if ($storeConfigsTable && !Schema::hasColumn($storeConfigsTable, 'verified_seller')) {
            Schema::table($storeConfigsTable, function (Blueprint $table) {
                $table->boolean('verified_seller')->default(false)->after('is_recommended_deleted');
            });
        }

        if (Schema::hasTable('business_settings')
            && !DB::table('business_settings')->where('key', 'verified_seller_badge')->exists()) {
            DB::table('business_settings')->insert([
                'key' => 'verified_seller_badge',
                'value' => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $storeConfigsTable = $this->storeConfigsTable();
        if ($storeConfigsTable && Schema::hasColumn($storeConfigsTable, 'verified_seller')) {
            Schema::table($storeConfigsTable, function (Blueprint $table) {
                $table->dropColumn('verified_seller');
            });
        }
    }

    private function storeConfigsTable(): ?string
    {
        if (Schema::hasTable('storeConfigs')) {
            return 'storeConfigs';
        }

        return Schema::hasTable('store_configs') ? 'store_configs' : null;
    }
};
