@props([
    'footerUrl' => null,
    'footerLabel' => null,
])

<div class="form-card flex flex-col justify-between gap-4">
    <div>
        <p class="action-title">{{ __('Rest Break Menu') }}</p>
        <p class="action-copy">
            {{ __('This is a placeholder menu for the waiting time while your character is resting.') }}
        </p>
    </div>

    <div class="theme-paper-panel px-4 py-4">
        <p class="text-[0.68rem] uppercase tracking-[0.22em] text-amber-900/70">{{ __('Future mini-game slot') }}</p>
        <p class="mt-3 text-sm leading-6 text-stone-700">
            {{ __('Later, this panel can host a lightweight mini-game during the rest timer, similar to the Chrome offline T-Rex game. For now this description marks the reserved slot.') }}
        </p>

        <div class="mt-4 space-y-2 text-sm leading-6 text-stone-700">
            <p>{{ __('Menu placeholder: Start mini-game') }}</p>
            <p>{{ __('Menu placeholder: Best score') }}</p>
            <p>{{ __('Menu placeholder: Return to rest timer') }}</p>
        </div>
    </div>

    @if ($footerUrl && $footerLabel)
        <a href="{{ $footerUrl }}" class="action-button-muted">
            {{ $footerLabel }}
        </a>
    @endif
</div>
