@php
    $panelClass = trim($panelClass ?? 'game-panel');
@endphp

<article class="{{ $panelClass }}">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="panel-kicker">{{ __('Map Questline') }}</p>
            <h2 class="mt-2 font-[Cinzel] text-2xl tracking-[0.12em] text-amber-50">
                {{ $questline ? $questline['title'] : __('Quest Overview') }}
            </h2>
        </div>
        <span class="cozy-chip">
            @if ($questline)
                {{ count($questline['completed']) }} {{ __('complete') }}
            @else
                {{ __('No active chain') }}
            @endif
        </span>
    </div>

    @if ($questline && $questline['active'] !== [])
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
    @elseif ($questline && $questline['upcoming'] !== [])
        <div class="paper-note mt-4 text-sm leading-6">
            {{ __('Next quest: :title at level :level.', ['title' => $questline['upcoming'][0]['title'], 'level' => $questline['upcoming'][0]['required_level']]) }}
        </div>
    @elseif ($questline)
        <div class="paper-note mt-4 text-sm leading-6">
            {{ __('This map questline is currently complete.') }}
        </div>
    @else
        <div class="paper-note mt-4 text-sm leading-6">
            {{ __('No active map questline is tracked right now. Keep using hotspots and clearing the current map to unlock the next quest chain.') }}
        </div>
    @endif
</article>
