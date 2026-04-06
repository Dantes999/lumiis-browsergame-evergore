<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-amber-300/60">Admin</p>
            <h1 class="font-[Cinzel] text-3xl font-semibold tracking-[0.16em] text-amber-50">Locations</h1>
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
                            <th class="px-4 py-3">Region</th>
                            <th class="px-4 py-3">Threat</th>
                            <th class="px-4 py-3">Safe</th>
                            <th class="px-4 py-3 text-right">Edit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($locations as $location)
                            <tr class="border-t border-stone-800">
                                <td class="px-4 py-3">{{ $location->slug }}</td>
                                <td class="px-4 py-3">{{ $location->name }}</td>
                                <td class="px-4 py-3">{{ $location->region }}</td>
                                <td class="px-4 py-3">{{ $location->threat_level }}</td>
                                <td class="px-4 py-3">{{ $location->is_safe_zone ? 'Yes' : 'No' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.locations.edit', $location) }}" class="action-button-muted">
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $locations->links() }}
            </div>
        </article>
    </div>
</x-app-layout>
