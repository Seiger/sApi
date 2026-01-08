<?php

return [
    'base_path' => env('SAPI_BASE_PATH', 'api'),
    'allowed_user_roles' => [4],
    'jwt_secret' => (string)env('SAPI_JWT_SECRET', ''),
    'jwt_ttl' => (int)env('SAPI_JWT_TTL', 3600),
    'jwt_scopes' => array_values(array_filter(array_map('trim', explode(',', (string)env('SAPI_JWT_SCOPES', '*'))))),
    'jwt_iss' => (string)env('SAPI_JWT_ISS', 'evo'),
    'logging' => [
        'enabled' => true,
        'access' => [
            'enabled' => true,
            'exclude_paths' => [],
            'log_body_on_error' => true,
            'max_body_bytes' => 4096,
        ],
        'audit' => [
            'enabled' => true,
            'exclude_events' => [],
            'max_context_bytes' => 8192,
        ],
        'redact' => [
            'body_keys' => ['password', 'token', 'refresh_token', 'jwt', 'secret'],
        ],
    ],
    'routes' => [
        [
            'method' => 'post',
            'path' => 'token',
            'prefix' => 'v1',
            'action' => [\Seiger\sApi\Controllers\TokenController::class, 'token'],
            'name' => 'v1.token',
        ],
    ],
];
