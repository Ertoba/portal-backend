<?php

namespace Modules\Rental\Providers;

use App\CentralLogics\Helpers;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Rental\Services\ProviderSidebarCountService;
use Modules\Rental\Services\RentalSidebarCountService;

class RentalServiceProvider extends ServiceProvider
{
    /**
     * @var string $moduleName
     */
    protected $moduleName = 'Rental';

    /**
     * @var string $moduleNameLower
     */
    protected $moduleNameLower = 'rental';

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        $this->composeAdminSidebarCounts();
        $this->composeProviderSidebarCounts();
    }

    private function composeAdminSidebarCounts(): void
    {
        View::composer('rental::admin.partials._sidebar_v2_rental', function ($view) {
            $moduleId = Config::get('module.current_module_id');

            $view->with(RentalSidebarCountService::get(
                moduleId: $moduleId === null ? null : (int) $moduleId
            ));
        });
    }

    private function composeProviderSidebarCounts(): void
    {
        View::composer([
            'rental::provider.partials._sidebar_rental',
            'rental::provider.partials._sidebar_v2_rental',
        ], function ($view) {
            $view->with(
                'tripCount',
                app(ProviderSidebarCountService::class)->tripCounts(Helpers::get_store_data()?->id)
            );
        });
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
        $this->app->singleton(ProviderSidebarCountService::class);
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            module_path($this->moduleName, 'Config/config.php') => config_path($this->moduleNameLower . '.php'),
        ], 'config');
        $this->mergeConfigFrom(
            module_path($this->moduleName, 'Config/config.php'), $this->moduleNameLower
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/' . $this->moduleNameLower);

        $sourcePath = module_path($this->moduleName, 'Resources/views');

        $this->publishes([
            $sourcePath => $viewPath
        ], ['views', $this->moduleNameLower . '-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/' . $this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
        } else {
            $this->loadTranslationsFrom(module_path($this->moduleName, 'Resources/lang'), $this->moduleNameLower);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (Config::get('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->moduleNameLower)) {
                $paths[] = $path . '/modules/' . $this->moduleNameLower;
            }
        }
        return $paths;
    }
}
