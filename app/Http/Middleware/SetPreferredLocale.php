<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active locale in a predictable order:
 * - authenticated user preference
 * - explicit session override
 * - browser Accept-Language
 * - configured app fallback
 *
 * This keeps guest pages localised too, while still letting players lock their
 * own language once they pick it in settings.
 */
class SetPreferredLocale
{
    /**
     * @var list<string>
     */
    private array $supportedLocales = ['de', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $sessionLocale = $request->session()->get('app_locale');
        $userLocale = $request->user() !== null
            ? data_get($request->user()->getAttributes(), 'locale')
            : null;

        $normalizedUserLocale = $this->normalizeLocale($userLocale);
        $normalizedSessionLocale = $this->normalizeLocale($sessionLocale);

        $locale = $normalizedUserLocale
            ?? $normalizedSessionLocale
            ?? $this->detectBrowserLocale($request)
            ?? config('app.locale', 'de');

        App::setLocale($locale);

        if ($normalizedUserLocale !== null) {
            $request->session()->put('app_locale', $normalizedUserLocale);
        } elseif ($normalizedSessionLocale !== null) {
            $request->session()->put('app_locale', $normalizedSessionLocale);
        } else {
            // Guests and legacy users without a saved locale should continue to
            // follow browser detection on each request.
            $request->session()->forget('app_locale');
        }

        return $next($request);
    }

    private function detectBrowserLocale(Request $request): ?string
    {
        return $this->normalizeLocale($request->getPreferredLanguage($this->supportedLocales));
    }

    private function normalizeLocale(?string $locale): ?string
    {
        if (! is_string($locale) || $locale === '') {
            return null;
        }

        $normalized = strtolower(substr($locale, 0, 2));

        return in_array($normalized, $this->supportedLocales, true) ? $normalized : null;
    }
}
