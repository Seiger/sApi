<?php namespace Seiger\sApi\Controllers;

use Illuminate\Http\Request;
use Seiger\sApi\Auth\JwtService;
use Seiger\sApi\Auth\UserProvider;
use Seiger\sApi\Http\ApiResponse;
use Seiger\sApi\sApi;

class TokenController
{
    public function token(Request $request)
    {
        $username = (string) $request->input('username', '');
        if ($username === '') {
            $raw = (string) $request->getContent();
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['username'])) {
                    $username = (string) $decoded['username'];
                }
            }
        }
        $username = trim($username);

        if ($username === '') {
            return ApiResponse::error('Username are required.', 422, (object) []);
        }

        $user = (new UserProvider())->findByUsername($username);
        if (!$user || (int) $user->id < 1) {
            return ApiResponse::error('User not found.', 404, (object) []);
        }

        $allowedUsernames = sApi::config('allowed_usernames', []);
        if (is_string($allowedUsernames)) {
            $allowedUsernames = array_values(array_filter(array_map('trim', explode(',', $allowedUsernames))));
        }

        if (!is_array($allowedUsernames) || $allowedUsernames === [] || !in_array($username, $allowedUsernames, true)) {
            return ApiResponse::error('Access denied.', 403, (object) []);
        }

        try {
            $token = (new JwtService())->issue([
                'sub' => $username,
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 500, (object) []);
        }

        return ApiResponse::success(['token' => $token], '', 200);
    }
}
