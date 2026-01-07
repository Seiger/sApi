<?php

use Illuminate\Support\Facades\Route;
use Seiger\sApi\Controllers\MgrController;

Route::middleware(['mgr'])->prefix('sapi')->name('sApi.')->group(function () {
    Route::get('/', [MgrController::class, 'dashboard'])->name('dashboard');
    Route::get('/routes', [MgrController::class, 'routes'])->name('routes');
    Route::get('/auth', [MgrController::class, 'auth'])->name('auth');
    Route::get('/providers', [MgrController::class, 'providers'])->name('providers');
    Route::get('/logs', [MgrController::class, 'logs'])->name('logs');
});