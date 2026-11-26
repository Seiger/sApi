<?php

declare(strict_types=1);

/**
 * Needs illuminate/routing; pass an Evolution autoloader:
 *   php tests/RouteInventoryTest.php /var/www/html/core/vendor/autoload.php
 */

$autoload = $argv[1] ?? getenv('SAPI_AUTOLOAD') ?: '';
if ($autoload === '' || !is_file($autoload)) {
    echo 'SKIP (no autoloader with illuminate/routing given)' . PHP_EOL;
    exit(0);
}
require $autoload;
require_once dirname(__DIR__) . '/src/Discovery/RouteInventory.php';

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;
use Seiger\sApi\Discovery\RouteInventory;

$failures = [];
$tests = 0;
$assert = static function (bool $condition, string $message) use (&$failures, &$tests): void {
    $tests++;
    if (!$condition) {
        $failures[] = $message;
    }
};

$router = new Router(new Dispatcher(new Container()), new Container());

// Mirrors src/Http/apiRoutes.php: token, a v1 provider, a provider with its own version, fallback.
$router->middleware(['sapi.access', 'web'])->prefix('api')->name('sApi.')->group(static function (Router $r): void {
    $r->post('v1/token', ['uses' => 'Seiger\sApi\Controllers\TokenController@token'])->name('token');
    $r->middleware(['sapi.jwt'])->group(static function (Router $r): void {
        $r->prefix('v1')->as('orders.v1.')->group(static function (Router $r): void {
            $r->get('orders', static fn () => 'ok')->name('index');
            $r->match(['put', 'patch'], 'orders/{id}', ['uses' => 'App\OrdersController@update']);
        });
        $r->prefix('v2')->as('stock.v2.')->group(static function (Router $r): void {
            $r->get('stock', ['uses' => 'App\StockController@index']);
        });
    });
    $r->any('{any}', static fn () => 'nf')->where('any', '.*')->fallback()->name('fallback');
});
$router->get('site/page', static fn () => 'site')->name('site.page');
// Manager pages share the sApi. name prefix but are not API routes.
$router->middleware(['mgr'])->prefix('sapi')->name('sApi.')->group(static function (Router $r): void {
    $r->get('routes', ['uses' => 'Seiger\sApi\Controllers\MgrController@routes'])->name('routes');
});

$routes = (new RouteInventory())->discover($router, 'api', 'v1', ['v2', '']);
$index = [];
foreach ($routes as $route) {
    $index[$route['method'] . ' ' . $route['prefix'] . '|' . $route['path']] = $route;
}

$assert(isset($index['post v1|token']), 'token route is listed with v1 prefix');
$assert(($index['post v1|token']['action'] ?? '') === 'Seiger\sApi\Controllers\TokenController@token', 'controller action is reported');
$assert(!in_array('sapi.jwt', $index['post v1|token']['middleware'] ?? ['sapi.jwt'], true), 'token route stays public');

$assert(isset($index['get v1|orders']), 'provider GET route is listed');
$assert(!isset($index['head v1|orders']), 'implicit HEAD is not listed');
$assert(($index['get v1|orders']['action'] ?? '') === 'Closure', 'closure action is reported as Closure');
$assert(in_array('sapi.jwt', $index['get v1|orders']['middleware'] ?? [], true), 'provider route keeps jwt middleware');
$assert(($index['get v1|orders']['name'] ?? '') === 'sApi.orders.v1.index', 'route name is reported');

$assert(isset($index['put v1|orders/{id}'], $index['patch v1|orders/{id}']), 'multi-method route yields one row per method');
$assert(isset($index['get v2|stock']), 'provider-specific version is used as prefix');

$paths = array_column($routes, 'path');
$assert(!in_array('{any}', $paths, true), 'fallback route is excluded');
$assert(!in_array('site/page', $paths, true) && !in_array('page', $paths, true), 'non-API routes are excluded');
$assert(!in_array('routes', $paths, true) && !in_array('sapi/routes', $paths, true), 'manager routes with sApi. names are excluded');
$assert(count($routes) === 5, 'exactly the API routes are listed (got ' . count($routes) . ')');

// Empty base path: API routes are identified by middleware, unknown first segment stays in path.
$router2 = new Router(new Dispatcher(new Container()), new Container());
$router2->middleware(['sapi.access'])->name('sApi.')->group(static function (Router $r): void {
    $r->get('ping', static fn () => 'pong')->name('ping');
});
$router2->get('other', static fn () => 'x')->name('sApi.other');
$routes2 = (new RouteInventory())->discover($router2, '', 'v1');
$assert(count($routes2) === 1 && $routes2[0]['path'] === 'ping' && $routes2[0]['prefix'] === '', 'empty base path lists API-group routes only');

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, array_map(static fn($f) => "FAIL: {$f}", $failures)) . PHP_EOL);
    exit(1);
}

echo "OK ({$tests} assertions)" . PHP_EOL;
