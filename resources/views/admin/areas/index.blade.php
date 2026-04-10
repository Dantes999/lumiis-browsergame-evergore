<x-app-layout>
    <x-slot name="header">
        <x-page-heading :eyebrow="__('Admin')" :title="__('Areas')" />
    </x-slot>

    <div class="portal-stage">
        @include('admin.partials.nav')
        @include('admin.partials.flash')

        <article class="game-panel">
            <div class="overflow-x-auto theme-table-wrap">
                <table class="theme-table">
                    <thead class="text-xs uppercase tracking-[0.25em] text-amber-200/60">
                        <tr>
                            <th class="px-4 py-3">Location</th>
                            <th class="px-4 py-3">Slug</th>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Kind</th>
                            <th class="px-4 py-3">Service</th>
                            <th class="px-4 py-3 text-right">Edit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($areas as $area)
                            <tr class="border-t border-stone-800">
                                <td class="px-4 py-3">{{ $area->location?->name }}</td>
                                <td class="px-4 py-3">{{ $area->slug }}</td>
                                <td class="px-4 py-3">{{ $area->name }}</td>
                                <td class="px-4 py-3">{{ $area->area_kind }}</td>
                                <td class="px-4 py-3">{{ $area->service_type ?? '—' }}</td>
                                <td class="px-4 py-3 text-right"><a href="{{ route('admin.areas.edit', $area) }}" class="action-button-muted">Edit</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $areas->links() }}
            </div>
        </article>
    </div>
</x-app-layout>
