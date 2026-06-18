<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('addon_settings')) {
            return;
        }

        $setting = DB::table('addon_settings')
            ->where('key_name', 'keepz')
            ->where('settings_type', 'payment_config')
            ->first();

        if (!$setting) {
            return;
        }

        DB::table('addon_settings')
            ->where('id', $setting->id)
            ->update([
                'live_values' => $this->withSplitConfig($setting->live_values),
                'test_values' => $this->withSplitConfig($setting->test_values),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('addon_settings')) {
            return;
        }

        $setting = DB::table('addon_settings')
            ->where('key_name', 'keepz')
            ->where('settings_type', 'payment_config')
            ->first();

        if (!$setting) {
            return;
        }

        DB::table('addon_settings')
            ->where('id', $setting->id)
            ->update([
                'live_values' => $this->withoutSplitConfig($setting->live_values),
                'test_values' => $this->withoutSplitConfig($setting->test_values),
                'updated_at' => now(),
            ]);
    }

    private function withSplitConfig(?string $values): string
    {
        $decoded = json_decode($values ?: '[]', true);
        $decoded = is_array($decoded) ? $decoded : [];

        $decoded['split_status'] = '1';
        $decoded['split_fallback_to_main_receiver'] = '0';

        return json_encode($decoded);
    }

    private function withoutSplitConfig(?string $values): string
    {
        $decoded = json_decode($values ?: '[]', true);
        $decoded = is_array($decoded) ? $decoded : [];

        unset($decoded['split_status'], $decoded['split_fallback_to_main_receiver']);

        return json_encode($decoded);
    }
};
