@switch($activeArea->service_type)
    @case('town')
    @case('camp')
        <div class="mt-5 grid gap-3 lg:grid-cols-2">
            <div class="form-card">
                <p class="action-title">{{ __('Rest Plans') }}</p>
                @if ($restingState)
                    <div class="mt-4 rounded-[1rem] border border-amber-500/18 bg-stone-950/25 px-4 py-4 text-sm leading-6 text-stone-200">
                        <p class="font-[Cinzel] text-lg tracking-[0.08em] text-amber-50">{{ $restingState['label'] }}</p>
                        <p class="shop-item-meta">{{ __('Still resting · cannot be cancelled') }}</p>
                        <p class="mt-3">{{ __('Time left') }}: <span x-text="restingLockLabel()">{{ $restingState['remaining_seconds'] > 0 ? ceil($restingState['remaining_seconds'] / 60) .' min' : __('Ready') }}</span></p>
                        <p class="mt-2">{{ __('Rest started') }}: {{ $restingState['started_at_label'] ?? '--:--' }} · {{ __('Ends around') }}: {{ $restingState['ends_at_label'] ?? '--:--' }}</p>
                        <p class="mt-3 text-[0.68rem] uppercase tracking-[0.22em] text-amber-200/70">
                            {{ __('Will receive at the end') }} · +{{ $restingState['stamina_gain'] }} {{ __('stamina') }} · +{{ $restingState['health_gain'] }} {{ __('health') }} · +{{ $restingState['sp_gain'] }} {{ __('SP') }} · +{{ $restingState['yang_gain'] }} {{ __('Yang') }}
                        </p>
                    </div>
                @else
                    <div class="mt-4 grid gap-2">
                        @foreach ($restPlans as $minutes => $restPlan)
                            <form method="POST" action="{{ route('game.actions.store', ['action' => 'rest']) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Start :label? You cannot cancel the rest, and the rewards are granted only when the timer ends.', ['label' => $restPlan['label']])) }})">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <input type="hidden" name="redirect_focus" value="{{ $activeArea->slug }}">
                                <input type="hidden" name="rest_minutes" value="{{ $minutes }}">

                                <div class="space-y-2">
                                    <button type="submit" class="action-button w-full">
                                        {{ $restPlan['label'] }}
                                    </button>

                                    <p class="text-center text-[0.68rem] uppercase tracking-[0.22em] text-stone-300/75">
                                        +{{ $restPlan['stamina_gain'] }} {{ __('stamina') }} · +{{ $restPlan['health_gain'] }} {{ __('health') }} · +{{ $restPlan['sp_gain'] }} {{ __('SP') }} · +{{ $restPlan['yang_gain'] }} {{ __('Yang') }}
                                    </p>
                                </div>
                            </form>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($restingState)
                @include('game.partials.rest-waiting-placeholder', [
                    'footerUrl' => route('dashboard'),
                    'footerLabel' => __('Back to Map'),
                ])
            @else
                <div class="form-card flex flex-col justify-between gap-4">
                    <div>
                        <p class="action-title">{{ __('Map Overview') }}</p>
                        <p class="shop-item-meta">{{ __('Return to the full map view') }}</p>
                    </div>

                    <a href="{{ route('dashboard') }}" class="action-button-muted">
                        {{ __('Back to Map') }}
                    </a>
                </div>
            @endif

            @if ($generalStoreArea)
                <div class="form-card flex flex-col justify-between gap-4">
                    <div>
                        <p class="action-title">{{ __('Potions and Supplies') }}</p>
                        <p class="shop-item-meta">{{ __('Potions · supplies') }}</p>
                    </div>

                    <a href="{{ route('dashboard', ['focus' => $generalStoreArea->slug]) }}" class="action-button-muted">
                        {{ __('Visit General Store') }}
                    </a>
                </div>
            @endif

            @if ($weaponDealerArea && $advancedTownServicesUnlocked)
                <div class="form-card flex flex-col justify-between gap-4">
                    <div>
                        <p class="action-title">{{ __('Weapon Dealer') }}</p>
                        <p class="shop-item-meta">{{ __('Weapons') }}</p>
                    </div>

                    <a href="{{ route('dashboard', ['focus' => $weaponDealerArea->slug]) }}" class="action-button-muted">
                        {{ __('Browse Weapons') }}
                    </a>
                </div>
            @endif

            @if ($armorDealerArea && $advancedTownServicesUnlocked)
                <div class="form-card flex flex-col justify-between gap-4">
                    <div>
                        <p class="action-title">{{ __('Armor Dealer') }}</p>
                        <p class="shop-item-meta">{{ __('Armor · shields · boots') }}</p>
                    </div>

                    <a href="{{ route('dashboard', ['focus' => $armorDealerArea->slug]) }}" class="action-button-muted">
                        {{ __('Browse Armor') }}
                    </a>
                </div>
            @endif

            @if ($blacksmithArea && $advancedTownServicesUnlocked)
                <div class="form-card flex flex-col justify-between gap-4">
                    <div>
                        <p class="action-title">{{ __('Town Smith') }}</p>
                        <p class="shop-item-meta">{{ __('Weapon upgrades') }}</p>
                    </div>

                    <a href="{{ route('dashboard', ['focus' => $blacksmithArea->slug]) }}" class="action-button-muted">
                        {{ __('Open Smithy') }}
                    </a>
                </div>
            @endif

            @if ($biologistArea)
                <div class="form-card flex flex-col justify-between gap-4">
                    <div>
                        <p class="action-title">{{ __('Biologist') }}</p>
                        <p class="shop-item-meta">
                            {{ $biologistResearchUnlocked ? __('Permanent bonuses') : __('Unlocks later') }}
                        </p>
                    </div>

                    @if ($biologistResearchUnlocked)
                        <a href="{{ route('dashboard', ['focus' => $biologistArea->slug]) }}" class="action-button-muted">
                            {{ __('Open Research Desk') }}
                        </a>
                    @else
                        <p class="action-cost-warning">{{ __('Unlocks at level :level.', ['level' => 5]) }}</p>
                    @endif
                </div>
            @endif
        </div>
    @break

    @case('inn')
        <div class="mt-5 grid gap-3 lg:grid-cols-2">
            <div class="form-card">
                <p class="action-title">{{ __('Inn Rest') }}</p>
                @if ($restingState)
                    <div class="mt-4 rounded-[1rem] border border-amber-500/18 bg-stone-950/25 px-4 py-4 text-sm leading-6 text-stone-200">
                        <p class="font-[Cinzel] text-lg tracking-[0.08em] text-amber-50">{{ $restingState['label'] }}</p>
                        <p class="shop-item-meta">{{ __('Still resting · cannot be cancelled') }}</p>
                        <p class="mt-3">{{ __('Time left') }}: <span x-text="restingLockLabel()">{{ $restingState['remaining_seconds'] > 0 ? ceil($restingState['remaining_seconds'] / 60) .' min' : __('Ready') }}</span></p>
                        <p class="mt-2">{{ __('Rest started') }}: {{ $restingState['started_at_label'] ?? '--:--' }} · {{ __('Ends around') }}: {{ $restingState['ends_at_label'] ?? '--:--' }}</p>
                        <p class="mt-3 text-[0.68rem] uppercase tracking-[0.22em] text-amber-200/70">
                            {{ __('Will receive at the end') }} · +{{ $restingState['stamina_gain'] }} {{ __('stamina') }} · +{{ $restingState['health_gain'] }} {{ __('health') }} · +{{ $restingState['sp_gain'] }} {{ __('SP') }} · +{{ $restingState['yang_gain'] }} {{ __('Yang') }}
                        </p>
                    </div>
                @else
                    <div class="mt-4 grid gap-2">
                        @foreach ($restPlans as $minutes => $restPlan)
                            <form method="POST" action="{{ route('game.actions.store', ['action' => 'rest']) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Start :label? You cannot cancel the rest, and the rewards are granted only when the timer ends.', ['label' => $restPlan['label']])) }})">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <input type="hidden" name="redirect_focus" value="{{ $activeArea->slug }}">
                                <input type="hidden" name="rest_minutes" value="{{ $minutes }}">

                                <div class="space-y-2">
                                    <button type="submit" class="action-button w-full">
                                        {{ $restPlan['label'] }}
                                    </button>

                                    <p class="text-center text-[0.68rem] uppercase tracking-[0.22em] text-stone-300/75">
                                        +{{ $restPlan['stamina_gain'] }} {{ __('stamina') }} · +{{ $restPlan['health_gain'] }} {{ __('health') }} · +{{ $restPlan['sp_gain'] }} {{ __('SP') }} · +{{ $restPlan['yang_gain'] }} {{ __('Yang') }}
                                    </p>
                                </div>
                            </form>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($restingState)
                @include('game.partials.rest-waiting-placeholder', [
                    'footerUrl' => route('dashboard', ['focus' => $huntingAreas->first()?->slug ?? $activeArea->slug]),
                    'footerLabel' => __('Open Hunting Grounds'),
                ])
            @else
                <div class="form-card flex flex-col justify-between gap-4">
                    <div>
                        <p class="action-title">{{ __('Back To Map') }}</p>
                        <p class="shop-item-meta">{{ __('Return to the field') }}</p>
                    </div>

                    <a href="{{ route('dashboard', ['focus' => $huntingAreas->first()?->slug ?? $activeArea->slug]) }}" class="action-button-muted">
                        {{ __('Open Hunting Grounds') }}
                    </a>
                </div>
            @endif
        </div>
    @break

    @case('shop')
    @case('weapon-shop')
    @case('armor-shop')
        <div class="mt-5 space-y-3">
            <div class="theme-paper-panel px-4 py-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="action-title">{{ $activeAreaShop?->name ?? $activeArea->name }}</p>
                    <span class="cozy-chip">{{ __('Wallet') }}: {{ number_format($character->yang) }} {{ __('Yang') }}</span>
                </div>
                <p class="shop-item-meta">{{ __('Buy here · sell via inventory panel') }}</p>
            </div>

            @if (! $activeAreaShop)
                <div class="form-card">
                    <p class="action-title">{{ __('Vendor Not Ready') }}</p>
                    <p class="action-copy">
                        {{ __('This hotspot has no shop inventory configured yet.') }}
                    </p>
                </div>
            @else
                @foreach ($activeAreaShop->shopItems->sortBy('sort_order') as $shopItem)
                    <div class="form-card">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <p class="action-title">{{ $shopItem->item->name }}</p>
                                <p class="shop-item-meta">
                                    {{ number_format($shopItem->price) }} Yang · Req. Level {{ $shopItem->item->required_level }}
                                </p>
                                @if ($shopItemValueText($shopItem->item))
                                    <p class="shop-item-values">{{ $shopItemValueText($shopItem->item) }}</p>
                                @endif
                            </div>

                            <form method="POST" action="{{ route('game.actions.store', ['action' => 'buy-item']) }}" class="w-full lg:w-auto lg:min-w-[10rem]">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <input type="hidden" name="location_area_id" value="{{ $activeAreaShop->location_area_id }}">
                                <input type="hidden" name="shop_item_id" value="{{ $shopItem->id }}">
                                <input type="hidden" name="redirect_focus" value="{{ $activeArea->slug }}">

                                <button
                                    type="submit"
                                    class="action-button w-full"
                                    @disabled($character->yang < $shopItem->price || $character->level < $shopItem->item->required_level)
                                >
                                    {{ __('Buy') }}
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    @break

    @case('smith')
        @if (! $advancedTownServicesUnlocked)
            <div class="form-card mt-5">
                <p class="action-title">{{ __('Blacksmith Locked') }}</p>
                <p class="action-copy">
                    {{ __('The smith unlocks at level 5 together with the weapon and armor dealers.') }}
                </p>
            </div>
        @elseif ($smithableItems->isEmpty())
            <div class="form-card mt-5">
                <p class="action-title">{{ __('No Weapon To Upgrade') }}</p>
                <p class="action-copy">
                    {{ __('You do not currently carry an upgradeable weapon. Visit the weapon dealer first.') }}
                </p>
            </div>
        @else
            <div class="mt-5 space-y-3">
                <div class="theme-paper-panel px-4 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="action-title">{{ __('Weapon Upgrades +0 to +9') }}</p>
                        <span class="cozy-chip">{{ __('Shards') }}: {{ number_format($lumiisShardCount) }}</span>
                    </div>
                    <p class="shop-item-meta">{{ __('Fail destroys the weapon') }}</p>
                </div>

                @foreach ($smithableItems as $inventoryItem)
                    @php
                        $item = $inventoryItem->item;
                        $nextUpgradeLevel = min(9, $inventoryItem->upgrade_level + 1);
                    @endphp

                    <div class="form-card">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="action-title">{{ $inventoryItem->displayName() }}</p>
                                    <span class="cozy-chip">Next: +{{ $nextUpgradeLevel }}</span>
                                    @if ($inventoryItem->isEquipped())
                                        <span class="theme-badge-equipped rounded-full px-2 py-1 text-[0.58rem] uppercase tracking-[0.22em]">
                                            Equipped
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-3 text-xs uppercase tracking-[0.24em] text-amber-900/70">
                                    {{ $inventoryItem->effectiveBonus('attack_bonus') }} ATK now
                                    · {{ $inventoryItem->smithUpgradeSuccessPercent() }}% success
                                    · {{ number_format($inventoryItem->smithUpgradeCost()) }} Yang
                                    @if ($inventoryItem->smithUpgradeShardCost() > 0)
                                        · {{ $inventoryItem->smithUpgradeShardCost() }} {{ $inventoryItem->smithUpgradeShardCost() === 1 ? __('Lumiis Shard') : __('Lumiis Shards') }}
                                    @endif
                                </p>
                            </div>

                            <form method="POST" action="{{ route('game.actions.store', ['action' => 'upgrade-item']) }}" class="w-full lg:w-auto lg:min-w-[12rem]">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <input type="hidden" name="location_area_id" value="{{ $activeArea->id }}">
                                <input type="hidden" name="character_item_id" value="{{ $inventoryItem->id }}">
                                <input type="hidden" name="redirect_focus" value="{{ $activeArea->slug }}">

                                <button
                                    type="submit"
                                    class="action-button w-full"
                                    @disabled($inventoryItem->upgrade_level >= 9 || $character->yang < $inventoryItem->smithUpgradeCost() || $lumiisShardCount < $inventoryItem->smithUpgradeShardCost())
                                >
                                    Upgrade
                                </button>
                            </form>
                        </div>
                        @if ($lumiisShardCount < $inventoryItem->smithUpgradeShardCost())
                            <p class="action-cost-warning mt-3">
                                {{ __('Missing') }} {{ $inventoryItem->smithUpgradeShardCost() - $lumiisShardCount }} {{ ($inventoryItem->smithUpgradeShardCost() - $lumiisShardCount) === 1 ? __('Lumiis Shard') : __('Lumiis Shards') }}
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    @break

    @case('biologist')
        @if (! $biologistResearchUnlocked)
            <div class="form-card mt-5">
                <p class="action-title">{{ __('Biologist Locked') }}</p>
                <p class="action-copy">
                    {{ __('Biologist research opens at level 5, but your first submission only unlocks after this map boss quest is finished.') }}
                </p>
            </div>
        @elseif (! $biologistResearch)
            <div class="form-card mt-5">
                <p class="action-title">{{ __('Research Board') }}</p>
                <p class="action-copy">
                    {{ __('No biologist research is configured for this map yet.') }}
                </p>
            </div>
        @else
            @php
                $activeBiologistMissingItems = $activeBiologistResearch === null
                    ? 0
                    : (int) ($activeBiologistResearch['missing_quantity'] ?? 0);
            @endphp

            <div class="mt-5 space-y-3">
                <div class="theme-paper-panel px-4 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="action-title">{{ __('Research Board') }}</p>
                        <span class="cozy-chip">
                            {{ data_get($biologistResearch, 'completed_count', 0) }}/{{ data_get($biologistResearch, 'total_count', 0) }} {{ __('complete') }}
                        </span>
                    </div>
                    <p class="shop-item-meta">{{ __('Turn in samples for permanent bonuses') }}</p>
                </div>

                @if ($completedBiologistStages !== [])
                    <div class="theme-paper-panel px-4 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="action-title">{{ __('Research Archive') }}</p>
                            <span class="cozy-chip">
                                {{ count($completedBiologistStages) }} {{ __('complete') }}
                            </span>
                        </div>

                        @if ($biologistBonusSummary !== [])
                            <div class="mt-4 flex flex-wrap gap-2">
                                @foreach ($biologistBonusSummary as $bonusSummaryLine)
                                    <span class="cozy-chip">{{ $bonusSummaryLine }}</span>
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-4 space-y-3">
                            @foreach ($completedBiologistStages as $completedStage)
                                <div class="theme-paper-panel px-4 py-3">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <p class="font-[Cinzel] text-sm tracking-[0.08em] text-stone-900">
                                            {{ $completedStage['title'] }}
                                        </p>
                                        <span class="cozy-chip">
                                            {{ __('Stage :current / :total', ['current' => $completedStage['stage_number'], 'total' => $completedStage['total_stages']]) }}
                                        </span>
                                    </div>
                                    @if ($completedStage['reward_bonus_description'])
                                        <p class="shop-item-meta">{{ $completedStage['reward_bonus_description'] }}</p>
                                    @endif
                                    @if ($completedStage['completed_at_label'])
                                        <p class="shop-item-meta">{{ __('Completed') }} · {{ $completedStage['completed_at_label'] }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($activeBiologistResearch)
                    <div class="form-card">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="action-title">{{ $activeBiologistResearch['title'] }}</p>
                                <p class="shop-item-meta">{{ $activeBiologistResearch['description'] }}</p>
                            </div>
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <span class="cozy-chip">
                                    {{ __('Stage :current / :total', ['current' => $activeBiologistResearch['stage_number'], 'total' => $activeBiologistResearch['total_stages']]) }}
                                </span>
                                <span class="cozy-chip">
                                    {{ $activeBiologistResearch['progress'] }}/{{ $activeBiologistResearch['objective_target'] }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 lg:grid-cols-4">
                            <div class="theme-paper-panel px-4 py-3">
                                <p class="text-[0.65rem] uppercase tracking-[0.28em] text-amber-900/70">{{ __('Current Submission') }}</p>
                                <p class="mt-2 font-[Cinzel] text-lg tracking-[0.08em] text-stone-900">
                                    {{ $activeBiologistResearch['objective_item_quantity'] }} x {{ $activeBiologistResearch['objective_item_label'] }}
                                </p>
                            </div>
                            <div class="theme-paper-panel px-4 py-3">
                                <p class="text-[0.65rem] uppercase tracking-[0.28em] text-amber-900/70">{{ __('Stage Goal') }}</p>
                                <p class="mt-2 font-[Cinzel] text-lg tracking-[0.08em] text-stone-900">
                                    {{ __(':count successful submissions', ['count' => $activeBiologistResearch['objective_target']]) }}
                                </p>
                            </div>
                            <div class="theme-paper-panel px-4 py-3">
                                <p class="text-[0.65rem] uppercase tracking-[0.28em] text-amber-900/70">{{ __('Owned') }}</p>
                                <p class="mt-2 font-[Cinzel] text-lg tracking-[0.08em] text-stone-900">
                                    {{ $activeBiologistResearch['owned_quantity'] }}
                                </p>
                            </div>
                            <div class="theme-paper-panel px-4 py-3">
                                <p class="text-[0.65rem] uppercase tracking-[0.28em] text-amber-900/70">{{ __('Research Reward') }}</p>
                                <p class="mt-2 font-[Cinzel] text-lg tracking-[0.08em] text-stone-900">
                                    {{ $activeBiologistResearch['reward_bonus_description'] ?? __('Yang and EXP') }}
                                </p>
                            </div>
                        </div>

                        <div class="theme-paper-panel mt-3 px-4 py-3">
                            <p class="text-[0.65rem] uppercase tracking-[0.28em] text-amber-900/70">{{ __('Success Chance') }}</p>
                            <p class="mt-2 font-[Cinzel] text-lg tracking-[0.08em] text-stone-900">
                                {{ $activeBiologistResearch['submission_success_rate'] }}%
                            </p>
                            @if ((int) $activeBiologistResearch['submission_success_bonus_percent'] > 0)
                                <p class="paper-surface-meta mt-2">
                                    {{ __('Base') }} {{ $activeBiologistResearch['submission_success_rate_base'] }}% ·
                                    {{ __('Catalyst') }} +{{ $activeBiologistResearch['submission_success_bonus_percent'] }}%
                                </p>
                            @endif
                            @if ((int) $activeBiologistResearch['submission_success_rate'] < 100)
                                <p class="paper-surface-meta mt-2">{{ __('Failed submissions still consume the sample and start the wait time.') }}</p>
                            @endif
                        </div>

                        <div class="mt-4 grid gap-3 lg:grid-cols-2">
                            <div class="theme-paper-panel px-4 py-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <p class="action-title">{{ __('Research Catalyst') }}</p>
                                    <span class="cozy-chip">x{{ $researchCatalystCount }}</span>
                                </div>
                                <p class="paper-surface-meta mt-2">
                                    {{ __('Add +20% success chance to the next submission roll for this stage.') }}
                                </p>

                                @if ($researchCatalystStack)
                                    <form method="POST" action="{{ route('game.actions.store', ['action' => 'boost-research-submission']) }}" class="mt-4">
                                        @csrf
                                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                        <input type="hidden" name="location_area_id" value="{{ $activeArea->id }}">
                                        <input type="hidden" name="character_quest_id" value="{{ $activeBiologistResearch['character_quest_id'] }}">
                                        <input type="hidden" name="character_item_id" value="{{ $researchCatalystStack->id }}">
                                        <input type="hidden" name="redirect_focus" value="{{ $activeArea->slug }}">

                                        <button
                                            type="submit"
                                            class="action-button-muted w-full"
                                            @disabled((int) $activeBiologistResearch['submission_success_bonus_percent'] > 0)
                                        >
                                            {{ __('Prepare Next Sample') }}
                                        </button>
                                    </form>
                                @endif

                                @if ($researchCatalystCount < 1)
                                    <p class="action-cost-warning mt-3">{{ __('You do not have a Research Catalyst right now.') }}</p>
                                @elseif ((int) $activeBiologistResearch['submission_success_bonus_percent'] > 0)
                                    <p class="action-cost-warning mt-3">{{ __('This stage is already prepared for the next submission.') }}</p>
                                @endif
                            </div>

                            <div class="theme-paper-panel px-4 py-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <p class="action-title">{{ __('Research Report') }}</p>
                                    <span class="cozy-chip">x{{ $researchReportCount }}</span>
                                </div>
                                <p class="paper-surface-meta mt-2">
                                    {{ __('Skip the current waiting time for this research stage.') }}
                                </p>

                                @if ($researchReportStack)
                                    <form method="POST" action="{{ route('game.actions.store', ['action' => 'skip-research-cooldown']) }}" class="mt-4">
                                        @csrf
                                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                        <input type="hidden" name="location_area_id" value="{{ $activeArea->id }}">
                                        <input type="hidden" name="character_quest_id" value="{{ $activeBiologistResearch['character_quest_id'] }}">
                                        <input type="hidden" name="character_item_id" value="{{ $researchReportStack->id }}">
                                        <input type="hidden" name="redirect_focus" value="{{ $activeArea->slug }}">

                                        <button
                                            type="submit"
                                            class="action-button-muted w-full"
                                            @disabled((int) $activeBiologistResearch['cooldown_seconds'] <= 0)
                                        >
                                            {{ __('Skip Wait Time') }}
                                        </button>
                                    </form>
                                @endif

                                @if ($researchReportCount < 1)
                                    <p class="action-cost-warning mt-3">{{ __('You do not have a Research Report right now.') }}</p>
                                @elseif ((int) $activeBiologistResearch['cooldown_seconds'] <= 0)
                                    <p class="action-cost-warning mt-3">{{ __('This stage is already ready for the next sample.') }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-stone-700">
                            <span>{{ __('Reward') }}: +{{ number_format($activeBiologistResearch['reward_experience']) }} EXP</span>
                            <span>· +{{ number_format($activeBiologistResearch['reward_yang']) }} Yang</span>
                            @if ($activeBiologistResearch['reward_item_label'])
                                <span>· {{ $activeBiologistResearch['reward_item_label'] }} x{{ $activeBiologistResearch['reward_item_quantity'] }}</span>
                            @endif
                        </div>

                        <form method="POST" action="{{ route('game.actions.store', ['action' => 'submit-quest-item']) }}" class="mt-5">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            <input type="hidden" name="location_area_id" value="{{ $activeArea->id }}">
                            <input type="hidden" name="character_quest_id" value="{{ $activeBiologistResearch['character_quest_id'] }}">
                            <input type="hidden" name="redirect_focus" value="{{ $activeArea->slug }}">

                            <button type="submit" class="action-button w-full" @disabled(! $activeBiologistResearch['can_submit'])>
                                {{ __('Submit :count x :item', [
                                    'count' => $activeBiologistResearch['objective_item_quantity'],
                                    'item' => $activeBiologistResearch['objective_item_label'],
                                ]) }}
                            </button>
                        </form>

                        @if ((int) $activeBiologistResearch['cooldown_seconds'] > 0)
                            <p class="action-cost-warning mt-3">
                                {{ $biologistCooldownLabel((int) $activeBiologistResearch['cooldown_seconds']) }}
                            </p>
                        @elseif ($activeBiologistMissingItems > 0)
                            <p class="action-cost-warning mt-3">
                                {{ __('Missing :count x :item.', [
                                    'count' => $activeBiologistMissingItems,
                                    'item' => $activeBiologistResearch['objective_item_label'],
                                ]) }}
                            </p>
                        @else
                            <p class="action-cost-ready mt-3">
                                {{ __('Ready to submit now.') }}
                            </p>
                        @endif
                    </div>
                @elseif ($upcomingBiologistResearch)
                    <div class="form-card">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="action-title">{{ __('Next Research') }}</p>
                            <span class="cozy-chip">
                                {{ __('Stage :current / :total', ['current' => $upcomingBiologistResearch['stage_number'], 'total' => $upcomingBiologistResearch['total_stages']]) }}
                            </span>
                        </div>
                        <p class="shop-item-meta">
                            {{ __('Required level: :level', ['level' => $upcomingBiologistResearch['required_level']]) }}
                            · {{ __(':count successful submissions', ['count' => $upcomingBiologistResearch['objective_target']]) }}
                            · {{ __(':count x :item per submission', [
                                'count' => $upcomingBiologistResearch['objective_item_quantity'],
                                'item' => $upcomingBiologistResearch['objective_item_label'],
                            ]) }}
                            · {{ __('Success Chance') }}: {{ $upcomingBiologistResearch['submission_success_rate'] }}%
                        </p>
                        @if (! empty($upcomingBiologistResearch['lock_reason']))
                            <p class="action-cost-warning mt-3">
                                {{ $upcomingBiologistResearch['lock_reason'] }}
                            </p>
                        @endif
                    </div>
                @else
                    <div class="form-card">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="action-title">{{ __('Research Complete') }}</p>
                            @if ($completedBiologistResearch)
                                <span class="cozy-chip">
                                    {{ __('Stage :current / :total', ['current' => $completedBiologistResearch['stage_number'], 'total' => $completedBiologistResearch['total_stages']]) }}
                                </span>
                            @endif
                        </div>
                        <p class="action-copy mt-2">
                            {{ $completedBiologistResearch['story_text'] ?? __('All current biologist studies in this town are complete.') }}
                        </p>
                        @if ($completedBiologistResearch && $completedBiologistResearch['reward_bonus_description'])
                            <p class="shop-item-meta">{{ $completedBiologistResearch['reward_bonus_description'] }}</p>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    @break

    @case('trade')
        <div class="mt-5 grid gap-3 lg:grid-cols-2">
            <div class="form-card flex flex-col justify-between gap-4">
                <div>
                    <p class="action-title">Global Board</p>
                    <p class="action-copy">
                        Coordinate hunts, route swaps and future boss timers in the chat panel.
                    </p>
                </div>

                <button type="button" class="action-button-muted" @click="openChatTab('global')">
                    {{ __('Open Global Chat') }}
                </button>
            </div>

            <div class="form-card flex flex-col justify-between gap-4">
                <div>
                    <p class="action-title">Whispers</p>
                    <p class="action-copy">
                        Direct player chat lives in the same fullscreen chat panel.
                    </p>
                </div>

                <button type="button" class="action-button-muted" @click="openChatTab('whisper')">
                    {{ __('Open Whispers') }}
                </button>
            </div>
        </div>
    @break
@endswitch
