<div class="metin2-auth-shell">
    <div class="metin2-auth-intro">
        <x-auth-session-status class="mb-4 theme-success-text" :status="session('status')" />

        <span class="metin2-auth-kicker">{{ __('Return to the Realm') }}</span>
        <h1 class="metin2-auth-title">{{ __('Welcome Back') }}</h1>
        <p class="metin2-auth-copy">
            {{ __('Log in and keep playing.') }}
        </p>
    </div>

    <form method="POST" action="{{ route('login') }}" class="metin2-auth-form">
        @csrf

        <div>
            <x-input-label for="{{ $emailId ?? 'email' }}" :value="__('Email')" />
            <x-text-input
                id="{{ $emailId ?? 'email' }}"
                class="mt-2 block w-full"
                type="email"
                name="email"
                :value="old('email')"
                required
                autofocus
                autocomplete="username"
            />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="{{ $passwordId ?? 'password' }}" :value="__('Password')" />
            <x-text-input
                id="{{ $passwordId ?? 'password' }}"
                class="mt-2 block w-full"
                type="password"
                name="password"
                required
                autocomplete="current-password"
            />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="block">
            <label for="{{ $rememberId ?? 'remember_me' }}" class="theme-checkbox-label inline-flex items-center gap-2 text-sm">
                <input
                    id="{{ $rememberId ?? 'remember_me' }}"
                    type="checkbox"
                    class="theme-checkbox"
                    name="remember"
                >
                <span>{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="metin2-auth-links">
            @if (Route::has('password.request'))
                <a class="theme-link text-sm" href="{{ route('password.request') }}">
                    {{ __('Passwort vergessen?') }}
                </a>
            @endif

            <x-primary-button class="w-full sm:w-auto">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>
</div>
