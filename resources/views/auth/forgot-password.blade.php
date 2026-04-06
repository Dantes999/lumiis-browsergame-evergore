<x-guest-layout>
    <div class="hero-banner mb-5">
        <p class="text-xs uppercase tracking-[0.35em] text-amber-100/65">{{ __('Account Recovery') }}</p>
        <h1 class="mt-2 font-[Cinzel] text-3xl tracking-[0.14em] text-amber-50">{{ __('Reset Your Password') }}</h1>
        <p class="mt-3 text-sm leading-6 text-amber-50/85">
            {{ __('Lost your key to the realm board? Enter your email and we will send a secure reset link.') }}
        </p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="form-card">
            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" class="mt-2 block w-full" type="email" name="email" :value="old('email')" required autofocus />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <x-primary-button>
                {{ __('Email Password Reset Link') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
