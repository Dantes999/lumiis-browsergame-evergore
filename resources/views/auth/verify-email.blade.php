<x-guest-layout>
    <div class="metin2-auth-shell">
        <div class="metin2-auth-intro">
            <span class="metin2-auth-kicker">{{ __('Mailbox Seal') }}</span>
            <h1 class="metin2-auth-title">{{ __('Verify Your Email') }}</h1>
            <p class="metin2-auth-copy">
                {{ __('One last step before entering the realm board. Open the message we sent you and confirm your address.') }}
            </p>
        </div>

        @if (session('status') == 'verification-link-sent')
            <div class="theme-status-flash theme-status-success">
                {{ __('A new verification link has been sent to the email address you provided during registration.') }}
            </div>
        @endif

        <div class="paper-note text-sm leading-6">
            {{ __('If you did not receive the email, you can request another verification link below.') }}
        </div>

        <div class="metin2-auth-links">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <x-primary-button class="w-full sm:w-auto">
                    {{ __('Resend Verification Email') }}
                </x-primary-button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="theme-link text-sm">
                    {{ __('Log Out') }}
                </button>
            </form>
        </div>
    </div>
</x-guest-layout>
