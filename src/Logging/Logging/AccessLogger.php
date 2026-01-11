<?php namespace Seiger\sApi\Logging;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class AccessLogger
{
    public const ATTR_REQUEST_ID = 'sapi.request_id';
    public const ATTR_START_TS = 'sapi.access.start_ts';
    public const ATTR_LOGGED = '_sapi_access_logged';
    private const CONTEXT_START_TS = 'sapi.access.start_ts';
    private const CONTEXT_LOGGED = 'sapi.access.logged';
    private const CONTEXT_STATUS = 'sapi.access.status';
    private const CONTEXT_REQ_TS = 'sapi.access._request_ts';

    private static bool $hooksInstalled = false;

    public static function installLifecycleHooks(): void
    {
        if (!(bool)config('sapi.access.lifecycle_hooks', true)) {
            return;
        }

        if (self::$hooksInstalled) {
            return;
        }
        self::$hooksInstalled = true;

        if (PHP_SAPI === 'cli') {
            return;
        }

        register_shutdown_function(static function (): void {
            try {
                self::terminateFromGlobals();
            } catch (\Throwable) {
            }
        });

        $previous = null;
        $handler = static function (\Throwable $exception) use (&$previous): void {
            try {
                self::logExceptionFromGlobals($exception);
            } catch (\Throwable) {
            }

            if (is_callable($previous)) {
                call_user_func($previous, $exception);
                return;
            }

            throw $exception;
        };

        $previous = set_exception_handler($handler);
    }

    public static function init(Request $request): string
    {
        self::ensureFreshRequestContext();

        $start = $request->attributes->get(self::ATTR_START_TS);
        if (!is_float($start)) {
            $start = isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float)$_SERVER['REQUEST_TIME_FLOAT'] : microtime(true);
            $request->attributes->set(self::ATTR_START_TS, $start);
        }
        RequestContext::set(self::CONTEXT_START_TS, $start);

        $existingRequestId = $request->attributes->get(self::ATTR_REQUEST_ID);
        if (is_string($existingRequestId) && trim($existingRequestId) !== '') {
            $requestId = substr(trim($existingRequestId), 0, 128);
        } else {
            $requestId = trim((string)$request->headers->get('X-Request-Id', ''));
            $requestId = $requestId !== '' ? substr($requestId, 0, 128) : self::uuidV4();
            $request->attributes->set(self::ATTR_REQUEST_ID, $requestId);
        }

        RequestContext::set('request_id', $requestId);
        if (RequestContext::get('ip') === null) {
            RequestContext::set('ip', (string)$request->ip());
        }

        $routeAction = self::resolveRouteAction($request);
        if ($routeAction !== 'unknown') {
            RequestContext::set('route', $routeAction);
        } elseif (!is_string(RequestContext::get('route'))) {
            RequestContext::set('route', $routeAction);
        }

        [$sub, $scopes] = self::resolveJwtClaims($request);
        if ($sub !== null) {
            RequestContext::set('sub', $sub);
        }
        if ($scopes !== null) {
            RequestContext::set('scopes', $scopes);
        }

        return $requestId;
    }

    public static function rememberResponse(Response $response): void
    {
        RequestContext::set(self::CONTEXT_STATUS, (int)$response->getStatusCode());
    }

    public static function log(Request $request, Response $response): void
    {
        $allowDuplicates = (bool)config('sapi.access.allow_duplicates', false);
        if (
            !$allowDuplicates
            && (
                (bool)$request->attributes->get(self::ATTR_LOGGED, false)
                || (bool)RequestContext::get(self::CONTEXT_LOGGED, false)
            )
        ) {
            return;
        }

        $requestId = self::init($request);
        $response->headers->set('X-Request-Id', $requestId);

        $logging = self::loggingConfig();
        if (!(bool)($logging['enabled'] ?? true)) {
            return;
        }

        $access = $logging['access'] ?? [];
        $access = is_array($access) ? $access : [];
        if (!(bool)($access['enabled'] ?? true)) {
            return;
        }

        $path = $request->getPathInfo();
        $path = $path === '' ? '/' : $path;

        $excludePaths = $access['exclude_paths'] ?? [];
        $excludePaths = is_array($excludePaths) ? $excludePaths : [];
        foreach ($excludePaths as $excluded) {
            $excluded = (string)$excluded;
            if ($excluded !== '' && $excluded === $path) {
                return;
            }
        }

        $status = (int)$response->getStatusCode();
        RequestContext::set(self::CONTEXT_STATUS, $status);

        $start = $request->attributes->get(self::ATTR_START_TS);
        if (!is_float($start)) {
            $contextStart = RequestContext::get(self::CONTEXT_START_TS);
            $start = is_float($contextStart) ? $contextStart : microtime(true);
        }
        $durationMs = (int)round((microtime(true) - $start) * 1000);

        $routeAction = (string)RequestContext::get('route', 'unknown');
        if ($routeAction === 'unknown') {
            $routeAction = self::resolveRouteAction($request);
        }

        $payload = [
            'ts' => Carbon::now()->toIso8601String(),
            'type' => 'access',
            'request_id' => $requestId,
            'method' => strtoupper($request->getMethod()),
            'path' => $path,
            'status' => $status,
            'duration_ms' => $durationMs,
            'ip' => (string)$request->ip(),
            'ua' => substr((string)$request->userAgent(), 0, 200),
            'route' => $routeAction,
        ];

        $query = $request->query->all();
        if (is_array($query) && $query !== []) {
            $payload['query'] = self::redactKeys($query, (array)($logging['redact']['body_keys'] ?? []));
        }

        $sub = RequestContext::get('sub');
        if (is_string($sub) && trim($sub) !== '') {
            $payload['sub'] = trim($sub);
        }

        if ($status >= 400 && (bool)($access['log_body_on_error'] ?? true)) {
            $maxBodyBytes = (int)($access['max_body_bytes'] ?? 4096);
            if ($maxBodyBytes < 1) {
                $maxBodyBytes = 4096;
            }

            $payload['body'] = self::safeRequestBody($request, $maxBodyBytes, (array)($logging['redact']['body_keys'] ?? []));
        }

        $level = AccessLogLevelResolver::resolve($status);
        Log::channel('sapi')->log($level, $path, $payload);

        $request->attributes->set(self::ATTR_LOGGED, true);
        RequestContext::set(self::CONTEXT_LOGGED, true);
    }

    public static function logExceptionFromGlobals(\Throwable $exception): void
    {
        self::ensureFreshRequestContext();

        if (!self::shouldLogCurrentRequest()) {
            return;
        }

        $status = 500;
        if ($exception instanceof HttpExceptionInterface) {
            $status = (int)$exception->getStatusCode();
        }

        $request = Request::createFromGlobals();

        $requestId = RequestContext::get('request_id');
        if (is_string($requestId) && trim($requestId) !== '') {
            $request->attributes->set(self::ATTR_REQUEST_ID, substr(trim($requestId), 0, 128));
        }

        $start = RequestContext::get(self::CONTEXT_START_TS);
        if (!is_float($start)) {
            $start = isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float)$_SERVER['REQUEST_TIME_FLOAT'] : microtime(true);
        }
        $request->attributes->set(self::ATTR_START_TS, $start);

        $response = new Response('', $status);
        self::log($request, $response);
    }

    public static function terminateFromGlobals(): void
    {
        self::ensureFreshRequestContext();

        if (!self::shouldLogCurrentRequest()) {
            return;
        }

        $status = RequestContext::get(self::CONTEXT_STATUS);
        if (!is_int($status) || $status < 100) {
            $status = (int)(http_response_code() ?: 200);
        }

        $request = Request::createFromGlobals();

        $requestId = RequestContext::get('request_id');
        if (is_string($requestId) && trim($requestId) !== '') {
            $request->attributes->set(self::ATTR_REQUEST_ID, substr(trim($requestId), 0, 128));
        }

        $start = RequestContext::get(self::CONTEXT_START_TS);
        if (!is_float($start)) {
            $start = isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float)$_SERVER['REQUEST_TIME_FLOAT'] : microtime(true);
        }
        $request->attributes->set(self::ATTR_START_TS, $start);

        $response = new Response('', $status);
        self::log($request, $response);
    }

    private static function shouldLogCurrentRequest(): bool
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $path = (string)(parse_url($uri, PHP_URL_PATH) ?: '');
        if ($path === '') {
            return false;
        }

        $basePath = trim((string)env('SAPI_BASE_PATH', 'api'), '/');
        if ($basePath === '') {
            return false;
        }

        return $path === '/' . $basePath || str_starts_with($path, '/' . $basePath . '/');
    }

    private static function ensureFreshRequestContext(): void
    {
        $ts = $_SERVER['REQUEST_TIME_FLOAT'] ?? $_SERVER['REQUEST_TIME'] ?? null;
        if ($ts === null) {
            return;
        }

        $current = (string)$ts;
        $previous = RequestContext::get(self::CONTEXT_REQ_TS);
        if (!is_string($previous) || $previous !== $current) {
            RequestContext::reset();
            RequestContext::set(self::CONTEXT_REQ_TS, $current);
        }
    }

    private static function loggingConfig(): array
    {
        $cfg = config('sapi.logging', []);
        $cfg = is_array($cfg) ? $cfg : [];
        if ($cfg !== []) {
            return $cfg;
        }

        $excludePathsRaw = (string)env('SAPI_LOG_EXCLUDE_PATHS', '');
        $excludePaths = $excludePathsRaw !== '' ? array_values(array_filter(array_map('trim', explode(',', $excludePathsRaw)))) : [];

        $redactKeysRaw = (string)env('SAPI_REDACT_BODY_KEYS', 'password,token,refresh_token,jwt,secret');
        $redactKeys = array_values(array_filter(array_map('trim', explode(',', $redactKeysRaw))));

        return [
            'enabled' => (bool)(int)env('SAPI_LOGGING_ENABLED', 1),
            'access' => [
                'enabled' => (bool)(int)env('SAPI_LOG_ACCESS_ENABLED', 1),
                'exclude_paths' => $excludePaths,
                'log_body_on_error' => (bool)(int)env('SAPI_LOG_BODY_ON_ERROR', 1),
                'max_body_bytes' => (int)env('SAPI_LOG_MAX_BODY_BYTES', 4096),
            ],
            'redact' => [
                'body_keys' => $redactKeys,
            ],
        ];
    }

    private static function resolveRouteAction(Request $request): string
    {
        $route = $request->route();
        if ($route === null) {
            return 'unknown';
        }

        if (is_object($route) && method_exists($route, 'getActionName')) {
            $action = (string)$route->getActionName();
            if ($action === '' || $action === 'Closure') {
                return 'unknown';
            }
            return $action;
        }

        return 'unknown';
    }

    /**
     * @return array{0:?string,1:null|array|int}
     */
    private static function resolveJwtClaims(Request $request): array
    {
        $token = self::extractBearerToken((string)$request->headers->get('Authorization', ''));
        if ($token === null) {
            return [null, null];
        }

        $secret = (string)env('SAPI_JWT_SECRET', '');
        if ($secret === '') {
            return [null, null];
        }

        $payload = null;

        try {
            if (class_exists(JWT::class) && class_exists(Key::class)) {
                $decoded = JWT::decode($token, new Key($secret, 'HS256'));
                $payload = is_object($decoded) ? (array)$decoded : null;
            } else {
                $payload = self::decodeJwtHs256($token, $secret);
            }
        } catch (\Throwable) {
            return [null, null];
        }

        if (!is_array($payload)) {
            return [null, null];
        }

        $sub = isset($payload['sub']) ? (string)$payload['sub'] : null;
        if ($sub !== null) {
            $sub = trim($sub);
            if ($sub === '') {
                $sub = null;
            }
        }

        $scopes = $payload['scopes'] ?? null;
        if (is_string($scopes)) {
            $scopes = array_values(array_filter(array_map('trim', explode(',', $scopes))));
        }

        if (is_array($scopes)) {
            $scopes = array_values(array_map('strval', $scopes));
        } else {
            $scopes = null;
        }

        return [$sub, $scopes];
    }

    private static function extractBearerToken(string $authorizationHeader): ?string
    {
        $authorizationHeader = trim($authorizationHeader);
        if ($authorizationHeader === '') {
            return null;
        }

        if (!preg_match('~^Bearer\\s+(.+)$~i', $authorizationHeader, $m)) {
            return null;
        }

        $token = trim($m[1]);
        return $token !== '' ? $token : null;
    }

    private static function safeRequestBody(Request $request, int $maxBodyBytes, array $redactKeys): array|string
    {
        $contentType = strtolower((string)$request->headers->get('Content-Type', ''));
        $raw = (string)$request->getContent();

        if ($raw === '') {
            return (object)[];
        }

        $rawTruncated = $raw;
        $wasTruncated = false;
        if (strlen($rawTruncated) > $maxBodyBytes) {
            $rawTruncated = substr($rawTruncated, 0, $maxBodyBytes);
            $wasTruncated = true;
        }

        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($rawTruncated, true);
            if (is_array($decoded)) {
                $decoded = self::redactKeys($decoded, $redactKeys);
                if ($wasTruncated) {
                    $decoded['_truncated'] = true;
                }
                return $decoded;
            }
        }

        $rawTruncated = self::redactRawString($rawTruncated, $redactKeys);
        if ($wasTruncated) {
            $rawTruncated .= '…';
        }

        return $rawTruncated;
    }

    private static function redactRawString(string $raw, array $redactKeys): string
    {
        foreach ($redactKeys as $key) {
            $key = (string)$key;
            if ($key === '') {
                continue;
            }

            $raw = preg_replace('~("' . preg_quote($key, '~') . '"\\s*:\\s*)"(.*?)"~i', '$1"***"', $raw) ?? $raw;
        }

        return $raw;
    }

    private static function redactKeys(array $data, array $redactKeys): array
    {
        $normalized = [];
        foreach ($redactKeys as $key) {
            $key = strtolower(trim((string)$key));
            if ($key !== '') {
                $normalized[$key] = true;
            }
        }

        return self::redactKeysRecursive($data, $normalized);
    }

    private static function redactKeysRecursive(array $data, array $redactMap): array
    {
        foreach ($data as $key => $value) {
            $keyString = is_string($key) ? strtolower($key) : null;

            if ($keyString !== null && isset($redactMap[$keyString])) {
                $data[$key] = '***';
                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::redactKeysRecursive($value, $redactMap);
            }
        }

        return $data;
    }

    private static function decodeJwtHs256(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = json_decode(self::base64UrlDecode($encodedHeader), true);
        if (!is_array($header) || (($header['alg'] ?? null) !== 'HS256')) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($encodedPayload), true);
        if (!is_array($payload)) {
            return null;
        }

        $signingInput = $encodedHeader . '.' . $encodedPayload;
        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        $expected = self::base64UrlEncode($signature);

        if (!hash_equals($expected, $encodedSignature)) {
            return null;
        }

        $now = time();
        if (isset($payload['nbf']) && (int)$payload['nbf'] > $now) {
            return null;
        }
        if (isset($payload['exp']) && (int)$payload['exp'] < $now) {
            return null;
        }

        return $payload;
    }

    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return (string)base64_decode(strtr($data, '-_', '+/'));
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
