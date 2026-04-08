<x-guest-layout>
    <div class="metin2-auth-shell">
        <div class="metin2-auth-intro">
            <span class="metin2-auth-kicker">{{ __('Secure Checkpoint') }}</span>
            <h1 class="metin2-auth-title">{{ __('Confirm Password') }}</h1>
            <p class="metin2-auth-copy">
                {{ __('This section is locked down. Confirm your password to continue.') }}
            </p>
        </div>

        <form method="POST" action="{{ route('password.confirm') }}" class="metin2-auth-form">
            @csrf

            <div>
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" class="mt-2 block w-full" type="password" name="password" required autocomplete="current-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="metin2-auth-links">
                <a class="theme-link text-sm" href="{{ route('dashboard') }}">
                    {{ __('Back to realm') }}
                </a>

                <x-primary-button class="w-full sm:w-auto">
                    {{ __('Confirm') }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-guest-layout>
