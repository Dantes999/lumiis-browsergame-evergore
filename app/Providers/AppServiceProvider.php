<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! app()->isProduction());

        RateLimiter::for('game-actions', function (Request $request): array {
            $accountKey = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return [
                Limit::perMinute(20)->by('game-actions:account:'.$accountKey),
                Limit::perMinute(60)->by('game-actions:ip:'.$request->ip()),
            ];
        });

        // JSON API counterparts for the SPA. Reads are cheaper than writes, so they
        // get a wider budget while every state-changing action stays tight.
        RateLimiter::for('game-api-state', function (Request $request): array {
            $accountKey = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return [
                Limit::perMinute(120)->by('game-api-state:account:'.$accountKey),
                Limit::perMinute(240)->by('game-api-state:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('game-api-reads', function (Request $request): array {
            $accountKey = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return [
                Limit::perMinute(120)->by('game-api-reads:account:'.$accountKey),
                Limit::perMinute(240)->by('game-api-reads:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('game-api-writes', function (Request $request): array {
            $accountKey = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return [
                Limit::perMinute(20)->by('game-api-writes:account:'.$accountKey),
                Limit::perMinute(60)->by('game-api-writes:ip:'.$request->ip()),
            ];
        });

        // Fight simulations are CPU-bound (iterations x rounds), so they get a much
        // tighter budget than regular player traffic. Keyed clients are limited per
        // key and per source IP so one leaked key cannot exhaust a shared host.
        RateLimiter::for('simulation-requests', function (Request $request): array {
            $apiKey = trim((string) $request->header('X-Simulation-Key', ''));
            $clientKey = $apiKey !== ''
                ? 'key:'.hash('sha256', $apiKey)
                : 'ip:'.$request->ip();

            return [
                Limit::perMinute(15)->by('simulation-requests:'.$clientKey),
                Limit::perMinute(30)->by('simulation-requests:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('dashboard-views', function (Request $request): array {
            $accountKey = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return [
                // The dashboard is the heaviest player-facing SSR page, so it gets its own limiter.
                // This is application-level abuse protection, not a replacement for edge DDoS filtering.
                Limit::perMinute(120)->by('dashboard-views:account:'.$accountKey),
                Limit::perMinute(240)->by('dashboard-views:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('login-requests', function (Request $request): array {
            return [
                // LoginRequest already rate limits by email+IP. This route limiter caps
                // wide credential-spraying against many addresses from one source IP.
                Limit::perMinute(30)->by('login-requests:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('registration-attempts', function (Request $request): array {
            $email = Str::lower($request->string('email')->trim()->value());

            $limits = [
                Limit::perMinute(5)->by('registration-attempts:ip:'.$request->ip()),
            ];

            if ($email !== '') {
                $limits[] = Limit::perHour(3)->by('registration-attempts:email:'.$email);
            }

            return $limits;
        });

        RateLimiter::for('password-reset-requests', function (Request $request): array {
            $email = Str::lower($request->string('email')->trim()->value());

            $limits = [
                Limit::perMinute(5)->by('password-reset-requests:ip:'.$request->ip()),
            ];

            if ($email !== '') {
                $limits[] = Limit::perMinute(3)->by('password-reset-requests:email:'.$email);
            }

            return $limits;
        });
    }
}
