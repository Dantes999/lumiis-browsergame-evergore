<x-app-layout>
    <x-slot name="header">
        <x-page-heading :eyebrow="__('Admin')" :title="__('Action Logs')" />
    </x-slot>

    <div class="portal-stage">
        @include('admin.partials.nav')
        @include('admin.partials.flash')

        <article class="game-panel">
            <div class="space-y-3">
                @foreach ($logs as $log)
                    <div class="paper-note">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm uppercase tracking-[0.25em] text-amber-900/70">
                                    {{ strtoupper($log->action) }} · {{ $log->character?->name ?? 'Unknown character' }}
                                </p>
                                <p class="text-xs text-stone-600">
                                    {{ $log->character?->user?->email ?? 'Unknown account' }}
                                    · {{ $log->processed_at?->format('Y-m-d H:i:s') }}
                                </p>
                            </div>
                            <p class="text-xs text-stone-600">
                                {{ $log->sourceLocation?->name ?? 'Unknown' }} -> {{ $log->targetLocation?->name ?? 'Unknown' }}
                            </p>
                        </div>

                        <p class="mt-3 text-sm leading-6 text-stone-800">
                            {{ data_get($log->outcome, 'message', 'No message stored.') }}
                        </p>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $logs->links() }}
            </div>
        </article>
    </div>
</x-app-layout>
