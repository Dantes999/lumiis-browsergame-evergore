<x-app-layout>
    <x-slot name="header">
        <div class="hero-banner flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.35em] text-amber-100/65">{{ __('Settings') }}</p>
                <h1 class="font-[Cinzel] text-3xl font-semibold tracking-[0.16em] text-amber-50 sm:text-4xl">
                    {{ __('Profile') }}
                </h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-amber-50/85">
                    {{ __('Keep your login, security and language settings up to date. This board stays compact on phones and opens into framed panels on larger screens.') }}
                </p>
            </div>

            <div class="hero-chips">
                <span class="cozy-chip">{{ __('Mobile first') }}</span>
                <span class="cozy-chip">{{ __('Account safety') }}</span>
                <span class="cozy-chip">{{ __('Language ready') }}</span>
            </div>
        </div>
    </x-slot>

    <div class="portal-stage utility-page-shell pt-5">
        <div class="utility-page-grid">
            <div class="utility-page-stack">
                <div class="game-panel utility-page-panel">
                    @include('profile.partials.update-profile-information-form')
                </div>

                <div class="game-panel utility-page-panel">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="utility-page-sidebar">
                <div class="game-panel utility-page-panel h-fit xl:sticky xl:top-6">
                    <div class="utility-page-callout mb-4">
                        {{ __('Account changes update your login profile immediately. Character progression and game balance remain part of the realm systems.') }}
                    </div>

                    <div class="max-w-xl">
                        @include('profile.partials.delete-user-form')
                    </div>
                </div>

                <div class="utility-page-callout">
                    {{ __('Need to return to active play? Use the realm button above to jump back into the current map state without losing your settings changes.') }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
