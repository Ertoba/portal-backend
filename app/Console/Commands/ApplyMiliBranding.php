<?php

namespace App\Console\Commands;

use App\Models\BusinessSetting;
use App\Support\Cache\ApiCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Safe, explicit update of MILI public logo/icon settings.
 * Run without --apply first to inspect; original settings are backed up.
 */
class ApplyMiliBranding extends Command
{
    protected $signature = 'mili:apply-branding {--apply : Apply the branding after a safe dry run}';

    protected $description = 'Synchronize MILI public logo and icon settings (dry run by default)';

    public function handle(): int
    {
        $assets = [
            'logo' => ['source' => public_path('assets/admin/img/6m-logo.png'), 'file' => 'mili-logo-20261013.png'],
            'icon' => ['source' => public_path('assets/admin/img/favicon.png'), 'file' => 'mili-icon-20261013.png'],
        ];

        foreach ($assets as $key => $data) {
            if (! is_file($data['source']) || @getimagesize($data['source']) === false) {
                $this->error("Missing or invalid brand PNG for {$key}: {$data['source']}");
                return self::FAILURE;
            }
            $this->line("{$key}: business/{$data['file']}");
        }

        if (! $this->option('apply')) {
            $this->info('Dry run only. Add --apply after verifying generated PNG assets.');
            return self::SUCCESS;
        }

        $original = [];
        foreach (array_keys($assets) as $key) {
            $setting = BusinessSetting::with('storage')->where('key', $key)->first();
            $original[$key] = [
                'value' => $setting?->value,
                'disk' => $setting?->storage?->first()?->value,
                'present' => $setting !== null,
            ];
        }

        $backup = storage_path('app/mili-brand-settings-'.now()->format('Ymd-His').'.json');
        if (file_put_contents($backup, json_encode($original, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
            $this->error('Cannot save original settings backup. Aborting.');
            return self::FAILURE;
        }
        $this->info("Original settings backed up: {$backup}");

        foreach ($assets as $data) {
            if (! Storage::disk('public')->put('business/'.$data['file'], file_get_contents($data['source']))) {
                $this->error('Brand image upload failed; database settings remain unchanged.');
                return self::FAILURE;
            }
        }

        DB::transaction(function () use ($assets): void {
            foreach ($assets as $key => $data) {
                $setting = BusinessSetting::firstOrNew(['key' => $key]);
                $setting->value = $data['file'];
                $setting->save();
                DB::table('storages')->updateOrInsert(
                    ['data_type' => BusinessSetting::class, 'data_id' => $setting->id, 'key' => 'value'],
                    ['value' => 'public', 'updated_at' => now(), 'created_at' => now()]
                );
            }
        });
        ApiCache::bust('business_setting');
        $this->info('MILI_BRANDING_SETTINGS_APPLIED: OK');
        return self::SUCCESS;
    }
}
