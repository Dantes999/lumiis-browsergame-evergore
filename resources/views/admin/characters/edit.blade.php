<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-amber-300/60">Admin</p>
            <h1 class="font-[Cinzel] text-3xl font-semibold tracking-[0.16em] text-amber-50">Edit Character</h1>
        </div>
    </x-slot>

    <div class="portal-stage">
        @include('admin.partials.nav')
        @include('admin.partials.flash')

        <article class="game-panel">
            <div class="paper-note mb-6 text-sm leading-6">
                Account owner: {{ $character->user->email }}
            </div>

            <form method="POST" action="{{ route('admin.characters.update', $character) }}" class="space-y-6">
                @csrf
                @method('PATCH')

                <div class="grid gap-6 md:grid-cols-3">
                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" name="name" class="mt-2" :value="old('name', $character->name)" required />
                    </div>

                    <div>
                        <x-input-label for="faction" value="Faction" />
                        <select id="faction" name="faction" class="mt-2 w-full rounded-[1.1rem] border border-amber-200/20 bg-gradient-to-b from-amber-50/95 to-amber-200/85 px-4 py-3 text-stone-900 focus:border-amber-400 focus:ring-amber-400">
                            @foreach (['Shinsoo', 'Chunjo', 'Jinno'] as $faction)
                                <option value="{{ $faction }}" @selected(old('faction', $character->faction) === $faction)>{{ $faction }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="game_state" value="Game State" />
                        <select id="game_state" name="game_state" class="mt-2 w-full rounded-[1.1rem] border border-amber-200/20 bg-gradient-to-b from-amber-50/95 to-amber-200/85 px-4 py-3 text-stone-900 focus:border-amber-400 focus:ring-amber-400">
                            @foreach ($gameStates as $gameState)
                                <option value="{{ $gameState->value }}" @selected(old('game_state', $character->game_state?->value ?? $character->game_state) === $gameState->value)>
                                    {{ ucfirst($gameState->value) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="race" value="Race" />
                        <select id="race" name="race" class="mt-2 w-full rounded-[1.1rem] border border-amber-200/20 bg-gradient-to-b from-amber-50/95 to-amber-200/85 px-4 py-3 text-stone-900 focus:border-amber-400 focus:ring-amber-400">
                            @foreach ($raceOptions as $raceKey => $raceOption)
                                <option value="{{ $raceKey }}" @selected(old('race', $character->chosenRace()) === $raceKey)>{{ $raceOption['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="skill_group" value="Skill Group" />
                        <select id="skill_group" name="skill_group" class="mt-2 w-full rounded-[1.1rem] border border-amber-200/20 bg-gradient-to-b from-amber-50/95 to-amber-200/85 px-4 py-3 text-stone-900 focus:border-amber-400 focus:ring-amber-400">
                            <option value="">No group chosen</option>
                            @foreach ($skillGroupOptions as $groupKey => $groupOption)
                                <option value="{{ $groupKey }}" @selected(old('skill_group', $character->skill_group) === $groupKey)>{{ $groupOption['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="location_id" value="Location" />
                        <select id="location_id" name="location_id" class="mt-2 w-full rounded-[1.1rem] border border-amber-200/20 bg-gradient-to-b from-amber-50/95 to-amber-200/85 px-4 py-3 text-stone-900 focus:border-amber-400 focus:ring-amber-400">
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected((int) old('location_id', $character->location_id) === $location->id)>
                                    {{ $location->region }} / {{ $location->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-4">
                    @foreach ([
                        'level' => $character->level,
                        'experience' => $character->experience,
                        'yang' => $character->yang,
                        'health' => $character->health,
                        'max_health' => $character->max_health,
                        'stamina' => $character->stamina,
                        'max_stamina' => $character->max_stamina,
                        'attack' => $character->attack,
                        'defense' => $character->defense,
                        'speed' => $character->speed,
                        'tutorial_step' => $character->tutorial_step,
                    ] as $field => $value)
                        <div>
                            <x-input-label :for="$field" :value="strtoupper(str_replace('_', ' ', $field))" />
                            <x-text-input :id="$field" :name="$field" type="number" class="mt-2" :value="old($field, $value)" :required="$field !== 'tutorial_step'" />
                        </div>
                    @endforeach

                    <div class="md:col-span-2">
                        <x-input-label for="action_available_at" value="Action Available At" />
                        <x-text-input
                            id="action_available_at"
                            name="action_available_at"
                            type="datetime-local"
                            class="mt-2"
                            :value="old('action_available_at', optional($character->action_available_at)->format('Y-m-d\\TH:i'))"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <x-input-label for="last_active_at" value="Last Active At" />
                        <x-text-input
                            id="last_active_at"
                            name="last_active_at"
                            type="datetime-local"
                            class="mt-2"
                            :value="old('last_active_at', optional($character->last_active_at)->format('Y-m-d\\TH:i'))"
                        />
                    </div>
                </div>

                <x-primary-button>Save Character</x-primary-button>
            </form>
        </article>
    </div>
</x-app-layout>
