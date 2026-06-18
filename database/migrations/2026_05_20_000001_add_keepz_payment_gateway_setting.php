<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('addon_settings')) {
            return;
        }

        $exists = DB::table('addon_settings')
            ->where('key_name', 'keepz')
            ->where('settings_type', 'payment_config')
            ->exists();

        if ($exists) {
            return;
        }

        $credentials = [
            'gateway' => 'keepz',
            'mode' => 'test',
            'status' => '0',
            'identifier' => '',
            'integrator_id' => '',
            'receiver_id' => '',
            'receiver_type' => 'BRANCH',
            'keepz_public_key' => '',
            'integrator_private_key' => '',
            'split_status' => '1',
            'split_fallback_to_main_receiver' => '0',
        ];

        DB::table('addon_settings')->insert([
            'id' => (string) Str::uuid(),
            'key_name' => 'keepz',
            'live_values' => json_encode($credentials),
            'test_values' => json_encode($credentials),
            'settings_type' => 'payment_config',
            'mode' => 'test',
            'is_active' => 0,
            'created_at' => now(),
            'updated_at' => now(),
            'additional_data' => json_encode([
                'gateway_title' => 'Keepz',
                'gateway_image' => '',
                'storage' => 'public',
            ]),
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('addon_settings')) {
            return;
        }

        DB::table('addon_settings')
            ->where('key_name', 'keepz')
            ->where('settings_type', 'payment_config')
            ->delete();
    }
};
