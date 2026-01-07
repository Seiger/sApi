<?php namespace Seiger\sApi\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Seiger\sApi\sApi;

class MgrController
{
    public function dashboard()
    {
        return view('sApi::dashboard', $this->layout('Dashboard'));
    }

    public function routes(Request $request)
    {
        $normalized = $this->normalizeConfiguredRoutes(
            (array) sApi::config('routes', []),
            (string) sApi::config('base_path', 'api')
        );

        $routes = $normalized['routes'];
        $summary = $normalized['summary'];

        $grouped = [];
        foreach ($routes as $route) {
            $key = $route['prefix'] !== '' ? $route['prefix'] : '—';
            $grouped[$key][] = $route;
        }
        ksort($grouped);

        return view('sApi::routes', array_merge($this->layout('Routes'), [
            'basePath' => $summary['basePath'],
            'summary' => $summary,
            'routes' => $routes,
            'groupedRoutes' => $grouped,
        ]));
    }

    public function auth()
    {
        return view('sApi::placeholder', array_merge($this->layout('Auth'), [
            'message' => 'Auth UI: not implemented.',
        ]));
    }

    public function providers()
    {
        return view('sApi::placeholder', array_merge($this->layout('Providers'), [
            'message' => 'Auto-discovery providers: not implemented.',
        ]));
    }

    public function logs()
    {
        return view('sApi::placeholder', array_merge($this->layout('Logs'), [
            'message' => 'Logs UI: not implemented.',
        ]));
    }

    private function layout(string $title): array
    {
        return [
            'pageTitle' => $title,
            'activeRouteName' => (string) (app('router')->currentRouteName() ?? ''),
        ];
    }

    private function normalizeConfiguredRoutes(array $routesConfig, string $basePath): array
    {
        $basePath = trim($basePath, '/');

        $definitions = [];
        foreach ($routesConfig as $key => $definition) {
            if (is_string($key) && is_string($definition)) {
                if (preg_match('~^(GET|POST|PUT|PATCH|DELETE|OPTIONS|HEAD)\\s+(.+)$~i', trim($key), $m)) {
                    $definitions[] = [
                        'method' => strtolower($m[1]),
                        'path' => trim($m[2]),
                        'action' => $definition,
                    ];
                }
                continue;
            }

            if (is_string($key) && is_array($definition)) {
                if (preg_match('~^(GET|POST|PUT|PATCH|DELETE|OPTIONS|HEAD)\\s+(.+)$~i', trim($key), $m)) {
                    $definitions[] = array_merge([
                        'method' => strtolower($m[1]),
                        'path' => trim($m[2]),
                    ], $definition);
                }
                continue;
            }

            if (is_array($definition)) {
                $definitions[] = $definition;
            }
        }

        $routes = [];
        $protected = 0;
        $public = 0;

        foreach ($definitions as $definition) {
            $method = strtolower((string) ($definition['method'] ?? ''));
            $allowedMethods = ['get', 'post', 'put', 'patch', 'delete', 'options', 'head'];
            if (!in_array($method, $allowedMethods, true)) {
                continue;
            }

            $path = trim((string) ($definition['path'] ?? ''), '/');
            if ($path === '') {
                continue;
            }

            $prefix = trim((string) ($definition['prefix'] ?? ''), '/');
            $middleware = $this->normalizeMiddleware($definition['middleware'] ?? []);

            $notes = [];
            $handler = $this->normalizeHandler($definition['action'] ?? null, $notes);
            if ($handler === null) {
                $handler = 'Not present';
            }

            $prefixInBasePath = $prefix !== '' && (bool) preg_match('~(^|/)' . preg_quote($prefix, '~') . '$~', $basePath);
            if ($prefix !== '' && $prefixInBasePath) {
                $notes[] = 'prefix dedup applied';
            }

            $parts = [];
            if ($basePath !== '') {
                $parts[] = $basePath;
            }
            if ($prefix !== '' && !$prefixInBasePath) {
                $parts[] = $prefix;
            }
            $parts[] = $path;
            $fullPath = '/' . implode('/', array_filter($parts, static fn ($p) => $p !== ''));

            $isProtected = $this->isProtected($middleware);
            if ($isProtected) {
                $protected++;
            } else {
                $public++;
            }

            $routes[] = [
                'method' => strtoupper($method),
                'path' => $fullPath,
                'handler' => $handler,
                'middleware' => $middleware,
                'middlewareText' => $middleware ? implode(', ', $middleware) : '',
                'notes' => $notes,
                'notesText' => $notes ? implode(', ', $notes) : '',
                'prefix' => $prefix,
                'isProtected' => $isProtected,
            ];
        }

        usort($routes, static function (array $a, array $b): int {
            return [$a['path'], $a['method']] <=> [$b['path'], $b['method']];
        });

        return [
            'routes' => $routes,
            'summary' => [
                'basePath' => $basePath,
                'total' => count($routes),
                'protected' => $protected,
                'public' => $public,
            ],
        ];
    }

    private function normalizeMiddleware(mixed $middleware): array
    {
        if (is_string($middleware)) {
            $middleware = array_values(array_filter(array_map('trim', explode(',', $middleware))));
        }

        if (!is_array($middleware)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $middleware)));
    }

    private function isProtected(array $middleware): bool
    {
        foreach ($middleware as $item) {
            if (Str::contains(Str::lower($item), 'jwt')) {
                return true;
            }
        }
        return false;
    }

    private function normalizeHandler(mixed $action, array &$notes): ?string
    {
        if (is_array($action) && count($action) === 2 && is_string($action[0]) && is_string($action[1])) {
            return $action[0] . '@' . $action[1];
        }

        if (is_string($action)) {
            if (str_contains($action, '@')) {
                return $action;
            }

            $notes[] = 'invokable';
            return $action . '@__invoke';
        }

        return null;
    }
}

