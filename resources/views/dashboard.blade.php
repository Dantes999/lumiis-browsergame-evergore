<x-app-layout>
    @php
        $equipmentBySlot = $character->equipmentBySlot();
        $learnedSkills = $character->learnedSkills->keyBy('skill_id');
        $globalChatUnlocked = $character->globalChatUnlocked();
        $whispersUnlocked = $character->whispersUnlocked();
        $lumiisUnlocked = $character->lumiisUnlocked();
        $travelRoutesUnlocked = $character->travelRoutesUnlocked();
        $bossFightsUnlocked = $character->bossFightsUnlocked();
        $tutorialActive = (bool) data_get($tutorial, 'active', false);
        $tutorialStep = (int) data_get($tutorial, 'step', 0);
        $tutorialDefinition = data_get($tutorial, 'definition', []);
        $tutorialHighlight = data_get($tutorialDefinition, 'highlight');
        $tutorialCanGoBack = (bool) data_get($tutorial, 'can_go_back', false);
        $tutorialCanGoNext = (bool) data_get($tutorial, 'can_go_next', false);
        $tutorialTotalSteps = count(config('game_tutorial.steps', []));
        $tutorialDriverPayload = [
            'active' => $tutorialActive,
            'step' => $tutorialStep,
            'totalSteps' => $tutorialTotalSteps,
            'stepLabel' => __('Tutorial Step'),
            'title' => data_get($tutorialDefinition, 'title'),
            'body' => data_get($tutorialDefinition, 'body'),
            'nextLabel' => data_get($tutorialDefinition, 'next_label'),
            'previousLabel' => data_get($tutorialDefinition, 'previous_label'),
        ];
        // Secondary systems open from the top nav as fullscreen panels so the map can
        // stay visually central instead of competing with persistent sidebars.
        $showSystemGrid = false;
        $showChatPanel = ! $tutorialActive && (bool) data_get($tutorial, 'show_chat', true) && $globalChatUnlocked;
        $advancedTownServicesUnlocked = $character->advancedTownServicesUnlocked();
        $biologistResearchUnlocked = $character->biologistResearchUnlocked();
        $serviceAreas = $locationAreas->filter(fn ($area) => $area->isService())->values();
        $huntingAreas = $locationAreas->filter(fn ($area) => $area->isHunting())->values();
        $tutorialFightArea = $huntingAreas->firstWhere('slug', 'riverbank');
        $generalStoreArea = $serviceAreas->firstWhere('slug', 'general-store');
        $weaponDealerArea = $serviceAreas->firstWhere('slug', 'weapon-dealer');
        $armorDealerArea = $serviceAreas->firstWhere('slug', 'armor-dealer');
        $blacksmithArea = $serviceAreas->firstWhere('slug', 'town-smith');
        $biologistArea = $serviceAreas->firstWhere('service_type', 'biologist');
        $routesArea = $serviceAreas->firstWhere('service_type', 'routes');
        $activeBiologistResearch = data_get($biologistResearch, 'active');
        $upcomingBiologistResearch = data_get($biologistResearch, 'upcoming');
        $completedBiologistStages = data_get($biologistResearch, 'completed', []);
        $completedBiologistResearch = data_get($biologistResearch, 'latest_completed');
        $biologistBonusSummary = data_get($biologistResearch, 'bonus_summary', []);
        $biologistCooldownLabel = function (?int $cooldownSeconds): string {
            if ($cooldownSeconds === null || $cooldownSeconds <= 0) {
                return __('Ready now');
            }

            return __('Next sample in :minutes min.', [
                'minutes' => max(1, (int) ceil($cooldownSeconds / 60)),
            ]);
        };
        $displayHealth = $healthPreview['health'] ?? $character->health;
        $displayStamina = $staminaPreview['stamina'] ?? $character->stamina;
        $displaySp = $spPreview['sp'] ?? $character->effectiveCurrentSp();
        $nextStaminaTickSeconds = $staminaPreview['next_tick_seconds'] ?? null;
        $currentLocalePreference = auth()->user() !== null
            ? data_get(auth()->user()->getAttributes(), 'locale')
            : null;
        $nextStaminaTickLabel = $nextStaminaTickSeconds === null
            ? __('Full')
            : ($nextStaminaTickSeconds >= 60
                ? ceil($nextStaminaTickSeconds / 60).' '.__('min')
                : $nextStaminaTickSeconds.' '.__('sec'));
        $availableStamina = (int) $displayStamina;
        $activeFocus = $activeArea?->slug;
        $activeAreaPressure = $activeAreaProgress?->pressure ?? 0;
        $activeAreaPressurePercent = $activeArea && $activeArea->isHunting()
            ? min(100, (int) round(($activeAreaPressure / max(1, $activeArea->boss_pressure_threshold)) * 100))
            : 0;
        $activeAreaBossReadiness = $activeArea && $activeArea->isHunting()
            ? rtrim(rtrim(number_format($activeArea->bossChancePercent($activeAreaPressure), 2), '0'), '.')
            : null;
        $activeAreaBossReady = $activeArea && $activeArea->isHunting()
            ? $activeAreaPressure >= $activeArea->boss_pressure_threshold
            : false;
        $sceneThemeClass = match ($character->location->slug) {
            'yongan' => 'scene-theme-yongan',
            'joan' => 'scene-theme-joan',
            'pyungmoo' => 'scene-theme-pyungmoo',
            'seungryong-valley' => 'scene-theme-seungryong',
            default => 'scene-theme-default',
        };
        $activeAreaMonsterAssignments = $activeArea?->monsterAssignments ?? collect();
        $activeAreaMinimumMonsterLevel = $activeArea?->minimumMonsterAssignmentLevel();
        $activeAreaLevelRequirement = $activeAreaMinimumMonsterLevel;
        $activeAreaLevelLocked = $activeArea !== null
            && $activeArea->isHunting()
            && $activeAreaLevelRequirement !== null
            && $character->level < $activeAreaLevelRequirement;
        $activeAreaMonsterNames = $activeAreaMonsterAssignments
            ->map(fn ($assignment) => $assignment->monster?->name)
            ->filter()
            ->unique()
            ->values();
        $activeMonsterAssignment = $activeAreaMonsterAssignments
            ->filter(fn ($assignment): bool => $assignment->isEligibleForLevel($character->level))
            ->sortBy('sort_order')
            ->first() ?? $activeAreaMonsterAssignments->sortBy('sort_order')->first();
        $monsterFightStaminaCost = $activeMonsterAssignment?->monster === null
            ? 4
            : 4 + intdiv(max(0, (int) $activeMonsterAssignment->monster->level - 1), 12);
        $lumiisFightStaminaCost = 6;
        $bossFightStaminaCost = 8;
        $staminaMissingFor = fn (int $cost): int => max(0, $cost - $availableStamina);
        $activeAreaPackSize = (int) ($activeAreaMonsterAssignments->max('pack_size') ?? 3);
        $activeAreaHighestMonsterLevel = (int) ($activeAreaMonsterAssignments
            ->map(fn ($assignment) => $assignment->monster?->level)
            ->filter()
            ->max() ?? 0);
        $latestBattleLines = collect(data_get($latestBattleLog?->outcome, 'battle_log', []));
        $activeBattleState = $activeBattle?->state ?? [];
        $activeBattlePlayer = data_get($activeBattleState, 'player', []);
        $activeBattleEnemies = collect(data_get($activeBattleState, 'enemies', []));
        $activeBattleSteps = collect(data_get($activeBattleState, 'battle_steps', []))->values();
        $sortBattleEnemiesForDisplay = function (\Illuminate\Support\Collection $enemies, ?string $encounterType) {
            return $enemies
                ->values()
                ->map(fn ($enemy, $index) => [
                    'enemy' => $enemy,
                    'index' => $index,
                    'is_pinned_lumiis_core' => $encounterType === 'lumiis'
                        && (string) data_get($enemy, 'category') === 'Lumiis Core',
                    'is_alive' => (int) data_get($enemy, 'current_health', 0) > 0,
                ])
                ->sortBy(fn (array $entry): string => sprintf(
                    '%d-%d-%04d',
                    $entry['is_pinned_lumiis_core'] ? 0 : 1,
                    $entry['is_alive'] ? 0 : 1,
                    $entry['index'],
                ))
                ->pluck('enemy')
                ->values();
        };
        $sortedActiveBattleEnemies = $activeBattleEnemies
            ->pipe(fn (\Illuminate\Support\Collection $enemies) => $sortBattleEnemiesForDisplay(
                $enemies,
                $activeBattle?->encounter_type,
            ));
        $activeBattleEnemyNames = $activeBattleEnemies
            ->flatMap(fn ($enemy) => [data_get($enemy, 'label'), data_get($enemy, 'name')])
            ->filter()
            ->unique()
            ->values()
            ->all();
        $latestBattleReplaySteps = collect(data_get($latestBattleLog?->outcome, 'battle_replay.steps', []))->values();
        $buildBattleLogEntries = function ($lines, $steps) {
            $remainingSteps = collect($steps)->values();

            return collect($lines)
                ->values()
                ->map(function ($line) use (&$remainingSteps) {
                    $matchedStepIndex = $remainingSteps->search(
                        fn ($step) => (string) data_get($step, 'message') === (string) $line,
                    );
                    $matchedStep = $matchedStepIndex === false ? null : $remainingSteps->pull($matchedStepIndex);

                    return [
                        'line' => (string) $line,
                        'step' => is_array($matchedStep) ? $matchedStep : null,
                    ];
                })
                ->all();
        };
        $formatBattleLogLine = function (string $line, ?array $step, string $playerName, array $enemyNames) {
            $html = e($line);

            if (preg_match('/^(Round|Runde)\s+\d+:/u', $line) === 1) {
                $html = preg_replace(
                    '/^((Round|Runde)\s+\d+:)/u',
                    '<span class="battle-log-round">$1</span>',
                    $html,
                ) ?? $html;
            }

            $highlightName = function (string $htmlValue, string $name, string $className): string {
                if ($name === '') {
                    return $htmlValue;
                }

                return preg_replace(
                    '/'.preg_quote(e($name), '/').'/u',
                    '<span class="'.$className.'">$0</span>',
                    $htmlValue,
                ) ?? $htmlValue;
            };

            $html = $highlightName($html, $playerName, 'battle-log-name-player');

            foreach (collect($enemyNames)->filter()->unique()->sortByDesc(fn ($name) => mb_strlen((string) $name)) as $enemyName) {
                $html = $highlightName($html, (string) $enemyName, 'battle-log-name-enemy');
            }

            $damageClass = match (true) {
                $step !== null && data_get($step, 'actor') === 'player' && data_get($step, 'target_type') === 'enemy' => 'battle-log-damage-dealt',
                $step !== null && data_get($step, 'actor') === 'enemy' && data_get($step, 'target_type') === 'player' => 'battle-log-damage-taken',
                default => null,
            };

            if ($damageClass !== null) {
                $html = preg_replace(
                    '/(for\s+)(\d+)(\s+damage)/iu',
                    '$1<span class="'.$damageClass.'">$2</span>$3',
                    $html,
                    1,
                ) ?? $html;
                $html = preg_replace(
                    '/(suffers\s+)(\d+)(\s+splash damage)/iu',
                    '$1<span class="'.$damageClass.'">$2</span>$3',
                    $html,
                    1,
                ) ?? $html;
            }

            $html = preg_replace(
                '/(recovers\s+)(\d+)(\s+HP)/iu',
                '$1<span class="battle-log-heal">$2</span>$3',
                $html,
                1,
            ) ?? $html;

            return new \Illuminate\Support\HtmlString($html);
        };
        $shopItemValueParts = function (\App\Models\Item $item): array {
            $parts = [];

            if ((int) $item->attack_bonus > 0) {
                $parts[] = '+'.$item->attack_bonus.' '.__('ATK');
            }

            if ((int) $item->defense_bonus > 0) {
                $parts[] = '+'.$item->defense_bonus.' '.__('DEF');
            }

            if ((int) $item->health_bonus > 0) {
                $parts[] = '+'.$item->health_bonus.' '.__('HP');
            }

            if ((int) $item->sp_restore > 0) {
                $parts[] = '+'.$item->sp_restore.' '.__('SP');
            }

            if ((int) $item->heal_amount > 0) {
                $parts[] = '+'.$item->heal_amount.' '.__('HP');
            }

            if ((int) $item->stamina_bonus > 0) {
                $parts[] = '+'.$item->stamina_bonus.' '.__('Stamina');
            }

            if ((int) $item->speed_bonus > 0) {
                $parts[] = '+'.$item->speed_bonus.' '.__('SPD');
            }

            if ((int) $item->travel_speed_percent_bonus > 0) {
                $parts[] = '+'.$item->travel_speed_percent_bonus.'% '.__('Travel');
            }

            return $parts;
        };
        $shopItemValueText = function (\App\Models\Item $item) use ($shopItemValueParts): ?string {
            $parts = $shopItemValueParts($item);

            return $parts === [] ? null : implode(' · ', $parts);
        };
        $soulPrismStack = $character->inventoryItems
            ->first(fn ($inventoryItem) => $inventoryItem->item?->slug === 'soul-prism');
        $soulPrismCount = (int) ($soulPrismStack?->quantity ?? 0);
        $soulImprintStack = $character->inventoryItems
            ->first(fn ($inventoryItem) => $inventoryItem->item?->slug === 'soul-imprint');
        $soulImprintCount = (int) ($soulImprintStack?->quantity ?? 0);
        $soulReweaverStack = $character->inventoryItems
            ->first(fn ($inventoryItem) => $inventoryItem->item?->slug === 'soul-reweaver');
        $soulReweaverCount = (int) ($soulReweaverStack?->quantity ?? 0);
        $soulAnchorStack = $character->inventoryItems
            ->first(fn ($inventoryItem) => $inventoryItem->item?->slug === 'soul-anchor');
        $soulAnchorCount = (int) ($soulAnchorStack?->quantity ?? 0);
        $lumiisShardStack = $character->inventoryItems
            ->first(fn ($inventoryItem) => $inventoryItem->item?->slug === 'lumiis-shard');
        $lumiisShardCount = (int) ($lumiisShardStack?->quantity ?? 0);
        $researchCatalystStack = $character->inventoryItems
            ->first(fn ($inventoryItem) => $inventoryItem->item?->slug === 'research-catalyst');
        $researchCatalystCount = (int) ($researchCatalystStack?->quantity ?? 0);
        $researchReportStack = $character->inventoryItems
            ->first(fn ($inventoryItem) => $inventoryItem->item?->slug === 'research-report');
        $researchReportCount = (int) ($researchReportStack?->quantity ?? 0);
        $inventorySellVendorArea = $activeArea !== null
            && in_array((string) $activeArea->service_type, ['shop', 'weapon-shop', 'armor-shop'], true)
            ? $activeArea
            : null;
        $inventorySellEnabled = ! $tutorialActive
            && $character->location->is_safe_zone
            && $inventorySellVendorArea !== null;
        $activeBattleSkills = collect(data_get($activeBattlePlayer, 'skills', []));
        $activeBattleLogLines = collect(data_get($activeBattleState, 'battle_log', []));
        $activeBattleLogEntries = $buildBattleLogEntries($activeBattleLogLines, $activeBattleSteps);
        $activeBattleLastResolvedRound = max(1, ((int) data_get($activeBattleState, 'round', 1)) - 1);
        $activeBattleRecentEnemyDeathIds = collect(data_get($activeBattleState, 'battle_steps', []))
            ->filter(fn (array $step): bool => (int) data_get($step, 'round', 0) === $activeBattleLastResolvedRound
                && data_get($step, 'target_type') === 'enemy'
                && (int) data_get($step, 'remaining_health', 1) === 0)
            ->pluck('target_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $activeBattleRecentDamageSteps = collect(data_get($activeBattleState, 'battle_steps', []))
            ->filter(fn (array $step): bool => (int) data_get($step, 'round', 0) === $activeBattleLastResolvedRound
                && (int) data_get($step, 'damage', 0) > 0);
        $activeBattleRecentPlayerDamage = (int) $activeBattleRecentDamageSteps
            ->where('target_type', 'player')
            ->sum(fn (array $step): int => (int) data_get($step, 'damage', 0));
        $activeBattleRecentEnemyDamageById = $activeBattleRecentDamageSteps
            ->where('target_type', 'enemy')
            ->groupBy(fn (array $step): string => (string) data_get($step, 'target_id'))
            ->map(fn ($steps): int => (int) collect($steps)->sum(fn (array $step): int => (int) data_get($step, 'damage', 0)))
            ->all();
        $hasActiveBattle = $activeBattle !== null;
        $manualBattleResultPayload = data_get($manualBattleResultLog?->outcome, 'battle_replay');
        $latestBattleReplayPayload = in_array((string) $latestBattleLog?->action, ['battle-attack', 'battle-use-skill'], true)
            ? null
            : data_get($latestBattleLog?->outcome, 'battle_replay');
        $battleReplayPayload = data_get($battleReplayLog?->outcome, 'battle_replay')
            ?? $latestBattleReplayPayload;
        $autoOpenBattleReplay = is_array(data_get($battleReplayLog?->outcome, 'battle_replay'));
        $activeAreaShop = $activeArea?->shop;
        $mobileFocusWindowOpen = ! $tutorialActive && ($hasExplicitFocus ?? false) && $activeArea !== null;
        $smithableItems = $character->inventoryItems
            ->filter(fn ($inventoryItem) => $inventoryItem->canBeSmithUpgraded())
            ->sortBy(fn ($inventoryItem) => $inventoryItem->displayName())
            ->values();
        $storedInventoryItems = $character->storedInventoryItems();
        $bagItems = $storedInventoryItems
            ->sortBy(fn ($inventoryItem) => $inventoryItem->displayName())
            ->values();
        $inventoryQuantitiesBySlug = $bagItems
            ->groupBy(fn ($inventoryItem) => $inventoryItem->item?->slug)
            ->map(fn ($rows) => (int) $rows->sum('quantity'));
        $inventorySelectionDefaultId = $bagItems->first()?->id;
        $inventoryTotalQuantity = (int) $bagItems->sum('quantity');
        $inventoryEquippableCount = $bagItems
            ->filter(fn ($inventoryItem) => $inventoryItem->item?->isEquippable())
            ->count();
        $inventorySkillBookCount = (int) $bagItems
            ->filter(fn ($inventoryItem) => $inventoryItem->item?->isSkillBook())
            ->sum('quantity');
        $inventorySellableCount = $inventorySellEnabled
            ? $bagItems
                ->filter(fn ($inventoryItem) => ! $inventoryItem->isEquipped() && (int) data_get($inventoryItem->item, 'sell_price', 0) > 0)
                ->count()
            : 0;
        $equipmentBoardSlots = [
            'weapon' => ['label' => __('Weapon'), 'class' => 'equipment-slot-weapon', 'empty' => __('Blade slot')],
            'offhand' => ['label' => __('Offhand'), 'class' => 'equipment-slot-offhand', 'empty' => __('Shield slot')],
            'armor' => ['label' => __('Armor'), 'class' => 'equipment-slot-armor', 'empty' => __('Armor slot')],
            'boots' => ['label' => __('Boots'), 'class' => 'equipment-slot-boots', 'empty' => __('Boots slot')],
            'mount' => ['label' => __('Mount'), 'class' => 'equipment-slot-mount', 'empty' => __('Mount slot')],
        ];
        $tutorialWeaponEquipped = $equipmentBySlot->has('weapon');
        $tutorialArmorEquipped = $equipmentBySlot->has('armor');
        $tutorialStarterGearReady = $tutorialWeaponEquipped && $tutorialArmorEquipped;
        $equipmentInspectorDefaultSlot = collect(['weapon', 'armor', 'offhand', 'boots', 'mount'])
            ->first(fn (string $slot): bool => $equipmentBySlot->has($slot)) ?? 'armor';
        $effectiveMaxStamina = (int) $character->effectiveMaxStamina();
        $restPlans = collect([
            30 => ['label' => __('Short 30m'), 'stamina_cap' => 5, 'health_percent' => 40, 'yang_gain' => 50],
            60 => ['label' => __('Extended 60m'), 'stamina_cap' => 10, 'health_percent' => 70, 'yang_gain' => 110],
            120 => ['label' => __('Deep 120m'), 'stamina_cap' => $effectiveMaxStamina, 'health_percent' => 100, 'yang_gain' => 220],
        ])->map(function (array $plan, int $minutes) use ($availableStamina, $effectiveMaxStamina, $character, $displayHealth, $displaySp): array {
            $staminaGain = min(
                max(0, $effectiveMaxStamina - $availableStamina),
                $plan['stamina_cap'],
            );
            $healthGain = min(
                max(0, $character->effectiveMaxHealth() - $displayHealth),
                (int) floor($character->effectiveMaxHealth() * ($plan['health_percent'] / 100)),
            );
            $spGain = min(
                max(0, $character->effectiveMaxSp() - $displaySp),
                (int) floor($character->effectiveMaxSp() * ($plan['health_percent'] / 100)),
            );

            return [
                'minutes' => $minutes,
                'label' => $plan['label'],
                'stamina_gain' => $staminaGain,
                'health_gain' => $healthGain,
                'sp_gain' => $spGain,
                'yang_gain' => (int) ($plan['yang_gain'] ?? 0),
            ];
        });
        $dashboardCanOpenCharacter = ! $tutorialActive || $tutorialStep === 1;
        $dashboardCanOpenInventory = ! $tutorialActive || $tutorialStep === 6;
        $dashboardCanOpenChat = ! $tutorialActive && $globalChatUnlocked;
        $dashboardCanOpenSettings = ! $tutorialActive;
        $dashboardRaceLabel = $raceDefinition['label'] ?? \App\Support\Game\MetinClassCatalog::raceLabel($character->chosenRace());
        $dashboardSkillGroupLabel = $character->hasChosenSkillGroup()
            ? \App\Support\Game\MetinClassCatalog::skillGroupLabel($character->chosenRace(), (string) $character->skill_group)
            : null;
        $dashboardQuestSummary = match (true) {
            $tutorialActive => [
                'kicker' => __('Tutorial'),
                'title' => data_get($tutorialDefinition, 'title', __('Onboarding')),
                'meta' => __('Step :step/:total', ['step' => $tutorialStep, 'total' => $tutorialTotalSteps]),
                'body' => data_get($tutorialDefinition, 'body', __('Finish the onboarding sequence to unlock the full realm interface.')),
            ],
            $questline && data_get($questline, 'active.0') !== null => [
                'kicker' => __('Map Questline'),
                'title' => (string) data_get($questline, 'active.0.title', __('Active Quest')),
                'meta' => __(':current/:target progress', [
                    'current' => number_format((int) data_get($questline, 'active.0.progress', 0)),
                    'target' => number_format((int) data_get($questline, 'active.0.objective_target', 0)),
                ]),
                'body' => (string) data_get($questline, 'active.0.story_text', __('Keep the local questline moving to unlock the next systems.')),
            ],
            $questline && data_get($questline, 'upcoming.0') !== null => [
                'kicker' => __('Upcoming Quest'),
                'title' => (string) data_get($questline, 'upcoming.0.title', __('Next Objective')),
                'meta' => __('Opens at level :level', ['level' => (int) data_get($questline, 'upcoming.0.required_level', 1)]),
                'body' => __('Push your level and clean the current map to unlock the next chapter.'),
            ],
            default => [
                'kicker' => __('Questlog'),
                'title' => __('Realm Progress'),
                'meta' => __('No active map quest'),
                'body' => __('Use the map hotspots, improve your gear and keep the hunting loop moving.'),
            ],
        };
        $dashboardFocusFacts = match (true) {
            $activeArea?->isHunting() => [
                ['label' => __('Pack Size'), 'value' => (string) $activeAreaPackSize],
                ['label' => __('Enemy Lv'), 'value' => $activeAreaHighestMonsterLevel > 0 ? (string) $activeAreaHighestMonsterLevel : '--'],
                ['label' => __('Pressure'), 'value' => $activeAreaPressurePercent.'%'],
                ['label' => __('Boss Chance'), 'value' => $activeAreaBossReadiness !== null ? $activeAreaBossReadiness.'%' : '--'],
            ],
            $activeArea?->isService() => [
                ['label' => __('Service'), 'value' => __(\Illuminate\Support\Str::headline((string) ($activeArea->service_type ?? __('Town'))))],
                ['label' => __('Map'), 'value' => $character->location->name],
                ['label' => __('Travel'), 'value' => $travelRoutesUnlocked ? __('Open') : __('Locked')],
                ['label' => __('Status'), 'value' => __('Safe')],
            ],
            default => [
                ['label' => __('Hotspots'), 'value' => (string) $locationAreas->count()],
                ['label' => __('Town Services'), 'value' => (string) $serviceAreas->count()],
                ['label' => __('Hunting Grounds'), 'value' => (string) $huntingAreas->count()],
                ['label' => __('Focus'), 'value' => __('Map overview')],
            ],
        };
    @endphp

        <div
            x-data="dashboardShell({
                activePanel: @js($tutorialActive ? match ($tutorialStep) {
                    6 => 'inventory',
                    default => null,
                } : null),
                chatTab: 'global',
                tutorial: @js($tutorialDriverPayload),
                actionLockSeconds: {{ $character->cooldownSecondsRemaining() }},
                resting: @js($restingState),
                activeBattleRound: {{ $hasActiveBattle ? max(1, (int) data_get($activeBattleState, 'round', 1)) : 0 }},
            })"
        class="portal-stage dashboard-portal"
        data-tutorial-step="{{ $tutorialActive ? $tutorialStep : '' }}"
        @keydown.escape.window="closePanel()"
        @dashboard-panel-open.window="openPanel($event.detail?.panel ?? $event.detail)"
        @dashboard-chat-open.window="openChatTab($event.detail?.tab ?? 'global')"
    >
        <div class="metin2-container-wrapper dashboard-portal-frame">
            <div class="metin2-container dashboard-metin2-layout">
                <aside class="metin2-column-left dashboard-metin2-column dashboard-mobile-rail">
                    <div class="metin2-side-shell dashboard-side-shell dashboard-mobile-order-actions">
                        <div class="metin2-side-top"></div>
                        <div class="metin2-side-body">
                            <ul class="metin2-main-nav dashboard-side-nav">
                                <li>
                                    <a href="{{ route('dashboard') }}" class="metin2-main-nav-link is-active">
                                        {{ __('Realm') }}
                                    </a>
                                </li>
                                <li>
                                    <button
                                        type="button"
                                        @class([
                                            'metin2-main-nav-link',
                                            'dashboard-side-link',
                                            'dashboard-side-link-disabled' => ! $dashboardCanOpenCharacter,
                                        ])
                                        @click="openPanel('character')"
                                        @disabled(! $dashboardCanOpenCharacter)
                                    >
                                        {{ __('Character') }}
                                    </button>
                                </li>
                                <li>
                                    <button
                                        type="button"
                                        @class([
                                            'metin2-main-nav-link',
                                            'dashboard-side-link',
                                            'dashboard-side-link-disabled' => ! $dashboardCanOpenInventory,
                                        ])
                                        @click="openPanel('inventory')"
                                        @disabled(! $dashboardCanOpenInventory)
                                    >
                                        {{ __('Inventory') }}
                                    </button>
                                </li>
                                <li>
                                    <button
                                        type="button"
                                        @class([
                                            'metin2-main-nav-link',
                                            'dashboard-side-link',
                                            'dashboard-side-link-disabled' => ! $dashboardCanOpenSettings,
                                        ])
                                        @click="openPanel('settings')"
                                        @disabled(! $dashboardCanOpenSettings)
                                    >
                                        {{ __('Settings') }}
                                    </button>
                                </li>
                            </ul>

                            <div class="dashboard-side-utility-nav">
                                @if ($dashboardCanOpenChat)
                                    <button
                                        type="button"
                                        class="dashboard-side-utility-link"
                                        @click="openPanel('chat')"
                                    >
                                        {{ __('Chat') }}
                                    </button>
                                @endif

                                <a href="{{ route('profile.edit') }}" class="dashboard-side-utility-link">
                                    {{ __('Profile') }}
                                </a>

                                @if (auth()->user()?->is_admin)
                                    <a href="{{ route('admin.dashboard') }}" class="dashboard-side-utility-link">
                                        {{ __('Admin') }}
                                    </a>
                                @endif

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dashboard-side-utility-link">
                                        {{ __('Logout') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="metin2-side-bottom"></div>
                    </div>

                    <section class="metin2-module dashboard-side-module dashboard-mobile-order-character">
                        <div class="metin2-side-top"></div>
                        <div class="metin2-side-body">
                            <h3 class="metin2-module-title">{{ __('Character') }}</h3>
                            <div class="metin2-module-body dashboard-side-body">
                                <p class="dashboard-side-title">{{ $character->name }}</p>
                                <p class="dashboard-side-meta">
                                    {{ $dashboardRaceLabel }}
                                    @if ($dashboardSkillGroupLabel)
                                        · {{ $dashboardSkillGroupLabel }}
                                    @endif
                                </p>

                                <ul class="dashboard-side-stats">
                                    <li>
                                        <span>{{ __('Level') }}</span>
                                        <strong>{{ $character->level }}</strong>
                                    </li>
                                    <li>
                                        <span>{{ __('Map') }}</span>
                                        <strong>{{ $character->location->name }}</strong>
                                    </li>
                                    <li>
                                        <span>{{ __('Yang') }}</span>
                                        <strong>{{ number_format($character->yang) }}</strong>
                                    </li>
                                    <li>
                                        <span>{{ __('Stamina') }}</span>
                                        <strong>{{ number_format((int) $displayStamina) }}/{{ number_format($character->effectiveMaxStamina()) }}</strong>
                                    </li>
                                    <li>
                                        <span>{{ __('HP') }}</span>
                                        <strong>{{ number_format((int) $displayHealth) }}/{{ number_format($character->effectiveMaxHealth()) }}</strong>
                                    </li>
                                    <li>
                                        <span>{{ __('SP') }}</span>
                                        <strong>{{ number_format((int) $displaySp) }}/{{ number_format($character->effectiveMaxSp()) }}</strong>
                                    </li>
                                    <li>
                                        <span>{{ __('Lock') }}</span>
                                        <strong>{{ $character->cooldownSecondsRemaining() > 0 ? sprintf('%02d:%02d', intdiv($character->cooldownSecondsRemaining(), 60), $character->cooldownSecondsRemaining() % 60) : __('Ready') }}</strong>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="metin2-side-bottom"></div>
                    </section>

                    <section class="metin2-module dashboard-side-module dashboard-mobile-order-quest">
                        <div class="metin2-side-top"></div>
                        <div class="metin2-side-body">
                            <h3 class="metin2-module-title">{{ __('Questlog') }}</h3>
                            <div class="metin2-module-body dashboard-side-body">
                                <p class="dashboard-side-kicker">{{ $dashboardQuestSummary['kicker'] }}</p>
                                <p class="dashboard-side-title">{{ $dashboardQuestSummary['title'] }}</p>
                                <p class="dashboard-side-meta">{{ $dashboardQuestSummary['meta'] }}</p>
                                <p class="dashboard-side-copy">{{ \Illuminate\Support\Str::limit($dashboardQuestSummary['body'], 92) }}</p>
                            </div>
                        </div>
                        <div class="metin2-side-bottom"></div>
                    </section>
                </aside>

                <section class="metin2-column-center dashboard-metin2-center-column">
        @if (session('status'))
            <div class="theme-status-flash theme-status-success mb-6">
                {{ session('status') }}
            </div>
        @endif

        @if (session('quest_updates'))
            <div class="theme-status-flash theme-status-info mb-6">
                <div class="space-y-2">
                    <p class="text-[0.7rem] uppercase tracking-[0.24em]">{{ __('Quest Update') }}</p>

                    @foreach ((array) session('quest_updates') as $questUpdate)
                        <p>{{ $questUpdate }}</p>
                    @endforeach
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="theme-status-flash theme-status-danger mb-6">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="theme-status-flash theme-status-danger mb-6">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($tutorialActive)
            <article
                class="game-panel tutorial-guide-panel mb-6 {{ $tutorialHighlight ? 'tutorial-guide-panel-'.$tutorialHighlight : '' }}"
                x-show="!tutorialDriverActive || tutorial?.step === 6"
                x-cloak
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="max-w-3xl">
                        <p class="panel-kicker">{{ __('Tutorial Step') }} {{ $tutorialStep }}/{{ $tutorialTotalSteps }}</p>
                        <h2 class="mt-2 font-[Cinzel] text-3xl tracking-[0.12em] text-amber-50">
                            {{ data_get($tutorialDefinition, 'title', __('Tutorial')) }}
                        </h2>
                        <p class="mt-4 text-sm leading-7 text-stone-300">
                            {{ data_get($tutorialDefinition, 'body') }}
                        </p>
                    </div>

                    <span class="cozy-chip">{{ __('Locked onboarding') }}</span>
                </div>

                <div class="mt-5 flex flex-wrap gap-3">
                    @if ($tutorialCanGoBack)
                        <form id="tutorial-previous-form" method="POST" action="{{ route('game.actions.store', ['action' => 'tutorial-previous']) }}">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            @if ($activeFocus)
                                <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                            @endif

                            <button type="submit" class="action-button-muted">
                                {{ data_get($tutorialDefinition, 'previous_label', __('Back')) }}
                            </button>
                        </form>
                    @endif

                    @if ($tutorialStep === 6)
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="cozy-chip">{{ $tutorialWeaponEquipped ? __('Weapon Equipped') : __('Weapon Missing') }}</span>
                            <span class="cozy-chip">{{ $tutorialArmorEquipped ? __('Armor Equipped') : __('Armor Missing') }}</span>
                        </div>

                        <form id="tutorial-next-form" method="POST" action="{{ route('game.actions.store', ['action' => 'tutorial-next']) }}">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            @if ($activeFocus)
                                <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                            @endif

                            <button type="submit" class="action-button" @disabled(! $tutorialStarterGearReady)>
                                {{ data_get($tutorialDefinition, 'next_label', __('Continue')) }}
                            </button>
                        </form>
                    @elseif ($tutorialStep === 7 && $tutorialFightArea && ! $hasActiveBattle)
                        <form method="POST" action="{{ route('game.actions.store', ['action' => 'start-manual-fight']) }}">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            <input type="hidden" name="location_area_id" value="{{ $tutorialFightArea->id }}">
                            <input type="hidden" name="encounter_request" value="monster">
                            <input type="hidden" name="redirect_focus" value="{{ $tutorialFightArea->slug }}">

                            <button type="submit" class="action-button">
                                {{ __('Start Tutorial Fight') }}
                            </button>
                        </form>
                    @elseif (in_array($tutorialStep, [8, 9, 10], true) && $tutorialFightArea && ! $hasActiveBattle)
                        <form method="POST" action="{{ route('game.actions.store', ['action' => 'start-manual-fight']) }}">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            <input type="hidden" name="location_area_id" value="{{ $tutorialFightArea->id }}">
                            <input type="hidden" name="encounter_request" value="monster">
                            <input type="hidden" name="redirect_focus" value="{{ $tutorialFightArea->slug }}">

                            <button type="submit" class="action-button">
                                {{ __('Resume Tutorial Fight') }}
                            </button>
                        </form>
                    @elseif ($tutorialCanGoNext && in_array($tutorialStep, [1, 2, 3, 4, 5, 8, 11, 12], true))
                        <form id="tutorial-next-form" method="POST" action="{{ route('game.actions.store', ['action' => 'tutorial-next']) }}">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            @if ($activeFocus)
                                <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                            @endif

                            <button type="submit" class="action-button">
                                {{ data_get($tutorialDefinition, 'next_label', __('Continue')) }}
                            </button>
                        </form>
                    @endif
                </div>
            </article>
        @endif

        @if ($hasActiveBattle)
            <div class="battle-replay-overlay">
                <div class="battle-replay-backdrop"></div>

                <div id="tutorial-battle-window" class="battle-replay-window {{ $tutorialActive && $tutorialHighlight === 'battle' ? 'tutorial-focus-ring' : '' }}">
                    <div class="battle-replay-header">
                        <div>
                            <p class="panel-kicker">{{ __('Fight Window') }}</p>
                            <h2 class="battle-replay-title">{{ $activeBattle->encounter_name }}</h2>
                            <p class="battle-replay-subtitle">
                                {{ $activeBattle->locationArea?->name ?? $character->location->name }}
                                ·
                                {{ $activeBattle->encounter_type === 'lumiis' ? __('Lumiis') : __(\Illuminate\Support\Str::headline($activeBattle->encounter_type)) }}
                                ·
                                {{ __('Manual Fight') }}
                            </p>
                        </div>

                        <div class="battle-replay-actions">
                            <span class="cozy-chip">{{ __('Round') }} {{ data_get($activeBattleState, 'round', 1) }}</span>
                            <span class="cozy-chip">{{ ! $tutorialActive && $activeBattleSkills->isNotEmpty() ? __('Skills Ready') : __('Attack Only') }}</span>
                        </div>
                    </div>

                    <div class="battle-replay-grid">
                        <article class="battle-replay-card">
                            @if ($activeBattleRecentPlayerDamage > 0)
                                <span class="battle-damage-pop battle-damage-pop-player">
                                    {{ $activeBattleRecentPlayerDamage }}
                                </span>
                            @endif

                            <p class="battle-replay-card-kicker">{{ __('Player') }}</p>
                            <h3 class="battle-replay-card-title">{{ data_get($activeBattlePlayer, 'name', $character->name) }}</h3>

                            <div class="battle-health-block">
                                <div class="battle-health-row">
                                    <span>{{ __('HP') }}</span>
                                    <span>{{ data_get($activeBattlePlayer, 'current_health', $character->health) }}/{{ data_get($activeBattlePlayer, 'max_health', $character->effectiveMaxHealth()) }}</span>
                                </div>
                                <div class="battle-health-bar">
                                    <span
                                        class="battle-health-fill battle-health-fill-player"
                                        style="width: {{ max(0, min(100, (int) round(((int) data_get($activeBattlePlayer, 'current_health', $character->health) / max(1, (int) data_get($activeBattlePlayer, 'max_health', $character->effectiveMaxHealth()))) * 100))) }}%;"
                                    ></span>
                                </div>
                            </div>

                            <div class="battle-health-block mt-3">
                                <div class="battle-health-row">
                                    <span>{{ __('SP') }}</span>
                                    <span>{{ data_get($activeBattlePlayer, 'current_sp', $character->effectiveCurrentSp()) }}/{{ data_get($activeBattlePlayer, 'max_sp', $character->effectiveMaxSp()) }}</span>
                                </div>
                                <div class="battle-health-bar">
                                    <span
                                        class="battle-health-fill battle-health-fill-player"
                                        style="width: {{ max(0, min(100, (int) round(((int) data_get($activeBattlePlayer, 'current_sp', $character->effectiveCurrentSp()) / max(1, (int) data_get($activeBattlePlayer, 'max_sp', $character->effectiveMaxSp()))) * 100))) }}%;"
                                    ></span>
                                </div>
                            </div>

                            <div class="battle-stat-grid">
                                <div class="scene-stat-pill">
                                    <span class="scene-stat-label">{{ __('Attack') }}</span>
                                    <span class="scene-stat-value">{{ data_get($activeBattlePlayer, 'attack', $character->effectiveAttack()) }}</span>
                                </div>
                                <div class="scene-stat-pill">
                                    <span class="scene-stat-label">{{ __('Defense') }}</span>
                                    <span class="scene-stat-value">{{ data_get($activeBattlePlayer, 'defense', $character->effectiveDefense()) }}</span>
                                </div>
                                <div class="scene-stat-pill">
                                    <span class="scene-stat-label">{{ __('Guard') }}</span>
                                    <span class="scene-stat-value">{{ data_get($activeBattlePlayer, 'temporary_guard', 0) }}</span>
                                </div>
                                <div class="scene-stat-pill">
                                    <span class="scene-stat-label">{{ __('Crit') }}</span>
                                    <span class="scene-stat-value">{{ data_get($activeBattlePlayer, 'crit_chance', $character->criticalChancePercent()) }}%</span>
                                </div>
                                <div class="scene-stat-pill">
                                    <span class="scene-stat-label">{{ __('Evade') }}</span>
                                    <span class="scene-stat-value">{{ data_get($activeBattlePlayer, 'evade_chance', $character->evadeChancePercent()) }}%</span>
                                </div>
                            </div>

                            <div class="battle-equipment-list">
                                @foreach (data_get($activeBattlePlayer, 'equipment', []) as $equipmentLine)
                                    <span class="cozy-chip">{{ $equipmentLine }}</span>
                                @endforeach
                            </div>
                        </article>

                        <article class="battle-replay-stage">
                            <div class="battle-stage-topline">
                                <span class="cozy-chip">{{ __('Manual Turn') }}</span>
                            </div>

                            @if ($tutorialActive && in_array($tutorialStep, [8, 9, 10], true))
                                <div class="paper-note battle-stage-message">
                                    <p class="text-[0.65rem] uppercase tracking-[0.26em] text-amber-900/70">{{ __('Tutorial') }}</p>
                                    <p class="mt-3 text-base leading-7 text-stone-800">
                                        {{ data_get($tutorialDefinition, 'body') }}
                                    </p>
                                </div>
                            @endif

                            <div class="battle-step-log">
                                @foreach ($activeBattleLogEntries as $entry)
                                    <p class="battle-step-line">
                                        {!! $formatBattleLogLine($entry['line'], $entry['step'], (string) data_get($activeBattlePlayer, 'name', $character->name), $activeBattleEnemyNames) !!}
                                    </p>
                                @endforeach
                            </div>
                        </article>

                        <article class="battle-replay-card">
                            <p class="battle-replay-card-kicker">{{ __('Enemies') }}</p>

                            <div class="battle-enemy-list">
                                @foreach ($sortedActiveBattleEnemies as $enemy)
                                    @php
                                        $enemyIsAlive = (int) data_get($enemy, 'current_health', 0) > 0;
                                        $enemyMaxHealth = max(1, (int) data_get($enemy, 'max_health', 1));
                                        $enemyHealthPercent = max(0, min(100, (int) round(((int) data_get($enemy, 'current_health', 0) / $enemyMaxHealth) * 100)));
                                    @endphp

                                    <div class="paper-note battle-enemy-card {{ ! $enemyIsAlive ? 'battle-entity-dead' : '' }} {{ in_array(data_get($enemy, 'id'), $activeBattleRecentEnemyDeathIds, true) ? 'battle-entity-death' : '' }}">
                                        @if (($activeBattleRecentEnemyDamageById[(string) data_get($enemy, 'id')] ?? 0) > 0)
                                            <span class="battle-damage-pop battle-damage-pop-enemy">
                                                {{ $activeBattleRecentEnemyDamageById[(string) data_get($enemy, 'id')] }}
                                            </span>
                                        @endif

                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="font-[Cinzel] text-xl tracking-[0.08em] text-stone-900">{{ data_get($enemy, 'label') }}</p>
                                                <p class="mt-1 text-xs uppercase tracking-[0.22em] text-amber-900/70">
                                                    Lv {{ data_get($enemy, 'level') }} · {{ __(data_get($enemy, 'category')) }}
                                                </p>
                                            </div>
                                            <span class="cozy-chip">
                                                {{ $enemyIsAlive ? data_get($enemy, 'minimum_attack') . '-' . data_get($enemy, 'maximum_attack') : __('Fallen') }}
                                            </span>
                                        </div>

                                        <div class="battle-health-block mt-4">
                                            <div class="battle-health-row">
                                                <span>{{ __('HP') }}</span>
                                                <span>{{ data_get($enemy, 'current_health') }}/{{ data_get($enemy, 'max_health') }}</span>
                                            </div>
                                            <div class="battle-health-bar">
                                                <span class="battle-health-fill battle-health-fill-enemy" style="width: {{ $enemyHealthPercent }}%;"></span>
                                            </div>
                                        </div>

                                        <div class="mt-4 grid gap-2">
                                            <form method="POST" action="{{ route('game.actions.store', ['action' => 'battle-attack']) }}">
                                                @csrf
                                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                                <input type="hidden" name="battle_id" value="{{ $activeBattle->id }}">
                                                <input type="hidden" name="battle_round" value="{{ $activeBattle->round }}">
                                                <input type="hidden" name="enemy_id" value="{{ data_get($enemy, 'id') }}">
                                                @if ($activeFocus)
                                                    <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                                                @endif

                                                <button
                                                    type="submit"
                                                    class="action-button w-full"
                                                    @disabled(! $enemyIsAlive || ($tutorialActive && $tutorialStep === 8))
                                                    @if ($tutorialActive && $tutorialStep === 9 && $loop->first && $enemyIsAlive)
                                                        id="tutorial-attack-button"
                                                    @endif
                                                >
                                                    {{ __('Attack') }}
                                                </button>
                                            </form>

                                            @if (! $tutorialActive)
                                                @foreach ($activeBattleSkills as $skill)
                                                    <form method="POST" action="{{ route('game.actions.store', ['action' => 'battle-use-skill']) }}">
                                                        @csrf
                                                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                                        <input type="hidden" name="battle_id" value="{{ $activeBattle->id }}">
                                                        <input type="hidden" name="battle_round" value="{{ $activeBattle->round }}">
                                                        <input type="hidden" name="enemy_id" value="{{ data_get($enemy, 'id') }}">
                                                        <input type="hidden" name="skill_slug" value="{{ data_get($skill, 'slug') }}">
                                                        @if ($activeFocus)
                                                            <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                                                        @endif

                                                        <button
                                                            type="submit"
                                                            class="action-button-muted w-full"
                                                            @disabled(
                                                                ! $enemyIsAlive
                                                                || (int) data_get($activeBattlePlayer, 'current_sp', $character->effectiveCurrentSp()) < (int) data_get($skill, 'sp_cost', 0)
                                                                || (int) data_get($skill, 'cooldown_remaining', 0) > 0
                                                            )
                                                        >
                                                            {{ data_get($skill, 'name') }}
                                                            ·
                                                            {{ data_get($skill, 'sp_cost', 0) }} SP
                                                            @if ((int) data_get($skill, 'cooldown_remaining', 0) > 0)
                                                                · {{ __('CD :rounds', ['rounds' => data_get($skill, 'cooldown_remaining')]) }}
                                                            @endif
                                                        </button>
                                                    </form>
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        @elseif ($manualBattleResultPayload)
            <div
                x-data="battleResolution({
                    result: @js($manualBattleResultPayload),
                    autoOpen: true,
                    labels: {
                        victory: @js(__('Victory')),
                        retreat: @js(__('Retreat')),
                        defeat: @js(__('Defeat')),
                        fallen: @js(__('Fallen')),
                        encounterTypes: {
                            monster: @js(__('Monster')),
                            boss: @js(__('Boss')),
                            lumiis: @js(__('Lumiis')),
                        },
                        titles: {
                            bossSoulRises: @js(__('A soul rises into the sky.')),
                            fieldIsYours: @js(__('The field is yours.')),
                            drivenBack: @js(__('You were driven back.')),
                        },
                        categories: {
                            monster: @js(__('Monster')),
                            boss: @js(__('Boss')),
                            lumiisCore: @js(__('Lumiis Core')),
                            freedSoul: @js(__('Freed Soul')),
                            tutorialPack: @js(__('Tutorial Pack')),
                        },
                    },
                })"
                @keydown.escape.window="close()"
            >
                <div x-show="isOpen" x-cloak class="battle-replay-overlay">
                    <div class="battle-replay-backdrop" @click="close()"></div>

                    <div id="tutorial-result-window" class="battle-replay-window battle-resolution-window {{ $tutorialActive && $tutorialHighlight === 'result' ? 'tutorial-focus-ring' : '' }}">
                        <div class="battle-replay-header">
                            <div>
                                <p class="panel-kicker">{{ __('Fight Result') }}</p>
                                <h2 class="battle-replay-title" x-text="result.title"></h2>
                                <p class="battle-replay-subtitle">
                                    <span x-text="result.location"></span>
                                    ·
                                    <span x-text="translatedEncounterType(result.encounter_type)"></span>
                                    ·
                                    {{ __('Manual Fight') }}
                                </p>
                            </div>

                            <div class="battle-replay-actions">
                                <span class="cozy-chip" x-text="resultStatusLabel()"></span>
                                <button type="button" class="action-button-muted" @click="close()">
                                    {{ __('Continue') }}
                                </button>
                            </div>
                        </div>

                        <div
                            id="tutorial-result-banner"
                            class="battle-outcome-banner battle-outcome-banner-active"
                            :class="[outcomeClass(), result.victory && result.encounter_type === 'boss' ? 'battle-outcome-soul-release' : '']"
                        >
                            <span class="battle-outcome-badge" x-text="resultOutcomeBadge()"></span>
                            <h3 class="battle-outcome-title" x-text="resultOutcomeTitle()"></h3>
                            <p class="battle-outcome-copy" x-text="result.summary.message"></p>
                        </div>

                        <div class="battle-replay-grid">
                            <article class="battle-replay-card">
                                <p class="battle-replay-card-kicker">{{ __('Player') }}</p>
                                <h3 class="battle-replay-card-title" x-text="result.player.name"></h3>

                                <div class="battle-health-block">
                                    <div class="battle-health-row">
                                        <span>{{ __('HP') }}</span>
                                        <span x-text="`${playerCurrentHealth}/${result.player.max_health}`"></span>
                                    </div>
                                    <div class="battle-health-bar">
                                        <span class="battle-health-fill battle-health-fill-player" :style="`width: ${healthPercent(playerCurrentHealth, result.player.max_health)}%`"></span>
                                    </div>
                                </div>

                                <div class="battle-stat-grid">
                                    <div class="scene-stat-pill">
                                        <span class="scene-stat-label">{{ __('Level') }}</span>
                                        <span class="scene-stat-value" x-text="result.player.level"></span>
                                    </div>
                                    <div class="scene-stat-pill">
                                        <span class="scene-stat-label">{{ __('Attack') }}</span>
                                        <span class="scene-stat-value" x-text="result.player.attack"></span>
                                    </div>
                                    <div class="scene-stat-pill">
                                        <span class="scene-stat-label">{{ __('Defense') }}</span>
                                        <span class="scene-stat-value" x-text="result.player.defense"></span>
                                    </div>
                                </div>

                                <div class="battle-equipment-list">
                                    <template x-for="equipmentLine in result.player.equipment" :key="equipmentLine">
                                        <span class="cozy-chip" x-text="equipmentLine"></span>
                                    </template>
                                </div>
                            </article>

                            <article class="battle-replay-stage">
                                @if ($tutorialActive && in_array($tutorialStep, [9, 10], true))
                                    <div class="paper-note battle-stage-message">
                                        <p class="text-[0.65rem] uppercase tracking-[0.26em] text-amber-900/70">{{ __('Tutorial') }}</p>
                                        <p class="mt-3 text-base leading-7 text-stone-800">
                                            {{ data_get($tutorialDefinition, 'body') }}
                                        </p>
                                    </div>
                                @endif

                                <div class="paper-note battle-stage-summary">
                                    <p class="text-[0.65rem] uppercase tracking-[0.26em] text-amber-900/70">{{ __('Battle Summary') }}</p>
                                    <div class="battle-summary-grid mt-4">
                                        <div class="scene-stat-pill">
                                            <span class="scene-stat-label">{{ __('Rounds') }}</span>
                                            <span class="scene-stat-value" x-text="result.summary.rounds"></span>
                                        </div>
                                        <div class="scene-stat-pill">
                                            <span class="scene-stat-label">{{ __('EXP') }}</span>
                                            <span class="scene-stat-value" x-text="result.summary.experience"></span>
                                        </div>
                                        <div class="scene-stat-pill">
                                            <span class="scene-stat-label">{{ __('Yang') }}</span>
                                            <span class="scene-stat-value" x-text="result.summary.yang"></span>
                                        </div>
                                        <div class="scene-stat-pill">
                                            <span class="scene-stat-label">{{ __('HP Lost') }}</span>
                                            <span class="scene-stat-value" x-text="result.summary.health_lost"></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="paper-note mt-4">
                                    <p class="text-[0.65rem] uppercase tracking-[0.26em] text-amber-900/70">{{ __('Loot') }}</p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <template x-if="(result.summary.loot ?? []).length === 0">
                                            <span class="cozy-chip">{{ __('No item drop') }}</span>
                                        </template>
                                        <template x-for="lootLine in result.summary.loot" :key="lootLine">
                                            <span class="cozy-chip" x-text="lootLine"></span>
                                        </template>
                                    </div>
                                </div>
                            </article>

                            <article class="battle-replay-card">
                                <p class="battle-replay-card-kicker">{{ __('Enemies') }}</p>

                                <div class="battle-enemy-list">
                                    <template x-for="enemy in enemies" :key="enemy.id">
                                        <div class="paper-note battle-enemy-card" :class="{ 'battle-entity-dead battle-entity-death': enemy.is_dead }">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <p class="font-[Cinzel] text-xl tracking-[0.08em] text-stone-900" x-text="enemy.label"></p>
                                                    <p class="mt-1 text-xs uppercase tracking-[0.22em] text-amber-900/70">
                                                        Lv <span x-text="enemy.level"></span> · <span x-text="translatedCategory(enemy.category)"></span>
                                                    </p>
                                                </div>
                                                <span class="cozy-chip" x-text="enemyAttackLabel(enemy)"></span>
                                            </div>

                                            <div class="battle-health-block mt-4">
                                                <div class="battle-health-row">
                                                    <span>{{ __('HP') }}</span>
                                                    <span x-text="`${enemy.current_health}/${enemy.max_health}`"></span>
                                                </div>
                                                <div class="battle-health-bar">
                                                    <span class="battle-health-fill battle-health-fill-enemy" :style="`width: ${healthPercent(enemy.current_health, enemy.max_health)}%`"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </div>
        @elseif ($battleReplayPayload)
            <div
                x-data="battleReplay({
                    replay: @js($battleReplayPayload),
                    autoOpen: @js($autoOpenBattleReplay),
                        labels: {
                        replayReady: @js(__('Battle replay ready.')),
                        victory: @js(__('Victory')),
                        defeat: @js(__('Defeat')),
                        retreat: @js(__('Retreat')),
                        playing: @js(__('Playing')),
                        finished: @js(__('Finished')),
                        ready: @js(__('Ready')),
                        battleWon: @js(__('Battle Won')),
                        battleLost: @js(__('Battle Lost')),
                        fallen: @js(__('Fallen')),
                        encounterTypes: {
                            monster: @js(__('Monster')),
                            boss: @js(__('Boss')),
                            lumiis: @js(__('Lumiis')),
                        },
                        categories: {
                            monster: @js(__('Monster')),
                            boss: @js(__('Boss')),
                            lumiisCore: @js(__('Lumiis Core')),
                            freedSoul: @js(__('Freed Soul')),
                            tutorialPack: @js(__('Tutorial Pack')),
                        },
                    },
                })"
                @open-battle-replay.window="open()"
                @keydown.escape.window="close()"
            >
                <div x-show="isOpen" x-cloak class="battle-replay-overlay">
                    <div class="battle-replay-backdrop" @click="close()"></div>

                    <div class="battle-replay-window">
                        <div class="battle-replay-header">
                            <div>
                                <p class="panel-kicker">{{ __('Fight Window') }}</p>
                                <h2 class="battle-replay-title" x-text="replay.title"></h2>
                                <p class="battle-replay-subtitle">
                                    <span x-text="replay.location"></span>
                                    ·
                                    <span x-text="translatedEncounterType(replay.encounter_type)"></span>
                                </p>
                            </div>

                            <div class="battle-replay-actions">
                                <span class="cozy-chip" x-text="replayStatusLabel()"></span>
                                <button type="button" class="action-button-muted" @click="restart()">
                                    {{ __('Replay') }}
                                </button>
                                <button type="button" class="action-button-muted" @click="close()">
                                    {{ __('Close') }}
                                </button>
                            </div>
                        </div>

                        <div class="battle-replay-grid">
                            <article class="battle-replay-card">
                                <template x-if="playerDamageEvent">
                                    <span
                                        class="battle-damage-pop battle-damage-pop-player"
                                        :key="playerDamageEvent.key"
                                        x-text="playerDamageEvent.value"
                                    ></span>
                                </template>

                                <p class="battle-replay-card-kicker">{{ __('Player') }}</p>
                                <h3 class="battle-replay-card-title" x-text="replay.player.name"></h3>

                                <div class="battle-health-block">
                                    <div class="battle-health-row">
                                        <span>{{ __('HP') }}</span>
                                        <span x-text="`${playerCurrentHealth}/${replay.player.max_health}`"></span>
                                    </div>
                                    <div class="battle-health-bar">
                                        <span class="battle-health-fill battle-health-fill-player" :style="`width: ${healthPercent(playerCurrentHealth, replay.player.max_health)}%`"></span>
                                    </div>
                                </div>

                                <div class="battle-stat-grid">
                                    <div class="scene-stat-pill">
                                        <span class="scene-stat-label">{{ __('Level') }}</span>
                                        <span class="scene-stat-value" x-text="replay.player.level"></span>
                                    </div>
                                    <div class="scene-stat-pill">
                                        <span class="scene-stat-label">{{ __('Attack') }}</span>
                                        <span class="scene-stat-value" x-text="replay.player.attack"></span>
                                    </div>
                                    <div class="scene-stat-pill">
                                        <span class="scene-stat-label">{{ __('Defense') }}</span>
                                        <span class="scene-stat-value" x-text="replay.player.defense"></span>
                                    </div>
                                </div>

                                <div class="battle-equipment-list">
                                    <template x-for="equipmentLine in replay.player.equipment" :key="equipmentLine">
                                        <span class="cozy-chip" x-text="equipmentLine"></span>
                                    </template>
                                </div>
                            </article>

                            <article class="battle-replay-stage">
                                <div class="battle-stage-topline">
                                    <span class="cozy-chip">{{ __('Round') }} <span x-text="currentRound"></span></span>
                                    <span class="cozy-chip" x-text="isFinished ? '{{ __('Finished') }}' : (isPlaying ? '{{ __('Playing') }}' : '{{ __('Ready') }}')"></span>
                                </div>

                                <div x-show="isFinished" x-cloak class="battle-outcome-banner battle-outcome-banner-active" :class="outcomeClass()">
                                    <span class="battle-outcome-badge" x-text="outcomeBadgeLabel()"></span>
                                    <h3 class="battle-outcome-title" x-text="outcomeTitleLabel()"></h3>
                                    <p class="battle-outcome-copy" x-text="replay.summary.message"></p>
                                </div>

                                <div class="paper-note battle-stage-message">
                                    <p class="text-[0.65rem] uppercase tracking-[0.26em] text-amber-900/70">{{ __('Current Action') }}</p>
                                    <p class="mt-3 text-base leading-7 text-stone-800" x-html="formattedCurrentMessage()"></p>
                                </div>

                                <div x-show="isFinished" x-cloak class="paper-note battle-stage-summary">
                                    <p class="text-[0.65rem] uppercase tracking-[0.26em] text-amber-900/70">{{ __('Battle Summary') }}</p>
                                    <div class="battle-summary-grid mt-4">
                                        <div class="scene-stat-pill">
                                            <span class="scene-stat-label">{{ __('Rounds') }}</span>
                                            <span class="scene-stat-value" x-text="replay.summary.rounds"></span>
                                        </div>
                                        <div class="scene-stat-pill">
                                            <span class="scene-stat-label">{{ __('EXP') }}</span>
                                            <span class="scene-stat-value" x-text="replay.summary.experience"></span>
                                        </div>
                                        <div class="scene-stat-pill">
                                            <span class="scene-stat-label">{{ __('Yang') }}</span>
                                            <span class="scene-stat-value" x-text="replay.summary.yang"></span>
                                        </div>
                                        <div class="scene-stat-pill">
                                            <span class="scene-stat-label">{{ __('HP Lost') }}</span>
                                            <span class="scene-stat-value" x-text="replay.summary.health_lost"></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="battle-step-log" x-ref="log">
                                    <template x-for="(step, index) in displayedSteps" :key="`${step.round}-${index}-${step.message}`">
                                        <p class="battle-step-line" x-html="formattedStepMessage(step)"></p>
                                    </template>
                                </div>
                            </article>

                            <article class="battle-replay-card">
                                <p class="battle-replay-card-kicker">{{ __('Enemies') }}</p>

                                <div class="battle-enemy-list">
                                    <template x-for="enemy in enemies" :key="enemy.id">
                                        <div
                                            x-show="enemy.spawned"
                                            class="paper-note battle-enemy-card"
                                            :class="{ 'battle-entity-dead battle-entity-death': enemy.current_health <= 0 }"
                                        >
                                            <template x-if="enemyDamageEvent(enemy.id)">
                                                <span
                                                    class="battle-damage-pop battle-damage-pop-enemy"
                                                    :key="enemyDamageEvent(enemy.id).key"
                                                    x-text="enemyDamageEvent(enemy.id).value"
                                                ></span>
                                            </template>

                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <p class="font-[Cinzel] text-xl tracking-[0.08em] text-stone-900" x-text="enemy.label"></p>
                                                    <p class="mt-1 text-xs uppercase tracking-[0.22em] text-amber-900/70">
                                                        Lv <span x-text="enemy.level"></span> · <span x-text="translatedCategory(enemy.category)"></span>
                                                    </p>
                                                </div>
                                                <span class="cozy-chip" x-text="enemyAttackLabel(enemy)"></span>
                                            </div>

                                            <div class="battle-health-block mt-4">
                                                <div class="battle-health-row">
                                                    <span>{{ __('HP') }}</span>
                                                    <span x-text="`${enemy.current_health}/${enemy.max_health}`"></span>
                                                </div>
                                                <div class="battle-health-bar">
                                                    <span class="battle-health-fill battle-health-fill-enemy" :style="`width: ${healthPercent(enemy.current_health, enemy.max_health)}%`"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <div x-show="isFinished" x-cloak class="paper-note mt-4 battle-stage-loot">
                                    <p class="text-[0.65rem] uppercase tracking-[0.26em] text-amber-900/70">{{ __('Loot') }}</p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <template x-if="(replay.summary.loot ?? []).length === 0">
                                            <span class="cozy-chip">{{ __('No item drop') }}</span>
                                        </template>
                                        <template x-for="lootLine in replay.summary.loot" :key="lootLine">
                                            <span class="cozy-chip" x-text="lootLine"></span>
                                        </template>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <section id="dashboard-settings-overlay-legacy" class="dashboard-overlay-panel dashboard-overlay-panel-legacy" x-show="false" x-cloak>
            <div class="dashboard-overlay-backdrop" @click="closePanel()"></div>

            <div class="dashboard-overlay-window">
                <div class="dashboard-overlay-head">
                    <div>
                        <p class="panel-kicker">{{ __('Settings') }}</p>
                        <h2 class="dashboard-overlay-title">{{ __('Language and Interface') }}</h2>
                    </div>

                    <button type="button" class="dashboard-overlay-close" @click="closePanel()">
                        {{ __('Close') }}
                    </button>
                </div>

                <div class="dashboard-overlay-scroll">
                    <article class="game-panel">
                        <h3 class="font-[Cinzel] text-2xl tracking-[0.12em] text-amber-50">
                            {{ __('Choose Your Language') }}
                        </h3>

                        <div class="mt-6 grid gap-4 sm:grid-cols-2">
                            <form method="POST" action="{{ route('profile.locale.update') }}" class="paper-note text-center">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="locale_preference" value="de">
                                <p class="text-[0.65rem] uppercase tracking-[0.28em] text-amber-900/70">{{ __('German') }}</p>
                                <p class="mt-3 text-3xl">🇩🇪</p>
                                <p class="mt-3 font-[Cinzel] text-xl tracking-[0.08em] text-stone-900">{{ __('Deutsch') }}</p>
                                <p class="mt-2 text-sm leading-6 text-stone-700">
                                    {{ __('Full German interface for story, menus and action feedback.') }}
                                </p>
                                <button type="submit" class="action-button mt-5 w-full" @disabled($currentLocalePreference === 'de')>
                                    {{ $currentLocalePreference === 'de' ? __('Active') : __('Use German') }}
                                </button>
                            </form>

                            <form method="POST" action="{{ route('profile.locale.update') }}" class="paper-note text-center">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="locale_preference" value="en">
                                <p class="text-[0.65rem] uppercase tracking-[0.28em] text-amber-900/70">{{ __('English') }}</p>
                                <p class="mt-3 text-3xl">🇬🇧</p>
                                <p class="mt-3 font-[Cinzel] text-xl tracking-[0.08em] text-stone-900">{{ __('English') }}</p>
                                <p class="mt-2 text-sm leading-6 text-stone-700">
                                    {{ __('Full English interface for story, menus and action feedback.') }}
                                </p>
                                <button type="submit" class="action-button mt-5 w-full" @disabled($currentLocalePreference === 'en')>
                                    {{ $currentLocalePreference === 'en' ? __('Active') : __('Use English') }}
                                </button>
                            </form>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section id="dashboard-mobile-info-overlay" class="dashboard-overlay-panel" x-show="activePanel === 'mobile-info'" x-cloak>
            <div class="dashboard-overlay-backdrop" @click="closePanel()"></div>

            <div class="dashboard-overlay-window dashboard-mobile-info-window">
                <div class="dashboard-overlay-head">
                    <div>
                        <p class="panel-kicker">{{ __('Map Intel') }}</p>
                        <h2 class="dashboard-overlay-title">{{ __('Realm Info') }}</h2>
                    </div>

                    <button type="button" class="dashboard-overlay-close" @click="closePanel()">
                        {{ __('Close') }}
                    </button>
                </div>

                <div class="dashboard-overlay-scroll">
                    <div class="dashboard-mobile-info-grid">
                        <article class="game-panel">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="panel-kicker">{{ __('Focus') }}</p>
                                    <h3 class="mt-2 font-[Cinzel] text-2xl tracking-[0.12em] text-amber-50">
                                        {{ $activeArea?->name ?? $character->location->name }}
                                    </h3>
                                </div>
                                <span class="cozy-chip">
                                    {{ $activeArea?->isService() ? __('Town Hotspot') : ($activeArea?->isHunting() ? __('Hunting Ground') : __('Map Overview')) }}
                                </span>
                            </div>

                            <div class="character-stats-grid mt-5">
                                @foreach ($dashboardFocusFacts as $focusFact)
                                    <div class="stat-card">
                                        <span class="stat-label">{{ $focusFact['label'] }}</span>
                                        <span class="stat-value stat-value-compact">{{ $focusFact['value'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </article>

                        <article class="game-panel">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="panel-kicker">{{ __('Hotspots') }}</p>
                                    <h3 class="mt-2 font-[Cinzel] text-2xl tracking-[0.12em] text-amber-50">
                                        {{ __('Fast Travel Focus') }}
                                    </h3>
                                </div>
                                <span class="cozy-chip">{{ number_format($locationAreas->count()) }} {{ __('Areas') }}</span>
                            </div>

                            <ol class="dashboard-side-ranking mt-5">
                                @foreach ($locationAreas->take(8) as $area)
                                    <li @class(['is-light' => $loop->even, 'is-active' => $activeArea?->is($area)])>
                                        <strong>{{ $loop->iteration }}</strong>
                                        <a href="{{ route('dashboard', ['focus' => $area->slug]) }}" @click="closePanel()">{{ $area->name }}</a>
                                    </li>
                                @endforeach
                            </ol>
                        </article>

                        <article class="game-panel">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="panel-kicker">{{ __('Materials') }}</p>
                                    <h3 class="mt-2 font-[Cinzel] text-2xl tracking-[0.12em] text-amber-50">
                                        {{ __('Current Resources') }}
                                    </h3>
                                </div>
                                <span class="cozy-chip">{{ __('Bag Ready') }}</span>
                            </div>

                            <div class="character-stats-grid mt-5">
                                <div class="stat-card">
                                    <span class="stat-label">{{ __('Yang') }}</span>
                                    <span class="stat-value">{{ number_format($character->yang) }}</span>
                                </div>
                                <div class="stat-card">
                                    <span class="stat-label">{{ __('Shards') }}</span>
                                    <span class="stat-value">{{ number_format($lumiisShardCount) }}</span>
                                </div>
                                <div class="stat-card">
                                    <span class="stat-label">{{ __('Soul Prism') }}</span>
                                    <span class="stat-value">{{ number_format($soulPrismCount) }}</span>
                                </div>
                                <div class="stat-card">
                                    <span class="stat-label">{{ __('Bag Items') }}</span>
                                    <span class="stat-value">{{ number_format($bagItems->count()) }}</span>
                                </div>
                            </div>

                            <div class="mt-5 flex flex-wrap gap-3">
                                <button
                                    type="button"
                                    class="action-button"
                                    @click="openPanel('inventory')"
                                    @disabled(! $dashboardCanOpenInventory)
                                >
                                    {{ __('Open Inventory') }}
                                </button>

                                <button
                                    type="button"
                                    class="action-button-muted"
                                    @click="openPanel('character')"
                                    @disabled(! $dashboardCanOpenCharacter)
                                >
                                    {{ __('Open Character') }}
                                </button>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section id="dashboard-character-overlay-legacy" class="dashboard-overlay-panel dashboard-overlay-panel-legacy" x-show="false" x-cloak>
            <div class="dashboard-overlay-backdrop" @click="if (!tutorial?.active) closePanel()"></div>

            <div class="dashboard-overlay-window">
            <div class="dashboard-overlay-head">
                <div>
                    <p class="panel-kicker">{{ __('Character') }}</p>
                    <h2 class="dashboard-overlay-title">{{ __('Character') }}</h2>
                </div>

                <button type="button" class="dashboard-overlay-close" @click="closePanel()" x-show="!tutorial?.active" x-cloak>
                    {{ __('Close') }}
                </button>
            </div>

            <div class="dashboard-overlay-scroll">
                <article id="tutorial-character-panel" class="game-panel {{ $tutorialActive && $tutorialHighlight === 'character' ? 'tutorial-focus-ring' : '' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="panel-kicker">{{ __('Character Area') }}</p>
                            <h2 class="mt-2 font-[Cinzel] text-2xl tracking-[0.12em] text-amber-50">
                                {{ $character->name }}
                            </h2>
                        </div>
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <span class="cozy-chip">{{ $raceDefinition['label'] }}</span>
                            @if ($selectedSkillGroup)
                                <span class="cozy-chip">{{ $selectedSkillGroup['label'] }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="character-stats-grid mt-5">
                        <div class="stat-card">
                            <span class="stat-label">{{ __('Level') }}</span>
                            <span class="stat-value">{{ $character->level }}</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-label">{{ __('EXP') }}</span>
                            <span class="stat-value stat-value-compact">{{ number_format($character->experience) }}/{{ number_format($character->nextLevelExperience()) }}</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-label">{{ __('Yang') }}</span>
                            <span class="stat-value">{{ number_format($character->yang) }}</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-label">{{ __('Skill Points') }}</span>
                            <span class="stat-value">{{ $character->unspent_skill_points }}</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-label">{{ __('Health') }}</span>
                            <span class="stat-value stat-value-compact">{{ number_format((int) $displayHealth) }}/{{ number_format($character->effectiveMaxHealth()) }}</span>
                        </div>
                        <div
                            class="stat-card"
                            x-data="staminaPreview({
                                current: {{ (int) $displayStamina }},
                                maximum: {{ (int) $character->effectiveMaxStamina() }},
                                nextTickSeconds: {{ $nextStaminaTickSeconds === null ? 'null' : (int) $nextStaminaTickSeconds }},
                                labels: {
                                    fullyRecovered: @js(__('Fully recovered')),
                                    nextPointIn: @js(__('Next point in')),
                                    minutesShort: @js(__('min')),
                                    fullShort: @js(__('Full')),
                                },
                            })"
                        >
                            <span class="stat-label">{{ __('Stamina') }}</span>
                            <span class="stat-value stat-value-compact">
                                <span x-text="current.toLocaleString()">{{ number_format($displayStamina) }}</span>/{{ number_format($character->effectiveMaxStamina()) }}
                            </span>
                            <span class="stat-note" x-text="formattedNextTick()">
                                {{ $nextStaminaTickLabel === __('Full') ? __('Fully recovered') : __('Next point in').' '.$nextStaminaTickLabel }}
                            </span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-label">{{ __('SP') }}</span>
                            <span class="stat-value stat-value-compact">{{ number_format((int) $displaySp) }}/{{ number_format($character->effectiveMaxSp()) }}</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-label">{{ __('Attack') }}</span>
                            <span class="stat-value">{{ $character->effectiveAttack() }}</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-label">{{ __('Defense') }}</span>
                            <span class="stat-value">{{ $character->effectiveDefense() }}</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-label">{{ __('Speed') }}</span>
                            <span class="stat-value">{{ $character->effectiveSpeed() }}</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-label">VIT</span>
                            <span class="stat-value">{{ $character->effectiveVitality() }}</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-label">INT</span>
                            <span class="stat-value">{{ $character->effectiveIntelligence() }}</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-label">STR</span>
                            <span class="stat-value">{{ $character->effectiveStrength() }}</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-label">DEX</span>
                            <span class="stat-value">{{ $character->effectiveDexterity() }}</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-label">{{ __('Crit') }}</span>
                            <span class="stat-value">{{ $character->criticalChancePercent() }}%</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-label">{{ __('Evade') }}</span>
                            <span class="stat-value">{{ $character->evadeChancePercent() }}%</span>
                        </div>
                    </div>
                </article>

                @if ($questline)
                    <article class="game-panel">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="panel-kicker">{{ __('Map Questline') }}</p>
                                <h2 class="mt-2 font-[Cinzel] text-2xl tracking-[0.12em] text-amber-50">
                                    {{ $questline['title'] }}
                                </h2>
                            </div>
                            <span class="cozy-chip">{{ count($questline['completed']) }} {{ __('complete') }}</span>
                        </div>

                        @if ($questline['active'] !== [])
                            <div class="mt-4 space-y-3">
                                @foreach ($questline['active'] as $quest)
                                    <div class="paper-note">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <p class="font-[Cinzel] text-lg tracking-[0.06em] text-stone-900">{{ $quest['title'] }}</p>
                                            <span class="cozy-chip">{{ number_format($quest['progress']) }}/{{ number_format($quest['objective_target']) }}</span>
                                        </div>
                                        <p class="mt-2 text-sm leading-6 text-stone-700">{{ $quest['story_text'] }}</p>
                                        <div class="pressure-meter mt-3 h-2">
                                            <span class="pressure-fill" style="width: {{ $quest['progress_percent'] }}%;"></span>
                                        </div>
                                        @if ($quest['feature_unlock_hint'])
                                            <p class="mt-3 text-[0.68rem] uppercase tracking-[0.22em] text-amber-900/70">
                                                {{ $quest['feature_unlock_hint'] }}
                                            </p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @elseif ($questline['upcoming'] !== [])
                            <div class="paper-note mt-4 text-sm leading-6">
                                {{ __('Next quest: :title at level :level.', ['title' => $questline['upcoming'][0]['title'], 'level' => $questline['upcoming'][0]['required_level']]) }}
                            </div>
                        @else
                            <div class="paper-note mt-4 text-sm leading-6">
                                {{ __('This map questline is currently complete.') }}
                            </div>
                        @endif
                    </article>
                @endif

                @include('game.partials.skill-panel')

            </div>
            </div>
        </section>

        <section id="tutorial-inventory-overlay-legacy" class="dashboard-overlay-panel dashboard-overlay-panel-inventory dashboard-overlay-panel-legacy" x-show="false" x-cloak>
            <div class="dashboard-overlay-backdrop" @click="if (!tutorial?.active) closePanel()"></div>

            <div class="dashboard-overlay-window dashboard-overlay-window-inventory">
            <div class="dashboard-overlay-head dashboard-overlay-head-inventory">
                <div>
                    <p class="panel-kicker">{{ __('Inventory Control') }}</p>
                    <h2 class="dashboard-overlay-title">{{ __('Inventory') }}</h2>
                    <p class="dashboard-overlay-subtitle">
                        {{ __('Manage equipment, consumables and sell-ready drops from one contained workspace.') }}
                    </p>
                </div>

                <div class="dashboard-overlay-head-actions">
                    <span class="dashboard-overlay-pill">
                        {{ number_format($bagItems->count()) }} {{ __('Stacks') }}
                    </span>

                    <button type="button" class="dashboard-overlay-close" @click="closePanel()" x-show="!tutorial?.active" x-cloak>
                        {{ __('Close') }}
                    </button>
                </div>
            </div>

            <div class="dashboard-overlay-scroll dashboard-overlay-scroll-inventory">
                @if ($tutorialActive && $tutorialStep === 6)
                    <article id="tutorial-inventory-panel" class="game-panel inventory-modern-panel tutorial-focus-ring">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                            <p class="panel-kicker">{{ __('Tutorial Loadout') }}</p>
                                <h2 class="mt-2 font-[Cinzel] text-2xl tracking-[0.12em] text-amber-50">
                                    {{ __('Equip Weapon and Armor') }}
                                </h2>
                            </div>
                            <span class="cozy-chip">{{ $tutorialStarterGearReady ? __('Ready') : __('Not Ready') }}</span>
                        </div>

                        <div class="mt-4 space-y-3">
                            <div class="paper-note">
                                <p class="font-[Cinzel] text-lg tracking-[0.06em] text-stone-900">{{ __('Required before the first fight') }}</p>
                                <p class="mt-2 text-sm leading-6 text-stone-700">
                                    {{ __('Equip your Recruit Blade and Cotton Armor from the inventory below. Once both are equipped, the tutorial can continue to the first fight.') }}
                                </p>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="paper-note">
                                    <p class="text-[0.62rem] uppercase tracking-[0.22em] text-amber-900/70">{{ __('Weapon Slot') }}</p>
                                    <p class="mt-2 font-[Cinzel] text-lg tracking-[0.06em] text-stone-900">
                                        {{ $tutorialWeaponEquipped ? __('Equipped') : __('Missing') }}
                                    </p>
                                </div>
                                <div class="paper-note">
                                    <p class="text-[0.62rem] uppercase tracking-[0.22em] text-amber-900/70">{{ __('Armor Slot') }}</p>
                                    <p class="mt-2 font-[Cinzel] text-lg tracking-[0.06em] text-stone-900">
                                        {{ $tutorialArmorEquipped ? __('Equipped') : __('Missing') }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-3">
                                @if ($tutorialCanGoBack)
                                    <form method="POST" action="{{ route('game.actions.store', ['action' => 'tutorial-previous']) }}">
                                        @csrf
                                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                        @if ($activeFocus)
                                            <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                                        @endif

                                        <button type="submit" class="action-button-muted">
                                            {{ data_get($tutorialDefinition, 'previous_label', __('Back')) }}
                                        </button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('game.actions.store', ['action' => 'tutorial-next']) }}">
                                    @csrf
                                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                    @if ($activeFocus)
                                        <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                                    @endif

                                    <button type="submit" class="action-button" @disabled(! $tutorialStarterGearReady)>
                                        {{ data_get($tutorialDefinition, 'next_label', __('Continue')) }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                @endif

                <div
                    class="inventory-workspace"
                    x-data="{
                        equipmentInspector: @js($equipmentInspectorDefaultSlot),
                        inventorySelection: @js($inventorySelectionDefaultId),
                    }"
                >
                    <div class="inventory-overview-grid">
                        <article class="inventory-summary-card">
                            <span class="inventory-summary-label">{{ __('Stored stacks') }}</span>
                            <strong class="inventory-summary-value">{{ number_format($bagItems->count()) }}</strong>
                            <p class="inventory-summary-copy">{{ __('Distinct bag entries currently available.') }}</p>
                        </article>

                        <article class="inventory-summary-card">
                            <span class="inventory-summary-label">{{ __('Total pieces') }}</span>
                            <strong class="inventory-summary-value">{{ number_format($inventoryTotalQuantity) }}</strong>
                            <p class="inventory-summary-copy">{{ __('Combined quantity across your current bag.') }}</p>
                        </article>

                        <article class="inventory-summary-card">
                            <span class="inventory-summary-label">{{ __('Gear pieces') }}</span>
                            <strong class="inventory-summary-value">{{ number_format($inventoryEquippableCount) }}</strong>
                            <p class="inventory-summary-copy">{{ __('Weapons, armor and other equip-ready drops.') }}</p>
                        </article>

                        <article class="inventory-summary-card">
                            <span class="inventory-summary-label">
                                {{ $inventorySellEnabled ? __('Sell ready') : __('Skill books') }}
                            </span>
                            <strong class="inventory-summary-value">
                                {{ number_format($inventorySellEnabled ? $inventorySellableCount : $inventorySkillBookCount) }}
                            </strong>
                            <p class="inventory-summary-copy">
                                @if ($inventorySellEnabled)
                                    {{ __('Town vendor access is active for :vendor.', ['vendor' => $inventorySellVendorArea->name]) }}
                                @else
                                    {{ __('Skill books stay in the bag until you are ready to study.') }}
                                @endif
                            </p>
                        </article>
                    </div>

                    <div class="inventory-modal-layout">
                        <div class="inventory-modal-sidebar">
                            <article id="tutorial-equipment-panel" class="game-panel inventory-modern-panel inventory-loadout-panel equipment-panel-compact">
                                <div class="dashboard-section-head dashboard-section-head-compact">
                                    <div>
                                        <p class="panel-kicker">{{ __('Equipment') }}</p>
                                        <h2 class="dashboard-section-title">{{ __('Equipment Window') }}</h2>
                                    </div>
                                    <span class="dashboard-section-badge">
                                        {{ __('Affects stats') }}
                                    </span>
                                </div>

                                <div class="equipment-board mt-6">
                                    <div class="equipment-board-shell">
                                        <div class="equipment-board-window">
                                            <div class="equipment-board-window-head">
                                                <span class="equipment-board-window-title">{{ __('Equipment') }}</span>
                                                <span class="equipment-board-window-emblem" aria-hidden="true"></span>
                                            </div>

                                            <div class="equipment-board-frame">
                                                <div class="equipment-board-silhouette" aria-hidden="true"></div>

                                                @foreach ($equipmentBoardSlots as $slot => $slotConfig)
                                                @php
                                                    $equippedSlot = $equipmentBySlot->get($slot);
                                                    $equippedInventoryItem = $equippedSlot?->characterItem;
                                                    $equippedItem = $equippedInventoryItem?->item;
                                                @endphp

                                                    <button
                                                        type="button"
                                                        class="equipment-board-slot {{ $slotConfig['class'] }} {{ $equippedItem ? 'equipment-board-slot-filled' : '' }}"
                                                        @click="equipmentInspector = '{{ $slot }}'"
                                                        x-bind:class="{ 'equipment-board-slot-active': equipmentInspector === '{{ $slot }}' }"
                                                        x-bind:aria-pressed="equipmentInspector === '{{ $slot }}'"
                                                        aria-label="{{ $slotConfig['label'] }}: {{ $equippedItem ? $equippedInventoryItem->displayName() : __('Nothing equipped.') }}"
                                                        title="{{ $slotConfig['label'] }}"
                                                    >
                                                        <span class="equipment-board-slot-kicker">{{ $slotConfig['label'] }}</span>

                                                        <span class="equipment-board-art">
                                                            @if ($equippedItem)
                                                                @if (filled($equippedInventoryItem->iconPath()))
                                                                    <img
                                                                        src="{{ asset($equippedInventoryItem->iconPath()) }}"
                                                                        alt=""
                                                                        class="equipment-board-icon"
                                                                    >
                                                                @else
                                                                    <span class="equipment-board-fallback">{{ strtoupper(substr($equippedItem->name, 0, 1)) }}</span>
                                                                @endif
                                                            @else
                                                                <span class="equipment-board-empty-mark">{{ $slotConfig['empty'] }}</span>
                                                            @endif
                                                        </span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>

                                        <div class="equipment-board-inspector">
                                            @foreach ($equipmentBoardSlots as $slot => $slotConfig)
                                            @php
                                                $equippedSlot = $equipmentBySlot->get($slot);
                                                $equippedInventoryItem = $equippedSlot?->characterItem;
                                                $equippedItem = $equippedInventoryItem?->item;
                                            @endphp

                                                <section
                                                    class="equipment-board-detail {{ $equippedItem ? 'equipment-board-detail-filled' : 'equipment-board-detail-empty' }}"
                                                    x-show="equipmentInspector === '{{ $slot }}'"
                                                    x-cloak
                                                >
                                                    <div class="equipment-board-detail-head">
                                                        <div class="equipment-board-detail-preview">
                                                            @if ($equippedItem)
                                                                @if (filled($equippedInventoryItem->iconPath()))
                                                                    <img
                                                                        src="{{ asset($equippedInventoryItem->iconPath()) }}"
                                                                        alt="{{ $equippedInventoryItem->displayName() }}"
                                                                        class="equipment-board-icon"
                                                                    >
                                                                @else
                                                                    <span class="equipment-board-fallback">{{ strtoupper(substr($equippedItem->name, 0, 1)) }}</span>
                                                                @endif
                                                            @else
                                                                <span class="equipment-board-empty-mark">{{ $slotConfig['empty'] }}</span>
                                                            @endif
                                                        </div>

                                                        <div class="equipment-board-detail-copy">
                                                            <p class="equipment-board-detail-slot">{{ $slotConfig['label'] }}</p>
                                                            <p class="equipment-board-name">
                                                                {{ $equippedItem ? $equippedInventoryItem->displayName() : __('Nothing equipped.') }}
                                                            </p>
                                                            <p class="equipment-board-bonus">
                                                                @if ($equippedItem)
                                                                    @if ($equippedInventoryItem->effectiveBonus('attack_bonus')) +{{ $equippedInventoryItem->effectiveBonus('attack_bonus') }} ATK @endif
                                                                    @if ($equippedInventoryItem->effectiveBonus('defense_bonus')) · +{{ $equippedInventoryItem->effectiveBonus('defense_bonus') }} DEF @endif
                                                                    @if ($equippedInventoryItem->effectiveBonus('speed_bonus')) · +{{ $equippedInventoryItem->effectiveBonus('speed_bonus') }} SPD @endif
                                                                    @if ($equippedInventoryItem->effectiveBonus('travel_speed_percent_bonus')) · +{{ $equippedInventoryItem->effectiveBonus('travel_speed_percent_bonus') }}% TRAVEL @endif
                                                                    @if ($equippedInventoryItem->effectiveBonus('health_bonus')) · +{{ $equippedInventoryItem->effectiveBonus('health_bonus') }} HP @endif
                                                                @else
                                                                    {{ __('Select an item in your bag to fill this slot.') }}
                                                                @endif
                                                            </p>
                                                            @if ($equippedItem)
                                                                <p class="equipment-board-detail-meta">
                                                                    {{ __('Bonuses :current/:slots', [
                                                                        'current' => $equippedInventoryItem->bonusCount(),
                                                                        'slots' => $equippedInventoryItem->bonus_slot_limit,
                                                                    ]) }}
                                                                </p>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    @if ($equippedItem && $equippedInventoryItem->bonusLines() !== [])
                                                        <div class="equipment-board-detail-chips">
                                                            @foreach ($equippedInventoryItem->bonusLines() as $bonusLine)
                                                                <span class="cozy-chip">{{ $bonusLine }}</span>
                                                            @endforeach
                                                        </div>
                                                    @endif

                                                    @if ($equippedItem)
                                                        <div class="equipment-board-detail-actions">
                                                            <form method="POST" action="{{ route('game.actions.store', ['action' => 'unequip-item']) }}">
                                                                @csrf
                                                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                                                <input type="hidden" name="character_item_id" value="{{ $equippedSlot->character_item_id }}">
                                                                @if ($activeFocus)
                                                                    <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                                                                @endif

                                                                <button type="submit" class="action-button-muted w-full">
                                                                    {{ __('Unequip') }}
                                                                </button>
                                                            </form>
                                                            @include('game.partials.item-bonus-actions', ['inventoryItem' => $equippedInventoryItem])
                                                        </div>
                                                    @endif
                                                </section>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <div class="inventory-modal-main">
                            <article class="game-panel inventory-modern-panel inventory-detail-workspace">
                                <div class="dashboard-section-head dashboard-section-head-modern">
                                    <div>
                                        <p class="panel-kicker">{{ __('Selected Item') }}</p>
                                        <h2 class="dashboard-section-title">{{ __('Item Workspace') }}</h2>
                                    </div>
                                    <span class="dashboard-section-badge">{{ __('Live actions') }}</span>
                                </div>

                                @forelse ($bagItems as $inventoryItem)
                                    @php
                                        $item = $inventoryItem->item;
                                        $isBiologistSupportItem = in_array($item->slug, ['research-catalyst', 'research-report'], true);
                                        $isSkillSupportMaterial = $item->slug === \App\Support\Game\MetinClassCatalog::soulStoneSlug();
                                        $linkedSkill = $item->linkedSkillDefinition();
                                    @endphp

                                    <section class="inventory-detail-sheet" x-show="inventorySelection === {{ $inventoryItem->id }}" x-cloak>
                                        <div class="inventory-detail-hero">
                                            <div class="inventory-detail-preview" aria-hidden="true">
                                                @if (filled($inventoryItem->iconPath()))
                                                    <img
                                                        src="{{ asset($inventoryItem->iconPath()) }}"
                                                        alt=""
                                                        class="inventory-detail-preview-icon"
                                                    >
                                                @else
                                                    <span class="inventory-detail-fallback">{{ strtoupper(substr($item->name, 0, 1)) }}</span>
                                                @endif
                                            </div>

                                            <div class="inventory-detail-copy">
                                                <div class="inventory-detail-title-row">
                                                    <h3 class="inventory-detail-title">{{ $inventoryItem->displayName() }}</h3>

                                                    <div class="inventory-detail-badges">
                                                        <span class="inventory-entry-chip">{{ strtoupper($item->item_type) }}</span>
                                                        @if ($inventoryItem->quantity > 1)
                                                            <span class="inventory-entry-chip">x{{ $inventoryItem->quantity }}</span>
                                                        @endif
                                                        @if ($inventoryItem->isEquipped())
                                                            <span class="inventory-entry-chip">{{ __('Equipped') }}</span>
                                                        @endif
                                                    </div>
                                                </div>

                                                <p class="inventory-detail-description">{{ $item->description }}</p>

                                                <div class="inventory-detail-stats">
                                                    <div class="inventory-detail-stat">
                                                        <span>{{ __('Req. Level') }}</span>
                                                        <strong>{{ $item->required_level }}</strong>
                                                    </div>
                                                    <div class="inventory-detail-stat">
                                                        <span>{{ __('Sell') }}</span>
                                                        <strong>{{ (int) $item->sell_price > 0 ? number_format($item->sell_price).' '.__('Yang') : __('None') }}</strong>
                                                    </div>
                                                    <div class="inventory-detail-stat">
                                                        <span>{{ __('Bonuses') }}</span>
                                                        <strong>
                                                            @if ($item->isEquippable())
                                                                {{ $inventoryItem->bonusCount() }}/{{ $inventoryItem->bonus_slot_limit }}
                                                            @else
                                                                {{ __('N/A') }}
                                                            @endif
                                                        </strong>
                                                    </div>
                                                </div>

                                                <p class="inventory-detail-meta">
                                                    @if ($item->isSkillBook() && $linkedSkill !== null) {{ __('Skill') }} {{ $linkedSkill['name'] }} · @endif
                                                    @if ($item->isSkillBook()) {{ __('Book Rank') }} {{ \App\Support\Game\MetinClassCatalog::skillBookMinimumRank() }}+ · @endif
                                                    @if ($item->heal_amount) +{{ $item->heal_amount }} HP · @endif
                                                    @if ($item->sp_restore) +{{ $item->sp_restore }} SP · @endif
                                                    @if ($inventoryItem->effectiveBonus('attack_bonus')) +{{ $inventoryItem->effectiveBonus('attack_bonus') }} ATK · @endif
                                                    @if ($inventoryItem->effectiveBonus('defense_bonus')) +{{ $inventoryItem->effectiveBonus('defense_bonus') }} DEF · @endif
                                                    @if ($inventoryItem->effectiveBonus('speed_bonus')) +{{ $inventoryItem->effectiveBonus('speed_bonus') }} SPD · @endif
                                                    @if ($inventoryItem->effectiveBonus('travel_speed_percent_bonus')) +{{ $inventoryItem->effectiveBonus('travel_speed_percent_bonus') }}% TRAVEL · @endif
                                                    @if ($inventoryItem->effectiveBonus('health_bonus')) +{{ $inventoryItem->effectiveBonus('health_bonus') }} HP @endif
                                                </p>
                                            </div>
                                        </div>

                                        @if ($inventoryItem->bonusLines() !== [])
                                            <div class="inventory-detail-chips">
                                                @foreach ($inventoryItem->bonusLines() as $bonusLine)
                                                    <span class="cozy-chip">{{ $bonusLine }}</span>
                                                @endforeach
                                            </div>
                                        @endif

                                        <div class="inventory-detail-actions">
                                            @if ($item->isConsumable() && ! $isBiologistSupportItem)
                                                <form method="POST" action="{{ route('game.actions.store', ['action' => 'use-item']) }}" class="space-y-3">
                                                    @csrf
                                                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                                    <input type="hidden" name="character_item_id" value="{{ $inventoryItem->id }}">
                                                    @if ($activeFocus)
                                                        <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                                                    @endif

                                                    <div class="flex flex-wrap items-center gap-3">
                                                        <label class="text-[0.62rem] font-semibold uppercase tracking-[0.2em] text-stone-700" for="consume-quantity-{{ $inventoryItem->id }}">
                                                            {{ __('Qty') }}
                                                        </label>
                                                        <input
                                                            id="consume-quantity-{{ $inventoryItem->id }}"
                                                            type="number"
                                                            name="quantity"
                                                            min="1"
                                                            max="{{ $inventoryItem->quantity }}"
                                                            value="1"
                                                            class="inventory-use-quantity"
                                                        >
                                                    </div>

                                                    <div class="inventory-detail-action-grid">
                                                        <button type="submit" class="action-button w-full">
                                                            {{ __('Use Selected') }}
                                                        </button>
                                                        <button type="submit" name="use_until_full" value="1" class="action-button-muted w-full">
                                                            {{ __('Use Until Full') }}
                                                        </button>
                                                    </div>
                                                </form>
                                            @elseif ($item->isSkillBook())
                                                <form method="POST" action="{{ route('game.actions.store', ['action' => 'use-item']) }}">
                                                    @csrf
                                                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                                    <input type="hidden" name="character_item_id" value="{{ $inventoryItem->id }}">
                                                    @if ($activeFocus)
                                                        <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                                                    @endif

                                                    <button type="submit" class="action-button w-full">
                                                        {{ __('Study Book') }}
                                                    </button>
                                                </form>
                                            @elseif ($isBiologistSupportItem)
                                                <div class="inventory-detail-note">
                                                    {{ __('Use this item from the Biologist panel in town.') }}
                                                </div>
                                            @elseif ($isSkillSupportMaterial)
                                                <div class="inventory-detail-note">
                                                    {{ __('Use this item from the skill panel once a skill reaches M10.') }}
                                                </div>
                                            @endif

                                            @if ($item->isEquippable())
                                                <form method="POST" action="{{ route('game.actions.store', ['action' => 'equip-item']) }}">
                                                    @csrf
                                                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                                    <input type="hidden" name="character_item_id" value="{{ $inventoryItem->id }}">
                                                    @if ($activeFocus)
                                                        <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                                                    @endif

                                                    <button
                                                        type="submit"
                                                        class="action-button w-full"
                                                        @disabled($character->level < $item->required_level)
                                                    >
                                                        {{ __('Equip') }}
                                                    </button>
                                                </form>
                                            @endif

                                            @if ($inventorySellEnabled && ! $inventoryItem->isEquipped() && (int) $item->sell_price > 0)
                                                <form method="POST" action="{{ route('game.actions.store', ['action' => 'sell-item']) }}" class="space-y-3">
                                                    @csrf
                                                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                                    <input type="hidden" name="location_area_id" value="{{ $inventorySellVendorArea->id }}">
                                                    <input type="hidden" name="character_item_id" value="{{ $inventoryItem->id }}">
                                                    @if ($activeFocus)
                                                        <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                                                    @endif

                                                    @if ($item->stackable && $inventoryItem->quantity > 1)
                                                        <div class="flex flex-wrap items-center gap-3">
                                                            <label class="text-[0.62rem] font-semibold uppercase tracking-[0.2em] text-stone-700" for="sell-quantity-{{ $inventoryItem->id }}">
                                                                {{ __('Qty') }}
                                                            </label>
                                                            <input
                                                                id="sell-quantity-{{ $inventoryItem->id }}"
                                                                type="number"
                                                                name="quantity"
                                                                min="1"
                                                                max="{{ $inventoryItem->quantity }}"
                                                                value="1"
                                                                class="inventory-use-quantity"
                                                            >
                                                        </div>
                                                    @endif

                                                    <button type="submit" class="action-button-muted w-full">
                                                        {{ __('Sell') }}
                                                    </button>
                                                </form>
                                            @endif
                                        </div>

                                        @include('game.partials.item-bonus-actions', ['inventoryItem' => $inventoryItem])

                                        <div class="inventory-detail-notes">
                                            @if ($item->isEquippable() && $character->level < $item->required_level)
                                                <p class="action-cost-warning">{{ __('Required level not reached yet.') }}</p>
                                            @endif
                                            @if ($inventorySellEnabled && ! $inventoryItem->isEquipped() && (int) $item->sell_price > 0)
                                                <p class="action-copy">{{ __('Selling here goes through :vendor.', ['vendor' => $inventorySellVendorArea->name]) }}</p>
                                            @endif
                                        </div>
                                    </section>
                                @empty
                                    <div class="inventory-empty-state">
                                        <p class="inventory-empty-title">{{ __('Your inventory is empty.') }}</p>
                                        <p class="inventory-empty-copy">{{ __('Travel, fight or visit town services to fill the bag again.') }}</p>
                                    </div>
                                @endforelse
                            </article>

                            <article class="game-panel inventory-modern-panel inventory-collection-panel">
                                <div class="dashboard-section-head dashboard-section-head-modern">
                                    <div>
                                        <p class="panel-kicker">{{ __('Bag Contents') }}</p>
                                        <h2 class="dashboard-section-title">{{ __('Quick Select') }}</h2>
                                    </div>
                                    <span class="dashboard-section-badge">{{ __('Tap or click') }}</span>
                                </div>

                                @if ($bagItems->isNotEmpty())
                                    <div class="inventory-collection-list mt-6">
                                        @foreach ($bagItems as $inventoryItem)
                                            @php
                                                $item = $inventoryItem->item;
                                                $linkedSkill = $item->linkedSkillDefinition();
                                            @endphp

                                            <button
                                                type="button"
                                                class="inventory-card-button"
                                                @click="inventorySelection = {{ $inventoryItem->id }}"
                                                x-bind:class="{ 'inventory-card-button-active': inventorySelection === {{ $inventoryItem->id }} }"
                                                x-bind:aria-pressed="inventorySelection === {{ $inventoryItem->id }}"
                                            >
                                                <span class="inventory-card-art" aria-hidden="true">
                                                    @if (filled($inventoryItem->iconPath()))
                                                        <img
                                                            src="{{ asset($inventoryItem->iconPath()) }}"
                                                            alt=""
                                                            class="inventory-card-art-icon"
                                                        >
                                                    @else
                                                        <span class="inventory-card-art-fallback">{{ strtoupper(substr($item->name, 0, 1)) }}</span>
                                                    @endif
                                                </span>

                                                <span class="inventory-card-copy">
                                                    <span class="inventory-card-topline">
                                                        <span class="inventory-card-name">{{ $inventoryItem->displayName() }}</span>
                                                        @if ($inventoryItem->isEquipped())
                                                            <span class="inventory-card-state">{{ __('Equipped') }}</span>
                                                        @endif
                                                    </span>

                                                    <span class="inventory-card-meta">
                                                        {{ strtoupper($item->item_type) }} · {{ __('Level') }} {{ $item->required_level }}
                                                        @if ((int) $item->sell_price > 0) · {{ number_format($item->sell_price) }} {{ __('Yang') }} @endif
                                                    </span>

                                                    <span class="inventory-card-footer">
                                                        @if ($inventoryItem->quantity > 1)
                                                            <span class="inventory-card-pill">x{{ $inventoryItem->quantity }}</span>
                                                        @endif
                                                        @if ($inventoryItem->bonusCount() > 0)
                                                            <span class="inventory-card-pill">
                                                                {{ __('Bonuses') }} {{ $inventoryItem->bonusCount() }}
                                                            </span>
                                                        @endif
                                                        @if ($item->isSkillBook() && $linkedSkill !== null)
                                                            <span class="inventory-card-pill">{{ $linkedSkill['name'] }}</span>
                                                        @endif
                                                    </span>
                                                </span>
                                            </button>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="inventory-empty-state mt-6">
                                        <p class="inventory-empty-title">{{ __('Nothing to select yet.') }}</p>
                                        <p class="inventory-empty-copy">{{ __('The quick select grid fills as soon as items reach your bag.') }}</p>
                                    </div>
                                @endif
                            </article>
                        </div>
                    </div>
                </div>

            </div>
            </div>
        </section>

        <div class="dashboard-main-stack">
            <article id="tutorial-map-panel" class="game-panel dashboard-map-stage {{ $tutorialActive && in_array($tutorialHighlight, ['map', 'town', 'hunting'], true) ? 'tutorial-focus-ring' : '' }}">
                <div class="dashboard-map-heading">
                    <div>
                        <p class="panel-kicker">{{ __('Current Map') }}</p>
                        <h1 class="mt-2 font-[Cinzel] text-3xl tracking-[0.12em] text-amber-50 sm:text-4xl">
                            {{ $character->location->name }}
                        </h1>
                    </div>

                    <div class="dashboard-map-chipbar">
                        <span class="cozy-chip">{{ $character->location->region }}</span>
                        <span class="cozy-chip">{{ $character->location->is_safe_zone ? __('Safe town') : __('Frontier field') }}</span>
                        <span class="cozy-chip">{{ $activeArea?->name ?? __('Map overview') }}</span>
                    </div>
                </div>

                <div class="dashboard-map-stack mt-6 {{ $activeArea ? 'dashboard-map-stack-has-focus' : '' }}">
                    <div class="scene-board dashboard-scene-board">
                        <div class="scene-canvas {{ $sceneThemeClass }}">
                            <div class="dashboard-scene-banner">
                                <span class="dashboard-scene-banner-item">{{ __('Map Core') }}</span>
                                <span class="dashboard-scene-banner-item">{{ $activeArea?->isService() ? __('Town Flow') : ($activeArea?->isHunting() ? __('Hunting Flow') : __('Select a Hotspot')) }}</span>
                            </div>

                            <div class="scene-copy">
                                <p class="scene-copy-kicker">{{ $character->location->region }}</p>
                                <h2 class="font-[Cinzel] text-3xl tracking-[0.12em] text-amber-50">
                                    {{ $character->location->name }}
                                </h2>
                                <p class="scene-copy-description mt-3 max-w-xl text-sm leading-6 text-amber-50/85">
                                    {{ $character->location->description }}
                                </p>
                            </div>

                            @foreach ($locationAreas as $area)
                                <a
                                    href="{{ route('dashboard', ['focus' => $area->slug]) }}"
                                    class="scene-hotspot {{ $area->isService() ? 'scene-hotspot-service' : 'scene-hotspot-hunting' }} {{ $activeArea?->is($area) ? 'scene-hotspot-active' : '' }}"
                                    style="left: {{ $area->hotspot_x }}%; top: {{ $area->hotspot_y }}%;"
                                    title="{{ $area->name }}"
                                    aria-label="{{ $area->name }}"
                                >
                                    <span>{{ $area->name }}</span>
                                </a>
                            @endforeach
                        </div>

                        <div class="scene-hotspot-list">
                            <div class="scene-hotspot-list-inner">
                                @foreach ($locationAreas as $area)
                                    <a
                                        href="{{ route('dashboard', ['focus' => $area->slug]) }}"
                                        class="theme-button theme-button-primary theme-button-compact {{ $activeArea?->is($area) ? 'theme-button-active' : '' }}"
                                    >
                                        {{ $area->name }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    @include('game.partials.dashboard-map-system-overlays')

                    @if ($activeArea)
                        <div class="dashboard-map-lower dashboard-map-lower-mobile-open">
                            <div id="tutorial-focus-panel" class="paper-note dashboard-focus-panel dashboard-focus-panel-mobile-open">
                            @if ($activeArea)
                                <div class="dashboard-mobile-focus-bar">
                                    <span class="dashboard-mobile-focus-bar-label">
                                        {{ $activeArea->isService() ? __('Town Hotspot') : __('Hunting Ground') }}
                                    </span>
                                    <a href="{{ route('dashboard') }}" class="theme-button theme-button-secondary theme-button-compact dashboard-mobile-focus-close">
                                        {{ __('Close') }}
                                    </a>
                                </div>
                            @endif

                            <div class="dashboard-focus-panel-head dashboard-focus-panel-head-mobile-open flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-[0.65rem] uppercase tracking-[0.28em] text-amber-900/70">
                                        {{ $activeArea->isService() ? __('Town Hotspot') : __('Hunting Ground') }}
                                    </p>
                                    <h3 class="mt-2 font-[Cinzel] text-2xl tracking-[0.08em] text-stone-900">
                                        {{ $activeArea->name }}
                                    </h3>
                                </div>
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    @if ($activeArea->recommended_level)
                                        <span class="rounded-full border border-amber-900/15 px-3 py-1 text-[0.62rem] uppercase tracking-[0.22em] text-amber-900/70">
                                            Lv {{ $activeArea->recommended_level }}+
                                        </span>
                                    @endif
                                    @if (! $tutorialActive)
                                        <a href="{{ route('dashboard') }}" class="theme-button theme-button-secondary theme-button-compact dashboard-map-window-close">
                                            {{ __('Close') }}
                                        </a>
                                    @endif
                                </div>
                            </div>

                            <p class="mt-4 text-sm leading-6 text-stone-700">
                                {{ $activeArea->description }}
                            </p>

                            @if ($tutorialActive)
                                <div class="mt-5 grid gap-3 lg:grid-cols-2">
                                    @if ($activeArea->slug === 'general-store')
                                        <div id="tutorial-general-store-card" class="form-card lg:col-span-2">
                            <p class="action-title">{{ __('General Store') }}</p>
                                            <p class="action-copy">
                                                {{ __('This is the safe place for potions and basic recovery supplies. The tutorial only introduces the store first; shopping unlocks after onboarding.') }}
                                            </p>
                                            <p class="mt-4 text-xs uppercase tracking-[0.22em] text-amber-900/70">
                                                {{ __('Potions keep your field loop alive once you start taking real damage.') }}
                                            </p>
                                        </div>
                                    @elseif ($activeArea->slug === 'riverbank')
                                        <div id="tutorial-hunting-overview-card" class="form-card lg:col-span-2">
                                            <p class="action-title">{{ __('Riverbank Tutorial Area') }}</p>
                                            <p class="action-copy">
                                                {{ __('This beginner field introduces the full loop: monster groups for steady rewards, Lumiis stones for soul releases, and a boss once enough pressure is built.') }}
                                            </p>
                                            <div class="mt-4 flex flex-wrap gap-2">
                                                <span class="cozy-chip">{{ __('Monster Groups') }}</span>
                                                <span class="cozy-chip">{{ __('Lumiis Waves') }}</span>
                                                <span class="cozy-chip">{{ __('Boss Pressure') }}</span>
                                            </div>
                                        </div>

                                        @if ($tutorialStep === 6)
                                            <div id="tutorial-inventory-card" class="form-card lg:col-span-2">
                                                <p class="action-title">{{ __('Starter Gear Check') }}</p>
                                                <p class="action-copy">
                                                    {{ __('Open the inventory window, equip your Recruit Blade and Cotton Armor, then continue once both slots are filled.') }}
                                                </p>
                                                <div class="mt-4 flex flex-wrap gap-2">
                                                    <span class="cozy-chip">{{ $tutorialWeaponEquipped ? __('Recruit Blade ready') : __('Equip Recruit Blade') }}</span>
                                                    <span class="cozy-chip">{{ $tutorialArmorEquipped ? __('Cotton Armor ready') : __('Equip Cotton Armor') }}</span>
                                                </div>
                                            </div>
                                        @elseif ($tutorialStep === 7 && $tutorialFightArea)
                                            <div id="tutorial-start-fight-card" class="form-card lg:col-span-2">
                                                <p class="action-title">{{ __('Start The First Manual Fight') }}</p>
                                                <p class="action-copy">
                                                    {{ __('The tutorial fight is manual, weaker than normal, and cannot be lost under expected play. Use it to learn the combat window.') }}
                                                </p>

                                                <form id="tutorial-start-fight-form" method="POST" action="{{ route('game.actions.store', ['action' => 'start-manual-fight']) }}" class="mt-5">
                                                    @csrf
                                                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                                    <input type="hidden" name="location_area_id" value="{{ $tutorialFightArea->id }}">
                                                    <input type="hidden" name="encounter_request" value="monster">
                                                    <input type="hidden" name="redirect_focus" value="{{ $tutorialFightArea->slug }}">

                                                    <button type="submit" class="action-button w-full">
                                                        {{ __('Start Tutorial Fight') }}
                                                    </button>
                                                </form>
                                            </div>
                                        @elseif ($tutorialStep === 8)
                                            <div class="form-card lg:col-span-2">
                                                <p class="action-title">{{ __('Read The Fight Window') }}</p>
                                                <p class="action-copy">
                                                    {{ __('The battle is already live. First read the player panel, battle log and enemy panel in the fight window, then continue to the first attack step.') }}
                                                </p>
                                            </div>
                                        @elseif (in_array($tutorialStep, [9, 10], true))
                                            <div class="form-card lg:col-span-2">
                                                <p class="action-title">{{ __('Fight In Progress') }}</p>
                                                <p class="action-copy">
                                                    {{ __('The live fight window is now the only place that accepts input. Attack until the whole pack is down.') }}
                                                </p>
                                            </div>
                                        @elseif (in_array($tutorialStep, [11, 12], true))
                                            <div class="form-card lg:col-span-2">
                                                <p class="action-title">{{ __('Rewards And Recovery') }}</p>
                                                <p class="action-copy">
                                                    {{ __('The battle is over. Your character has already been restored in the background so you can enter the real game loop immediately after the tutorial.') }}
                                                </p>
                                            </div>
                                        @endif
                                    @else
                                        <div class="form-card lg:col-span-2">
                                            <p class="action-title">{{ __('Town Services') }}</p>
                                            <p class="action-copy">
                                                {{ __('Town hotspots stay locked down during the tutorial. We only reveal the next safe service when its explanation is due.') }}
                                            </p>
                                        </div>
                                    @endif
                                </div>
                            @elseif ($activeArea->isHunting())
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

                                <div class="mt-5 grid gap-3 lg:grid-cols-2">
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
                                                {{ __('Auto Fight') }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('game.actions.store', ['action' => 'start-manual-fight']) }}" class="mt-3">
                                            @csrf
                                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                            <input type="hidden" name="location_area_id" value="{{ $activeArea->id }}">
                                            <input type="hidden" name="encounter_request" value="monster">
                                            <input type="hidden" name="redirect_focus" value="{{ $activeArea->slug }}">

                                            <button
                                                type="submit"
                                                class="action-button-muted w-full"
                                                x-bind:disabled="isActionLocked() || @js($monsterFightStaminaMissing > 0 || $monsterFightLocked)"
                                                @disabled($monsterFightStaminaMissing > 0 || $monsterFightLocked)
                                            >
                                                {{ __('Manual Fight') }}
                                            </button>
                                        </form>

                                        <p class="action-cost-warning" x-show="isResting()" x-text="restingLockLabel()" x-cloak></p>

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
                                                    {{ __('Auto Lumiis') }}
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
                                                    {{ __('Manual Lumiis') }}
                                                </button>
                                            </form>

                                            <p class="action-cost-warning" x-show="isResting()" x-text="restingLockLabel()" x-cloak></p>

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

                                                <p class="action-cost-warning" x-show="isResting()" x-text="restingLockLabel()" x-cloak></p>

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
                            @else
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
                                                    'footerUrl' => route('dashboard', ['focus' => $routesArea?->slug ?? $activeArea->slug]),
                                                    'footerLabel' => __('View Routes'),
                                                ])
                                            @else
                                                <div class="form-card flex flex-col justify-between gap-4">
                                                    <div>
                                                        <p class="action-title">{{ __('Route Control') }}</p>
                                                        <p class="shop-item-meta">{{ __('Maps and field exits') }}</p>
                                                    </div>

                                                    <a href="{{ route('dashboard', ['focus' => $routesArea?->slug ?? $activeArea->slug]) }}" class="action-button-muted">
                                                        {{ __('View Routes') }}
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

                                    @case('routes')
                                        <div class="mt-5 space-y-3">
                                            @forelse ($character->location->outgoingConnections as $connection)
                                                @php
                                                    $travelStaminaMissing = $staminaMissingFor((int) $connection->stamina_cost);
                                                    $travelLevelMissing = max(0, (int) $connection->required_level - (int) $character->level);
                                                @endphp
                                                <form method="POST" action="{{ route('game.actions.store', ['action' => 'travel']) }}" class="route-card">
                                                    @csrf
                                                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                                    <input type="hidden" name="target_location_id" value="{{ $connection->toLocation->id }}">
                                                    <input type="hidden" name="redirect_focus" value="{{ $activeArea->slug }}">

                                                    <div>
                                                        <p class="font-[Cinzel] text-lg tracking-[0.12em] text-amber-50">
                                                            {{ $connection->toLocation->name }}
                                                        </p>
                                                        <p class="text-xs uppercase tracking-[0.25em] text-amber-200/60">
                                                            {{ $character->travelCooldownFor($connection->travel_seconds) }} sec cooldown · {{ $connection->stamina_cost }} stamina · {{ __('Req. Level') }} {{ $connection->required_level }}
                                                        </p>
                                                        @if ($travelLevelMissing > 0)
                                                            <p class="action-cost-warning mt-2">
                                                                {{ __('Requires level :level.', ['level' => $connection->required_level]) }}
                                                            </p>
                                                        @elseif ($travelStaminaMissing > 0)
                                                            <p class="action-cost-warning mt-2">Missing {{ $travelStaminaMissing }} stamina.</p>
                                                        @endif
                                                    </div>

                                                    <button
                                                        type="submit"
                                                        class="action-button"
                                                        x-bind:disabled="isActionLocked() || @js($travelLevelMissing > 0 || $travelStaminaMissing > 0)"
                                                        @disabled($travelLevelMissing > 0 || $travelStaminaMissing > 0)
                                                    >
                                                        Travel
                                                    </button>
                                                </form>
                                            @empty
                                                <p class="text-sm text-stone-700">No routes are configured from this node yet.</p>
                                            @endforelse
                                        </div>
                                    @break
                                @endswitch
                            @endif
                        </div>

                    @endif
                </div>
            </article>

            @if ($showSystemGrid)
                <div class="dashboard-system-grid">
                <article class="game-panel">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="panel-kicker">Town Actions</p>
                            <h2 class="mt-2 font-[Cinzel] text-2xl tracking-[0.12em] text-amber-50">Preparation and Recovery</h2>
                        </div>
                        <span class="rounded-full border border-amber-500/20 px-3 py-1 text-xs uppercase tracking-[0.25em] text-stone-300">
                            Server authoritative
                        </span>
                    </div>

                    <div class="mt-6 grid gap-4 lg:grid-cols-2">
                        <div class="action-card">
                            <p class="action-title">Rest Plans</p>
                            <p class="action-copy">
                                {{ __('Choose a longer rest if you need more stamina, SP and healing. While resting, actions stay locked.') }}
                            </p>

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
                                            @if ($activeFocus)
                                                <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                                            @endif
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

                        <div class="action-card">
                            <p class="action-title">Field Loop</p>
                            <p class="action-copy">
                                Fight a pack, buy potions, recover stamina, return to the map and keep pushing the next area.
                            </p>

                            <div class="mt-4 text-xs uppercase tracking-[0.22em] text-amber-200/60">
                                {{ $serviceAreas->count() }} town hotspots · {{ $huntingAreas->count() }} hunting hotspots
                            </div>
                        </div>
                    </div>
                </article>

                <article class="game-panel" id="travel-routes">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="panel-kicker">Travel Routes</p>
                            <h2 class="mt-2 font-[Cinzel] text-2xl tracking-[0.12em] text-amber-50">Map Connections</h2>
                        </div>
                        <span class="rounded-full border border-amber-500/20 px-3 py-1 text-xs uppercase tracking-[0.25em] text-stone-300">
                            Mount bonus applied
                        </span>
                    </div>

                    @if (! $travelRoutesUnlocked)
                        <div class="mt-4 rounded-[1.2rem] border border-dashed border-amber-500/18 bg-stone-950/25 px-5 py-5 text-sm leading-7 text-stone-300">
                            Travel routes unlock at level 4. Finish the early questline, keep clearing packs and Lumiis, and the wider world will open once your character is strong enough to leave the starter land.
                        </div>
                    @else
                        <div class="mt-4 space-y-3">
                            @forelse ($character->location->outgoingConnections as $connection)
                                @php
                                    $travelStaminaMissing = $staminaMissingFor((int) $connection->stamina_cost);
                                    $travelLevelMissing = max(0, (int) $connection->required_level - (int) $character->level);
                                @endphp
                                <form method="POST" action="{{ route('game.actions.store', ['action' => 'travel']) }}" class="route-card">
                                    @csrf
                                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                    <input type="hidden" name="target_location_id" value="{{ $connection->toLocation->id }}">
                                    @if ($activeFocus)
                                        <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                                    @endif

                                    <div>
                                        <p class="font-[Cinzel] text-lg tracking-[0.12em] text-amber-50">
                                            {{ $connection->toLocation->name }}
                                        </p>
                                        <p class="text-xs uppercase tracking-[0.25em] text-amber-200/60">
                                            {{ $character->travelCooldownFor($connection->travel_seconds) }} sec cooldown · {{ $connection->stamina_cost }} stamina · {{ __('Req. Level') }} {{ $connection->required_level }}
                                            @if ($character->travelSpeedPercentBonus() > 0)
                                                · mount / skill bonus active
                                            @endif
                                        </p>
                                        @if ($travelLevelMissing > 0)
                                            <p class="action-cost-warning mt-2">
                                                {{ __('Requires level :level.', ['level' => $connection->required_level]) }}
                                            </p>
                                        @elseif ($travelStaminaMissing > 0)
                                            <p class="action-cost-warning mt-2">Missing {{ $travelStaminaMissing }} stamina.</p>
                                        @endif
                                    </div>

                                    <button
                                        type="submit"
                                        class="action-button"
                                        x-bind:disabled="isActionLocked() || @js($travelLevelMissing > 0 || $travelStaminaMissing > 0)"
                                        @disabled($travelLevelMissing > 0 || $travelStaminaMissing > 0)
                                    >
                                        Travel
                                    </button>
                                </form>
                            @empty
                                <p class="text-sm text-stone-400">No routes configured from this location yet.</p>
                            @endforelse
                        </div>
                    @endif
                </article>

                <article class="game-panel" id="town-vendor">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="panel-kicker">Town Vendor</p>
                            <h2 class="mt-2 font-[Cinzel] text-2xl tracking-[0.12em] text-amber-50">
                                {{ $activeAreaShop?->name ?? 'Choose a Vendor Hotspot' }}
                            </h2>
                        </div>
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <span class="rounded-full border border-amber-500/20 px-3 py-1 text-xs uppercase tracking-[0.25em] text-stone-300">
                                Wallet: {{ number_format($character->yang) }} Yang
                            </span>
                            @if ($activeAreaShop)
                                <span class="rounded-full border border-amber-500/20 px-3 py-1 text-xs uppercase tracking-[0.25em] text-stone-300">
                                    {{ $activeArea?->name }}
                                </span>
                            @endif
                        </div>
                    </div>

                    @if (! $activeAreaShop)
                        <div class="paper-note mt-6 text-sm leading-6">
                            Click a vendor hotspot on the map to browse its own inventory. Potions, weapons and armor are now split across different town NPCs. To sell loot, keep a vendor focused and open your inventory panel.
                        </div>
                    @else
                        <div class="mt-6 space-y-3">
                            @foreach ($activeAreaShop->shopItems->sortBy('sort_order') as $shopItem)
                                <div class="paper-note">
                                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <p class="font-[Cinzel] text-xl tracking-[0.08em] text-stone-900">{{ $shopItem->item->name }}</p>
                                            <p class="mt-2 text-sm leading-6 text-stone-700">{{ $shopItem->item->description }}</p>
                                            <p class="shop-item-meta">
                                                {{ __(':yang Yang · Req. Level :level', ['yang' => number_format($shopItem->price), 'level' => $shopItem->item->required_level]) }}
                                            </p>
                                            @if ($shopItemValueText($shopItem->item))
                                                <p class="shop-item-values">{{ $shopItemValueText($shopItem->item) }}</p>
                                            @endif
                                        </div>

                                        <form method="POST" action="{{ route('game.actions.store', ['action' => 'buy-item']) }}" class="w-full sm:w-auto sm:min-w-[10rem]">
                                            @csrf
                                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                            <input type="hidden" name="location_area_id" value="{{ $activeAreaShop->location_area_id }}">
                                            <input type="hidden" name="shop_item_id" value="{{ $shopItem->id }}">
                                            @if ($activeFocus)
                                                <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                                            @endif

                                            <button
                                                type="submit"
                                                class="action-button"
                                                @disabled($character->yang < $shopItem->price || $character->level < $shopItem->item->required_level)
                                            >
                                                {{ __('Buy') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </article>

                <article class="game-panel" id="blacksmith">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="panel-kicker">{{ __('Blacksmith') }}</p>
                            <h2 class="mt-2 font-[Cinzel] text-2xl tracking-[0.12em] text-amber-50">{{ __('Weapon Upgrades +0 to +9') }}</h2>
                        </div>
                        @if ($activeArea?->service_type === 'smith')
                            <span class="rounded-full border border-amber-500/20 px-3 py-1 text-xs uppercase tracking-[0.25em] text-stone-300">
                                {{ $activeArea->name }}
                            </span>
                        @endif
                    </div>

                    @if (! $advancedTownServicesUnlocked)
                        <div class="paper-note mt-6 text-sm leading-6">
                            {{ __('The smith unlocks at level 5 together with the weapon and armor dealers.') }}
                        </div>
                    @elseif ($activeArea?->service_type !== 'smith')
                        <div class="paper-note mt-6 text-sm leading-6">
                            {{ __('Click the smith hotspot on the map to attempt weapon upgrades. Failed upgrades destroy the weapon.') }}
                        </div>
                    @elseif ($smithableItems->isEmpty())
                        <div class="paper-note mt-6 text-sm leading-6">
                            {{ __('You do not currently carry an upgradeable weapon. Visit the weapon dealer first.') }}
                        </div>
                    @else
                        <div class="mt-6 space-y-3">
                            <div class="paper-note">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <p class="font-[Cinzel] text-xl tracking-[0.08em] text-stone-900">{{ __('Weapon Upgrades +0 to +9') }}</p>
                                    <span class="cozy-chip">{{ __('Shards') }}: {{ number_format($lumiisShardCount) }}</span>
                                </div>
                                <p class="mt-2 text-sm leading-6 text-stone-700">
                                    {{ __('Early upgrades cost only Yang. Higher steps also consume Lumiis Shards gathered from Lumiis stones and boss souls.') }}
                                </p>
                            </div>
                            @foreach ($smithableItems as $inventoryItem)
                                @php
                                    $item = $inventoryItem->item;
                                    $nextUpgradeLevel = min(9, $inventoryItem->upgrade_level + 1);
                                @endphp

                                <div class="paper-note">
                                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="font-[Cinzel] text-xl tracking-[0.08em] text-stone-900">{{ $inventoryItem->displayName() }}</p>
                                                <span class="rounded-full border border-amber-900/15 px-2 py-1 text-[0.58rem] uppercase tracking-[0.22em] text-amber-900/70">
                                                    {{ __('Next: +:level', ['level' => $nextUpgradeLevel]) }}
                                                </span>
                                                @if ($inventoryItem->isEquipped())
                                                    <span class="theme-badge-equipped rounded-full px-2 py-1 text-[0.58rem] uppercase tracking-[0.22em]">
                                                        {{ __('Equipped') }}
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="mt-2 text-sm leading-6 text-stone-700">{{ $item->description }}</p>
                                            <p class="mt-3 text-xs uppercase tracking-[0.24em] text-amber-900/70">
                                                {{ __(':attack ATK now · :success% success · :yang Yang', [
                                                    'attack' => $inventoryItem->effectiveBonus('attack_bonus'),
                                                    'success' => $inventoryItem->smithUpgradeSuccessPercent(),
                                                    'yang' => number_format($inventoryItem->smithUpgradeCost()),
                                                ]) }}
                                                @if ($inventoryItem->smithUpgradeShardCost() > 0)
                                                    · {{ $inventoryItem->smithUpgradeShardCost() }} {{ $inventoryItem->smithUpgradeShardCost() === 1 ? __('Lumiis Shard') : __('Lumiis Shards') }}
                                                @endif
                                            </p>
                                        </div>

                                        <form method="POST" action="{{ route('game.actions.store', ['action' => 'upgrade-item']) }}" class="w-full sm:w-auto sm:min-w-[12rem]">
                                            @csrf
                                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                            <input type="hidden" name="location_area_id" value="{{ $activeArea->id }}">
                                            <input type="hidden" name="character_item_id" value="{{ $inventoryItem->id }}">
                                            @if ($activeFocus)
                                                <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                                            @endif

                                            <button
                                                type="submit"
                                                class="action-button"
                                                @disabled($inventoryItem->upgrade_level >= 9 || $character->yang < $inventoryItem->smithUpgradeCost() || $lumiisShardCount < $inventoryItem->smithUpgradeShardCost())
                                            >
                                                {{ __('Upgrade') }}
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
                </article>
                </div>
            @endif
                </section>

                <aside class="metin2-column-right dashboard-metin2-column dashboard-mobile-rail">
                    <section class="metin2-module dashboard-side-module dashboard-mobile-order-hotspots">
                        <div class="metin2-side-top"></div>
                        <div class="metin2-side-body">
                            <h3 class="metin2-module-title">{{ __('Hotspots') }}</h3>
                            <div class="metin2-module-body dashboard-side-body">
                                <ol class="dashboard-side-ranking">
                                    @foreach ($locationAreas->take(5) as $area)
                                        <li @class(['is-light' => $loop->even, 'is-active' => $activeArea?->is($area)])>
                                            <strong>{{ $loop->iteration }}</strong>
                                            <a href="{{ route('dashboard', ['focus' => $area->slug]) }}">{{ $area->name }}</a>
                                        </li>
                                    @endforeach
                                </ol>
                            </div>
                        </div>
                        <div class="metin2-side-bottom"></div>
                    </section>

                    <section class="metin2-module dashboard-side-module dashboard-mobile-order-focus">
                        <div class="metin2-side-top"></div>
                        <div class="metin2-side-body">
                            <h3 class="metin2-module-title">{{ __('Focus') }}</h3>
                            <div class="metin2-module-body dashboard-side-body">
                                <p class="dashboard-side-kicker">
                                    {{ $activeArea?->isService() ? __('Town Hotspot') : ($activeArea?->isHunting() ? __('Hunting Ground') : __('Map Overview')) }}
                                </p>
                                <p class="dashboard-side-title">{{ $activeArea?->name ?? $character->location->name }}</p>

                                <ul class="dashboard-side-stats dashboard-side-stats-compact">
                                    @foreach ($dashboardFocusFacts as $focusFact)
                                        <li>
                                            <span>{{ $focusFact['label'] }}</span>
                                            <strong>{{ $focusFact['value'] }}</strong>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        <div class="metin2-side-bottom"></div>
                    </section>

                    <section class="metin2-module dashboard-side-module dashboard-mobile-order-materials">
                        <div class="metin2-side-top"></div>
                        <div class="metin2-side-body">
                            <h3 class="metin2-module-title">{{ __('Materials') }}</h3>
                            <div class="metin2-module-body dashboard-side-body">
                                <ul class="dashboard-side-stats dashboard-side-stats-compact">
                                    <li>
                                        <span>{{ __('Yang') }}</span>
                                        <strong>{{ number_format($character->yang) }}</strong>
                                    </li>
                                    <li>
                                        <span>{{ __('Shards') }}</span>
                                        <strong>{{ number_format($lumiisShardCount) }}</strong>
                                    </li>
                                    <li>
                                        <span>{{ __('Soul Prism') }}</span>
                                        <strong>{{ number_format($soulPrismCount) }}</strong>
                                    </li>
                                    <li>
                                        <span>{{ __('Bag Items') }}</span>
                                        <strong>{{ number_format($bagItems->count()) }}</strong>
                                    </li>
                                </ul>

                                <button
                                    type="button"
                                    @class([
                                        'metin2-download-button',
                                        'dashboard-side-cta',
                                        'dashboard-side-link-disabled' => ! $dashboardCanOpenInventory,
                                    ])
                                    @click="openPanel('inventory')"
                                    @disabled(! $dashboardCanOpenInventory)
                                >
                                    {{ __('Open Inventory') }}
                                </button>
                            </div>
                        </div>
                        <div class="metin2-side-bottom"></div>
                    </section>
                </aside>
            </div>
        </div>

        @if ($showChatPanel)
            <section id="dashboard-chat-overlay" class="dashboard-overlay-panel" x-show="activePanel === 'chat'" x-cloak>
                <div class="dashboard-overlay-backdrop" @click="closePanel()"></div>

                <div class="dashboard-overlay-window dashboard-chat-window">
                    <div class="dashboard-overlay-head">
                        <div>
                            <p class="panel-kicker">{{ __('Communication') }}</p>
                            <h2 class="dashboard-overlay-title">{{ __('Chat and Whispers') }}</h2>
                        </div>

                        <button type="button" class="dashboard-overlay-close" @click="closePanel()">
                            {{ __('Close') }}
                        </button>
                    </div>

                    <div class="dashboard-overlay-scroll dashboard-chat-scroll">
                        <article class="game-panel">
                            <div class="chat-dock-header">
                                <div>
                                    <p class="panel-kicker">{{ __('Realm Channels') }}</p>
                                    <h3 class="mt-2 font-[Cinzel] text-2xl tracking-[0.12em] text-amber-50">{{ __('Choose Your Channel') }}</h3>
                                </div>

                                <div class="chat-dock-controls">
                                    <div class="chat-dock-tabs">
                                        <button
                                            type="button"
                                            class="chat-dock-tab"
                                            :class="{ 'chat-dock-tab-active': chatTab === 'global' }"
                                            @click="chatTab = 'global'"
                                        >
                                            {{ __('Global Chat') }}
                                        </button>
                                        @if ($whispersUnlocked)
                                            <button
                                                type="button"
                                                class="chat-dock-tab"
                                                :class="{ 'chat-dock-tab-active': chatTab === 'whisper' }"
                                                @click="chatTab = 'whisper'"
                                            >
                                                {{ __('Whisper') }}
                                            </button>
                                        @else
                                            <span class="chat-dock-tab opacity-60">
                                                {{ __('Whisper Lv 3') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div x-show="chatTab === 'global'" x-cloak class="chat-dock-pane mt-6" id="realm-chat">
                                <form method="POST" action="{{ route('game.actions.store', ['action' => 'global-chat']) }}" class="chat-dock-form">
                                    @csrf
                                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                    <textarea name="message" rows="4" class="theme-input" placeholder="{{ __('Post to the whole realm...') }}" required maxlength="280">{{ old('message') }}</textarea>
                                    <div class="flex justify-end">
                                        <button type="submit" class="action-button">
                                            {{ __('Send Global') }}
                                        </button>
                                    </div>
                                </form>

                                <div class="chat-dock-feed">
                                    @forelse ($globalMessages as $message)
                                        <div class="paper-note">
                                            <div class="flex items-center justify-between gap-3">
                                                <p class="text-sm uppercase tracking-[0.22em] text-amber-900/70">
                                                    {{ $message->sender->name }}
                                                </p>
                                                <p class="text-xs text-stone-600">{{ $message->created_at->diffForHumans() }}</p>
                                            </div>
                                            <p class="mt-2 text-sm leading-6 text-stone-800">{{ $message->body }}</p>
                                        </div>
                                    @empty
                                        <p class="text-sm text-stone-400">{{ __('No global messages yet.') }}</p>
                                    @endforelse
                                </div>

                                @if (! $whispersUnlocked)
                                    <div class="mt-4 rounded-[1rem] border border-dashed border-amber-500/18 bg-stone-950/25 px-4 py-4 text-sm leading-6 text-stone-300">
                                        {{ __('Whispers unlock at level 3. Use the realm chat for now, keep moving through the starter questline, and direct messages will open once the town trusts you more.') }}
                                    </div>
                                @endif
                            </div>

                            @if ($whispersUnlocked)
                                <div x-show="chatTab === 'whisper'" x-cloak class="chat-dock-pane mt-6" id="personal-chat">
                                    <form method="POST" action="{{ route('game.actions.store', ['action' => 'whisper']) }}" class="chat-dock-form">
                                        @csrf
                                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                        <input type="text" name="recipient_name" value="{{ old('recipient_name') }}" class="theme-input" placeholder="{{ __('Character name') }}" required maxlength="20">
                                        <textarea name="message" rows="4" class="theme-input" placeholder="{{ __('Send a private whisper...') }}" required maxlength="280">{{ old('message') }}</textarea>
                                        <div class="flex justify-end">
                                            <button type="submit" class="action-button">
                                                {{ __('Send Whisper') }}
                                            </button>
                                        </div>
                                    </form>

                                    <div class="chat-dock-feed">
                                        @forelse ($whispers as $message)
                                            <div class="paper-note">
                                                <div class="flex items-center justify-between gap-3">
                                                    <p class="text-sm uppercase tracking-[0.22em] text-amber-900/70">
                                                        {{ $message->sender_character_id === $character->id ? __('To :name', ['name' => $message->recipient->name]) : __('From :name', ['name' => $message->sender->name]) }}
                                                    </p>
                                                    <p class="text-xs text-stone-600">{{ $message->created_at->diffForHumans() }}</p>
                                                </div>
                                                <p class="mt-2 text-sm leading-6 text-stone-800">{{ $message->body }}</p>
                                            </div>
                                        @empty
                                            <p class="text-sm text-stone-400">{{ __('No whispers yet.') }}</p>
                                        @endforelse
                                    </div>
                                </div>
                            @endif
                        </article>
                    </div>
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
