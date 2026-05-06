<x-guest-layout class="public-auth-main-wide">
    <div class="metin2-auth-shell">
            <div class="metin2-auth-intro">
            <h1 class="metin2-auth-title">{{ __('Join Lumiis') }}</h1>
        </div>

        <form method="POST" action="{{ route('register') }}" class="metin2-auth-form">
            @csrf

            <div class="metin2-auth-grid lg:grid-cols-2">
                <div>
                    <x-input-label for="character_name" :value="__('Character Name')" />
                    <x-text-input id="character_name" class="mt-2 block w-full" type="text" name="character_name" :value="old('character_name')" required autofocus autocomplete="nickname" />
                    <x-input-error :messages="$errors->get('character_name')" class="mt-2" />
                </div>

            </div>
                <x-input-label for="race_input" :value="__('Race')" />
                <input type="hidden" name="race" id="race_input" value="{{ old('race', $defaultRace ?? '') }}" required />
                <x-input-error :messages="$errors->get('race')" class="mt-2" />
            <div class="metin2-race-grid">

                @foreach ($raceOptions as $raceKey => $raceOption)
                    @php($isSelected = old('race', $defaultRace ?? '') === $raceKey)
                    <div
                        class="metin2-race-card {{ $isSelected ? 'selected' : '' }}"
                        data-race="{{ $raceKey }}"
                        role="button"
                        tabindex="0"
                        aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                    >
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
                    <x-input-label for="email" :value="__('Email')" />
                    <x-text-input id="email" class="mt-2 block w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password" :value="__('Password')" />
                    <x-text-input id="password" class="mt-2 block w-full" type="password" name="password" required autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                <x-text-input id="password_confirmation" class="mt-2 block w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <div class="metin2-auth-links">
                <a class="theme-link text-sm" href="{{ route('login') }}">
                    {{ __('Already registered?') }}
                </a>

                <x-primary-button class="w-full sm:w-auto">
                    {{ __('Create Your Realm Character') }}
                </x-primary-button>
            </div>
        </form>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const raceInput = document.getElementById('race_input');
        if (!raceInput) return;

        const cards = document.querySelectorAll('.metin2-race-card');

        const selectRace = (key, card) => {
            raceInput.value = key;
            cards.forEach(c => {
                const selected = c === card;
                c.classList.toggle('selected', selected);
                c.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });
        };

        cards.forEach(card => {
            card.addEventListener('click', function () {
                selectRace(this.dataset.race, this);
            });
            card.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    selectRace(this.dataset.race, this);
                }
            });
            if (card.dataset.race === raceInput.value) {
                card.classList.add('selected');
                card.setAttribute('aria-pressed', 'true');
            }
        });
    });
    </script>
</x-guest-layout>
