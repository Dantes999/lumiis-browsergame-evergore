<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The player UI lives in the standalone SPA. Legacy Blade game pages stay
 * available to the automated test suite but redirect real visitors.
 */
class RedirectLegacyGameFrontend
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing') || config('app.legacy_game_ui')) {
            return $next($request);
        }

        $path = $request->routeIs('characters.index') ? '/characters' : '/map/1';

        return redirect()->away(rtrim((string) config('app.frontend_url'), '/').$path);
    }
}
