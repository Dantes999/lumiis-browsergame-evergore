<footer class="public-site-footer">
    <div class="public-site-footer-inner public-surface">
        <div>
            <p class="public-kicker">Lumiis</p>
            <p class="public-footer-copy">{{ __('A redesigned realm shell with Metin2-inspired color language and modern interaction patterns.') }}</p>
        </div>

        <nav class="public-footer-links" aria-label="{{ __('Footer navigation') }}">
            <a href="{{ url('/') }}">{{ __('Overview') }}</a>
            <a href="{{ url('/#systems') }}">{{ __('Systems') }}</a>
            <a href="{{ url('/#gallery') }}">{{ __('Interface') }}</a>
            @auth
                <a href="{{ route('dashboard') }}">{{ __('Realm') }}</a>
                <a href="{{ route('profile.edit') }}">{{ __('Profile') }}</a>
            @else
                <a href="{{ route('register') }}">{{ __('Register') }}</a>
                <a href="{{ route('login') }}">{{ __('Login') }}</a>
            @endauth
        </nav>

        <p class="public-footer-meta">{{ __('Desktop, portrait and landscape layouts share the same system.') }}</p>
    </div>
</footer>
