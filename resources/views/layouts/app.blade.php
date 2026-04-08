<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    @php
        $isDashboardRoute = request()->routeIs('dashboard', 'game.dashboard');
        $isAdminRoute = request()->routeIs('admin.*');
        $isProfileRoute = request()->routeIs('profile.*');
        $useUtilityShell = ! ($isDashboardRoute || $isProfileRoute);
    @endphp
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=cinzel:500,600,700|source-sans-pro:400,600,700&display=swap" rel="stylesheet" />

        <!-- Avoid runtime errors from third-party globals in test/embedded browsers -->
        <script>
            try {
                if (typeof window !== 'undefined') {
                    window.mgt = window.mgt || {};
                    if (typeof window.mgt.clearMarks !== 'function') {
                        window.mgt.clearMarks = function() { /* noop for environments without full mgt */ };
                    }
                }
            } catch (e) {
                console.warn('mgt stub setup failed', e);
            }
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body @class([
        'game-shell antialiased',
        'app-shell-dashboard' => $isDashboardRoute,
        'app-shell-utility' => $useUtilityShell,
        'app-shell-admin' => $isAdminRoute,
        'app-shell-profile' => $isProfileRoute,
    ])>
        @if ($useUtilityShell)
            <div class="public-site-shell utility-site-shell">
                @include('layouts.navigation')

                @isset($header)
                    <header class="public-site-main utility-page-header-stage">
                        <div class="public-surface public-section-card utility-page-header-card">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main class="public-site-main utility-page-main pb-12">
                    {{ $slot }}
                </main>

                @include('partials.metin2-footer')
            </div>
        @else
            <div class="min-h-screen">
                @include('layouts.navigation')

                @isset($header)
                    <header class="portal-stage pb-0">
                        <div class="w-full">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main class="pb-12">
                    {{ $slot }}
                </main>
            </div>
        @endif
    </body>
</html>
