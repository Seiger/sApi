<?php namespace Seiger\sApi\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MgrController
{
    public function __construct()
    {
        if (!evo()->hasPermission('sapi_manager')) {
            throw new HttpException(403, 'Access denied');
        }
    }

    public function dashboard()
    {
        $data = [
            'tabIcon' => 'tabler-layout-dashboard',
            'tabName' => __('sApi::global.dashboard'),
        ];

        $period = $this->countAccessStatsForDays(env('LOG_DAILY_DAYS', 14));
        $stats = [
            'requests_today' => $this->countRequestsToday(),
            'success' => $period['success'],
            'clients' => $period['clients'],
            'servers' => $period['servers'],
            'total' => $period['total'],
        ];

        $query = (string)request('q', '');
        $requests = $this->getLatestAccessRequests(10, $query);

        return view('sApi::dashboard', array_merge($this->layout($data), [
            'stats' => $stats,
            'requests' => $requests,
            'requestsQuery' => $query,
        ]));
    }

    public function logs()
    {
        $data = [
            'tabIcon' => 'tabler-activity-heartbeat',
            'tabName' => __('sApi::global.logs/timeline'),
        ];

        return view('sApi::placeholder', array_merge($this->layout($data), [
            'message' => 'Logs UI: not implemented.',
        ]));
    }

    public function routes(Request $request)
    {
        $data = [
            'tabIcon' => 'tabler-routes',
            'tabName' => __('sApi::global.routes'),
        ];

        $normalized = $this->normalizeConfiguredRoutes(
            $this->getConfiguredRoutes(),
            trim((string)env('SAPI_BASE_PATH', 'api'), '/')
        );

        $routes = $normalized['routes'];
        $summary = $normalized['summary'];

        $grouped = [];
        foreach ($routes as $route) {
            $key = $route['prefix'] !== '' ? $route['prefix'] : '—';
            $grouped[$key][] = $route;
        }
        ksort($grouped);

        return view('sApi::routes', array_merge($this->layout($data), [
            'basePath' => $summary['basePath'],
            'summary' => $summary,
            'routes' => $routes,
            'groupedRoutes' => $grouped,
        ]));
    }

    public function auth()
    {
        $data = [
            'tabIcon' => 'tabler-lock',
            'tabName' => 'Auth',
        ];

        return view('sApi::placeholder', array_merge($this->layout($data), [
            'message' => 'Auth UI: not implemented.',
        ]));
    }

    public function providers()
    {
        $data = [
            'tabIcon' => 'tabler-plug-connected',
            'tabName' => 'Providers',
        ];

        return view('sApi::placeholder', array_merge($this->layout($data), [
            'message' => 'Auto-discovery providers: not implemented.',
        ]));
    }

    private function layout(array $data): array
    {
        return array_merge($data, ['activeRoute' => (string)(app('router')->currentRouteName() ?? '')]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getConfiguredRoutes(): array
    {
        $version = trim((string)env('SAPI_VERSION', 'v1'), '/');

        return [
            [
                'method' => 'post',
                'path' => 'token',
                'prefix' => $version,
                'action' => [\Seiger\sApi\Controllers\TokenController::class, 'token'],
                'name' => 'token',
            ],
        ];
    }

    private function countRequestsToday(): int
    {
        return $this->countAccessStatsForDays(1)['total'];
    }

    /**
     * @return array{success:int,clients:int,servers:int,total:int}
     */
    private function countAccessStatsForDays(int $days): array
    {
        if ($days < 1) {
            $days = 1;
        }

        $success = 0;
        $clients = 0;
        $servers = 0;
        $total = 0;

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime('-' . $i . ' days'));
            $logFile = EVO_STORAGE_PATH . 'logs/sapi-' . $date . '.log';
            if (!is_file($logFile) || !is_readable($logFile)) {
                continue;
            }

            $handle = @fopen($logFile, 'rb');
            if (!is_resource($handle)) {
                continue;
            }

            try {
                while (($line = fgets($handle)) !== false) {
                    $line = (string)$line;

                    // Primary: access log entries emitted by AccessLogger::log()
                    $isAccessLog = str_contains($line, '"type":"access"');

                    // Fallback: error-shaped entries that still carry request context (e.g. "Unhandled API exception").
                    // Those may appear when logging fails mid-flight or when exceptions are logged separately.
                    $looksLikeRequestContext =
                        str_contains($line, '"request_id"') &&
                        str_contains($line, '"method"') &&
                        str_contains($line, '"path"') &&
                        str_contains($line, '"status"');

                    if (!$isAccessLog && !$looksLikeRequestContext) {
                        continue;
                    }

                    if (!preg_match('~"status"\\s*:\\s*(\\d{3})~', $line, $m)) {
                        continue;
                    }

                    $status = (int)$m[1];
                    $total++;

                    if ($status >= 200 && $status <= 299) {
                        $success++;
                    } elseif ($status >= 400 && $status <= 499) {
                        $clients++;
                    } elseif ($status >= 500 && $status <= 599) {
                        $servers++;
                    }
                }
            } finally {
                fclose($handle);
            }
        }

        return [
            'success' => $success,
            'clients' => $clients,
            'servers' => $servers,
            'total' => $total,
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

    /**
     * Read and parse latest access-log requests for dashboard widget.
     *
     * Expected log format (Monolog-like):
     * 2026-01-08 10:12:33 INFO sapi.access {"method":"GET","path":"/rest/v1/orders","status":200,"duration_ms":38,"request_id":"..."}
     *
     * @param int $limit
     * @param string $query
     * @return array<int, array<string, mixed>>
     */
    protected function getLatestAccessRequests(int $limit = 10, string $query = ''): array
    {
        $dir = evo()->storagePath() . 'logs';
        if (!is_dir($dir)) {
            return [];
        }

        $items = [];
        foreach ($this->resolveAccessLogFiles($dir) as $logFile) {
            if (count($items) >= $limit) break;

            $entries = $this->tailAccessLogEntries($logFile, max(250, ($limit - count($items)) * 40));

            foreach ($entries as $entry) {
                $row = $this->parseAccessLogLine($entry);
                if (!$row) {
                    continue;
                }

                if ($query !== '' && !$this->matchesRequestQuery($row, $query)) {
                    continue;
                }

                $items[] = $row;
                if (count($items) >= $limit) {
                    break;
                }
            }
        }

        return $items;
    }

    /**
     * Group last N lines into log entries (supports multiline exceptions/stacktraces).
     *
     * We read the file tail as lines (newest-first) and then stitch "continuation"
     * lines back to the closest preceding "[YYYY-mm-dd HH:ii:ss]" header line.
     *
     * @param string $file
     * @param int $lines
     * @return array<int, string> Entries, newest first.
     */
    protected function tailAccessLogEntries(string $file, int $lines = 250): array
    {
        $rawLines = $this->tailFile($file, $lines);
        if ($rawLines === []) {
            return [];
        }

        $entries = [];
        $pending = [];

        foreach ($rawLines as $line) {
            if ($this->looksLikeLogEntryHeader($line)) {
                $entry = $line;
                if ($pending !== []) {
                    $entry .= "\n" . implode("\n", array_reverse($pending));
                }

                $entries[] = $entry;
                $pending = [];
                continue;
            }

            $pending[] = $line;
        }

        return $entries;
    }

    protected function looksLikeLogEntryHeader(string $line): bool
    {
        return preg_match('/^\\[\\d{4}-\\d{2}-\\d{2}\\s+\\d{2}:\\d{2}:\\d{2}\\]/', $line) === 1;
    }

    /**
     * Resolve access-log files to read (newest first).
     *
     * Supports daily logs like:
     * - sapi-access-2026-01-08.log
     * - sapi-access.2026-01-08.log
     *
     * Falls back to "same basename*" files (e.g. logrotate: .1, .2).
     *
     * @param string $file
     * @return array<int, string>
     */
    protected function resolveAccessLogFiles(string $dir): array
    {
        // If config points to a directory, try to find plausible logs inside.
        if (is_dir($dir)) {
            $candidates = glob(rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sapi-*.log') ?: [];
            $out = array_values(array_filter($candidates, static fn($p) => is_file($p) && is_readable($p)));
            usort($out, static fn($a, $b) => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
            return $out;
        } else {
            return [];
        }
    }

    /**
     * Read last N lines from a file efficiently.
     *
     * @param string $file
     * @param int $lines
     * @return array<int, string>
     */
    protected function tailFile(string $file, int $lines = 200): array
    {
        $f = new \SplFileObject($file, 'r');
        $f->seek(PHP_INT_MAX);
        $lastLine = $f->key();

        $start = max(0, $lastLine - $lines);
        $f->seek($start);

        $out = [];
        while (!$f->eof()) {
            $line = trim((string) $f->fgets());
            if ($line !== '') {
                $out[] = $line;
            }
        }

        // We want newest first
        return array_reverse($out);
    }

    /**
     * Parse one access log line into dashboard row.
     *
     * Format example:
     * [2026-01-07 21:21:16] new2.velotrade.com.ua.INFO: /rest/token {...json...}
     *
     * @param string $line
     * @return array<string, mixed>|null
     */
    protected function parseAccessLogLine(string $line): ?array
    {
        $entry = trim($line);
        $entry = str_replace("\r", '', $entry);

        $firstLine = explode("\n", $entry, 2)[0] ?? '';
        $firstLine = trim($firstLine);

        // 1) Parse prefix: [dt] channel.LEVEL: message
        if (!preg_match(
            '/^\[(?<dt>\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\]\s+(?<channel>.+?)\.(?<level>[A-Z]+):\s*(?<msg>.*)$/u',
            $firstLine,
            $m
        )) {
            return null;
        }

        $createdAt = $m['dt'];
        $level = $m['level'];
        $msg = $m['msg'];

        // 2) Extract JSON context (your JSON is after the message, we take from the first "{")
        $context = $this->extractLogContext($msg . "\n" . $entry);
        if ($context === null) {
            return null;
        }

        // 3) Take required fields from context
        $method = $context['method'] ?? null;
        $path = $context['path'] ?? null;
        $status = $context['status'] ?? null;
        $duration = $context['duration_ms'] ?? null;
        $requestId = $context['request_id'] ?? null;

        if (!$method || !$path || !is_numeric($status)) {
            return null;
        }

        return [
            'created_at' => $createdAt,
            'method' => (string) $method,
            'path' => (string) $path,
            'status' => (int) $status,
            'duration' => is_numeric($duration) ? (int) $duration : null,
            'level' => $context['level'] ?? $level,
            'request_id' => (string) ($requestId ?? ''),
            'ip' => $context['ip'] ?? null,
            'ua' => $context['ua'] ?? null,
            'route' => $context['route'] ?? null,
        ];
    }

    /**
     * Best-effort extraction of the first JSON object from a log entry.
     *
     * @param string $text
     * @return array<string, mixed>|null
     */
    protected function extractLogContext(string $text): ?array
    {
        $offset = 0;
        $len = strlen($text);

        while ($offset < $len) {
            $pos = strpos($text, '{', $offset);
            if ($pos === false) {
                break;
            }

            $json = $this->extractFirstJsonObject($text, $pos);
            if ($json === null) {
                break;
            }

            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            $offset = $pos + 1;
        }

        // Fallback for multiline/invalid JSON produced by some log formatters (inline stacktraces).
        // We only need a few fields for the dashboard table, so parse them with regex.
        return $this->extractKnownContextFields($text);
    }

    /**
     * Extract a minimal set of context fields from a log entry without relying on valid JSON.
     *
     * @param string $text
     * @return array<string, mixed>|null
     */
    protected function extractKnownContextFields(string $text): ?array
    {
        $out = [];

        if (preg_match('~"request_id"\\s*:\\s*"([^"]+)"~', $text, $m)) {
            $out['request_id'] = $m[1];
        }
        if (preg_match('~"method"\\s*:\\s*"([^"]+)"~', $text, $m)) {
            $out['method'] = $m[1];
        }
        if (preg_match('~"path"\\s*:\\s*"([^"]+)"~', $text, $m)) {
            $out['path'] = $m[1];
        }
        if (preg_match('~"status"\\s*:\\s*(\\d{3})~', $text, $m)) {
            $out['status'] = (int)$m[1];
        }
        if (preg_match('~"duration_ms"\\s*:\\s*(\\d+)~', $text, $m)) {
            $out['duration_ms'] = (int)$m[1];
        }
        if (preg_match('~"level"\\s*:\\s*"([A-Z]+)"~', $text, $m)) {
            $out['level'] = $m[1];
        }

        // We consider this a valid context only if it has the fields required by the dashboard.
        if (!isset($out['method'], $out['path'], $out['status'])) {
            return null;
        }

        return $out;
    }

    /**
     * Extract a JSON object starting at $startPos by balancing braces, respecting strings/escapes.
     */
    protected function extractFirstJsonObject(string $text, int $startPos): ?string
    {
        $len = strlen($text);
        if ($startPos < 0 || $startPos >= $len || $text[$startPos] !== '{') {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escape = false;

        for ($i = $startPos; $i < $len; $i++) {
            $ch = $text[$i];

            if ($inString) {
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($ch === '\\') {
                    $escape = true;
                    continue;
                }
                if ($ch === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($ch === '"') {
                $inString = true;
                continue;
            }

            if ($ch === '{') {
                $depth++;
                continue;
            }

            if ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($text, $startPos, $i - $startPos + 1);
                }
            }
        }

        return null;
    }

    /**
     * Check whether row matches a simple dashboard query.
     *
     * @param array<string, mixed> $row
     * @param string $query
     * @return bool
     */
    protected function matchesRequestQuery(array $row, string $query): bool
    {
        $q = mb_strtolower(trim($query));
        if ($q === '') return true;

        $hay = mb_strtolower(implode(' ', [
            (string)($row['path'] ?? ''),
            (string)($row['request_id'] ?? ''),
            (string)($row['method'] ?? ''),
            (string)($row['status'] ?? ''),
            (string)($row['level'] ?? ''),
        ]));

        return mb_strpos($hay, $q) !== false;
    }
}
