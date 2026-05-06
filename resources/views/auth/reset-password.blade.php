<x-guest-layout>
    <div class="metin2-auth-shell">
        <div class="metin2-auth-intro">
            <span class="metin2-auth-kicker">{{ __('Recovery Gate') }}</span>
            <h1 class="metin2-auth-title">{{ __('Choose A New Password') }}</h1>
            <p class="metin2-auth-copy">
                {{ __('Set a fresh password and return to the realm board with a clean key.') }}
            </p>
        </div>

        <form method="POST" action="{{ route('password.store') }}" class="metin2-auth-form">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div>
                <x-input-label for="email" :value="__('Email')" />
                {{-- keep email value in a hidden input to prevent casual client-side tampering --}}
                <input type="hidden" name="email" value="{{ old('email', $request->email) }}">
                {{-- show the email as a disabled/read-only field for clarity --}}
                <input id="email_display" class="mt-2 block w-full bg-gray-100/60 text-gray-800 border rounded px-3 py-2" type="text" value="{{ old('email', $request->email) }}" disabled aria-disabled="true" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" class="mt-2 block w-full" type="password" name="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                <x-text-input id="password_confirmation" class="mt-2 block w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <div class="metin2-auth-links">
                <a class="theme-link text-sm" href="{{ route('login') }}">
                    {{ __('Back to login') }}
                </a>

                <x-primary-button class="w-full sm:w-auto">
                    {{ __('Reset Password') }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-guest-layout>
