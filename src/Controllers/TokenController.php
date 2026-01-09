<?php namespace Seiger\sApi\Controllers;

use Illuminate\Http\Request;
use Seiger\sApi\Auth\JwtService;
use Seiger\sApi\Auth\UserProvider;
use Seiger\sApi\Http\ApiResponse;
use Seiger\sApi\Logging\AuditLogger;
use Seiger\sApi\Logging\RequestContext;

class TokenController
{
    public function token(Request $request)
    {
        $decoded = null;
        $raw = (string)$request->getContent();
        if ($raw !== '') {
            $maybe = json_decode($raw, true);
            if (is_array($maybe)) {
                $decoded = $maybe;
            }
        }

        $username = (string)$request->input('username', '');
        if ($username === '' && is_array($decoded) && isset($decoded['username'])) {
            $username = (string)$decoded['username'];
        }
        $username = trim($username);

        if ($username === '') {
            return ApiResponse::error('Username is required.', 422, (object)[]);
        }

        $password = (string)$request->input('password', '');
        if ($password === '' && is_array($decoded) && isset($decoded['password'])) {
            $password = (string)$decoded['password'];
        }
        $password = trim($password);

        if ($password === '') {
            $password = 'password';
        }

        $provider = new UserProvider;
        $user = $provider->findByUsername($username);
        if (!$user || (int)$user->id < 1) {
            return ApiResponse::error('User not found.', 404, (object)[]);
        }

        if (!$provider->checkPassword($user, $password)) {
            return ApiResponse::error('Invalid credentials.', 401, (object)[]);
        }

        $rolesRaw = (string)env('SAPI_ALLOWED_USER_ROLES', '1');
        $roles = array_values(array_filter(array_map('trim', explode(',', $rolesRaw))));
        $roles = array_values(array_filter(array_map('intval', $roles), static fn(int $v) => $v > 0));
        if ($roles === []) {
            $roles = [4];
        }

        $role = (int)($user->attributes->role ?? 0);
        if (!in_array($role, $roles, true)) {
            return ApiResponse::error('Access denied.', 403, (object)[]);
        }

        try {
            $token = (new JwtService)->issue([
                'sub' => $username,
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 500, (object)[]);
        }

        try {
            RequestContext::set('user_id', (int)$user->id);
            RequestContext::set('sub', $username);

            app(AuditLogger::class)->event('token.issued', [
                'username' => $username,
            ], 'notice');
        } catch (\Throwable) {
            // audit logging must never break the request
        }

        return ApiResponse::success(['token' => $token], '', 200);
    }
}
