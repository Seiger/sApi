<?php

use Illuminate\Support\Facades\Route;
use Seiger\sApi\Controllers\TokenController;
use Seiger\sApi\Discovery\RouteProviderDiscovery;
use Seiger\sApi\Http\ApiResponse;
use Seiger\sApi\Http\Middleware\ApiAccessLogMiddleware;

$basePath = trim((string)env('SAPI_BASE_PATH', 'api'), '/');
$globalVersion = trim((string)env('SAPI_VERSION', 'v1'), '/');

$routesMap = (new RouteProviderDiscovery())->loadRoutesMap();
$routesMap = is_array($routesMap) ? $routesMap : [];

$group = Route::middleware(['web', ApiAccessLogMiddleware::class]);
if ($basePath !== '') {
    $group = $group->prefix($basePath);
}

$group->name('sApi.')->group(function () use ($routesMap, $globalVersion) {
    /** @var \Illuminate\Routing\Router $router */
    $router = app('router');

    // PUBLIC: core token route (can be versioned via SAPI_VERSION; version may be empty).
    $tokenUri = $globalVersion !== '' ? ($globalVersion . '/token') : 'token';
    Route::post($tokenUri, [TokenController::class, 'token'])->name('token');

    // PROTECTED: all discovered provider routes.
    Route::middleware(['sapi.jwt'])->group(function () use ($routesMap, $router): void {
        foreach ($routesMap as $mapKey => $descriptor) {
            if ($mapKey === '_meta') {
                continue;
            }

            if (!is_array($descriptor)) {
                continue;
            }

            $class = trim((string)($descriptor['class'] ?? ''));
            $endpoint = trim((string)($descriptor['endpoint'] ?? ''));
            $endpoint = strtolower(trim($endpoint, '/'));
            $effectiveVersion = trim((string)($descriptor['version'] ?? ''), '/');

            if ($class === '' || $endpoint === '') {
                continue;
            }

            // Ensure the class implements the expected contract (fast check before instantiation).
            if (!is_subclass_of($class, \Seiger\sApi\Contracts\RouteProviderInterface::class)) {
                continue;
            }

            try {
                /** @var \Seiger\sApi\Contracts\RouteProviderInterface $provider */
                $provider = app()->make($class);
            } catch (\Throwable) {
                continue;
            }

            // Build route name prefix: sapi.{endpoint}.{version}.*
            // Version segment is omitted when empty, but the prefix always ends with a dot.
            $as = $endpoint . '.' . ($effectiveVersion !== '' ? $effectiveVersion . '.' : '');

            $register = static function () use ($provider, $router): void {
                $provider->register($router);
            };

            // Version group (may be empty => routes live directly under /{basePath}).
            if ($effectiveVersion !== '') {
                Route::prefix($effectiveVersion)->as($as)->group($register);
            } else {
                Route::as($as)->group($register);
            }
        }
    });

    Route::any('{any}', static function () {
        return ApiResponse::error('Not found.', 404, (object)[]);
    })->where('any', '.*')->fallback()->name('fallback');
});
