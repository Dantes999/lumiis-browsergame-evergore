<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=cinzel:500,600,700|source-sans-pro:400,600,700&display=swap" rel="stylesheet" />

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
    <body class="game-shell public-login-page antialiased">
        <div class="public-site-shell public-site-shell-login public-site-shell-guest">
            @include('partials.metin2-header', ['compactHeader' => true])

            <main class="public-site-main public-landing-main public-guest-main">
                <section {{ $attributes->class(['public-surface public-auth-main public-auth-main-standalone']) }}>
                    {{ $slot }}
                </section>
            </main>
        </div>
    </body>
</html>
