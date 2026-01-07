<?php namespace Seiger\sApi;

use EvolutionCMS\ServiceProvider;
use Seiger\sApi\sApi;

/**
 * Class sApiServiceProvider
 *
 * @package Seiger\sApi
 * @author Seiger IT Team
 * @since 1.0.0
 */
class sApiServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // Merge configuration
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/sApiCheck.php', 'cms.settings');

        // Load migrations, translations, views
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
        $this->loadTranslationsFrom(dirname(__DIR__) . '/lang', 'sApi');
        $this->loadViewsFrom(dirname(__DIR__) . '/views', 'sApi');

        // API routes
        $this->loadApiRoutes();

        // Manager routes + resources
        if (defined('IN_MANAGER_MODE') && IN_MANAGER_MODE) {
            $this->loadMgrRoutes();
            $this->publishResources();
        }

        // Register singletons
        $this->app->singleton(sApi::class);
        $this->app->alias(sApi::class, 'sApi');
    }

    public function register()
    {
        // Plugins
        $this->loadPluginsFrom(dirname(__DIR__) . '/plugins/');
    }

    /**
     * Load API routes
     *
     * @return void
     */
    protected function loadApiRoutes(): void
    {
        include __DIR__ . '/Http/apiRoutes.php';
    }

    /**
     * Load manager routes
     *
     * @return void
     */
    protected function loadMgrRoutes()
    {
        $this->app->router->middlewareGroup('mgr', config('app.middleware.mgr', []));
        include(__DIR__ . '/Http/mgrRoutes.php');
    }

    /**
     * Publish the necessary resources for the package.
     *
     * @return void
     */
    protected function publishResources()
    {
        $this->publishes([
            dirname(__DIR__) . '/images/seigerit.svg' => public_path('assets/site/seigerit.svg'),
            dirname(__DIR__) . '/images/logo.svg' => public_path('assets/site/sapi.svg'),
            dirname(__DIR__) . '/css/tailwind.min.css' => public_path('assets/site/sapi.min.css'),
        ], 'sapi');
    }
}
