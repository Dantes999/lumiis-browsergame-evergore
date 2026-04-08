<x-app-layout>
    @php
        $backUrl = $user->character ? route('dashboard') : url('/');
        $backLabel = $user->character ? __('Back to Realm') : __('Back to Start');
    @endphp

    <div class="portal-stage pt-6">
        <div class="grid w-full gap-4">
            <section class="game-panel">
                <div class="dashboard-section-head dashboard-section-head-modern gap-3">
                    <div>
                        <p class="panel-kicker">{{ __('Settings') }}</p>
                        <h1 class="dashboard-section-title">{{ __('Settings') }}</h1>
                    </div>

                    <a href="{{ $backUrl }}" class="theme-button theme-button-primary theme-button-compact">
                        {{ $backLabel }}
                    </a>
                </div>

                <p class="action-copy mt-4">
                    {{ __('Manage your login, password and language settings here without switching to a separate desktop layout.') }}
                </p>
            </section>

            <div class="game-panel">
                @include('profile.partials.update-profile-information-form')
            </div>

            <div class="game-panel">
                @include('profile.partials.update-password-form')
            </div>

            <div class="game-panel">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</x-app-layout>
