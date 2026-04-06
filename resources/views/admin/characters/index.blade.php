<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-amber-300/60">Admin</p>
            <h1 class="font-[Cinzel] text-3xl font-semibold tracking-[0.16em] text-amber-50">Characters</h1>
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
                            <th class="px-4 py-3">Character</th>
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">Faction</th>
                            <th class="px-4 py-3">State</th>
                            <th class="px-4 py-3">Location</th>
                            <th class="px-4 py-3">Level</th>
                            <th class="px-4 py-3">Yang</th>
                            <th class="px-4 py-3 text-right">Edit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($characters as $character)
                            <tr class="border-t border-stone-800">
                                <td class="px-4 py-3">{{ $character->name }}</td>
                                <td class="px-4 py-3">{{ $character->user->email }}</td>
                                <td class="px-4 py-3">{{ $character->faction }}</td>
                                <td class="px-4 py-3">
                                    {{ ucfirst($character->game_state?->value ?? $character->game_state) }}
                                    @if ($character->tutorial_step)
                                        · Step {{ $character->tutorial_step }}
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $character->location->name }}</td>
                                <td class="px-4 py-3">{{ $character->level }}</td>
                                <td class="px-4 py-3">{{ number_format($character->yang) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.characters.edit', $character) }}" class="action-button-muted">
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $characters->links() }}
            </div>
        </article>
    </div>
</x-app-layout>
