<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-amber-300/60">Admin</p>
            <h1 class="font-[Cinzel] text-3xl font-semibold tracking-[0.16em] text-amber-50">Quests</h1>
        </div>
    </x-slot>

    <div class="portal-stage">
        @include('admin.partials.nav')
        @include('admin.partials.flash')

        <article class="game-panel">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm text-stone-300">
                    <thead class="text-xs uppercase tracking-[0.25em] text-amber-200/60">
                        <tr>
                            <th class="px-4 py-3">Slug</th>
                            <th class="px-4 py-3">Title</th>
                            <th class="px-4 py-3">Location</th>
                            <th class="px-4 py-3">Objective</th>
                            <th class="px-4 py-3">Rewards</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Edit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($quests as $quest)
                            <tr class="border-t border-stone-800">
                                <td class="px-4 py-3">{{ $quest->slug }}</td>
                                <td class="px-4 py-3">{{ $quest->title }}</td>
                                <td class="px-4 py-3">{{ $quest->location?->name ?? 'Global' }}</td>
                                <td class="px-4 py-3">
                                    {{ $quest->objective_type }}
                                    <div class="mt-1 text-xs text-stone-400">
                                        Target {{ number_format($quest->objective_target) }}
                                        @if ($quest->objective_area_slug)
                                            · {{ $quest->objective_area_slug }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    {{ number_format($quest->reward_experience) }} EXP · {{ number_format($quest->reward_yang) }} Yang
                                    @if ($quest->reward_item_slug)
                                        <div class="mt-1 text-xs text-stone-400">
                                            {{ $quest->reward_item_slug }} x{{ $quest->reward_item_quantity }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $quest->is_active ? 'Active' : 'Inactive' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.quests.edit', $quest) }}" class="action-button-muted">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $quests->links() }}
            </div>
        </article>
    </div>
</x-app-layout>
