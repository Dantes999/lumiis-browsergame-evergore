<x-app-layout>
    <x-slot name="header">
        <x-page-heading :eyebrow="__('Admin')" :title="__('Control Panel')" />
    </x-slot>

    <div class="portal-stage">
        @include('admin.partials.nav')
        @include('admin.partials.flash')

        <div class="mb-6 grid gap-4 md:grid-cols-3 xl:grid-cols-6">
            @foreach ($metrics as $label => $value)
                <article class="stat-card">
                    <span class="stat-label">{{ strtoupper($label) }}</span>
                    <span class="stat-value">{{ number_format($value) }}</span>
                </article>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-[0.85fr_1.15fr]">
            <article class="game-panel">
                <p class="panel-kicker">Storage</p>
                <h2 class="mt-3 font-[Cinzel] text-2xl tracking-[0.12em] text-amber-50">Current data store</h2>
                <p class="mt-4 text-sm leading-7 text-stone-300">
                    Local development is currently using SQLite.
                </p>
                <div class="paper-note mt-4 text-sm leading-6">
                    <code>{{ $databasePath }}</code>
                </div>
                <p class="mt-4 text-sm leading-7 text-stone-400">
                    Production should move to MySQL or PostgreSQL, but for local development this single file is the active data source.
                </p>
            </article>

            <article class="game-panel">
                <p class="panel-kicker">Recent Actions</p>
                <div class="mt-4 space-y-3">
                    @forelse ($recentLogs as $log)
                        <div class="paper-note">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm uppercase tracking-[0.25em] text-amber-900/70">
                                        {{ strtoupper($log->action) }} · {{ $log->character?->name ?? 'Unknown' }}
                                    </p>
                                    <p class="text-xs text-stone-600">
                                        {{ $log->sourceLocation?->name ?? 'Unknown' }} -> {{ $log->targetLocation?->name ?? 'Unknown' }}
                                    </p>
                                </div>
                                <p class="text-xs text-stone-600">{{ $log->processed_at?->diffForHumans() }}</p>
                            </div>
                            <p class="mt-2 text-sm leading-6 text-stone-800">
                                {{ data_get($log->outcome, 'message', 'No outcome payload.') }}
                            </p>
                        </div>
                    @empty
                        <p class="text-sm text-stone-400">No action logs available.</p>
                    @endforelse
                </div>
            </article>
        </div>
    </div>
</x-app-layout>
