<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSimulationApiAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only the automated test environment bypasses the explicit switch and key.
        // A public server misconfigured as APP_ENV=local therefore no longer exposes
        // the CPU-heavy simulation endpoints.
        $trustedEnvironment = app()->environment('testing');
        $enabled = $trustedEnvironment || (bool) config('game_simulator.enabled', false);

        if (! $enabled) {
            return response()->json([
                'message' => 'Simulation API is not enabled.',
            ], 404);
        }

        $configuredKey = trim((string) config('game_simulator.key', ''));

        if (! $trustedEnvironment && $configuredKey === '') {
            return response()->json([
                'message' => 'Simulation API key is not configured.',
            ], 503);
        }

        if ($configuredKey !== '') {
            $providedKey = trim((string) $request->header('X-Simulation-Key', ''));

            if (! hash_equals($configuredKey, $providedKey)) {
                return response()->json([
                    'message' => 'Missing or invalid simulation API key.',
                ], 401);
            }
        }

        return $next($request);
    }
}
