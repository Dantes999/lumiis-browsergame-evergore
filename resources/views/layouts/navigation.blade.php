@php
    $authUser = Auth::user();
    $activeCharacterResolver = app(\App\Services\Game\ActiveCharacterResolver::class);
    $currentCharacter = $activeCharacterResolver->current($authUser, request(), [
            'location',
            'equipment.characterItem.item',
            'equipment.characterItem.bonuses',
            'learnedSkills.skill',
        ]);
    $navbarMoment = now();
    $staminaNavbarPreview = $currentCharacter?->staminaRecoveryState($navbarMoment);
    $healthNavbarPreview = $currentCharacter?->healthRecoveryState($navbarMoment);
    $spNavbarPreview = $currentCharacter?->spRecoveryState($navbarMoment);
    $displayNavbarStamina = $staminaNavbarPreview['stamina'] ?? $currentCharacter?->stamina;
    $displayNavbarHealth = $healthNavbarPreview['health'] ?? $currentCharacter?->health;
    $displayNavbarSp = $spNavbarPreview['sp'] ?? $currentCharacter?->effectiveCurrentSp();
    $navbarMaxStamina = $currentCharacter?->effectiveMaxStamina() ?? 0;
    $navbarMaxHealth = $currentCharacter?->effectiveMaxHealth() ?? 0;
    $navbarMaxSp = $currentCharacter?->effectiveMaxSp() ?? 0;
    $staminaNavbarNextTickSeconds = $staminaNavbarPreview['next_tick_seconds'] ?? null;
    $healthNavbarNextTickSeconds = $healthNavbarPreview['next_tick_seconds'] ?? null;
    $spNavbarNextTickSeconds = $spNavbarPreview['next_tick_seconds'] ?? null;
    $navbarHealthPointsPerTick = $currentCharacter ? max(2, (int) ceil($navbarMaxHealth * 0.05)) : 0;
    $navbarSpPointsPerTick = $currentCharacter ? max(2, (int) ceil($navbarMaxSp * 0.15)) : 0;
    $navbarHealthPercent = $navbarMaxHealth > 0
        ? max(0, min(100, round((((int) $displayNavbarHealth) / $navbarMaxHealth) * 100, 2)))
        : 0;
    $navbarSpPercent = $navbarMaxSp > 0
        ? max(0, min(100, round((((int) $displayNavbarSp) / $navbarMaxSp) * 100, 2)))
        : 0;
    $navbarStaminaPercent = $navbarMaxStamina > 0
        ? max(0, min(100, round((((int) $displayNavbarStamina) / $navbarMaxStamina) * 100, 2)))
        : 0;
    $staminaNavbarCompactTickLabel = $staminaNavbarNextTickSeconds === null
        ? __('Full')
        : ceil($staminaNavbarNextTickSeconds / 60).' '.__('min');
    $navbarTutorialActive = (bool) $currentCharacter?->isInTutorial();
    $navbarTutorialStep = (int) ($currentCharacter?->tutorial_step ?? 0);
    $navbarCanOpenCharacter = $currentCharacter && (! $navbarTutorialActive || $navbarTutorialStep === 1);
    $navbarCanOpenSkills = $currentCharacter && ! $navbarTutorialActive;
    $navbarCanOpenInventory = $currentCharacter && (! $navbarTutorialActive || $navbarTutorialStep === 6);
    $navbarCanOpenQuests = $currentCharacter && ! $navbarTutorialActive;
    $navbarCanOpenEquipment = $currentCharacter && ! $navbarTutorialActive;
    $navbarCanOpenChat = $currentCharacter && ! $navbarTutorialActive && $currentCharacter->globalChatUnlocked();
    $navbarCanOpenSettings = $currentCharacter && ! $navbarTutorialActive;
    $navbarCanOpenMobileInfo = $currentCharacter && ! $navbarTutorialActive;
    $settingsUrl = $currentCharacter ? route('dashboard', ['panel' => 'settings']) : route('profile.edit');
    $charactersUrl = route('characters.index');
    $settingsLinkIsActive = request()->routeIs('profile.*')
        || (request()->routeIs('dashboard', 'game.dashboard') && request()->query('panel') === 'settings');
    $useClassicDashboardHeader = request()->routeIs('dashboard', 'game.dashboard', 'profile.*') && $currentCharacter !== null;
    $showDashboardPanelButtons = request()->routeIs('dashboard', 'game.dashboard') && $currentCharacter !== null;
    $useUtilityPublicHeader = ! $useClassicDashboardHeader;
@endphp

@if ($useUtilityPublicHeader)
    <div class="public-site-header-wrap utility-site-header-wrap">
        <header class="public-site-header public-surface utility-site-header">
            <a class="public-site-brand" href="{{ url('/') }}">
                <span class="public-site-brand-mark" aria-hidden="true">L</span>
                <span class="public-site-brand-copy">
                    <span class="public-site-brand-name">Lumiis</span>
                    <span class="public-site-brand-tag">
                        {{ $currentCharacter?->name ?? __('Browser Realm') }}
                    </span>
                </span>
            </a>

            <nav class="public-site-nav" aria-label="{{ __('Authenticated navigation') }}">
                <a href="{{ url('/') }}" class="public-site-nav-link">{{ __('Overview') }}</a>
                <a href="{{ url('/#systems') }}" class="public-site-nav-link">{{ __('Systems') }}</a>
                <a href="{{ url('/#gallery') }}" class="public-site-nav-link">{{ __('Interface') }}</a>
            </nav>

            <div class="public-site-actions utility-site-actions">
                <a href="{{ route('dashboard') }}" @class(['theme-primary-button public-site-cta-link', 'is-active' => request()->routeIs('dashboard', 'game.dashboard')])>
                    {{ __('Realm') }}
                </a>

                <a href="{{ $settingsUrl }}" @class(['theme-secondary-button public-site-cta-link', 'is-active' => $settingsLinkIsActive])>
                    {{ __('Settings') }}
                </a>

                @if (Auth::user()->is_admin)
                    <a href="{{ route('admin.dashboard') }}" @class(['theme-secondary-button public-site-cta-link', 'is-active' => request()->routeIs('admin.*')])>
                        {{ __('Admin') }}
                    </a>
                @endif

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button type="button" class="theme-secondary-button public-site-cta-link utility-site-logout-button">
                            {{ __('Exit') }}
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <a href="{{ $charactersUrl }}" class="theme-dropdown-link">
                            {{ __('Change Character') }}
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="theme-dropdown-link">
                                {{ __('Logout') }}
                            </button>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>
        </header>
    </div>
@else
    <nav @class(['portal-stage pb-0', 'dashboard-classic-header-wrapper' => $useClassicDashboardHeader])>
        <div
            @class(['portal-shell portal-navbar', 'dashboard-classic-header' => $useClassicDashboardHeader])
            @if ($currentCharacter)
                x-data="actionLock({
                    secondsRemaining: {{ $currentCharacter->cooldownSecondsRemaining() }},
                    title: @js(config('app.name').' · '.$currentCharacter->name),
                    syncDocumentTitle: @js(request()->routeIs('dashboard', 'game.dashboard')),
                    labels: {
                        ready: @js(__('Ready')),
                        cooldownActive: @js(__('Cooldown Active')),
                        actionLockOpen: @js(__('Action Lock Open')),
                    },
                })"
            @endif
        >
            <div class="portal-navbar-brand">
                <a href="{{ route('dashboard') }}" @class(['portal-navbar-brand-link', 'dashboard-classic-logo-link' => $useClassicDashboardHeader])>
                    <x-application-logo class="text-amber-100" />
                </a>

                <div class="portal-navbar-copy">
                    <p class="portal-navbar-title">{{ $currentCharacter?->name ?? Auth::user()->name }}</p>
                </div>
            </div>

            @if ($currentCharacter)
                <div class="portal-navbar-status">
                    <div class="portal-navbar-stat">
                        <span class="portal-navbar-stat-label">{{ __('Current Map') }}</span>
                        <span class="portal-navbar-stat-value">{{ $currentCharacter->location->name }}</span>
                    </div>
                    <div class="portal-navbar-stat">
                        <span class="portal-navbar-stat-label">{{ __('Action Lock') }}</span>
                        <span class="portal-navbar-stat-value" @if ($currentCharacter) x-text="formattedCountdown()" @endif>
                            {{ $currentCharacter->cooldownSecondsRemaining() > 0 ? sprintf('%02d:%02d', intdiv($currentCharacter->cooldownSecondsRemaining(), 60), $currentCharacter->cooldownSecondsRemaining() % 60) : __('Ready') }}
                        </span>
                    </div>
                    <div class="portal-navbar-stat portal-navbar-stat-currency">
                        <span class="portal-navbar-stat-label">{{ __('Yang') }}</span>
                        <span class="portal-navbar-stat-value portal-navbar-stat-value-with-icon">
                            <span class="portal-navbar-coin" aria-hidden="true"></span>
                            <span>{{ number_format($currentCharacter->yang) }}</span>
                        </span>
                    </div>
                    <div
                        class="portal-navbar-stat portal-navbar-stat-gauge"
                        x-data="resourceGauge({
                            current: {{ (int) $displayNavbarStamina }},
                            maximum: {{ $navbarMaxStamina }},
                            nextTickSeconds: {{ $staminaNavbarNextTickSeconds === null ? 'null' : (int) $staminaNavbarNextTickSeconds }},
                            intervalSeconds: 600,
                            pointsPerTick: 1,
                            labels: {
                                fullShort: @js(__('Full')),
                                minutesShort: @js(__('min')),
                            },
                        })"
                    >
                        <div class="portal-navbar-stat-head">
                            <span class="portal-navbar-stat-label">{{ __('Stamina') }}</span>
                            <span class="portal-navbar-stat-value portal-navbar-stat-value-inline" x-text="formattedValue()">
                                {{ number_format((int) $displayNavbarStamina) }}/{{ number_format($navbarMaxStamina) }}
                            </span>
                        </div>
                        <div class="portal-resource-gauge portal-resource-gauge-stamina" x-bind:class="{ 'is-refilling': isRefilling }" aria-hidden="true">
                            <span class="portal-resource-gauge-fill" x-bind:style="fillStyle()" style="width: {{ $navbarStaminaPercent }}%;"></span>
                        </div>
                        <span class="portal-navbar-stat-note" x-text="compactNextTick()">{{ $staminaNavbarCompactTickLabel }}</span>
                    </div>
                    <div
                        class="portal-navbar-stat portal-navbar-stat-gauge"
                        x-data="resourceGauge({
                            current: {{ (int) $displayNavbarHealth }},
                            maximum: {{ $navbarMaxHealth }},
                            nextTickSeconds: {{ $healthNavbarNextTickSeconds === null ? 'null' : (int) $healthNavbarNextTickSeconds }},
                            intervalSeconds: 300,
                            pointsPerTick: {{ $navbarHealthPointsPerTick }},
                            labels: {
                                fullShort: @js(__('Full')),
                                minutesShort: @js(__('min')),
                            },
                        })"
                    >
                        <div class="portal-navbar-stat-head">
                            <span class="portal-navbar-stat-label">{{ __('HP') }}</span>
                            <span class="portal-navbar-stat-value portal-navbar-stat-value-inline" x-text="formattedValue()">
                                {{ number_format((int) $displayNavbarHealth) }}/{{ number_format($navbarMaxHealth) }}
                            </span>
                        </div>
                        <div class="portal-resource-gauge portal-resource-gauge-health" x-bind:class="{ 'is-refilling': isRefilling }" aria-hidden="true">
                            <span class="portal-resource-gauge-fill" x-bind:style="fillStyle()" style="width: {{ $navbarHealthPercent }}%;"></span>
                        </div>
                    </div>
                    <div
                        class="portal-navbar-stat portal-navbar-stat-gauge"
                        x-data="resourceGauge({
                            current: {{ (int) $displayNavbarSp }},
                            maximum: {{ $navbarMaxSp }},
                            nextTickSeconds: {{ $spNavbarNextTickSeconds === null ? 'null' : (int) $spNavbarNextTickSeconds }},
                            intervalSeconds: 180,
                            pointsPerTick: {{ $navbarSpPointsPerTick }},
                            labels: {
                                fullShort: @js(__('Full')),
                                minutesShort: @js(__('min')),
                            },
                        })"
                    >
                        <div class="portal-navbar-stat-head">
                            <span class="portal-navbar-stat-label">{{ __('SP') }}</span>
                            <span class="portal-navbar-stat-value portal-navbar-stat-value-inline" x-text="formattedValue()">
                                {{ number_format((int) $displayNavbarSp) }}/{{ number_format($navbarMaxSp) }}
                            </span>
                        </div>
                        <div class="portal-resource-gauge portal-resource-gauge-sp" x-bind:class="{ 'is-refilling': isRefilling }" aria-hidden="true">
                            <span class="portal-resource-gauge-fill" x-bind:style="fillStyle()" style="width: {{ $navbarSpPercent }}%;"></span>
                        </div>
                    </div>
                </div>
            @endif

            <div class="portal-navbar-links">
                <a
                    href="{{ route('dashboard') }}"
                    @class([
                        'theme-button theme-button-primary theme-button-compact',
                        'theme-button-active' => request()->routeIs('dashboard', 'game.dashboard'),
                    ])
                >
                    {{ __('Realm') }}
                </a>

                @if ($showDashboardPanelButtons)
                    <button
                        id="tutorial-character-button"
                        type="button"
                        class="theme-button theme-button-primary theme-button-compact"
                        x-on:click="$dispatch('dashboard-panel-open', { panel: 'character' })"
                        @disabled(! $navbarCanOpenCharacter)
                    >
                        {{ __('Character') }}
                    </button>

                    <button
                        type="button"
                        class="theme-button theme-button-primary theme-button-compact"
                        x-on:click="$dispatch('dashboard-panel-open', { panel: 'skills' })"
                        @disabled(! $navbarCanOpenSkills)
                    >
                        {{ __('Skills') }}
                    </button>

                    <button
                        type="button"
                        class="theme-button theme-button-primary theme-button-compact"
                        x-on:click="$dispatch('dashboard-panel-open', { panel: 'quests' })"
                        @disabled(! $navbarCanOpenQuests)
                    >
                        {{ __('Quests') }}
                    </button>

                    <button
                        type="button"
                        class="theme-button theme-button-primary theme-button-compact"
                        x-on:click="$dispatch('dashboard-panel-open', { panel: 'inventory' })"
                        @disabled(! $navbarCanOpenInventory)
                    >
                        {{ __('Inventory') }}
                    </button>

                    <button
                        type="button"
                        class="theme-button theme-button-primary theme-button-compact"
                        x-on:click="$dispatch('dashboard-panel-open', { panel: 'equipment' })"
                        @disabled(! $navbarCanOpenEquipment)
                    >
                        {{ __('Equipment') }}
                    </button>

                    <!--<button
                        type="button"
                        class="theme-button theme-button-primary theme-button-compact dashboard-mobile-window-pill"
                        x-on:click="$dispatch('dashboard-panel-open', { panel: 'mobile-info' })"
                        @disabled(! $navbarCanOpenMobileInfo)
                    >
                        {{ __('Info') }}
                    </button>-->

                    @if ($navbarCanOpenSettings)
                        <button
                            type="button"
                            class="theme-button theme-button-primary theme-button-compact"
                            x-on:click="$dispatch('dashboard-panel-open', { panel: 'settings' })"
                        >
                            {{ __('Settings') }}
                        </button>
                    @endif

                    @if ($navbarCanOpenChat)
                        <button
                            type="button"
                            class="theme-button theme-button-primary theme-button-compact"
                            x-on:click="$dispatch('dashboard-panel-open', { panel: 'chat' })"
                        >
                            {{ __('Chat') }}
                        </button>
                    @endif
                @endif

                @if (Auth::user()->is_admin)
                    <a
                        href="{{ route('admin.dashboard') }}"
                        @class([
                            'theme-button theme-button-primary theme-button-compact',
                            'theme-button-active' => request()->routeIs('admin.*'),
                        ])
                    >
                        {{ __('Admin') }}
                    </a>
                @endif

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button type="button" class="theme-button theme-button-primary theme-button-compact">
                            {{ __('Exit') }}
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        @if (request()->routeIs('dashboard', 'game.dashboard'))
                            <button
                                type="button"
                                class="theme-dropdown-link"
                                x-on:click="$dispatch('dashboard-active-battle-link', { href: '{{ $charactersUrl }}' })"
                            >
                                {{ __('Change Character') }}
                            </button>
                        @else
                            <a href="{{ $charactersUrl }}" class="theme-dropdown-link">
                                {{ __('Change Character') }}
                            </a>
                        @endif

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="theme-dropdown-link">
                                {{ __('Logout') }}
                            </button>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </nav>
@endif
