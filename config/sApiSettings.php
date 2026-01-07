<?php return [
    'base_path' => env('SAPI_BASE_PATH', 'api'),
    'allowed_usernames' => array_values(array_filter(array_map('trim', explode(',', (string)env('SAPI_ALLOWED_USERNAMES', ''))))),
    'jwt_secret' => (string)env('SAPI_JWT_SECRET', ''),
    'jwt_ttl' => (int)env('SAPI_JWT_TTL', 3600),
    'jwt_scopes' => array_values(array_filter(array_map('trim', explode(',', (string)env('SAPI_JWT_SCOPES', '*'))))),
    'jwt_iss' => (string)env('SAPI_JWT_ISS', 'evo'),
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
