@php
    $compactHeader = $compactHeader ?? false;
    $primaryAction = Auth::check()
        ? ['label' => __('Realm'), 'route' => route('dashboard')]
        : ['label' => __('Create Account'), 'route' => route('register')];
    $settingsRoute = Auth::check() && Auth::user()?->character()->exists()
        ? route('dashboard', ['panel' => 'settings'])
        : route('profile.edit');

    $secondaryAction = Auth::check()
        ? ['label' => __('Settings'), 'route' => $settingsRoute]
        : ['label' => __('Login'), 'route' => route('login')];

    $publicNavItems = [
        ['label' => __('Overview'), 'route' => url('/#overview'), 'active' => request()->route()?->uri() === '/'],
        ['label' => __('Systems'), 'route' => url('/#systems'), 'active' => false],
        ['label' => __('Interface'), 'route' => url('/#gallery'), 'active' => false],
    ];
    // Resolve the current locale the same way middleware does: prefer the
    // authenticated user's stored locale, then the session override, then
    // browser detection, finally the app fallback. Using `app()->getLocale()`
    // here ensures the value matches what the middleware set for the request.
    $currentLocalePreference = app()->getLocale();
@endphp

<div class="public-site-header-wrap">
    <header @class(['public-site-header public-surface', 'public-site-header-compact' => $compactHeader])>
        <a class="public-site-brand" href="{{ url('/') }}">
            <span class="public-site-brand-mark" aria-hidden="true">L</span>
            <span class="public-site-brand-copy">
                <span class="public-site-brand-name">Lumiis</span>
                <!--<span class="public-site-brand-tag">{{ __('Browser Realm') }}</span>-->
            </span>
            @guest()
                <span>
                    <form method="POST" action="{{ route('locale.update') }}" class="text-center">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="locale_preference" value="de">
                        <button type="submit" class="action-button w-full" @disabled($currentLocalePreference === 'de')>
                            🇩🇪
                        </button>
                    </form>
                </span>
                <span>
                    <form method="POST" action="{{ route('locale.update') }}" class="text-center">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="locale_preference" value="en">
                        <button type="submit" class="action-button w-full" @disabled($currentLocalePreference === 'en')>
                            🇬🇧
                        </button>
                    </form>
                </span>
            @endguest
        </a>
     

        @unless ($compactHeader)
            <nav class="public-site-nav" aria-label="{{ __('Primary navigation') }}">
                @foreach ($publicNavItems as $navItem)
                    <a
                        href="{{ $navItem['route'] }}"
                        @class(['public-site-nav-link', 'is-active' => $navItem['active']])
                    >
                        {{ $navItem['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="public-site-actions">
                <a href="{{ $secondaryAction['route'] }}" class="theme-secondary-button public-site-cta-link">
                    {{ $secondaryAction['label'] }}
                </a>

                <a href="{{ $primaryAction['route'] }}" class="theme-primary-button public-site-cta-link">
                    {{ $primaryAction['label'] }}
                </a>
            </div>
        @endunless
    </header>
</div>
