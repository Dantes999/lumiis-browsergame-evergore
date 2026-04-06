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
    <body class="game-shell antialiased">
        @php
            $portalCtaUrl = Auth::check() ? route('dashboard') : route('register');
            $publicMetrics = [
                ['label' => __('Responsive states'), 'value' => __('Desktop + portrait + landscape')],
                ['label' => __('Visual direction'), 'value' => __('Metin2 palette, modern structure')],
                ['label' => __('Future asset support'), 'value' => __('Ready for your custom buttons and frames')],
            ];
            $systemCards = [
                [
                    'title' => __('Combat first'),
                    'copy' => __('Map movement, battles, loot and progress stay at the center while surrounding UI becomes quieter and easier to scan.'),
                ],
                [
                    'title' => __('Useful forms'),
                    'copy' => __('Login, registration and account recovery use larger targets, clearer hierarchy and a cleaner mobile rhythm.'),
                ],
                [
                    'title' => __('Admin stays coherent'),
                    'copy' => __('The same panel system now carries over to dashboards, management screens and utility pages instead of splitting into different skins.'),
                ],
            ];
            $previewCards = [
                ['tone' => 'ember', 'eyebrow' => __('Realm HUD'), 'title' => __('Map focus, system overlays, clean status bars')],
                ['tone' => 'gold', 'eyebrow' => __('Auth Flow'), 'title' => __('Account entry without the old portal clutter')],
                ['tone' => 'obsidian', 'eyebrow' => __('Admin'), 'title' => __('One control language across tables, editors and tools')],
                ['tone' => 'forest', 'eyebrow' => __('Mobile'), 'title' => __('Portrait and landscape layouts that stay usable mid-session')],
            ];
            $flowSteps = [
                __('Enter through a compact top bar with direct access to the realm or account flow.'),
                __('Move through larger surfaces and cleaner action groups instead of decorative image slices.'),
                __('Swap in custom buttons and container art later without needing another structural rewrite.'),
            ];
        @endphp

        <div class="public-site-shell">
            @include('partials.metin2-header')

            <main class="public-site-main">
                <section class="public-surface public-hero-panel" id="overview">
                    <div class="public-hero-grid">
                        <div class="public-hero-copy">
                            <span class="public-eyebrow">{{ __('Lumiis Interface Rework') }}</span>
                            <h1 class="public-hero-title">{{ __('The Metin2 mood stays. The old copycat frontend does not.') }}</h1>
                            <p class="public-hero-lead">
                                {{ __('The new UI keeps the warm gold, ember red and dark lacquer tone of Metin2, but replaces the outdated image-heavy layout with a modern system that is readable, touch-safe and ready for your own assets.') }}
                            </p>

                            <div class="public-hero-actions">
                                <a href="{{ $portalCtaUrl }}" class="theme-primary-button public-site-cta-link">
                                    {{ Auth::check() ? __('Open Realm') : __('Create Character') }}
                                </a>

                                <a href="{{ url('/#systems') }}" class="theme-secondary-button public-site-cta-link">
                                    {{ __('See The System') }}
                                </a>
                            </div>

                            <div class="public-chip-row">
                                <span class="public-chip">{{ __('No original Metin2 imagery') }}</span>
                                <span class="public-chip">{{ __('Shared design language') }}</span>
                                <span class="public-chip">{{ __('Ready for custom art pass') }}</span>
                            </div>
                        </div>

                        <div class="public-preview-stage" aria-label="{{ __('Interface preview') }}">
                            @foreach ($previewCards as $previewCard)
                                <article class="public-preview-card" data-tone="{{ $previewCard['tone'] }}">
                                    <span class="public-preview-badge">{{ $previewCard['eyebrow'] }}</span>
                                    <h2 class="public-panel-title">{{ $previewCard['title'] }}</h2>
                                </article>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section class="public-metric-grid" aria-label="{{ __('Project metrics') }}">
                    @foreach ($publicMetrics as $metric)
                        <article class="public-surface public-metric-card">
                            <span class="public-mini-label">{{ $metric['label'] }}</span>
                            <p class="public-metric-value">{{ $metric['value'] }}</p>
                        </article>
                    @endforeach
                </section>

                <section class="public-grid public-grid-systems" id="systems">
                    <article class="public-surface public-section-card">
                        <p class="public-kicker">{{ __('System Flow') }}</p>
                        <h2 class="public-panel-title">{{ __('A complete frontend system instead of a 1:1 nostalgia copy.') }}</h2>
                        <p class="public-panel-copy">
                            {{ __('The layout now works like an actual product shell: stable header, breathable spacing, modern forms, reusable panels and room for your own art direction later.') }}
                        </p>

                        <div class="public-flow-list">
                            @foreach ($flowSteps as $index => $flowStep)
                                <article class="public-flow-item">
                                    <span class="public-flow-step">0{{ $index + 1 }}</span>
                                    <p>{{ $flowStep }}</p>
                                </article>
                            @endforeach
                        </div>
                    </article>

                    <aside class="public-auth-aside">
                        @include('partials.metin2-side-nav')
                    </aside>
                </section>

                <section class="public-surface public-section-card" id="game">
                    <div class="public-section-head">
                        <div>
                            <p class="public-kicker">{{ __('Frontend Scope') }}</p>
                            <h2 class="public-panel-title">{{ __('Public pages, auth flow, realm shell and admin all share one direction now.') }}</h2>
                        </div>
                        <p class="public-panel-copy public-section-copy">
                            {{ __('The old image-sliced portal treatment is gone. What remains is a warmer, sharper interface system that can absorb future button packs, frame art and container assets without another rewrite.') }}
                        </p>
                    </div>

                    <div class="public-feature-grid">
                        @foreach ($systemCards as $systemCard)
                            <article class="public-feature-card">
                                <h3 class="public-panel-title">{{ $systemCard['title'] }}</h3>
                                <p class="public-panel-copy">{{ $systemCard['copy'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section class="public-surface public-section-card" id="gallery">
                    <div class="public-section-head">
                        <div>
                            <p class="public-kicker">{{ __('Interface Scenes') }}</p>
                            <h2 class="public-panel-title">{{ __('Visual placeholders now, custom art-ready frames later.') }}</h2>
                        </div>
                        <p class="public-panel-copy public-section-copy">
                            {{ __('These cards stand in for the upcoming custom buttons, containers and ornaments. The system already supports them without relying on inherited Metin2 images.') }}
                        </p>
                    </div>

                    <div class="public-preview-stage public-preview-stage-wide">
                        @foreach ($previewCards as $previewCard)
                            <article class="public-preview-card public-preview-card-detail" data-tone="{{ $previewCard['tone'] }}">
                                <span class="public-preview-badge">{{ $previewCard['eyebrow'] }}</span>
                                <h3 class="public-panel-title">{{ $previewCard['title'] }}</h3>
                                <p class="public-panel-copy">{{ __('Custom asset slots can replace these pure CSS panels later without touching the layout grid.') }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>

                @guest
                    <section class="public-grid public-grid-systems">
                        <article class="public-surface public-login-card">
                            <p class="public-kicker">{{ __('Fast Access') }}</p>
                            <h2 class="public-panel-title">{{ __('Log in directly from the landing page.') }}</h2>
                            <form method="POST" action="{{ route('login') }}" class="public-login-grid">
                                @csrf
                                <div>
                                    <x-input-label for="welcome-email" :value="__('E-Mail')" />
                                    <x-text-input id="welcome-email" class="mt-2 block w-full" type="email" name="email" :value="old('email')" autocomplete="username" />
                                </div>

                                <div>
                                    <x-input-label for="welcome-password" :value="__('Passwort')" />
                                    <x-text-input id="welcome-password" class="mt-2 block w-full" type="password" name="password" autocomplete="current-password" />
                                </div>

                                <div class="public-hero-actions">
                                    <button type="submit" class="theme-primary-button">
                                        {{ __('Login') }}
                                    </button>
                                    <a href="{{ route('register') }}" class="theme-secondary-button public-site-cta-link">
                                        {{ __('Register') }}
                                    </a>
                                </div>
                            </form>
                        </article>

                        <article class="public-surface public-section-card">
                            <p class="public-kicker">{{ __('Ready For Asset Pass') }}</p>
                            <h2 class="public-panel-title">{{ __('The new shell is intentionally neutral about future art drops.') }}</h2>
                            <p class="public-panel-copy">
                                {{ __('Once you provide replacement button art, containers or ornaments, they can slot into the new structure instead of driving the structure.') }}
                            </p>
                        </article>
                    </section>
                @endguest
            </main>

            @include('partials.metin2-footer')
        </div>
    </body>
</html>
