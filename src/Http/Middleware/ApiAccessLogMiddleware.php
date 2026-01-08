<?php namespace Seiger\sApi\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Seiger\sApi\Http\ApiResponse;
use Seiger\sApi\Logging\RequestContext;
use Seiger\sApi\sApi;
use Symfony\Component\HttpFoundation\Response;

class ApiAccessLogMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $requestId = (string) $request->headers->get('X-Request-Id', '');
        $requestId = trim($requestId);
        if ($requestId === '') {
            $requestId = $this->uuidV4();
        } else {
            $requestId = substr($requestId, 0, 128);
        }

        $logging = sApi::config('logging', []);
        $logging = is_array($logging) ? $logging : [];

        $routeAction = $this->resolveRouteAction($request);
        [$sub, $scopes] = $this->resolveJwtClaims($request);

        $this->fillRequestContext($request, $requestId, $routeAction, $sub, $scopes);

        try {
            /** @var Response $response */
            $response = $next($request);
        } catch (\Throwable $e) {
            $response = ApiResponse::error('Internal server error.', 500, (object) []);
        }

        $response->headers->set('X-Request-Id', $requestId);

        try {
            if ($routeAction === 'unknown') {
                $routeAction = $this->resolveRouteAction($request);
            }
            $this->logAccess($request, $response, $requestId, $start, $logging, $routeAction, $sub);
        } catch (\Throwable) {
            // Never fail the request because of access log issues.
        }

        return $response;
    }

    private function fillRequestContext(Request $request, string $requestId, string $route, ?string $sub, null|array|int $scopes): void
    {
        RequestContext::set('request_id', $requestId);
        RequestContext::set('ip', (string)$request->ip());
        RequestContext::set('route', $route);
        if ($sub !== null) {
            RequestContext::set('sub', $sub);
        }
        if ($scopes !== null) {
            RequestContext::set('scopes', $scopes);
        }
    }

    private function logAccess(
        Request $request,
        Response $response,
        string $requestId,
        float $start,
        array $logging,
        string $route,
        ?string $sub
    ): void
    {
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
            $excluded = (string) $excluded;
            if ($excluded !== '' && $excluded === $path) {
                return;
            }
        }

        $status = (int) $response->getStatusCode();
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $payload = [
            'ts' => Carbon::now()->toIso8601String(),
            'type' => 'access',
            'request_id' => $requestId,
            'method' => strtoupper($request->getMethod()),
            'path' => $path,
            'status' => $status,
            'duration_ms' => $durationMs,
            'ip' => (string) $request->ip(),
            'ua' => substr((string) $request->userAgent(), 0, 200),
            'route' => $route,
        ];

        $query = $request->query->all();
        if (is_array($query) && $query !== []) {
            $payload['query'] = $this->redactKeys($query, (array)($logging['redact']['body_keys'] ?? []));
        }

        if ($sub !== null) {
            $payload['sub'] = $sub;
        }

        if ($status >= 400 && (bool)($access['log_body_on_error'] ?? true)) {
            $maxBodyBytes = (int)($access['max_body_bytes'] ?? 4096);
            if ($maxBodyBytes < 1) {
                $maxBodyBytes = 4096;
            }

            $payload['body'] = $this->safeRequestBody($request, $maxBodyBytes, (array)($logging['redact']['body_keys'] ?? []));
        }

        Log::channel('sapi')->info($path, $payload);
    }

    private function resolveRouteAction(Request $request): string
    {
        $route = $request->route();
        if ($route === null) {
            return 'unknown';
        }

        if (is_object($route) && method_exists($route, 'getActionName')) {
            $action = (string) $route->getActionName();
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
    private function resolveJwtClaims(Request $request): array
    {
        $token = $this->extractBearerToken((string) $request->headers->get('Authorization', ''));
        if ($token === null) {
            return [null, null];
        }

        $secret = (string) sApi::config('jwt_secret', '');
        if ($secret === '') {
            return [null, null];
        }

        $payload = null;

        try {
            if (class_exists(JWT::class) && class_exists(Key::class)) {
                $decoded = JWT::decode($token, new Key($secret, 'HS256'));
                $payload = is_object($decoded) ? (array) $decoded : null;
            } else {
                $payload = $this->decodeJwtHs256($token, $secret);
            }
        } catch (\Throwable) {
            return [null, null];
        }

        if (!is_array($payload)) {
            return [null, null];
        }

        $sub = isset($payload['sub']) ? (string) $payload['sub'] : null;
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

    private function extractBearerToken(string $authorizationHeader): ?string
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

    private function safeRequestBody(Request $request, int $maxBodyBytes, array $redactKeys): array|string
    {
        $contentType = strtolower((string) $request->headers->get('Content-Type', ''));
        $raw = (string) $request->getContent();

        if ($raw === '') {
            return (object) [];
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
                $decoded = $this->redactKeys($decoded, $redactKeys);
                if ($wasTruncated) {
                    $decoded['_truncated'] = true;
                }
                return $decoded;
            }
        }

        $rawTruncated = $this->redactRawString($rawTruncated, $redactKeys);
        if ($wasTruncated) {
            $rawTruncated .= '…';
        }

        return $rawTruncated;
    }

    private function redactRawString(string $raw, array $redactKeys): string
    {
        foreach ($redactKeys as $key) {
            $key = (string) $key;
            if ($key === '') {
                continue;
            }

            $raw = preg_replace('~("' . preg_quote($key, '~') . '"\\s*:\\s*)"(.*?)"~i', '$1"***"', $raw) ?? $raw;
        }

        return $raw;
    }

    private function redactKeys(array $data, array $redactKeys): array
    {
        $normalized = [];
        foreach ($redactKeys as $key) {
            $key = strtolower(trim((string) $key));
            if ($key !== '') {
                $normalized[$key] = true;
            }
        }

        return $this->redactKeysRecursive($data, $normalized);
    }

    private function redactKeysRecursive(array $data, array $redactMap): array
    {
        foreach ($data as $key => $value) {
            $keyString = is_string($key) ? strtolower($key) : null;

            if ($keyString !== null && isset($redactMap[$keyString])) {
                $data[$key] = '***';
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->redactKeysRecursive($value, $redactMap);
            }
        }

        return $data;
    }

    private function decodeJwtHs256(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = json_decode($this->base64UrlDecode($encodedHeader), true);
        if (!is_array($header) || (($header['alg'] ?? null) !== 'HS256')) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);
        if (!is_array($payload)) {
            return null;
        }

        $signingInput = $encodedHeader . '.' . $encodedPayload;
        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        $expected = $this->base64UrlEncode($signature);

        if (!hash_equals($expected, $encodedSignature)) {
            return null;
        }

        $now = time();
        if (isset($payload['nbf']) && (int) $payload['nbf'] > $now) {
            return null;
        }
        if (isset($payload['exp']) && (int) $payload['exp'] < $now) {
            return null;
        }

        return $payload;
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
