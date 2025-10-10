<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['auth:sanctum', \App\Http\Middleware\HandleCors::class]],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register custom middleware
        $middleware->alias([
            'sanctum.broadcasting' => \App\Http\Middleware\SanctumBroadcastingAuth::class,
            'track.activity' => \App\Http\Middleware\TrackUserActivity::class,
        ]);
        
        // Apply activity tracking middleware to authenticated routes
        $middleware->web(append: [
            \App\Http\Middleware\TrackUserActivity::class,
        ]);
        
        $middleware->api(append: [
            \App\Http\Middleware\TrackUserActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
