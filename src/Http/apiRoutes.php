<?php

use Illuminate\Support\Facades\Route;
use Seiger\sApi\sApi;
use Seiger\sApi\Http\ApiResponse;
use Seiger\sApi\Http\Middleware\ApiAccessLogMiddleware;

$basePath = trim((string) sApi::config('base_path', 'api'), '/');

$group = Route::middleware(['web', ApiAccessLogMiddleware::class]);
if ($basePath !== '') {
    $group = $group->prefix($basePath);
}

$routesConfig = sApi::config('routes', []);
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

$dedupe = [];
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

$group->name('sApi.')->group(function () use ($definitions, $basePath, &$dedupe) {
    $register = static function (array $route) use ($basePath, &$dedupe): void {
        $method = strtolower((string)($route['method'] ?? ''));
        $path = trim((string)($route['path'] ?? ''), '/');
        $prefix = trim((string)($route['prefix'] ?? ''), '/');
        $action = $route['action'] ?? null;

        $allowedMethods = ['get', 'post', 'put', 'patch', 'delete', 'options', 'head'];
        if (!in_array($method, $allowedMethods, true)) {
            return;
        }

        if ($method === '' || $path === '' || $action === null) {
            return;
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
            return;
        }

        $prefixInBasePath = $prefix !== '' && (bool)preg_match('~(^|/)' . preg_quote($prefix, '~') . '$~', $basePath);
        $uri = $prefix !== '' && !$prefixInBasePath ? $prefix . '/' . $path : $path;
        $uri = trim($uri, '/');

        $registerUri = static function (string $uriToRegister) use ($method, $action, $route, &$dedupe): void {
            $dedupeKey = $method . ' ' . $uriToRegister;
            if (isset($dedupe[$dedupeKey])) {
                return;
            }
            $dedupe[$dedupeKey] = true;

            $registered = Route::$method($uriToRegister, $action);

            if (!empty($route['middleware'])) {
                $registered->middleware($route['middleware']);
            }

            if (!empty($route['name'])) {
                $registered->name((string) $route['name']);
            }
        };

        $registerUri($uri);

        // Also register a trailing-slash variant to avoid falling back to parser 404 pages.
        if (!str_ends_with($uri, '/')) {
            $registerUri($uri . '/');
        }
    };

    foreach ($definitions as $definition) {
        $register($definition);
    }

    // API fallback: return JSON 404 instead of falling back to the site parser (HTML 404).
    Route::any('{any}', static function () {
        return ApiResponse::error('Not found.', 404, (object)[]);
    })->where('any', '.*')->fallback()->name('fallback');
});
