<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $frontendOrigin = $this->origin((string) config('app.frontend_url'));
        $appOrigin = $this->origin((string) config('app.url'));
        $formAction = $frontendOrigin !== null && $frontendOrigin !== $appOrigin
            ? "'self' ".$frontendOrigin
            : "'self'";

        $response->headers->set(
            'Content-Security-Policy',
            "base-uri 'self'; form-action ".$formAction."; frame-ancestors 'none'; object-src 'none'",
        );

        return $response;
    }

    private function origin(string $url): ?string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }
}
