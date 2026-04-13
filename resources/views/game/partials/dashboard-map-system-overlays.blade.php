<section id="dashboard-settings-overlay" class="dashboard-overlay-panel dashboard-map-overlay-panel" x-show="activePanel === 'settings'" x-cloak>
    <div class="dashboard-overlay-backdrop" @click="closePanel()"></div>

    <div class="dashboard-overlay-window dashboard-map-overlay-window">
        <div class="dashboard-overlay-head">
            <div>
                <p class="panel-kicker">{{ __('Settings') }}</p>
                <h2 class="dashboard-overlay-title">{{ __('Settings') }}</h2>
                <p class="dashboard-overlay-subtitle">
                    {{ __('Manage your login, password and language settings here without switching to a separate desktop layout.') }}
                </p>
            </div>

            <button type="button" class="dashboard-overlay-close" @click="closePanel()">
                {{ __('Close') }}
            </button>
        </div>

        <div class="dashboard-overlay-scroll">
            <div class="grid w-full gap-4">
                <article class="game-panel">
                    @include('profile.partials.update-profile-information-form', ['user' => auth()->user()])
                </article>

                <article class="game-panel">
                    @include('profile.partials.update-password-form')
                </article>

                <article class="game-panel">
                    @include('profile.partials.delete-user-form')
                </article>
            </div>
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
                        <span class="stat-label">{{ __('Status Points') }}</span>
                        <span class="stat-value">{{ $character->unspent_status_points }}</span>
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
                        @if ($character->unspent_status_points > 0)
                            <form method="POST" action="{{ route('game.actions.store', ['action' => 'allocate-status-point']) }}" class="mt-3">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <input type="hidden" name="attribute" value="vitality">
                                @if ($activeFocus)
                                    <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                                @endif

                                <button type="submit" class="action-button-muted w-full">+1 VIT</button>
                            </form>
                        @endif
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">INT</span>
                        <span class="stat-value">{{ $character->effectiveIntelligence() }}</span>
                        @if ($character->unspent_status_points > 0)
                            <form method="POST" action="{{ route('game.actions.store', ['action' => 'allocate-status-point']) }}" class="mt-3">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <input type="hidden" name="attribute" value="intelligence">
                                @if ($activeFocus)
                                    <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                                @endif

                                <button type="submit" class="action-button-muted w-full">+1 INT</button>
                            </form>
                        @endif
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">STR</span>
                        <span class="stat-value">{{ $character->effectiveStrength() }}</span>
                        @if ($character->unspent_status_points > 0)
                            <form method="POST" action="{{ route('game.actions.store', ['action' => 'allocate-status-point']) }}" class="mt-3">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <input type="hidden" name="attribute" value="strength">
                                @if ($activeFocus)
                                    <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                                @endif

                                <button type="submit" class="action-button-muted w-full">+1 STR</button>
                            </form>
                        @endif
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">DEX</span>
                        <span class="stat-value">{{ $character->effectiveDexterity() }}</span>
                        @if ($character->unspent_status_points > 0)
                            <form method="POST" action="{{ route('game.actions.store', ['action' => 'allocate-status-point']) }}" class="mt-3">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <input type="hidden" name="attribute" value="dexterity">
                                @if ($activeFocus)
                                    <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                                @endif

                                <button type="submit" class="action-button-muted w-full">+1 DEX</button>
                            </form>
                        @endif
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

        </div>
    </div>
</section>

@if ($activeArea && ! $tutorialActive)
    <section id="dashboard-focus-overlay" class="dashboard-overlay-panel dashboard-map-overlay-panel" x-show="activePanel === 'focus'" x-cloak>
        <div class="dashboard-overlay-backdrop" @click="closePanel()"></div>

        <div class="dashboard-overlay-window dashboard-map-overlay-window">
            <div class="dashboard-overlay-head">
                <div>
                    <p class="panel-kicker">{{ $activeArea->isService() ? __('Town Hotspot') : __('Hunting Ground') }}</p>
                    <h2 class="dashboard-overlay-title">{{ $activeArea->name }}</h2>
                    <p class="dashboard-overlay-subtitle">
                        {{ $activeArea->description }}
                    </p>
                </div>

                <div class="dashboard-overlay-head-actions">
                    @if ($activeArea->recommended_level)
                        <span class="dashboard-overlay-pill">
                            Lv {{ $activeArea->recommended_level }}+
                        </span>
                    @endif

                    <button type="button" class="dashboard-overlay-close" @click="closePanel()">
                        {{ __('Close') }}
                    </button>
                </div>
            </div>

            <div class="dashboard-overlay-scroll">
                <article class="game-panel">
                    <div class="character-stats-grid">
                        @foreach ($dashboardFocusFacts as $focusFact)
                            <div class="stat-card">
                                <span class="stat-label">{{ $focusFact['label'] }}</span>
                                <span class="stat-value stat-value-compact">{{ $focusFact['value'] }}</span>
                            </div>
                        @endforeach
                    </div>

                    @if ($activeArea->isHunting())
                        @include('game.partials.dashboard-focus-hunting-panel')
                    @else
                        @include('game.partials.dashboard-focus-service-panel')
                    @endif
                </article>
            </div>
        </div>
    </section>
@endif

<section id="dashboard-skills-overlay" class="dashboard-overlay-panel dashboard-map-overlay-panel" x-show="activePanel === 'skills'" x-cloak>
    <div class="dashboard-overlay-backdrop" @click="closePanel()"></div>

    <div class="dashboard-overlay-window dashboard-map-overlay-window">
        <div class="dashboard-overlay-head">
            <div>
                <p class="panel-kicker">{{ __('Skills') }}</p>
                <h2 class="dashboard-overlay-title">{{ __('Skills') }}</h2>
            </div>

            <button type="button" class="dashboard-overlay-close" @click="closePanel()">
                {{ __('Close') }}
            </button>
        </div>

        <div class="dashboard-overlay-scroll">
            @include('game.partials.skill-panel')
        </div>
    </div>
</section>

<section id="dashboard-quests-overlay" class="dashboard-overlay-panel dashboard-map-overlay-panel" x-show="activePanel === 'quests'" x-cloak>
    <div class="dashboard-overlay-backdrop" @click="closePanel()"></div>

    <div class="dashboard-overlay-window dashboard-map-overlay-window">
        <div class="dashboard-overlay-head">
            <div>
                <p class="panel-kicker">{{ __('Quests') }}</p>
                <h2 class="dashboard-overlay-title">{{ __('Questlog') }}</h2>
            </div>

            <button type="button" class="dashboard-overlay-close" @click="closePanel()">
                {{ __('Close') }}
            </button>
        </div>

        <div class="dashboard-overlay-scroll">
            @include('game.partials.dashboard-questlog-panel')
        </div>
    </div>
</section>

<section id="dashboard-equipment-overlay" class="dashboard-overlay-panel dashboard-overlay-panel-inventory dashboard-map-overlay-panel dashboard-map-overlay-panel-inventory" x-show="activePanel === 'equipment'" x-cloak>
    <div class="dashboard-overlay-backdrop" @click="closePanel()"></div>

    <div class="dashboard-overlay-window dashboard-overlay-window-inventory dashboard-map-overlay-window">
        <div class="dashboard-overlay-head dashboard-overlay-head-inventory">
            <div>
                <p class="panel-kicker">{{ __('Equipment') }}</p>
                <h2 class="dashboard-overlay-title">{{ __('Equipment') }}</h2>
            </div>

            <div class="dashboard-overlay-head-actions">
                <button type="button" class="dashboard-overlay-close" @click="closePanel()">
                    {{ __('Close') }}
                </button>
            </div>
        </div>

        <div class="dashboard-overlay-scroll dashboard-overlay-scroll-inventory">
            <div class="inventory-workspace">
                @include('game.partials.dashboard-equipment-workspace', [
                    'showPanelHead' => false,
                ])
            </div>
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
                    {{ __('Manage bag items, consumables and sell-ready drops from one contained workspace.') }}
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
                x-data="inventoryList({
                    totalItems: {{ $bagItems->count() }},
                    perPage: 10,
                })"
            >
                @if ($tutorialActive && $tutorialStep === 6)
                    <div class="inventory-modal-main">
                        @include('game.partials.dashboard-equipment-workspace', [
                            'articleId' => 'tutorial-equipment-panel',
                        ])
                    </div>
                @endif

                <article class="game-panel inventory-modern-panel inventory-list-panel">
                    <div class="dashboard-section-head dashboard-section-head-modern">
                        <div>
                            <p class="panel-kicker">{{ __('Bag Contents') }}</p>
                            <h2 class="dashboard-section-title">{{ __('Inventory List') }}</h2>
                        </div>
                        <span class="dashboard-section-badge">{{ __('Live actions') }}</span>
                    </div>

                    @if ($bagItems->isNotEmpty())
                        <div class="inventory-list-pagination mt-6">
                            <p class="inventory-list-pagination-copy">
                                <span x-text="(startIndex() + 1).toLocaleString()">{{ number_format($bagItems->isEmpty() ? 0 : 1) }}</span>
                                -
                                <span x-text="endIndex().toLocaleString()">{{ number_format(min(10, $bagItems->count())) }}</span>
                                /
                                {{ number_format($bagItems->count()) }}
                            </p>

                            <div class="inventory-list-pagination-controls">
                                <button
                                    type="button"
                                    class="action-button-muted"
                                    @click="previousPage()"
                                    x-bind:disabled="currentPage <= 1"
                                >
                                    {{ __('Previous') }}
                                </button>

                                <span class="inventory-list-page-indicator">
                                    {{ __('Page') }}
                                    <span x-text="currentPage">1</span>
                                    /
                                    <span x-text="totalPages()">1</span>
                                </span>

                                <button
                                    type="button"
                                    class="action-button-muted"
                                    @click="nextPage()"
                                    x-bind:disabled="currentPage >= totalPages()"
                                >
                                    {{ __('Next') }}
                                </button>
                            </div>
                        </div>

                        <div class="inventory-list-rows mt-6">
                            @foreach ($bagItems as $inventoryItem)
                                @php
                                    $item = $inventoryItem->item;
                                    $isBiologistSupportItem = in_array($item->slug, ['research-catalyst', 'research-report'], true);
                                    $isSkillSupportMaterial = $item->slug === \App\Support\Game\MetinClassCatalog::soulStoneSlug();
                                    $linkedSkill = $item->linkedSkillDefinition();
                                @endphp

                                <article
                                    class="inventory-list-row"
                                    x-show="isVisible({{ $loop->index }})"
                                    x-cloak
                                    x-bind:class="{ 'inventory-list-row-open': isInfoOpen({{ $inventoryItem->id }}) }"
                                >
                                    <div class="inventory-list-row-header">
                                        <div class="inventory-list-row-main">
                                            <span class="inventory-list-row-art" aria-hidden="true">
                                                @if (filled($inventoryItem->iconPath()))
                                                    <img
                                                        src="{{ asset($inventoryItem->iconPath()) }}"
                                                        alt=""
                                                        class="inventory-list-row-icon"
                                                    >
                                                @else
                                                    <span class="inventory-detail-fallback">{{ strtoupper(substr($item->name, 0, 1)) }}</span>
                                                @endif
                                            </span>

                                            <div class="inventory-list-row-copy">
                                                <div class="inventory-list-row-titleline">
                                                    <h3 class="inventory-list-row-title">{{ $inventoryItem->displayName() }}</h3>
                                                    <span class="inventory-list-row-count">x{{ number_format($inventoryItem->quantity) }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="inventory-list-row-actions">
                                            @if ($item->isSkillBook())
                                                <form method="POST" action="{{ route('game.actions.store', ['action' => 'use-item']) }}" class="inventory-list-row-action-form">
                                                    @csrf
                                                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                                    <input type="hidden" name="character_item_id" value="{{ $inventoryItem->id }}">
                                                    @if ($activeFocus)
                                                        <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                                                    @endif

                                                    <button type="submit" class="action-button">
                                                        {{ __('Study Book') }}
                                                    </button>
                                                </form>
                                            @elseif ($item->isConsumable() && ! $isBiologistSupportItem)
                                                <form method="POST" action="{{ route('game.actions.store', ['action' => 'use-item']) }}" class="inventory-list-row-action-form">
                                                    @csrf
                                                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                                    <input type="hidden" name="character_item_id" value="{{ $inventoryItem->id }}">
                                                    @if ($activeFocus)
                                                        <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                                                    @endif

                                                    <button type="submit" class="action-button">
                                                        {{ __('Use') }}
                                                    </button>
                                                </form>
                                            @endif

                                            @if ($item->isEquippable())
                                                <form method="POST" action="{{ route('game.actions.store', ['action' => 'equip-item']) }}" class="inventory-list-row-action-form">
                                                    @csrf
                                                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                                    <input type="hidden" name="character_item_id" value="{{ $inventoryItem->id }}">
                                                    @if ($activeFocus)
                                                        <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                                                    @endif

                                                    <button
                                                        type="submit"
                                                        class="action-button"
                                                        @disabled($character->level < $item->required_level)
                                                    >
                                                        {{ __('Equip') }}
                                                    </button>
                                                </form>
                                            @endif

                                            @if ($inventorySellEnabled && ! $inventoryItem->isEquipped() && (int) $item->sell_price > 0)
                                                <form method="POST" action="{{ route('game.actions.store', ['action' => 'sell-item']) }}" class="inventory-list-row-action-form inventory-list-row-action-form-sell">
                                                    @csrf
                                                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                                    <input type="hidden" name="location_area_id" value="{{ $inventorySellVendorArea->id }}">
                                                    <input type="hidden" name="character_item_id" value="{{ $inventoryItem->id }}">
                                                    @if ($activeFocus)
                                                        <input type="hidden" name="redirect_focus" value="{{ $activeFocus }}">
                                                    @endif

                                                    @if ($item->stackable && $inventoryItem->quantity > 1)
                                                        <label class="sr-only" for="inventory-list-sell-quantity-{{ $inventoryItem->id }}">
                                                            {{ __('Qty') }}
                                                        </label>
                                                        <input
                                                            id="inventory-list-sell-quantity-{{ $inventoryItem->id }}"
                                                            type="number"
                                                            name="quantity"
                                                            min="1"
                                                            max="{{ $inventoryItem->quantity }}"
                                                            value="1"
                                                            class="inventory-use-quantity inventory-list-row-quantity"
                                                        >
                                                    @endif

                                                    <button type="submit" class="action-button-muted">
                                                        {{ __('Sell') }}
                                                    </button>
                                                </form>
                                            @endif

                                            <button
                                                type="button"
                                                class="inventory-list-row-info-toggle"
                                                @click.stop="toggleInfo({{ $inventoryItem->id }})"
                                                x-bind:aria-expanded="isInfoOpen({{ $inventoryItem->id }})"
                                            >
                                                {{ __('Info') }}
                                            </button>
                                        </div>
                                    </div>

                                    <div class="inventory-list-row-details">
                                        @include('game.partials.inventory-list-row-details', ['inventoryItem' => $inventoryItem])
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="inventory-empty-state mt-6">
                            <p class="inventory-empty-title">{{ __('Your inventory is empty.') }}</p>
                            <p class="inventory-empty-copy">{{ __('Fight monsters or visit town services to fill the bag again.') }}</p>
                        </div>
                    @endif
                </article>
            </div>
        </div>
    </div>
    </div>
</section>
