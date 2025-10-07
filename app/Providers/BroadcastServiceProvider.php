<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // We use the 'api' middleware group to ensure the request is authenticated
        // using the 'auth:sanctum' guard before checking channel permissions.
        // This solves the AccessDeniedHttpException when using Bearer tokens.
        Route::prefix('broadcasting')
            ->middleware(['api', 'auth:sanctum']) // Apply both API and Sanctum guard
            ->group(base_path('routes/channels.php'));
    }
}
