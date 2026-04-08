<x-guest-layout class="public-auth-main-wide">
    <div class="metin2-auth-shell">
        <div class="metin2-auth-intro">
            <span class="metin2-auth-kicker">{{ __('Charaktererstellung') }}</span>
            <h1 class="metin2-auth-title">{{ __('Betritt Lumiis') }}</h1>
            <p class="metin2-auth-copy">
                {{ __('Name, Klasse und Zugangsdaten festlegen. Danach geht es direkt ins Reich.') }}
            </p>
        </div>

        <form method="POST" action="{{ route('register') }}" class="metin2-auth-form">
            @csrf

            <div class="metin2-auth-grid lg:grid-cols-2">
                <div>
                    <x-input-label for="character_name" :value="__('Charaktername')" />
                    <x-text-input id="character_name" class="mt-2 block w-full" type="text" name="character_name" :value="old('character_name')" required autofocus autocomplete="nickname" />
                    <x-input-error :messages="$errors->get('character_name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="race" :value="__('Rasse')" />
                    <div class="relative mt-2">
                        <select
                            id="race"
                            name="race"
                            class="theme-input theme-select block w-full appearance-none pr-12"
                            required
                        >
                            <option value="">{{ __('Wähle deine Kampfrasse') }}</option>
                            @foreach ($raceOptions as $raceKey => $raceOption)
                                <option value="{{ $raceKey }}" @selected(old('race') === $raceKey)>{{ $raceOption['label'] }}</option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-[#59311a]" aria-hidden="true">▾</span>
                    </div>
                    <x-input-error :messages="$errors->get('race')" class="mt-2" />
                </div>
            </div>

            <div class="metin2-race-grid">
                @foreach ($raceOptions as $raceOption)
                    <div class="metin2-race-card">
                        <p class="metin2-race-card-title">{{ $raceOption['label'] }}</p>
                        <p class="metin2-race-card-copy">{{ $raceOption['description'] }}</p>
                        <p class="metin2-race-card-meta">
                            {{ collect($raceOption['skill_groups'])->pluck('label')->implode(' / ') }}
                        </p>
                    </div>
                @endforeach
            </div>

            <div class="metin2-auth-grid lg:grid-cols-2">
                <div>
                    <x-input-label for="email" :value="__('E-Mail')" />
                    <x-text-input id="email" class="mt-2 block w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password" :value="__('Passwort')" />
                    <x-text-input id="password" class="mt-2 block w-full" type="password" name="password" required autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="password_confirmation" :value="__('Passwort bestätigen')" />
                <x-text-input id="password_confirmation" class="mt-2 block w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <div class="metin2-auth-links">
                <a class="theme-link text-sm" href="{{ route('login') }}">
                    {{ __('Bereits registriert?') }}
                </a>

                <x-primary-button class="w-full sm:w-auto">
                    {{ __('Charakter erschaffen') }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-guest-layout>
