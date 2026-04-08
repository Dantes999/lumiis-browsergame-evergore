@php
    $authUser = Auth::user();
    $currentCharacter = $authUser?->character()
        ->with([
            'location',
            'equipment.characterItem.item',
            'equipment.characterItem.bonuses',
            'learnedSkills.skill',
        ])
        ->first();
    $navbarMoment = now();
    $staminaNavbarPreview = $currentCharacter?->staminaRecoveryState($navbarMoment);
    $healthNavbarPreview = $currentCharacter?->healthRecoveryState($navbarMoment);
    $spNavbarPreview = $currentCharacter?->spRecoveryState($navbarMoment);
    $displayNavbarStamina = $staminaNavbarPreview['stamina'] ?? $currentCharacter?->stamina;
    $displayNavbarHealth = $healthNavbarPreview['health'] ?? $currentCharacter?->health;
    $displayNavbarSp = $spNavbarPreview['sp'] ?? $currentCharacter?->effectiveCurrentSp();
    $navbarTutorialActive = (bool) $currentCharacter?->isInTutorial();
    $navbarTutorialStep = (int) ($currentCharacter?->tutorial_step ?? 0);
    $navbarCanOpenCharacter = $currentCharacter && (! $navbarTutorialActive || $navbarTutorialStep === 1);
    $navbarCanOpenInventory = $currentCharacter && (! $navbarTutorialActive || $navbarTutorialStep === 6);
    $navbarCanOpenQuests = $currentCharacter && ! $navbarTutorialActive;
    $navbarCanOpenEquipment = $currentCharacter && ! $navbarTutorialActive;
    $navbarCanOpenChat = $currentCharacter && ! $navbarTutorialActive && $currentCharacter->globalChatUnlocked();
    $navbarCanOpenSettings = $currentCharacter && ! $navbarTutorialActive;
    $navbarCanOpenMobileInfo = $currentCharacter && ! $navbarTutorialActive;
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

                <a href="{{ route('profile.edit') }}" @class(['theme-secondary-button public-site-cta-link', 'is-active' => request()->routeIs('profile.*')])>
                    {{ __('Profile') }}
                </a>

                @if (Auth::user()->is_admin)
                    <a href="{{ route('admin.dashboard') }}" @class(['theme-secondary-button public-site-cta-link', 'is-active' => request()->routeIs('admin.*')])>
                        {{ __('Admin') }}
                    </a>
                @endif

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="theme-secondary-button public-site-cta-link utility-site-logout-button">
                        {{ __('Logout') }}
                    </button>
                </form>
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
                    <div class="portal-navbar-stat">
                        <span class="portal-navbar-stat-label">{{ __('Yang') }}</span>
                        <span class="portal-navbar-stat-value">{{ number_format($currentCharacter->yang) }}</span>
                    </div>
                    <div class="portal-navbar-stat">
                        <span class="portal-navbar-stat-label">{{ __('Stamina') }}</span>
                        <span class="portal-navbar-stat-value">
                            {{ number_format((int) $displayNavbarStamina) }}/{{ number_format($currentCharacter->effectiveMaxStamina()) }}
                        </span>
                    </div>
                    <div class="portal-navbar-stat">
                        <span class="portal-navbar-stat-label">{{ __('HP') }}</span>
                        <span class="portal-navbar-stat-value">
                            {{ number_format((int) $displayNavbarHealth) }}/{{ number_format($currentCharacter->effectiveMaxHealth()) }}
                        </span>
                    </div>
                    <div class="portal-navbar-stat">
                        <span class="portal-navbar-stat-label">{{ __('SP') }}</span>
                        <span class="portal-navbar-stat-value">
                            {{ number_format((int) $displayNavbarSp) }}/{{ number_format($currentCharacter->effectiveMaxSp()) }}
                        </span>
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

                    <button
                        type="button"
                        class="theme-button theme-button-primary theme-button-compact dashboard-mobile-window-pill"
                        x-on:click="$dispatch('dashboard-panel-open', { panel: 'mobile-info' })"
                        @disabled(! $navbarCanOpenMobileInfo)
                    >
                        {{ __('Info') }}
                    </button>

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

                <a
                    href="{{ route('profile.edit') }}"
                    @class([
                        'theme-button theme-button-primary theme-button-compact',
                        'theme-button-active' => request()->routeIs('profile.*'),
                    ])
                >
                    {{ __('Profile') }}
                </a>

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

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="theme-button theme-button-primary theme-button-compact">
                        {{ __('Logout') }}
                    </button>
                </form>
            </div>
        </div>
    </nav>
@endif
