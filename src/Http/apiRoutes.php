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

$group->name('sApi.')->group(function () use ($routesMap, $globalVersion, $basePath) {
    /** @var \Illuminate\Routing\Router $router */
    $router = app('router');

    // Register discovered provider routes first.
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

    // Core token route (can be versioned via SAPI_VERSION; version may be empty).
    $tokenUri = $globalVersion !== '' ? ($globalVersion . '/token') : 'token';
    Route::post($tokenUri, [TokenController::class, 'token'])->name('token');

    $dedupe = [
        'post ' . $tokenUri => true,
    ];

    // Fallback/manual config routes mode: `seiger.settings.sApi.routes` (kept for compatibility).
    $routesConfig = config('seiger.settings.sApi.routes', []);
    $routesConfig = is_array($routesConfig) ? $routesConfig : [];

    $normalizeKeyedRoute = static function (string $key, mixed $value): ?array {
        if (!preg_match('~^(GET|POST|PUT|PATCH|DELETE|OPTIONS|HEAD)\\s+(.+)$~i', trim($key), $m)) {
            return null;
        }

        $method = strtolower($m[1]);
        $path = trim($m[2]);

        if (is_string($value)) {
            return ['method' => $method, 'path' => $path, 'action' => $value];
        }

        if (is_array($value)) {
            return array_merge(['method' => $method, 'path' => $path], $value);
        }

        return null;
    };

    $definitions = [];
    foreach ($routesConfig as $key => $definition) {
        if (is_string($key)) {
            $normalized = $normalizeKeyedRoute($key, $definition);
            if (is_array($normalized)) {
                $definitions[] = $normalized;
                continue;
            }
        }

        if (is_array($definition)) {
            $definitions[] = $definition;
        }
    }

    foreach ($definitions as $route) {
        $method = strtolower((string)($route['method'] ?? ''));
        $path = trim((string)($route['path'] ?? ''), '/');

        // If prefix is absent, use global version; if provided (even empty), respect it.
        if (array_key_exists('prefix', $route)) {
            $prefix = trim((string)($route['prefix'] ?? ''), '/');
        } else {
            $prefix = $globalVersion;
        }

        $action = $route['action'] ?? null;

        $allowedMethods = ['get', 'post', 'put', 'patch', 'delete', 'options', 'head'];
        if (!in_array($method, $allowedMethods, true)) {
            continue;
        }

        if ($path === '' || $action === null) {
            continue;
        }

        // If someone specified the base path inside the route path (e.g. "rest/token"),
        // avoid doubling it (base path is already applied via group->prefix()).
        if ($basePath !== '') {
            $basePrefix = $basePath . '/';
            if ($path === $basePath) {
                $path = '';
            } elseif (str_starts_with($path, $basePrefix)) {
                $path = substr($path, strlen($basePrefix));
            }
        }

        $path = trim($path, '/');
        if ($path === '') {
            continue;
        }

        // If config path already includes prefix (e.g. "v1/orders"), do not double it.
        $uri = $path;
        if ($prefix !== '' && $uri !== $prefix && !str_starts_with($uri, $prefix . '/')) {
            $uri = $prefix . '/' . $uri;
        }
        $uri = trim($uri, '/');
        if ($uri === '') {
            continue;
        }

        $dedupeKey = $method . ' ' . $uri;
        if (isset($dedupe[$dedupeKey])) {
            continue;
        }
        $dedupe[$dedupeKey] = true;

        $registered = Route::$method($uri, $action);

        if (!empty($route['middleware'])) {
            $registered->middleware($route['middleware']);
        }

        if (!empty($route['name'])) {
            $registered->name((string)$route['name']);
        }
    }

    Route::any('{any}', static function () {
        return ApiResponse::error('Not found.', 404, (object)[]);
    })->where('any', '.*')->fallback()->name('fallback');
});
