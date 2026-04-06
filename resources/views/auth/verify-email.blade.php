<x-guest-layout>
    <div class="hero-banner mb-5">
        <p class="text-xs uppercase tracking-[0.35em] text-amber-100/65">{{ __('Mailbox Seal') }}</p>
        <h1 class="mt-2 font-[Cinzel] text-3xl tracking-[0.14em] text-amber-50">{{ __('Verify Your Email') }}</h1>
        <p class="mt-3 text-sm leading-6 text-amber-50/85">
            {{ __('One last step before entering the realm board. Open the message we sent you and confirm your address.') }}
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="theme-status-flash theme-status-success mb-4">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="paper-note text-sm leading-6">
        {{ __('If you did not receive the email, you can request another verification link below.') }}
    </div>

    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Resend Verification Email') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="text-sm text-amber-100/75 underline decoration-amber-100/50 underline-offset-4 transition hover:text-amber-50 focus:outline-none focus:ring-2 focus:ring-amber-300 focus:ring-offset-0">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
