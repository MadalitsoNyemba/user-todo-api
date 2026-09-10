<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
|
| Versioned from the start so a breaking change ships as /v2 rather than as a
| coordinated client release. Register and login are the only public routes;
| logout, refresh, profile, and everything else sit behind auth.jwt.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login', [AuthController::class, 'login'])->name('login');

        Route::middleware('auth.jwt')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
        });
    });

    Route::middleware('auth.jwt')->group(function (): void {
        Route::get('me', [ProfileController::class, 'show'])->name('me.show');
        Route::patch('me', [ProfileController::class, 'update'])->name('me.update');
        Route::delete('me', [ProfileController::class, 'destroy'])->name('me.destroy');
    });
});
