<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
|
| Versioned from the start so a breaking change ships as /v2 rather than as
| a coordinated client release. Register and login are the only public
| routes; everything else sits behind the JWT guard.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    // Endpoints land here from #25 onward.
});
