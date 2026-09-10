<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\JobStatusController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\TodoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
|
| Versioned from the start so a breaking change ships as /v2 rather than as a
| coordinated client release. Register and login are the only public routes;
| logout, refresh, profile, todos, jobs, and everything else sit behind auth.jwt.
|
| Todo {id} is a plain integer — no route model binding — so lookups go through
| findForUser and another user’s todo is indistinguishable from a missing one.
| Job {uuid} is likewise scoped by user; UUIDs are not enumerable across tenants.
|
| Rate limits: public auth uses the `auth` limiter (email + IP). Everything
| behind auth.jwt uses the `api` limiter (authenticated user).
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::middleware('throttle:auth')->group(function (): void {
            Route::post('register', [AuthController::class, 'register'])->name('register');
            Route::post('login', [AuthController::class, 'login'])->name('login');
        });

        Route::middleware(['auth.jwt', 'throttle:api'])->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
        });
    });

    Route::middleware(['auth.jwt', 'throttle:api'])->group(function (): void {
        Route::get('me', [ProfileController::class, 'show'])->name('me.show');
        Route::patch('me', [ProfileController::class, 'update'])->name('me.update');
        Route::delete('me', [ProfileController::class, 'destroy'])->name('me.destroy');

        Route::get('todos', [TodoController::class, 'index'])->name('todos.index');
        Route::post('todos', [TodoController::class, 'store'])->name('todos.store');
        Route::post('todos/bulk-complete', [TodoController::class, 'bulkComplete'])->name('todos.bulk-complete');
        Route::get('todos/{id}', [TodoController::class, 'show'])->whereNumber('id')->name('todos.show');
        Route::patch('todos/{id}', [TodoController::class, 'update'])->whereNumber('id')->name('todos.update');
        Route::delete('todos/{id}', [TodoController::class, 'destroy'])->whereNumber('id')->name('todos.destroy');

        Route::get('jobs/{uuid}', [JobStatusController::class, 'show'])
            ->whereUuid('uuid')
            ->name('jobs.show');
    });
});
