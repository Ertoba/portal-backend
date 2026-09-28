<?php

namespace App\Builder;

use App\Models\BusinessSetting;
use Modules\Builder\Contracts\LocaleProvider as LocaleProviderContract;

class LocaleProvider implements LocaleProviderContract
{
    public function availableLanguages(): array
    {
        try {
            $setting = BusinessSetting::where('key', 'system_language')->first();

            if (!$setting) {
                return [['code' => 'en', 'direction' => 'ltr', 'default' => true]];
            }

            return collect(json_decode($setting->value, true))
                ->where('status', 1)
                ->values()
                ->toArray();
        } catch (\Throwable) {
            return [['code' => 'en', 'direction' => 'ltr', 'default' => true]];
        }
    }
}
