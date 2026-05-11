<section>
    @php
        $selectedCharacter = app(\App\Services\Game\ActiveCharacterResolver::class)->current($user, request());
    @endphp

    <header class="utility-page-section-head">
        <div class="utility-page-section-titlebar">
            <p class="panel-kicker">{{ __('Login') }}</p>
            <h2>{{ __('Profile Information') }}</h2>
        </div>

        <p class="utility-page-section-copy">
            {{ __('Your character name is the in-game identity. This profile section only manages your login email address.') }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div class="paper-note text-sm leading-6">
            <p class="text-[0.65rem] uppercase tracking-[0.28em] text-amber-900/70">{{ __('Character Name') }}</p>
            <p class="mt-2 font-[Cinzel] text-xl tracking-[0.08em] text-stone-900">
                {{ $selectedCharacter?->name ?? __('No character linked') }}
            </p>
            <p class="mt-2 text-stone-700">
                {{ __('Character renaming should stay a separate game or admin action, not a login profile field.') }}
            </p>
        </div>

        @php
            $storedLocalePreference = data_get($user->getAttributes(), 'locale');
            $resolvedLocale = app()->getLocale();
            $localePreference = old('locale_preference', $storedLocalePreference ?? $resolvedLocale);
        @endphp

        <div class="paper-note text-sm leading-6">
            <p class="text-[0.65rem] uppercase tracking-[0.28em] text-amber-900/70">{{ __('Language') }}</p>
            <p class="mt-2 font-[Cinzel] text-xl tracking-[0.08em] text-stone-900">
                {{ __('Choose your interface language.') }}
            </p>
            <p class="mt-2 text-stone-700">
                {{ __('If no language was saved yet, Lumiis uses your browser language when it is German or English. Otherwise German stays active.') }}
            </p>

            <div class="mt-4 flex flex-wrap gap-2">
                <span class="cozy-chip">
                    {{ __('Current Language') }}: {{ strtoupper($resolvedLocale) }}
                </span>
                <span class="cozy-chip">
                    {{ __('Saved Preference') }}: {{ $storedLocalePreference !== null ? strtoupper($storedLocalePreference) : __('Not set') }}
                </span>
            </div>

            <div class="profile-locale-grid mt-4">
                <label @class(['profile-locale-option', 'profile-locale-option-active' => $localePreference === 'de'])>
                    <input type="radio" name="locale_preference" value="de" class="sr-only" @checked($localePreference === 'de')>
                    <span class="profile-locale-flag">🇩🇪</span>
                    <span class="profile-locale-name">{{ __('German') }}</span>
                </label>

                <label @class(['profile-locale-option', 'profile-locale-option-active' => $localePreference === 'en'])>
                    <input type="radio" name="locale_preference" value="en" class="sr-only" @checked($localePreference === 'en')>
                    <span class="profile-locale-flag">🇬🇧</span>
                    <span class="profile-locale-name">{{ __('English') }}</span>
                </label>
            </div>

            <x-input-error class="mt-2" :messages="$errors->get('locale_preference')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autofocus autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="paper-note mt-4 text-sm leading-6">
                    <p>
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="font-semibold text-rose-700 underline decoration-rose-400/60 underline-offset-4 transition hover:text-rose-900 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-0">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="theme-success-text mt-2 text-sm font-semibold">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="theme-success-text text-sm"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
