<x-guest-layout>
    <div class="hero-banner mb-5">
        <p class="text-xs uppercase tracking-[0.35em] text-amber-100/65">Secure Checkpoint</p>
        <h1 class="mt-2 font-[Cinzel] text-3xl tracking-[0.14em] text-amber-50">Confirm Password</h1>
        <p class="mt-3 text-sm leading-6 text-amber-50/85">
            This section is locked down. Confirm your password to continue.
        </p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div class="form-card">
            <div>
                <x-input-label for="password" :value="__('Password')" />

                <x-text-input id="password" class="mt-2 block w-full"
                    type="password"
                    name="password"
                    required autocomplete="current-password" />

                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <x-primary-button>
                {{ __('Confirm') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
