<x-app-layout>
    @php
        $slotCharacters = $characters->values();
        $createCharacterErrors = $errors->getBag('createCharacter');
    @endphp

    <div class="portal-stage pt-6">
        <div class="grid gap-4">
            @if (session('status'))
                <section class="theme-status-flash theme-status-success">
                    {{ session('status') }}
                </section>
            @endif

            @if (session('error'))
                <section class="theme-status-flash theme-status-danger">
                    {{ session('error') }}
                </section>
            @endif

            <section class="game-panel">
                <div class="dashboard-section-head dashboard-section-head-modern gap-3">
                    <div>
                        <p class="panel-kicker">{{ __('Character Hall') }}</p>
                        <h1 class="dashboard-section-title">{{ __('Character Hall') }}</h1>
                    </div>

                    @if ($activeCharacter)
                        <a href="{{ route('dashboard') }}" class="theme-button theme-button-primary theme-button-compact">
                            {{ __('Back to Realm') }}
                        </a>
                    @endif
                </div>

                <p class="action-copy mt-4">
                    {{ __('Each account can hold up to :count characters. Choose who enters the realm, or open an empty slot for a new start.', ['count' => $maxCharactersPerAccount]) }}
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="cozy-chip">{{ __('Slots Used') }}: {{ $characters->count() }}/{{ $maxCharactersPerAccount }}</span>
                    @if ($activeCharacter)
                        <span class="cozy-chip">{{ __('Active Character') }}: {{ $activeCharacter->name }}</span>
                    @endif
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-2">
                @for ($slot = 0; $slot < $maxCharactersPerAccount; $slot++)
                    @php
                        /** @var \App\Models\GameCharacter|null $slotCharacter */
                        $slotCharacter = $slotCharacters->get($slot);
                        $slotNumber = $slot + 1;
                    @endphp

                    <article class="game-panel flex h-full flex-col gap-4">
                        @php
                            $renameErrorBag = $errors->getBag('renameCharacter_'.$slotCharacter?->id);
                            $renameValue = old('editing_character_id') == $slotCharacter?->id
                                ? old('character_name')
                                : $slotCharacter?->name;
                            $deleteModalName = $slotCharacter ? 'delete-character-'.$slotCharacter->id : null;
                            $slotLockedByBattle = $slotCharacter && (int) $slotCharacter->ongoing_battle_count > 0;
                        @endphp

                        <div class="dashboard-section-head dashboard-section-head-modern gap-3">
                            <div>
                                <p class="panel-kicker">{{ __('Slot') }} {{ $slotNumber }}</p>
                                <h2 class="dashboard-section-title">
                                    {{ $slotCharacter?->name ?? __('Empty Slot') }}
                                </h2>
                            </div>

                            @if ($slotCharacter)
                                <div class="flex flex-wrap gap-2">
                                    @if ($activeCharacter?->is($slotCharacter))
                                        <span class="cozy-chip">{{ __('Selected') }}</span>
                                    @endif

                                    @if ((int) $slotCharacter->ongoing_battle_count > 0)
                                        <span class="cozy-chip">{{ __('Fight Open') }}</span>
                                    @endif
                                </div>
                            @endif
                        </div>

                        @if ($slotCharacter)
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="paper-note">
                                    <p class="text-[0.65rem] uppercase tracking-[0.28em] text-amber-900/70">{{ __('Race') }}</p>
                                    <p class="mt-2 font-[Cinzel] text-xl tracking-[0.08em] text-stone-900">{{ \App\Support\Game\MetinClassCatalog::raceLabel($slotCharacter->chosenRace()) }}</p>
                                </div>

                                <div class="paper-note">
                                    <p class="text-[0.65rem] uppercase tracking-[0.28em] text-amber-900/70">{{ __('Level') }}</p>
                                    <p class="mt-2 font-[Cinzel] text-xl tracking-[0.08em] text-stone-900">{{ $slotCharacter->level }}</p>
                                </div>

                                <div class="paper-note">
                                    <p class="text-[0.65rem] uppercase tracking-[0.28em] text-amber-900/70">{{ __('Location') }}</p>
                                    <p class="mt-2 text-stone-800">{{ $slotCharacter->location?->name ?? __('Unknown') }}</p>
                                </div>

                                <div class="paper-note">
                                    <p class="text-[0.65rem] uppercase tracking-[0.28em] text-amber-900/70">{{ __('State') }}</p>
                                    <p class="mt-2 text-stone-800">
                                        {{ $slotCharacter->isInTutorial() ? __('Tutorial') : __('Active') }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-auto flex flex-wrap gap-3">
                                @if ($activeCharacter?->is($slotCharacter))
                                    <a href="{{ route('dashboard') }}" class="theme-button theme-button-primary">
                                        {{ __('Enter Realm') }}
                                    </a>
                                @else
                                    <form method="POST" action="{{ route('characters.select', $slotCharacter) }}">
                                        @csrf
                                        <button type="submit" class="theme-button theme-button-primary">
                                            {{ __('Select Character') }}
                                        </button>
                                    </form>
                                @endif
                            </div>

                            <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
                                <form method="POST" action="{{ route('characters.update', $slotCharacter) }}" class="grid gap-3">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="editing_character_id" value="{{ $slotCharacter->id }}">

                                    <div>
                                        <x-input-label :for="'rename-character-'.$slotCharacter->id" :value="__('Rename Character')" />
                                        <x-text-input
                                            :id="'rename-character-'.$slotCharacter->id"
                                            class="mt-2 block w-full"
                                            type="text"
                                            name="character_name"
                                            :value="$renameValue"
                                            :disabled="$slotLockedByBattle"
                                            required
                                            autocomplete="nickname"
                                        />
                                        <x-input-error :messages="$renameErrorBag->get('character_name')" class="mt-2" />
                                    </div>

                                    @if ($slotLockedByBattle)
                                        <p class="text-sm text-amber-900/80">
                                            {{ __('Finish or abandon the live manual fight on this slot before renaming or deleting it.') }}
                                        </p>
                                    @endif

                                    <div class="flex flex-wrap gap-3">
                                        <button type="submit" class="theme-button theme-button-secondary" @disabled($slotLockedByBattle)>
                                            {{ __('Save Name') }}
                                        </button>
                                    </div>
                                </form>

                                    <div class="flex flex-wrap gap-3 xl:justify-end">
                                    <button
                                        type="button"
                                        class="theme-button theme-button-danger"
                                        onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: '{{ $deleteModalName }}' })); return false;"
                                        @disabled($slotLockedByBattle)
                                    >
                                        {{ __('Delete Slot') }}
                                    </button>
                                </div>
                            </div>

                            <x-modal :name="$deleteModalName" maxWidth="lg" focusable>
                                <div class="p-6 sm:p-8">
                                    <p class="panel-kicker">{{ __('Delete Character') }}</p>
                                    <h3 class="mt-2 font-[Cinzel] text-2xl tracking-[0.08em] text-amber-50">
                                        {{ $slotCharacter->name }}
                                    </h3>

                                    <p class="mt-4 text-sm leading-7 text-amber-50/88">
                                        {{ __('Deleting this slot permanently removes its progress, inventory, quests, battles, and messages. This cannot be undone.') }}
                                    </p>

                                    @if ($activeCharacter?->is($slotCharacter))
                                        <p class="mt-3 text-sm leading-7 text-amber-200/90">
                                            {{ __('This is your selected character. After deletion, the hall will switch to the next available slot or stay empty until you create another character.') }}
                                        </p>
                                    @endif

                                        <div class="mt-6 flex flex-wrap gap-3">
                                        <button type="button" class="theme-button theme-button-secondary" onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: '{{ $deleteModalName }}' })); return false;">
                                            {{ __('Keep Slot') }}
                                        </button>

                                        <form method="POST" action="{{ route('characters.destroy', $slotCharacter) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="theme-button theme-button-danger">
                                                {{ __('Delete Character') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </x-modal>
                        @else
                            <div class="paper-note">
                                <p class="text-[0.65rem] uppercase tracking-[0.28em] text-amber-900/70">{{ __('Open Slot') }}</p>
                                <p class="mt-2 text-stone-800">
                                    {{ __('This slot is free. Create another character here when you want a new build, class, or fresh progression path.') }}
                                </p>
                            </div>
                        @endif
                    </article>
                @endfor
            </section>

            @if ($remainingSlots > 0)
                <section class="game-panel">
                    <div class="dashboard-section-head dashboard-section-head-modern gap-3">
                        <div>
                            <p class="panel-kicker">{{ __('New Character') }}</p>
                            <h2 class="dashboard-section-title">{{ __('Open Another Slot') }}</h2>
                        </div>

                        <span class="cozy-chip">{{ __('Remaining Slots') }}: {{ $remainingSlots }}</span>
                    </div>

                    <p class="action-copy mt-4">
                        {{ __('Every new character starts in Yongan with the same baseline gear and tutorial state as a fresh account.') }}
                    </p>

                    <form method="POST" action="{{ route('characters.store') }}" class="mt-6 grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] xl:items-end">
                        @csrf

                        <div>
                            <x-input-label for="new_character_name" :value="__('Character Name')" />
                            <x-text-input id="new_character_name" class="mt-2 block w-full" type="text" name="new_character_name" :value="old('new_character_name')" required autocomplete="nickname" />
                            <x-input-error :messages="$createCharacterErrors->get('new_character_name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="new_race" :value="__('Race')" />
                            <div class="relative mt-2">
                                <select id="new_race" name="new_race" class="theme-input theme-select block w-full appearance-none pr-12" required>
                                    <option value="">{{ __('Choose a combat race') }}</option>
                                    @foreach ($raceOptions as $raceKey => $raceOption)
                                        <option value="{{ $raceKey }}" @selected(old('new_race') === $raceKey)>{{ $raceOption['label'] }}</option>
                                    @endforeach
                                </select>
                                <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-[#59311a]" aria-hidden="true">▾</span>
                            </div>
                            <x-input-error :messages="$createCharacterErrors->get('new_race')" class="mt-2" />
                        </div>

                        <div class="flex items-end">
                            <button type="submit" class="theme-button theme-button-primary w-full xl:w-auto">
                                {{ __('Create Character') }}
                            </button>
                        </div>
                    </form>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
