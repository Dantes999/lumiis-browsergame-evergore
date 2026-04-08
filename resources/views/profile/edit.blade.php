<x-app-layout>
    <div class="portal-stage pt-6">
        <div class="grid w-full gap-4">
            <section class="game-panel">
                <div class="dashboard-section-head dashboard-section-head-modern gap-3">
                    <div>
                        <p class="panel-kicker">{{ __('Settings') }}</p>
                        <h1 class="dashboard-section-title">{{ __('Profile') }}</h1>
                    </div>

                    <a href="{{ route('dashboard') }}" class="theme-button theme-button-primary theme-button-compact">
                        {{ __('Back to Realm') }}
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
