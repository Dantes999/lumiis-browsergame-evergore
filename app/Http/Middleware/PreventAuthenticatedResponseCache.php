<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prevents authenticated HTML pages from being cached by browsers or shared proxies.
 *
 * This is a small but important privacy layer for SSR screens such as the dashboard:
 * user A should never receive user B's cached page through a misconfigured intermediary.
 */
class PreventAuthenticatedResponseCache
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() === null || ! $request->isMethodSafe()) {
            return $response;
        }

        $response->headers->set('Cache-Control', 'no-store, private, max-age=0, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');

        return $response;
    }
}
