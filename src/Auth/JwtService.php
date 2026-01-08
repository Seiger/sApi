<?php namespace Seiger\sApi\Auth;

use Firebase\JWT\JWT;
use Seiger\sApi\sApi;

/**
 * Class JwtService
 *
 * Issues JSON Web Tokens (JWT) for sApi authentication layer.
 *
 * This service supports HS256 signing and automatically handles:
 * - issued-at (iat) claim
 * - expiration (exp) claim
 * - scopes normalization
 * - issuer (iss) claim
 *
 * If Firebase\JWT\JWT is available, it will be used.
 * Otherwise, a minimal internal HS256 encoder is applied.
 */
class JwtService
{
    /**
     * Issue a signed JWT token.
     *
     * Automatically injects standard JWT claims if they are missing:
     * - iat (issued at)
     * - exp (expiration time)
     * - iss (issuer, if configured)
     *
     * Scopes are normalized into an array of strings.
     *
     * @param array<string, mixed> $payload Initial JWT payload
     * @param int|null             $ttl     Token time-to-live in seconds
     *
     * @return string Signed JWT string
     *
     * @throws \RuntimeException If JWT secret is not configured
     */
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

        $payload['scopes'] = $this->normalizeScopes(
            $payload['scopes'] ?? sApi::config('jwt_scopes', ['*'])
        );

        if (!isset($payload['iss'])) {
            $iss = (string) sApi::config('jwt_iss', '');
            if ($iss !== '') {
                $payload['iss'] = $iss;
            }
        }

        $secret = (string) sApi::config('jwt_secret', '');
        if ($secret === '') {
            throw new \RuntimeException('sApi JWT secret is not configured.');
        }

        if (class_exists(JWT::class)) {
            return JWT::encode($payload, $secret, 'HS256');
        }

        return $this->encodeJwtHs256($payload, $secret);
    }

    /**
     * Normalize JWT scopes into a string array.
     *
     * Accepted formats:
     * - string: "read,write,admin"
     * - array:  ["read", "write"]
     *
     * If scopes are missing or invalid, wildcard scope ["*"] is returned.
     *
     * @param mixed $scopes
     * @return array<int, string>
     */
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

    /**
     * Encode a JWT token using HS256 without external libraries.
     *
     * This method is used as a fallback when firebase/php-jwt
     * is not available in the environment.
     *
     * @param array<string, mixed> $payload JWT payload
     * @param string              $secret  Signing secret
     *
     * @return string Signed JWT
     */
    private function encodeJwtHs256(array $payload, string $secret): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signingInput = $encodedHeader . '.' . $encodedPayload;
        $signature = hash_hmac('sha256', $signingInput, $secret, true);

        return $signingInput . '.' . $this->base64UrlEncode($signature);
    }

    /**
     * Encode data using Base64 URL-safe format.
     *
     * @param string $data Raw binary or string data
     * @return string Base64 URL-encoded string
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
