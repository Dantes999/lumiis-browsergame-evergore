<x-guest-layout>
    <div class="metin2-auth-shell">
        <div class="metin2-auth-intro">
            <x-auth-session-status class="mb-4 theme-success-text" :status="session('status')" />

            <span class="metin2-auth-kicker">{{ __('Account Recovery') }}</span>
            <h1 class="metin2-auth-title">{{ __('Reset Your Password') }}</h1>
            <p class="metin2-auth-copy">
                {{ __('Lost your key to the realm board? Enter your email and we will send a secure reset link.') }}
            </p>
        </div>

        <form method="POST" action="{{ route('password.email') }}" class="metin2-auth-form">
            @csrf

            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" class="mt-2 block w-full" type="email" name="email" :value="old('email')" required autofocus />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>
            <div class="metin2-auth-links">
                <a class="theme-link text-sm" href="{{ route('login') }}">
                    {{ __('Back to login') }}
                </a>

                <x-primary-button class="w-full sm:w-auto">
                    {{ __('Email Password Reset Link') }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-guest-layout>
