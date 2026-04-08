@php
    $settingsRoute = Auth::check() && Auth::user()?->character()->exists()
        ? route('dashboard', ['panel' => 'settings'])
        : route('profile.edit');
    $quickLinks = Auth::check()
        ? [
            ['label' => __('Open Realm'), 'route' => route('dashboard')],
            ['label' => __('Settings'), 'route' => $settingsRoute],
            ['label' => __('Back Home'), 'route' => url('/')],
        ]
        : [
            ['label' => __('Start Page'), 'route' => url('/')],
            ['label' => __('Register'), 'route' => route('register')],
            ['label' => __('Login'), 'route' => route('login')],
        ];

    $designNotes = [
        [
            'label' => __('Palette'),
            'value' => __('Ember reds, lacquer black and aged gold stay intact.'),
        ],
        [
            'label' => __('Layout'),
            'value' => __('Desktop gets room to breathe; portrait and landscape stay fast.'),
        ],
        [
            'label' => __('Assets'),
            'value' => __('The shell is ready for your replacement buttons and containers later.'),
        ],
    ];
@endphp

<div class="public-side-rail">
    <section class="public-surface public-side-card">
        <p class="public-kicker">{{ __('Access') }}</p>
        <h2 class="public-panel-title">{{ Auth::check() ? __('Resume your realm session') : __('Built for quick entry') }}</h2>
        <p class="public-panel-copy">
            {{ Auth::check()
                ? __('The new shell keeps the realm close and secondary account tasks out of the way.')
                : __('The entry flow stays compact, readable and touch-safe across every breakpoint.') }}
        </p>

        <div class="public-link-cluster">
            @foreach ($quickLinks as $quickLink)
                <a href="{{ $quickLink['route'] }}" class="public-link-chip">{{ $quickLink['label'] }}</a>
            @endforeach
        </div>
    </section>

    <section class="public-surface public-side-card">
        <p class="public-kicker">{{ __('UI Direction') }}</p>

        <div class="public-mini-stack">
            @foreach ($designNotes as $designNote)
                <article class="public-mini-card">
                    <span class="public-mini-label">{{ $designNote['label'] }}</span>
                    <p class="public-mini-copy">{{ $designNote['value'] }}</p>
                </article>
            @endforeach
        </div>
    </section>
</div>
