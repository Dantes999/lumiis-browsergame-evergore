@php
    $authUser = Auth::user();
    $activeCharacterResolver = app(\App\Services\Game\ActiveCharacterResolver::class);
    $currentCharacter = $activeCharacterResolver->current($authUser, request(), [
            'location',
            'equipment.characterItem.item',
            'equipment.characterItem.bonuses',
            'learnedSkills.skill',
    ]);
    $navbarMaxHealth = $currentCharacter?->effectiveMaxHealth() ?? 0;
    $navbarMaxSp = $currentCharacter?->effectiveMaxSp() ?? 0;
    $navbarMaxStamina = $currentCharacter?->effectiveMaxStamina() ?? 0;
    $displayNavbarHealth = $displayNavbarHealth ?? ($currentCharacter ? (int) $currentCharacter->health : 0);
    $displayNavbarSp = $displayNavbarSp ?? ($currentCharacter ? (int) $currentCharacter->sp : 0);
    $displayNavbarStamina = $displayNavbarStamina ?? ($currentCharacter ? (int) $currentCharacter->stamina : 0);
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
                <a
                    href="{{ route('dashboard') }}"
                    @class(['theme-primary-button public-site-cta-link', 'is-active' => request()->routeIs('dashboard', 'game.dashboard')])
                    @click="if($event.metaKey || $event.ctrlKey || $event.shiftKey || $event.altKey) return; $event.preventDefault(); window.dispatchEvent(new CustomEvent('dashboard-navigate-home')); setTimeout(() => { window.location.assign(@json(route('dashboard'))); }, 20);"
                >
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
                data-action-lock-seconds-remaining="{{ $currentCharacter->cooldownSecondsRemaining() }}"
                data-action-lock-title="{{ config('app.name').' · '.$currentCharacter->name }}"
                data-action-lock-sync-document-title="{{ request()->routeIs('dashboard', 'game.dashboard') ? '1' : '0' }}"
                data-action-lock-ready-label="{{ __('Ready') }}"
                data-action-lock-cooldown-label="{{ __('Cooldown Active') }}"
                data-action-lock-open-label="{{ __('Action Lock Open') }}"
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
                        <span class="portal-navbar-stat-value" data-action-lock-countdown-target>
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
                    <div class="portal-navbar-stat portal-navbar-stat-gauge"
                         data-resource-gauge
                         data-resource-key="stamina"
                         data-current="{{ (int) $displayNavbarStamina }}"
                         data-maximum="{{ $navbarMaxStamina }}"
                         data-next-tick-seconds="{{ $staminaNavbarNextTickSeconds === null ? 'null' : (int) $staminaNavbarNextTickSeconds }}"
                         data-interval-seconds="600"
                         data-points-per-tick="1"
                         data-labels-full-short="{{ __('Full') }}"
                         data-labels-minutes-short="{{ __('min') }}">
                        <div class="portal-navbar-stat-head">
                            <span class="portal-navbar-stat-label">{{ __('Stamina') }}</span>
                            <span class="portal-navbar-stat-value portal-navbar-stat-value-inline" data-resource-value-target>
                                {{ number_format((int) $displayNavbarStamina) }}/{{ number_format($navbarMaxStamina) }}
                            </span>
                        </div>
                        <div class="portal-resource-gauge portal-resource-gauge-stamina" aria-hidden="true">
                            <span class="portal-resource-gauge-fill" data-resource-fill style="width: {{ $navbarStaminaPercent }}%;"></span>
                        </div>
                        <span class="portal-navbar-stat-note" data-resource-nexttick-target>{{ $staminaNavbarCompactTickLabel }}</span>
                    </div>
                    <div class="portal-navbar-stat portal-navbar-stat-gauge"
                         data-resource-gauge
                         data-resource-key="health"
                         data-current="{{ (int) $displayNavbarHealth }}"
                         data-maximum="{{ $navbarMaxHealth }}"
                         data-next-tick-seconds="{{ $healthNavbarNextTickSeconds === null ? 'null' : (int) $healthNavbarNextTickSeconds }}"
                         data-interval-seconds="300"
                         data-points-per-tick="{{ $navbarHealthPointsPerTick }}"
                         data-labels-full-short="{{ __('Full') }}"
                         data-labels-minutes-short="{{ __('min') }}">
                        <div class="portal-navbar-stat-head">
                            <span class="portal-navbar-stat-label">{{ __('HP') }}</span>
                            <span class="portal-navbar-stat-value portal-navbar-stat-value-inline" data-resource-value-target>
                                {{ number_format((int) $displayNavbarHealth) }}/{{ number_format($navbarMaxHealth) }}
                            </span>
                        </div>
                        <div class="portal-resource-gauge portal-resource-gauge-health" aria-hidden="true">
                            <span class="portal-resource-gauge-fill" data-resource-fill style="width: {{ $navbarHealthPercent }}%;"></span>
                        </div>
                    </div>
                    <div class="portal-navbar-stat portal-navbar-stat-gauge"
                         data-resource-gauge
                         data-resource-key="sp"
                         data-current="{{ (int) $displayNavbarSp }}"
                         data-maximum="{{ $navbarMaxSp }}"
                         data-next-tick-seconds="{{ $spNavbarNextTickSeconds === null ? 'null' : (int) $spNavbarNextTickSeconds }}"
                         data-interval-seconds="180"
                         data-points-per-tick="{{ $navbarSpPointsPerTick }}"
                         data-labels-full-short="{{ __('Full') }}"
                         data-labels-minutes-short="{{ __('min') }}">
                        <div class="portal-navbar-stat-head">
                            <span class="portal-navbar-stat-label">{{ __('SP') }}</span>
                            <span class="portal-navbar-stat-value portal-navbar-stat-value-inline" data-resource-value-target>
                                {{ number_format((int) $displayNavbarSp) }}/{{ number_format($navbarMaxSp) }}
                            </span>
                        </div>
                        <div class="portal-resource-gauge portal-resource-gauge-sp" aria-hidden="true">
                            <span class="portal-resource-gauge-fill" data-resource-fill style="width: {{ $navbarSpPercent }}%;"></span>
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
                        data-open-panel="character"
                        @disabled(! $navbarCanOpenCharacter)
                    >
                        {{ __('Character') }}
                    </button>

                    <button
                        type="button"
                        class="theme-button theme-button-primary theme-button-compact"
                        data-open-panel="skills"
                        @disabled(! $navbarCanOpenSkills)
                    >
                        {{ __('Skills') }}
                    </button>

                    <button
                        type="button"
                        class="theme-button theme-button-primary theme-button-compact"
                        data-open-panel="quests"
                        @disabled(! $navbarCanOpenQuests)
                    >
                        {{ __('Quests') }}
                    </button>

                    <button
                        type="button"
                        class="theme-button theme-button-primary theme-button-compact"
                        data-open-panel="inventory"
                        @disabled(! $navbarCanOpenInventory)
                    >
                        {{ __('Inventory') }}
                    </button>

                    <button
                        type="button"
                        class="theme-button theme-button-primary theme-button-compact"
                        data-open-panel="equipment"
                        @disabled(! $navbarCanOpenEquipment)
                    >
                        {{ __('Equipment') }}
                    </button>

                    <!--<button
                        type="button"
                        class="theme-button theme-button-primary theme-button-compact dashboard-mobile-window-pill"
                        onclick="window.dispatchEvent(new CustomEvent('dashboard-panel-open', { detail: 'mobile-info' }));"
                        @disabled(! $navbarCanOpenMobileInfo)
                    >
                        {{ __('Info') }}
                    </button>-->

                    @if ($navbarCanOpenSettings)
                        <button
                            type="button"
                            class="theme-button theme-button-primary theme-button-compact"
                            data-open-panel="settings"
                        >
                            {{ __('Settings') }}
                        </button>
                    @endif

                    @if ($navbarCanOpenChat)
                        <button
                            type="button"
                            class="theme-button theme-button-primary theme-button-compact"
                            data-open-panel="chat"
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
                                onclick="window.dispatchEvent(new CustomEvent('dashboard-active-battle-link', { detail: { href: '{{ $charactersUrl }}' } }));"
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
