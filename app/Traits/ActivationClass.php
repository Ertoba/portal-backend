<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

trait ActivationClass
{
    public function is_local(): bool
    {
        return true;
    }

    public function getDomain(): string
    {
        return str_replace(["http://", "https://", "www."], "", url('/'));
    }

    public function getSystemAddonCacheKey(string|null $app = 'default'): string
    {
        $appName = env('APP_NAME').'_cache';
        return str_replace('-', '_', Str::slug($appName.'cache_system_addons_for_' . $app . '_' . $this->getDomain()));
    }

    public function getAddonsConfig(): array
    {
        if (file_exists(base_path('config/system-addons.php'))) {
            return include(base_path('config/system-addons.php'));
        }

        $apps = ['admin_panel', 'vendor_app', 'deliveryman_app', 'react_web'];
        $appConfig = [];
        foreach ($apps as $app) {
            $appConfig[$app] = [
                "active" => "0",
                "username" => "",
                "purchase_key" => "",
                "software_id" => "",
                "domain" => "",
                "software_type" => $app == 'admin_panel' ? "product" : 'addon',
            ];
        }
        return $appConfig;
    }

    public function getCacheTimeoutByDays(int $days = 3): int
    {
        return 60 * 60 * 24 * $days;
    }

    public function getRequestConfig(string|null $username = null, string|null $purchaseKey = null, string|null $softwareId = null, string|null $softwareType = null): array
    {
        return [
            "active"        => "1",
            "username"      => trim($username ?? ''),
            "purchase_key"  => $purchaseKey ?? 'bypassed',
            "software_id"   => $softwareId ?? (defined('SOFTWARE_ID') ? SOFTWARE_ID : 'bypassed'),
            "domain"        => $this->getDomain(),
            "software_type" => $softwareType ?? base64_decode('cHJvZHVjdA=='),
        ];
    }

    public function checkActivationCache(string|null $app)
    {
        if (is_null($app)) {
            return true;
        }

        $config = $this->getAddonsConfig();
        $cacheKey = $this->getSystemAddonCacheKey(app: $app);
        $appConfig = $config[$app] ?? null;

        if (!$appConfig) {
            Cache::forget($cacheKey);
            return false;
        }

        return Cache::remember($cacheKey, $this->getCacheTimeoutByDays(days: 1), function () use ($app, $appConfig) {
            $response = $this->getRequestConfig(
                username: $appConfig['username'],
                purchaseKey: $appConfig['purchase_key'],
                softwareId: $appConfig['software_id'],
                softwareType: $appConfig['software_type'] ?? base64_decode('cHJvZHVjdA==')
            );

            $this->updateActivationConfig(app: $app, response: $response);

            return (bool) $response['active'];
        });
    }

    public function updateActivationConfig($app, $response): void
    {
        if ('admin.business-settings.addon-activation.index' === \Illuminate\Support\Facades\Route::currentRouteName()) {
            return;
        }

        $config = $this->getAddonsConfig();
        $config[$app] = $response;

        $configContents = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        file_put_contents(base_path('config/system-addons.php'), $configContents);

        $cacheKey = $this->getSystemAddonCacheKey(app: $app);
        Cache::forget($cacheKey);
    }
}