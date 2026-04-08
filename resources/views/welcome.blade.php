<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=cinzel:500,600,700|source-sans-pro:400,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="game-shell public-login-page antialiased">
        <div class="public-site-shell public-site-shell-login">
            @include('partials.metin2-header', ['compactHeader' => true])

            <main class="public-site-main public-landing-main">
                <section class="public-surface public-auth-main public-landing-login-card">
                    @include('auth.partials.login-panel', [
                        'emailId' => 'welcome-email',
                        'passwordId' => 'welcome-password',
                        'rememberId' => 'welcome-remember',
                    ])
                </section>
            </main>
        </div>
    </body>
</html>
