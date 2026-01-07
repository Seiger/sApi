<?php namespace Seiger\sApi\Auth;

use Firebase\JWT\JWT;
use Seiger\sApi\sApi;

class JwtService
{
    public function issue(array $payload, int $ttl = null): string
    {
        $now = time();

        if (!isset($payload['iat'])) {
            $payload['iat'] = $now;
        }

        $ttl = $ttl ?? (int) sApi::config('jwt_ttl', 3600);
        if ($ttl < 1) {
            $ttl = 3600;
        }

        if (!isset($payload['exp'])) {
            $payload['exp'] = (int) $payload['iat'] + $ttl;
        }

        $payload['scopes'] = $this->normalizeScopes($payload['scopes'] ?? sApi::config('jwt_scopes', ['*']));

        if (!isset($payload['iss'])) {
            $iss = (string) sApi::config('jwt_iss', '');
            if ($iss !== '') {
                $payload['iss'] = $iss;
            }
        }

        $secret = (string) sApi::config('jwt_secret', '');
        if ($secret === '') {
            throw new \RuntimeException('sApi JWT secret is not configured (seiger.settings.sApi.jwt_secret).');
        }

        if (class_exists(JWT::class)) {
            return JWT::encode($payload, $secret, 'HS256');
        }

        return $this->encodeJwtHs256($payload, $secret);
    }

    private function normalizeScopes(mixed $scopes): array
    {
        if (is_string($scopes)) {
            $scopes = array_values(array_filter(array_map('trim', explode(',', $scopes))));
        }

        if (!is_array($scopes) || $scopes === []) {
            return ['*'];
        }

        return array_values(array_filter(array_map('strval', $scopes)));
    }

    private function encodeJwtHs256(array $payload, string $secret): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));

        $signingInput = $encodedHeader . '.' . $encodedPayload;
        $signature = hash_hmac('sha256', $signingInput, $secret, true);

        return $signingInput . '.' . $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
