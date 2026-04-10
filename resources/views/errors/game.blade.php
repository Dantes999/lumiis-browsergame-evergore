@php
    $homeUrl = auth()->check() ? route('dashboard') : url('/');
    $secondaryUrl = auth()->check()
        ? (auth()->user()?->character()->exists() ? route('dashboard', ['panel' => 'settings']) : route('profile.edit'))
        : route('login');
    $secondaryLabel = auth()->check() ? __('Settings') : __('Login');
@endphp

<x-guest-layout class="guest-card-wide">
    <div class="hero-banner mb-5">
        <x-page-heading
            :eyebrow="__('System Message')"
            :title="$statusCode.' · '.$statusTitle"
            :description="$statusMessage"
        />
    </div>

    <div class="grid gap-5 lg:grid-cols-[1.1fr_0.9fr]">
        <section class="form-card">
            <p class="panel-kicker">{{ __('Next Step') }}</p>
            <h2 class="mt-3 font-[Cinzel] text-2xl tracking-[0.12em] text-amber-50">
                {{ __('Return to a stable route') }}
            </h2>
            <p class="mt-3 text-sm leading-6 text-stone-300">
                {{ __('Use one of the safe routes below to continue playing or to recover your session state.') }}
            </p>

            <div class="mt-5 flex flex-wrap gap-3">
                <a href="{{ $homeUrl }}" class="theme-primary-button">
                    {{ auth()->check() ? __('Back to Realm') : __('Back to Start') }}
                </a>

                <a href="{{ $secondaryUrl }}" class="theme-secondary-button">
                    {{ $secondaryLabel }}
                </a>
            </div>
        </section>

        <section class="form-card">
            <p class="panel-kicker">{{ __('Status') }}</p>
            <div class="paper-note mt-4">
                <p class="text-[0.65rem] uppercase tracking-[0.28em] text-amber-900/70">{{ __('Code') }}</p>
                <p class="mt-2 font-[Cinzel] text-2xl tracking-[0.08em] text-stone-900">{{ $statusCode }}</p>
                <p class="mt-2 text-stone-700">{{ $statusTitle }}</p>
            </div>

            <p class="mt-4 text-sm leading-6 text-stone-300">
                {{ __('If the problem keeps repeating, verify the route, refresh the page and try again from a known location such as the portal or your settings panel.') }}
            </p>
        </section>
    </div>
</x-guest-layout>
