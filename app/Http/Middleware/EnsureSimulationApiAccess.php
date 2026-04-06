<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSimulationApiAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = app()->environment(['local', 'testing']) || (bool) config('game_simulator.enabled', false);

        if (! $enabled) {
            return response()->json([
                'message' => 'Simulation API is not enabled.',
            ], 404);
        }

        $configuredKey = trim((string) config('game_simulator.key', ''));

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
