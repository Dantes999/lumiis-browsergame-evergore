<section id="dashboard-settings-overlay" class="dashboard-overlay-panel dashboard-map-overlay-panel" x-show="activePanel === 'settings'" x-cloak>
    <div class="dashboard-overlay-backdrop" @click="closePanel()"></div>

    <div class="dashboard-overlay-window dashboard-map-overlay-window">
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

<section id="dashboard-character-overlay" class="dashboard-overlay-panel dashboard-map-overlay-panel" x-show="activePanel === 'character'" x-cloak>
    <div class="dashboard-overlay-backdrop" @click="if (!tutorial?.active) closePanel()"></div>

    <div class="dashboard-overlay-window dashboard-map-overlay-window">
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

<section id="tutorial-inventory-overlay" class="dashboard-overlay-panel dashboard-overlay-panel-inventory dashboard-map-overlay-panel dashboard-map-overlay-panel-inventory" x-show="activePanel === 'inventory'" x-cloak>
    <div class="dashboard-overlay-backdrop" @click="if (!tutorial?.active) closePanel()"></div>

    <div class="dashboard-overlay-window dashboard-overlay-window-inventory dashboard-map-overlay-window">
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
                                <form id="tutorial-inventory-back-form" method="POST" action="{{ route('game.actions.store', ['action' => 'tutorial-previous']) }}">
                                    @csrf
                                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                    @if ($activeFocus)
                                        <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                                    @endif

                                    <button type="submit" class="action-button-muted">
                                        {{ __('Back') }}
                                    </button>
                                </form>
                            @endif

                            <form id="tutorial-inventory-next-form" method="POST" action="{{ route('game.actions.store', ['action' => 'tutorial-next']) }}">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                @if ($activeFocus)
                                    <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                                @endif

                                <button type="submit" class="action-button" @disabled(! $tutorialStarterGearReady)>
                                    {{ __('Continue') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </article>
            @endif

            <div
                class="inventory-workspace"
                x-data="{
                    inventorySelection: @js($inventorySelectionDefaultId),
                    equipmentInspector: @js($equipmentInspectorDefaultSlot),
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
                                {{ __('Consumable books currently carried in the bag.') }}
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
