@php
    $compactHeader = $compactHeader ?? false;
    $primaryAction = Auth::check()
        ? ['label' => __('Realm'), 'route' => route('dashboard')]
        : ['label' => __('Create Account'), 'route' => route('register')];

    $secondaryAction = Auth::check()
        ? ['label' => __('Profile'), 'route' => route('profile.edit')]
        : ['label' => __('Login'), 'route' => route('login')];

    $publicNavItems = [
        ['label' => __('Overview'), 'route' => url('/#overview'), 'active' => request()->route()?->uri() === '/'],
        ['label' => __('Systems'), 'route' => url('/#systems'), 'active' => false],
        ['label' => __('Interface'), 'route' => url('/#gallery'), 'active' => false],
    ];
@endphp

<div class="public-site-header-wrap">
    <header @class(['public-site-header public-surface', 'public-site-header-compact' => $compactHeader])>
        <a class="public-site-brand" href="{{ url('/') }}">
            <span class="public-site-brand-mark" aria-hidden="true">L</span>
            <span class="public-site-brand-copy">
                <span class="public-site-brand-name">Lumiis</span>
                <span class="public-site-brand-tag">{{ __('Browser Realm') }}</span>
            </span>
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
