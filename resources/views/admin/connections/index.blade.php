<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-amber-300/60">Admin</p>
            <h1 class="font-[Cinzel] text-3xl font-semibold tracking-[0.16em] text-amber-50">Routes</h1>
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
                            <th class="px-4 py-3">From</th>
                            <th class="px-4 py-3">To</th>
                            <th class="px-4 py-3">Cooldown</th>
                            <th class="px-4 py-3">Stamina</th>
                            <th class="px-4 py-3">Req. Level</th>
                            <th class="px-4 py-3 text-right">Edit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($connections as $connection)
                            <tr class="border-t border-stone-800">
                                <td class="px-4 py-3">{{ $connection->fromLocation->name }}</td>
                                <td class="px-4 py-3">{{ $connection->toLocation->name }}</td>
                                <td class="px-4 py-3">{{ $connection->travel_seconds }} sec</td>
                                <td class="px-4 py-3">{{ $connection->stamina_cost }}</td>
                                <td class="px-4 py-3">{{ $connection->required_level }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.connections.edit', $connection) }}" class="action-button-muted">
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $connections->links() }}
            </div>
        </article>
    </div>
</x-app-layout>
