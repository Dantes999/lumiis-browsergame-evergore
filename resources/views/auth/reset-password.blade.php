<x-guest-layout>
    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <div class="hero-banner mb-5">
            <p class="text-xs uppercase tracking-[0.35em] text-amber-100/65">{{ __('Recovery Gate') }}</p>
            <h1 class="mt-2 font-[Cinzel] text-3xl tracking-[0.14em] text-amber-50">{{ __('Choose A New Password') }}</h1>
            <p class="mt-3 text-sm leading-6 text-amber-50/85">
                {{ __('Set a fresh password and return to the realm board with a clean key.') }}
            </p>
        </div>

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="form-card">
            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" class="mt-2 block w-full" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" class="mt-2 block w-full" type="password" name="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

                <x-text-input id="password_confirmation" class="mt-2 block w-full"
                    type="password"
                    name="password_confirmation" required autocomplete="new-password" />

                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <x-primary-button>
                {{ __('Reset Password') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
