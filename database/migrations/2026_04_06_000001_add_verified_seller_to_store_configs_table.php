<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = Schema::hasTable('store_configs') ? 'store_configs' : 'storeConfigs';
        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (!Schema::hasColumn($tableName, 'verified_seller')) {
                $table->boolean('verified_seller')->default(0);
            }
            if (!Schema::hasColumn($tableName, 'has_seen_verified_badge_popup')) {
                $table->boolean('has_seen_verified_badge_popup')->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(Schema::hasTable('store_configs') ? 'store_configs' : 'storeConfigs', function (Blueprint $table) {
            $table->dropColumn('verified_seller');
            $table->dropColumn('has_seen_verified_badge_popup');
        });
    }
};
