<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
|
| Versioned from the start so a breaking change ships as /v2 rather than as a
| coordinated client release. Register and login are the only public routes;
| everything else sits behind the auth.jwt middleware.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login', [AuthController::class, 'login'])->name('login');
    });

    Route::middleware('auth.jwt')->group(function (): void {
        // Protected endpoints land here from the next slice onward.
    });
});
