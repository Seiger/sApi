<?php namespace Seiger\sApi\Contracts;

use Illuminate\Routing\Router;

/**
 * RouteProviderInterface
 *
 * Packages can expose API endpoints for sApi by implementing this interface.
 *
 * sApi will call {@see RouteProviderInterface::register()} during route bootstrapping.
 * Implementations should register routes on the provided Laravel router instance.
 */
interface RouteProviderInterface
{
    /**
     * Register routes on the given router instance.
     *
     * Implementations are expected to register routes relative to the current
     * sApi group (base path + optional version group).
     */
    public function register(Router $router): void;
}

