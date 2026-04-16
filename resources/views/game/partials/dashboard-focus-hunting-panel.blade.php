<div class="scene-stat-strip mt-5">
    <div class="scene-stat-pill">
        <span class="scene-stat-label">{{ __('Pressure') }}</span>
        <span class="scene-stat-value">{{ number_format($activeAreaPressure) }}/{{ number_format($activeArea->boss_pressure_threshold) }}</span>
    </div>
    <div class="scene-stat-pill">
        <span class="scene-stat-label">{{ __('Pack Size') }}</span>
        <span class="scene-stat-value">{{ $activeAreaPackSize }}</span>
    </div>
    <div class="scene-stat-pill">
        <span class="scene-stat-label">{{ __('Fights') }}</span>
        <span class="scene-stat-value">{{ number_format($activeAreaProgress?->monster_fights ?? 0) }}</span>
    </div>
    <div class="scene-stat-pill">
        <span class="scene-stat-label">{{ __('Monster Pool') }}</span>
        <span class="scene-stat-value">{{ number_format($activeAreaMonsterNames->count()) }}</span>
    </div>
    <div class="scene-stat-pill">
        <span class="scene-stat-label">{{ __('Highest Lv') }}</span>
        <span class="scene-stat-value">{{ $activeAreaHighestMonsterLevel > 0 ? $activeAreaHighestMonsterLevel : '-' }}</span>
    </div>
    <div class="scene-stat-pill">
        <span class="scene-stat-label">{{ __('Boss Ready') }}</span>
        <span class="scene-stat-value">
            {{ $activeAreaBossReady ? __('Ready') : ($activeAreaBossReadiness !== null ? $activeAreaBossReadiness.'%' : '-') }}
        </span>
    </div>
</div>

<div class="pressure-meter mt-4">
    <span class="pressure-fill" style="width: {{ $activeAreaPressurePercent }}%;"></span>
</div>

@if ($storyline)
    <div class="paper-note mt-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[0.65rem] uppercase tracking-[0.26em] text-amber-900/70">{{ __('Story Arc') }}</p>
                <h4 class="mt-2 font-[Cinzel] text-xl tracking-[0.08em] text-stone-900">
                    {{ $storyline['title'] }}
                </h4>
            </div>
            <span class="cozy-chip">{{ $storyline['completion_percent'] }}% {{ __('complete') }}</span>
        </div>

        <p class="mt-4 text-sm leading-6 text-stone-700">
            {{ $storyline['summary'] }}
        </p>

        <div class="mt-4 space-y-3">
            @foreach ($storyline['steps'] as $step)
                <div class="theme-paper-panel px-4 py-3">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="font-[Cinzel] text-lg tracking-[0.06em] text-stone-900">{{ $step['label'] }}</p>
                            <p class="mt-1 text-sm leading-6 text-stone-700">{{ $step['description'] }}</p>
                        </div>
                        <span class="cozy-chip">{{ number_format($step['current']) }}/{{ number_format($step['target']) }}</span>
                    </div>
                    <div class="pressure-meter mt-3 h-2">
                        <span class="pressure-fill" style="width: {{ $step['progress_percent'] }}%;"></span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

@if ($activeAreaMonsterNames->isNotEmpty())
    <div class="mt-4">
        <p class="text-[0.62rem] uppercase tracking-[0.24em] text-amber-900/70">{{ __('Map1 Monster Pool') }}</p>
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach ($activeAreaMonsterNames->take(8) as $monsterName)
                <span class="cozy-chip">{{ $monsterName }}</span>
            @endforeach
        </div>
    </div>
@endif

<div class="theme-empty-state mt-5 px-4 py-4 text-sm leading-6" x-show="isActionLocked()" x-cloak>
    <p class="text-[0.62rem] uppercase tracking-[0.24em] text-amber-900/70">
        <span x-show="!isResting()" x-cloak>{{ __('Action cooldown') }}</span>
        <span x-show="isResting()" x-cloak>{{ __('Rest in progress') }}</span>
    </p>

    <p class="mt-2 font-semibold text-stone-900" x-show="!isResting()" x-cloak>
        {{ __('Hunting is on cooldown.') }}
    </p>
    <p class="mt-2 font-semibold text-stone-900" x-show="isResting()" x-cloak>
        {{ __('You are currently resting.') }}
    </p>

    <p class="mt-2 text-stone-700" x-show="!isResting()" x-cloak>
        {{ __('Time left') }}:
        <strong x-text="formattedActionLockCountdown()"></strong>
        · {{ __('Ready at') }}:
        <strong x-text="actionLockReadyAtLabel()"></strong>
    </p>
    <p class="mt-2 text-stone-700" x-show="isResting()" x-cloak>
        {{ __('Rest ends in') }}:
        <strong x-text="formattedActionLockCountdown()"></strong>
        · {{ __('Ready at') }}:
        <strong x-text="actionLockReadyAtLabel()"></strong>
    </p>
</div>

@php
    $manualEncounterPreviewCharacter = [
        'effectiveAttack' => $character->effectiveAttack(),
        'effectiveDefense' => $character->effectiveDefense(),
        'effectiveSpeed' => $character->effectiveSpeed(),
        'effectiveMagicAttack' => $character->effectiveMagicAttack(),
        'maxHealth' => $character->effectiveMaxHealth(),
        'maxSp' => $character->effectiveMaxSp(),
        'combatRating' => app(\App\Services\Game\ManualEncounterPreviewService::class)->publicCharacterCombatRating($character),
        'averageDamageBonus' => $character->averageDamagePercentBonus(),
        'criticalChance' => $character->criticalChancePercent(),
        'skillDamageBonus' => $character->skillDamagePercentBonus(),
        'evadeChance' => $character->evadeChancePercent(),
        'skillCount' => $character->learnedSkills->count(),
    ];
    $manualEncounterPreviewOptions = collect($activeAreaMonsterFamilies)
        ->mapWithKeys(function (array $family): array {
            return collect($family['options'] ?? [])
                ->mapWithKeys(fn (array $option, string $difficulty): array => [
                    $family['key'].'.'.$difficulty => (array) ($option['live_preview'] ?? []),
                ])
                ->all();
        })
        ->merge(
            collect($encounterActionPreviews ?? [])->mapWithKeys(fn (array $option, string $key): array => [
                $key => (array) ($option['live_preview'] ?? []),
            ])
        )
        ->all();
    $monsterAutoPreview = (array) ($encounterActionPreviews['monster_auto'] ?? []);
    $lumiisAutoPreview = (array) ($encounterActionPreviews['lumiis_auto'] ?? []);
    $lumiisManualPreview = (array) ($encounterActionPreviews['lumiis_manual'] ?? []);
@endphp

<div
    class="mt-5 grid gap-3 lg:grid-cols-2"
    x-data="manualEncounterPreview({
        character: @js($manualEncounterPreviewCharacter),
        initialResources: @js([
            'health' => (int) $displayHealth,
            'sp' => (int) $displaySp,
            'stamina' => (int) $displayStamina,
        ]),
        options: @js($manualEncounterPreviewOptions),
        labels: {
            estimatedSuccess: @js(__('Est. success')),
            safe: @js(__('Safe')),
            fair: @js(__('Fair')),
            risky: @js(__('Risky')),
            dangerous: @js(__('Dangerous')),
        },
    })"
>
    <div class="form-card">
        @php
            $monsterFightStaminaMissing = $staminaMissingFor($monsterFightStaminaCost);
            $monsterFightLocked = $activeAreaLevelLocked;
        @endphp
        <p class="action-title">{{ __('Fight Monsters') }}</p>
        <p class="action-copy">
            {{ __('Clear roaming packs, free lesser souls and keep building pressure toward the boss reveal.') }}
        </p>
        <p class="mt-4 text-xs uppercase tracking-[0.22em] text-amber-900/70">
            {{ __('3-monster packs') }} · {{ $monsterFightStaminaCost }} {{ __('stamina right now') }} · {{ __('cooldown scales by battle length') }}
            @if ($activeAreaLevelRequirement !== null)
                · {{ __('Req. Level :level', ['level' => $activeAreaLevelRequirement]) }}
            @endif
        </p>

        <form method="POST" action="{{ route('game.actions.store', ['action' => 'fight-monsters']) }}" class="mt-5">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <input type="hidden" name="location_area_id" value="{{ $activeArea->id }}">
            <input type="hidden" name="redirect_focus" value="{{ $activeArea->slug }}">

            <button
                type="submit"
                class="action-button mt-5"
                x-bind:disabled="isActionLocked() || @js($monsterFightStaminaMissing > 0 || $monsterFightLocked)"
                @disabled($monsterFightStaminaMissing > 0 || $monsterFightLocked)
            >
                <span class="block">{{ __('Auto Fight') }}</span>
                @if ($monsterAutoPreview !== [])
                    <span
                        class="mt-1 block text-[0.58rem] uppercase tracking-[0.18em] text-emerald-50"
                        x-bind:class="successToneClassByKey('monster_auto')"
                        x-text="successRateLabelByKey('monster_auto')"
                    >
                        {{ __('Est. success') }} {{ data_get($monsterAutoPreview, 'success_rate_percent', 0) }}% · {{ data_get($monsterAutoPreview, 'risk_label') }}
                    </span>
                    <span
                        class="mt-1 block text-[0.58rem] tracking-[0.06em] text-white/75"
                        x-text="encounterSummaryByKey('monster_auto')"
                    >
                        {{ data_get($monsterAutoPreview, 'encounter_summary') }}
                    </span>
                @endif
            </button>
        </form>

        @if ($activeAreaMonsterFamilies->isNotEmpty())
            <div class="mt-4 space-y-3">
                <p class="text-xs uppercase tracking-[0.22em] text-amber-900/70">
                    {{ __('Manual target selection') }}
                </p>

                @foreach ($activeAreaMonsterFamilies as $family)
                    <div class="paper-note">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <p class="font-[Cinzel] text-lg tracking-[0.06em] text-stone-900">{{ $family['label'] }}</p>
                            <span class="cozy-chip">{{ implode(' · ', $family['examples']) }}</span>
                        </div>

                        <div class="mt-4 grid gap-2 md:grid-cols-3">
                            @foreach ($monsterDifficultyLabels as $difficultyKey => $difficultyLabel)
                                @php
                                    $difficultyStaminaCost = $monsterFightStaminaCost + ($difficultyKey === 'hard' ? 2 : ($difficultyKey === 'medium' ? 1 : 0));
                                    $difficultyStaminaMissing = $staminaMissingFor($difficultyStaminaCost);
                                    $difficultyPreview = data_get($family, "options.$difficultyKey");
                                    $successRate = (int) data_get($difficultyPreview, 'success_rate_percent', 0);
                                    $successToneClass = match (true) {
                                        $successRate >= 85 => 'text-emerald-50',
                                        $successRate >= 65 => 'text-sky-50',
                                        $successRate >= 45 => 'text-amber-50',
                                        default => 'text-rose-50',
                                    };
                                @endphp

                                <form method="POST" action="{{ route('game.actions.store', ['action' => 'start-manual-fight']) }}">
                                    @csrf
                                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                    <input type="hidden" name="location_area_id" value="{{ $activeArea->id }}">
                                    <input type="hidden" name="encounter_request" value="monster">
                                    <input type="hidden" name="monster_family" value="{{ $family['key'] }}">
                                    <input type="hidden" name="monster_difficulty" value="{{ $difficultyKey }}">
                                    <input type="hidden" name="redirect_focus" value="{{ $activeArea->slug }}">

                                    <button
                                        type="submit"
                                        class="action-button-muted w-full"
                                        x-bind:disabled="isActionLocked() || @js($difficultyStaminaMissing > 0 || $monsterFightLocked)"
                                        @disabled($difficultyStaminaMissing > 0 || $monsterFightLocked)
                                    >
                                        <span class="block">{{ $difficultyLabel }}</span>
                                        @if ($difficultyPreview)
                                            <span
                                                class="mt-1 block text-[0.58rem] uppercase tracking-[0.18em] {{ $successToneClass }}"
                                                x-bind:class="successToneClass('{{ $family['key'] }}', '{{ $difficultyKey }}')"
                                                x-text="successRateLabel('{{ $family['key'] }}', '{{ $difficultyKey }}')"
                                            >
                                                {{ __('Est. success') }} {{ $successRate }}% · {{ data_get($difficultyPreview, 'risk_label') }}
                                            </span>
                                            <span
                                                class="mt-1 block text-[0.58rem] tracking-[0.06em] text-white/75"
                                                x-text="encounterSummary('{{ $family['key'] }}', '{{ $difficultyKey }}')"
                                            >
                                                {{ data_get($difficultyPreview, 'encounter_summary') }}
                                            </span>
                                        @endif
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($monsterFightLocked)
            <p class="action-cost-warning">{{ __('Recommended from level :level.', ['level' => $activeAreaLevelRequirement]) }}</p>
        @elseif ($monsterFightStaminaMissing > 0)
            <p class="action-cost-warning">Missing {{ $monsterFightStaminaMissing }} stamina.</p>
        @endif
    </div>

    <div class="form-card">
        @php
            $lumiisFightStaminaMissing = $staminaMissingFor($lumiisFightStaminaCost);
            $lumiisFightLocked = $activeAreaLevelLocked;
        @endphp
        <p class="action-title">{{ __('Search Lumiis') }}</p>
        <p class="action-copy">
            {{ __('Break a glowing Lumiis core to release trapped monster souls and push the area closer to its boss.') }}
        </p>
        @if (! $lumiisUnlocked)
            <p class="mt-4 text-xs uppercase tracking-[0.22em] text-amber-900/70">
                {{ __('Unlocks at level 2') }}
            </p>
            <div class="theme-empty-state mt-5 px-4 py-4 text-sm leading-6">
                {{ __('Keep clearing monster packs and follow the map questline. Lumiis hunts unlock once your character reaches level 2.') }}
            </div>
        @else
            <p class="mt-4 text-xs uppercase tracking-[0.22em] text-amber-900/70">
                {{ $activeArea->lumiis_cooldown_seconds }} {{ __('sec lock') }} · {{ $lumiisFightStaminaCost }} {{ __('stamina') }}
                @if ($activeAreaLevelRequirement !== null)
                    · {{ __('Req. Level :level', ['level' => $activeAreaLevelRequirement]) }}
                @endif
            </p>

            <form method="POST" action="{{ route('game.actions.store', ['action' => 'search-lumiis']) }}" class="mt-5">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <input type="hidden" name="location_area_id" value="{{ $activeArea->id }}">
                <input type="hidden" name="redirect_focus" value="{{ $activeArea->slug }}">

                <button
                    type="submit"
                    class="action-button"
                    x-bind:disabled="isActionLocked() || @js($lumiisFightStaminaMissing > 0 || $lumiisFightLocked)"
                    @disabled($lumiisFightStaminaMissing > 0 || $lumiisFightLocked)
                >
                    <span class="block">{{ __('Auto Lumiis') }}</span>
                    @if ($lumiisAutoPreview !== [])
                        <span
                            class="mt-1 block text-[0.58rem] uppercase tracking-[0.18em] text-emerald-50"
                            x-bind:class="successToneClassByKey('lumiis_auto')"
                            x-text="successRateLabelByKey('lumiis_auto')"
                        >
                            {{ __('Est. success') }} {{ data_get($lumiisAutoPreview, 'success_rate_percent', 0) }}% · {{ data_get($lumiisAutoPreview, 'risk_label') }}
                        </span>
                        <span
                            class="mt-1 block text-[0.58rem] tracking-[0.06em] text-white/75"
                            x-text="encounterSummaryByKey('lumiis_auto')"
                        >
                            {{ data_get($lumiisAutoPreview, 'encounter_summary') }}
                        </span>
                    @endif
                </button>
            </form>

            <form method="POST" action="{{ route('game.actions.store', ['action' => 'start-manual-fight']) }}" class="mt-3">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <input type="hidden" name="location_area_id" value="{{ $activeArea->id }}">
                <input type="hidden" name="encounter_request" value="lumiis">
                <input type="hidden" name="redirect_focus" value="{{ $activeArea->slug }}">

                <button
                    type="submit"
                    class="action-button-muted w-full"
                    x-bind:disabled="isActionLocked() || @js($lumiisFightStaminaMissing > 0 || $lumiisFightLocked)"
                    @disabled($lumiisFightStaminaMissing > 0 || $lumiisFightLocked)
                >
                    <span class="block">{{ __('Manual Lumiis') }}</span>
                    @if ($lumiisManualPreview !== [])
                        <span
                            class="mt-1 block text-[0.58rem] uppercase tracking-[0.18em] text-emerald-50"
                            x-bind:class="successToneClassByKey('lumiis_manual')"
                            x-text="successRateLabelByKey('lumiis_manual')"
                        >
                            {{ __('Est. success') }} {{ data_get($lumiisManualPreview, 'success_rate_percent', 0) }}% · {{ data_get($lumiisManualPreview, 'risk_label') }}
                        </span>
                        <span
                            class="mt-1 block text-[0.58rem] tracking-[0.06em] text-white/75"
                            x-text="encounterSummaryByKey('lumiis_manual')"
                        >
                            {{ data_get($lumiisManualPreview, 'encounter_summary') }}
                        </span>
                    @endif
                </button>
            </form>

            @if ($lumiisFightLocked)
                <p class="action-cost-warning">{{ __('Recommended from level :level.', ['level' => $activeAreaLevelRequirement]) }}</p>
            @elseif ($lumiisFightStaminaMissing > 0)
                <p class="action-cost-warning">{{ __('Missing :amount stamina.', ['amount' => $lumiisFightStaminaMissing]) }}</p>
            @endif
        @endif
    </div>

    <div class="form-card lg:col-span-2">
        <p class="action-title">{{ __('Boss Soul') }}</p>
        <p class="action-copy">
            {{ __('Boss fights are manual only. Fill the pressure meter by clearing packs and Lumiis, then release the source of the corruption yourself.') }}
        </p>
        @if (! $bossFightsUnlocked)
            <p class="mt-4 text-xs uppercase tracking-[0.22em] text-amber-900/70">
                {{ __('Unlocks at level 4') }}
            </p>
            <div class="theme-empty-state mt-5 px-4 py-4 text-sm leading-6">
                {{ __('Boss souls stay sealed until level 4. Keep leveling, complete the starter questline, and fill the area pressure when the boss unlock opens.') }}
            </div>
        @else
            <p class="mt-4 text-xs uppercase tracking-[0.22em] text-amber-900/70">
                {{ $activeAreaBossReady ? __('Ready for release') : __('Not ready yet') }} · {{ number_format($activeAreaPressure) }}/{{ number_format($activeArea->boss_pressure_threshold) }} {{ __('pressure') }} · {{ $bossFightStaminaCost }} {{ __('stamina') }}
            </p>

            @if ($activeAreaBossReady)
                @php
                    $bossFightStaminaMissing = $staminaMissingFor($bossFightStaminaCost);
                    $bossFightLocked = $activeAreaLevelLocked;
                @endphp
                <form method="POST" action="{{ route('game.actions.store', ['action' => 'start-manual-fight']) }}" class="mt-5">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <input type="hidden" name="location_area_id" value="{{ $activeArea->id }}">
                    <input type="hidden" name="encounter_request" value="boss">
                    <input type="hidden" name="redirect_focus" value="{{ $activeArea->slug }}">

                    <button
                        type="submit"
                        class="action-button w-full"
                        x-bind:disabled="isActionLocked() || @js($bossFightStaminaMissing > 0 || $bossFightLocked)"
                        @disabled($bossFightStaminaMissing > 0 || $bossFightLocked)
                    >
                        {{ __('Manual Boss Fight') }}
                    </button>
                </form>

                @if ($bossFightLocked)
                    <p class="action-cost-warning">{{ __('Recommended from level :level.', ['level' => $activeAreaLevelRequirement]) }}</p>
                @elseif ($bossFightStaminaMissing > 0)
                    <p class="action-cost-warning">{{ __('Missing :amount stamina.', ['amount' => $bossFightStaminaMissing]) }}</p>
                @endif
            @else
                <div class="theme-empty-state mt-5 px-4 py-4 text-sm leading-6">
                    {{ __('Free more souls in this area first. The boss will only appear once the corruption pressure is full.') }}
                </div>
            @endif
        @endif
    </div>
</div>
