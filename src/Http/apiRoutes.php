<?php

use Illuminate\Support\Facades\Route;
use Seiger\sApi\Controllers\TokenController;
use Seiger\sApi\Http\ApiResponse;
use Seiger\sApi\Http\Middleware\ApiAccessLogMiddleware;

$basePath = trim((string)env('SAPI_BASE_PATH', 'api'), '/');
$version = trim((string)env('SAPI_VERSION', ''), '/');

$group = Route::middleware(['web', ApiAccessLogMiddleware::class]);
if ($basePath !== '') {
    $group = $group->prefix($basePath);
}

$group->name('sApi.')->group(function () use ($version) {
    $registerToken = static function (): void {
        Route::post('token', [TokenController::class, 'token'])->name('token');
    };

    if ($version !== '') {
        Route::prefix($version)->group($registerToken);
    } else {
        $registerToken();
    }

    Route::any('{any}', static function () {
        return ApiResponse::error('Not found.', 404, (object)[]);
    })->where('any', '.*')->fallback()->name('fallback');
});
