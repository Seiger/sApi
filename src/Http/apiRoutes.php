<?php

use Illuminate\Support\Facades\Route;
use Seiger\sApi\sApi;

$basePath = trim((string) sApi::config('base_path', 'api'), '/');

$group = Route::middleware('web');
if ($basePath !== '') {
    $group = $group->prefix($basePath);
}

$routesConfig = (array) sApi::config('routes', []);
if (!is_array($routesConfig) || $routesConfig === []) {
    return;
}

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

        $prefixInBasePath = $prefix !== '' && (bool)preg_match('~(^|/)' . preg_quote($prefix, '~') . '$~', $basePath);
        $uri = $prefix !== '' && !$prefixInBasePath ? $prefix . '/' . $path : $path;
        $uri = trim($uri, '/');

        $dedupeKey = $method . ' ' . $uri;
        if (isset($dedupe[$dedupeKey])) {
            return;
        }
        $dedupe[$dedupeKey] = true;

        $registered = Route::$method($uri, $action);

        if (!empty($route['middleware'])) {
            $registered->middleware($route['middleware']);
        }

        if (!empty($route['name'])) {
            $registered->name((string) $route['name']);
        }
    };

    foreach ($definitions as $definition) {
        $register($definition);
    }
});
