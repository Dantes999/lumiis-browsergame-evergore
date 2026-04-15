@php
    $currentRaceLabel = $raceDefinition['label'] ?? \App\Support\Game\MetinClassCatalog::raceLabel($character->chosenRace());
    $soulStoneCount = (int) ($inventoryQuantitiesBySlug[\App\Support\Game\MetinClassCatalog::soulStoneSlug()] ?? 0);
@endphp

<article class="game-panel">
    <div class="flex items-center justify-between gap-4">
        <div>
            <p class="panel-kicker">{{ __('Character Development') }}</p>
            <h2 class="mt-2 font-[Cinzel] text-2xl tracking-[0.12em] text-amber-50">{{ __('Combat Skills') }}</h2>
        </div>
        <span class="rounded-full border border-amber-500/20 px-3 py-1 text-xs uppercase tracking-[0.25em] text-stone-300">
            {{ __(':points points open', ['points' => $character->unspent_skill_points]) }}
        </span>
    </div>

    @if (! $character->skillsUnlocked())
        <div class="paper-note mt-6 text-sm leading-6">
            {{ __('Skills unlock at level 5. At that point you choose one of the two combat schools of your race and can spend the skill points banked from earlier levels.') }}
        </div>
    @elseif (! $character->hasChosenSkillGroup())
        <div class="paper-note mt-6">
            <p class="font-[Cinzel] text-lg tracking-[0.08em] text-stone-900">{{ $currentRaceLabel }}</p>
            <p class="mt-2 text-sm leading-6 text-stone-700">
                {{ $raceDefinition['description'] ?? __('Choose one of the two combat schools for your race. This decision defines which Metin2-like skill tree you can learn.') }}
            </p>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            @foreach ($skillGroupOptions as $groupKey => $groupOption)
                <div class="paper-note flex h-full flex-col justify-between gap-4">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-[Cinzel] text-xl tracking-[0.08em] text-stone-900">{{ $groupOption['label'] }}</p>
                                <span class="rounded-full border border-amber-900/15 px-2 py-1 text-[0.58rem] uppercase tracking-[0.22em] text-stone-700">
                                    {{ $currentRaceLabel }}
                                </span>
                                @if (! empty($groupOption['identity']))
                                    <span class="rounded-full border border-amber-900/15 px-2 py-1 text-[0.58rem] uppercase tracking-[0.22em] text-stone-700">
                                        {{ $groupOption['identity'] }}
                                    </span>
                                @endif
                            </div>
                            <p class="mt-2 text-sm leading-6 text-stone-700">{{ $groupOption['description'] }}</p>
                            @if (! empty($groupOption['passive_summary']))
                                <p class="mt-3 text-xs uppercase tracking-[0.24em] text-stone-700">
                                    {{ __('Passive: :bonuses', ['bonuses' => implode(' · ', $groupOption['passive_summary'])]) }}
                                </p>
                            @endif
                            @if (! empty($groupOption['combat_summary']))
                                <p class="mt-2 text-xs uppercase tracking-[0.24em] text-stone-700">
                                    {{ __('Battle: :bonuses', ['bonuses' => implode(' · ', $groupOption['combat_summary'])]) }}
                                </p>
                            @endif
                        </div>

                    <form method="POST" action="{{ route('game.actions.store', ['action' => 'choose-skill-group']) }}">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <input type="hidden" name="skill_group" value="{{ $groupKey }}">
                        <button type="submit" class="action-button">
                            {{ __('Choose :group', ['group' => $groupOption['label']]) }}
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    @else
            <div class="paper-note mt-6">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="font-[Cinzel] text-lg tracking-[0.08em] text-stone-900">{{ $selectedSkillGroup['label'] }}</p>
                    <span class="rounded-full border border-amber-900/15 px-2 py-1 text-[0.58rem] uppercase tracking-[0.22em] text-stone-700">
                        {{ $currentRaceLabel }}
                    </span>
                    @if (! empty($selectedSkillGroup['identity']))
                        <span class="rounded-full border border-amber-900/15 px-2 py-1 text-[0.58rem] uppercase tracking-[0.22em] text-stone-700">
                            {{ $selectedSkillGroup['identity'] }}
                        </span>
                    @endif
                </div>
                <p class="mt-2 text-sm leading-6 text-stone-700">{{ $selectedSkillGroup['description'] }}</p>
                @if (! empty($selectedSkillGroup['passive_summary']))
                    <p class="mt-3 text-xs uppercase tracking-[0.24em] text-stone-700">
                        {{ __('Passive: :bonuses', ['bonuses' => implode(' · ', $selectedSkillGroup['passive_summary'])]) }}
                    </p>
                @endif
                @if (! empty($selectedSkillGroup['combat_summary']))
                    <p class="mt-2 text-xs uppercase tracking-[0.24em] text-stone-700">
                        {{ __('Battle: :bonuses', ['bonuses' => implode(' · ', $selectedSkillGroup['combat_summary'])]) }}
                    </p>
                @endif
            </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            @foreach ($availableSkills as $skill)
                @php
                    $learnedSkill = $learnedSkills->get($skill->id);
                    $rank = $learnedSkill?->rank ?? 0;
                    $isMaxed = $learnedSkill?->hasReachedPerfectMastery() ?? false;
                    $requiresBook = $learnedSkill?->needsSkillBookProgression() ?? false;
                    $requiresSoulStone = $learnedSkill?->needsSoulStoneProgression() ?? false;
                    $stageLabel = $learnedSkill?->stageLabel();
                    $bookCooldownMinutes = $learnedSkill?->bookCooldownRemainingMinutes() ?? 0;
                    $bookSuccessChance = $learnedSkill?->nextBookSuccessChance();
                    $bookProgressCurrent = (int) ($learnedSkill?->mastery_progress ?? 0);
                    $bookProgressTarget = (int) ($learnedSkill?->requiredBookSuccessesForNextMastery() ?? 0);
                    $soulStoneSuccessChance = $learnedSkill?->nextSoulStoneSuccessChance() ?? 0;
                    $perfectMastery = $learnedSkill?->hasReachedPerfectMastery() ?? false;
                    $previewProfile = $learnedSkill
                        ? (\App\Support\Game\MetinClassCatalog::combatProfileForLearnedSkill($learnedSkill)
                            ?? \App\Support\Game\MetinClassCatalog::combatProfile($skill, max(1, $rank ?: 1)))
                        : \App\Support\Game\MetinClassCatalog::combatProfile($skill, max(1, $rank ?: 1));
                @endphp

                <div class="paper-note flex h-full flex-col justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-[Cinzel] text-xl tracking-[0.08em] text-stone-900">{{ $skill->name }}</p>
                            <span class="rounded-full border border-amber-900/15 px-2 py-1 text-[0.58rem] uppercase tracking-[0.22em] text-stone-700">
                                {{ $stageLabel ? __('Stage :stage', ['stage' => $stageLabel]) : __('Rank :rank/:max', ['rank' => $rank, 'max' => $skill->max_rank]) }}
                            </span>
                        </div>
                        <p class="mt-2 text-sm leading-6 text-stone-700">{{ $skill->description }}</p>
                        <p class="mt-3 text-xs uppercase tracking-[0.24em] text-stone-600">
                            {{ __('Level :level · :role · :type · :sp SP · :cooldown round CD', [
                                'level' => $skill->required_level,
                                'role' => $previewProfile['role_label'],
                                'type' => $previewProfile['damage_type_label'] ?? __('Physical'),
                                'sp' => $previewProfile['sp_cost'],
                                'cooldown' => $previewProfile['cooldown_rounds'],
                            ]) }}
                        </p>
                        @if ($requiresBook)
                            <p class="mt-2 text-xs uppercase tracking-[0.24em] text-stone-700">
                                {{ __('After rank :rank and the M1 breakthrough, the matching skill book drives each mastery step.', ['rank' => \App\Support\Game\MetinClassCatalog::skillBookMinimumRank()]) }}
                            </p>
                            <p class="mt-2 text-xs uppercase tracking-[0.24em] text-stone-700">
                                {{ __('Book Progress :current/:target · Success :chance% · Cooldown :cooldown min', [
                                    'current' => $bookProgressCurrent,
                                    'target' => $bookProgressTarget,
                                    'chance' => $bookSuccessChance,
                                    'cooldown' => \App\Support\Game\MetinClassCatalog::skillBookCooldownMinutes(),
                                ]) }}
                            </p>
                            @if ($bookCooldownMinutes > 0)
                                <p class="mt-2 text-xs uppercase tracking-[0.24em] text-stone-700">
                                    {{ __('Next reading in :minutes min', ['minutes' => $bookCooldownMinutes]) }}
                                </p>
                            @else
                                <p class="mt-2 text-xs uppercase tracking-[0.24em] text-stone-700">
                                    {{ __('Ready to read now.') }}
                                </p>
                            @endif
                        @elseif ($requiresSoulStone)
                            <p class="mt-2 text-xs uppercase tracking-[0.24em] text-stone-700">
                                {{ __('Soul Stone Success :chance% · Carry :count', [
                                    'chance' => $soulStoneSuccessChance,
                                    'count' => $soulStoneCount,
                                ]) }}
                            </p>
                            <p class="mt-2 text-xs uppercase tracking-[0.24em] text-stone-700">
                                {{ $perfectMastery
                                    ? __('Perfect mastery reached.')
                                    : __('Once a skill reaches G1, Soul Stones drive it through the remaining G stages and into P.') }}
                            </p>
                        @elseif ($perfectMastery)
                            <p class="mt-2 text-xs uppercase tracking-[0.24em] text-stone-700">
                                {{ __('Perfect mastery reached.') }}
                            </p>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('game.actions.store', ['action' => 'learn-skill']) }}">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <input type="hidden" name="skill_id" value="{{ $skill->id }}">

                        <button
                            type="submit"
                            class="action-button"
                            @disabled($character->unspent_skill_points < 1 || $character->level < $skill->required_level || $isMaxed || $requiresBook || $requiresSoulStone)
                        >
                            {{ $isMaxed ? __('Maxed') : ($requiresBook ? __('Requires Book') : ($requiresSoulStone ? __('Soul Stage') : __('Learn / Upgrade'))) }}
                        </button>
                    </form>

                    @if ($requiresSoulStone && ! $perfectMastery)
                        <form method="POST" action="{{ route('game.actions.store', ['action' => 'advance-skill-mastery']) }}">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            <input type="hidden" name="skill_id" value="{{ $skill->id }}">

                            <button
                                type="submit"
                                class="action-button"
                                @disabled($soulStoneCount < 1)
                            >
                                {{ $soulStoneCount > 0 ? __('Use Soul Stone') : __('Need Soul Stone') }}
                            </button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</article>
