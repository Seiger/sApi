<?php namespace Seiger\sApi\Discovery;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * Collects sApi routes from the live Laravel router for the manager routes table.
 *
 * Returns definitions in the shape consumed by MgrController::normalizeConfiguredRoutes():
 * method, path (relative to base path and prefix), prefix, action, middleware, name.
 */
final class RouteInventory
{
    /** Applied to the whole API group in Http/apiRoutes.php; manager pages share the sApi. name prefix, so names can't be used. */
    private const API_MIDDLEWARE = 'sapi.access';

    /**
     * @param array<int, string> $versions Extra version prefixes (e.g. per-provider overrides).
     * @return array<int, array<string, mixed>>
     */
    public function discover(Router $router, string $basePath, string $version, array $versions = []): array
    {
        $basePath = trim($basePath, '/');
        $versions = array_values(array_unique(array_filter(
            array_map(static fn ($v) => trim((string)$v, '/'), array_merge([$version], $versions)),
            static fn ($v) => $v !== ''
        )));

        $definitions = [];
        foreach ($router->getRoutes()->getRoutes() as $route) {
            if (!$route instanceof Route || !$this->belongsToApi($route)) {
                continue;
            }

            $uri = trim($route->uri(), '/');
            if ($basePath !== '' && ($uri === $basePath || str_starts_with($uri, $basePath . '/'))) {
                $uri = ltrim(substr($uri, strlen($basePath)), '/');
            }

            [$prefix, $path] = $this->splitPrefix($uri, $versions);

            $methods = array_map('strtolower', $route->methods());
            // Laravel adds HEAD to every GET route; listing it separately is noise.
            if (in_array('get', $methods, true)) {
                $methods = array_diff($methods, ['head']);
            }

            foreach ($methods as $method) {
                $definitions[] = [
                    'method' => $method,
                    'path' => $path,
                    'prefix' => $prefix,
                    'action' => $route->getActionName(),
                    'middleware' => array_values(array_map('strval', $route->gatherMiddleware())),
                    'name' => (string)($route->getName() ?? ''),
                ];
            }
        }

        return $definitions;
    }

    private function belongsToApi(Route $route): bool
    {
        return !$route->isFallback && in_array(self::API_MIDDLEWARE, $route->gatherMiddleware(), true);
    }

    /**
     * @param array<int, string> $versions
     * @return array{0:string,1:string}
     */
    private function splitPrefix(string $uri, array $versions): array
    {
        foreach ($versions as $version) {
            if (str_starts_with($uri, $version . '/')) {
                return [$version, substr($uri, strlen($version) + 1)];
            }
        }

        return ['', $uri];
    }
}
