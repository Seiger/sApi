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
        // Register singletons
        $this->app->singleton(sApi::class);
        $this->app->alias(sApi::class, 'sApi');
    }
}