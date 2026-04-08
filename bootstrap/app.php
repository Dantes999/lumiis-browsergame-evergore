<?php

use App\Http\Middleware\ApplySecurityHeaders;
use App\Http\Middleware\EnsureSimulationApiAccess;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\PreventAuthenticatedResponseCache;
use App\Http\Middleware\SetPreferredLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetPreferredLocale::class,
            ApplySecurityHeaders::class,
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'no-store' => PreventAuthenticatedResponseCache::class,
            'simulation-api' => EnsureSimulationApiAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
