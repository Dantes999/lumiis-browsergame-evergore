<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-amber-300/60">Admin</p>
            <h1 class="font-[Cinzel] text-3xl font-semibold tracking-[0.16em] text-amber-50">Monsters</h1>
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
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Level</th>
                            <th class="px-4 py-3">HP</th>
                            <th class="px-4 py-3">Attack</th>
                            <th class="px-4 py-3">EXP</th>
                            <th class="px-4 py-3 text-right">Edit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($monsters as $monster)
                            <tr class="border-t border-stone-800">
                                <td class="px-4 py-3">{{ $monster->slug }}</td>
                                <td class="px-4 py-3">{{ $monster->name }}</td>
                                <td class="px-4 py-3">{{ $monster->level }}</td>
                                <td class="px-4 py-3">{{ number_format($monster->maximum_health) }}</td>
                                <td class="px-4 py-3">{{ $monster->minimum_attack }} - {{ $monster->maximum_attack }}</td>
                                <td class="px-4 py-3">{{ number_format($monster->experience_reward) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.monsters.edit', $monster) }}" class="action-button-muted">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $monsters->links() }}
            </div>
        </article>
    </div>
</x-app-layout>
