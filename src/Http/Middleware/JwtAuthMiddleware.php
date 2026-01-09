<?php namespace Seiger\sApi\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Seiger\sApi\Http\ApiResponse;
use Seiger\sApi\Logging\RequestContext;
use Symfony\Component\HttpFoundation\Response;

final class JwtAuthMiddleware
{
    public function handle(Request $request, Closure $next, ...$requiredScopes): Response
    {
        $requiredScopes = array_values(array_filter(array_map(static fn($v) => trim((string)$v), $requiredScopes)));

        $existingPayload = $request->attributes->get('sapi.jwt.payload');
        if (is_array($existingPayload)) {
            $existingScopes = $request->attributes->get('sapi.jwt.scopes');
            $existingScopes = is_array($existingScopes) ? array_values(array_map('strval', $existingScopes)) : $this->normalizeScopes($existingPayload['scopes'] ?? null);

            if ($requiredScopes !== [] && !$this->scopesAllow($existingScopes, $requiredScopes)) {
                return ApiResponse::error('Forbidden.', 403, (object)[]);
            }

            return $next($request);
        }

        $token = $this->extractBearerToken((string)$request->headers->get('Authorization', ''));
        if ($token === null) {
            return ApiResponse::error('Unauthorized.', 401, (object)[]);
        }

        $secret = (string)env('SAPI_JWT_SECRET', '');
        if ($secret === '') {
            return ApiResponse::error('Server misconfigured.', 500, (object)[]);
        }

        $payload = $this->decodeHs256($token, $secret);
        if (!is_array($payload)) {
            return ApiResponse::error('Unauthorized.', 401, (object)[]);
        }

        if (!$this->validateIssuer($payload)) {
            return ApiResponse::error('Unauthorized.', 401, (object)[]);
        }

        if (!$this->validateTimeClaims($payload)) {
            return ApiResponse::error('Unauthorized.', 401, (object)[]);
        }

        $sub = isset($payload['sub']) ? trim((string)$payload['sub']) : '';
        $sub = $sub !== '' ? $sub : null;

        $scopes = $this->normalizeScopes($payload['scopes'] ?? null);

        RequestContext::set('sub', $sub);
        RequestContext::set('scopes', $scopes);

        $request->attributes->set('sapi.jwt.payload', $payload);
        $request->attributes->set('sapi.jwt.sub', $sub);
        $request->attributes->set('sapi.jwt.scopes', $scopes);

        if ($requiredScopes !== [] && !$this->scopesAllow($scopes, $requiredScopes)) {
            return ApiResponse::error('Forbidden.', 403, (object)[]);
        }

        return $next($request);
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

    private function decodeHs256(string $token, string $secret): ?array
    {
        try {
            if (class_exists(JWT::class) && class_exists(Key::class)) {
                $decoded = JWT::decode($token, new Key($secret, 'HS256'));
                return is_object($decoded) ? (array)$decoded : (is_array($decoded) ? $decoded : null);
            }
        } catch (\Throwable) {
            return null;
        }

        return $this->decodeJwtHs256Fallback($token, $secret);
    }

    private function validateIssuer(array $payload): bool
    {
        $expected = trim((string)env('SAPI_JWT_ISS', ''));
        if ($expected === '') {
            return true;
        }

        $actual = isset($payload['iss']) ? trim((string)$payload['iss']) : '';
        return $actual !== '' && hash_equals($expected, $actual);
    }

    private function validateTimeClaims(array $payload): bool
    {
        $now = time();

        if (isset($payload['nbf']) && (int)$payload['nbf'] > $now) {
            return false;
        }
        if (isset($payload['exp']) && (int)$payload['exp'] < $now) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeScopes(mixed $scopes): array
    {
        if (is_string($scopes)) {
            $scopes = array_values(array_filter(array_map('trim', explode(',', $scopes))));
        }

        if (is_array($scopes)) {
            return array_values(array_filter(array_map('strval', $scopes), static fn(string $v) => $v !== ''));
        }

        return [];
    }

    /**
     * @param array<int, string> $tokenScopes
     * @param array<int, string> $requiredScopes
     */
    private function scopesAllow(array $tokenScopes, array $requiredScopes): bool
    {
        if (in_array('*', $tokenScopes, true)) {
            return true;
        }

        $set = [];
        foreach ($tokenScopes as $scope) {
            $set[$scope] = true;
        }

        foreach ($requiredScopes as $required) {
            if (!isset($set[$required])) {
                return false;
            }
        }

        return true;
    }

    private function decodeJwtHs256Fallback(string $token, string $secret): ?array
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

        return $payload;
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return (string)base64_decode(strtr($data, '-_', '+/'));
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
