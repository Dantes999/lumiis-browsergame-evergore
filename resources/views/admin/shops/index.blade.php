<x-app-layout>
    <x-slot name="header">
        <x-page-heading :eyebrow="__('Admin')" :title="__('Shops')" />
    </x-slot>

    <div class="portal-stage">
        @include('admin.partials.nav')
        @include('admin.partials.flash')

        <article class="game-panel">
            <div class="overflow-x-auto theme-table-wrap">
                <table class="theme-table">
                    <thead class="text-xs uppercase tracking-[0.25em] text-amber-200/60">
                        <tr>
                            <th class="px-4 py-3">Shop</th>
                            <th class="px-4 py-3">Location</th>
                            <th class="px-4 py-3">Area</th>
                            <th class="px-4 py-3">Items</th>
                            <th class="px-4 py-3 text-right">Edit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($shops as $shop)
                            <tr class="border-t border-stone-800">
                                <td class="px-4 py-3">{{ $shop->name }}</td>
                                <td class="px-4 py-3">{{ $shop->location?->name }}</td>
                                <td class="px-4 py-3">{{ $shop->locationArea?->name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ number_format($shop->shop_items_count) }}</td>
                                <td class="px-4 py-3 text-right"><a href="{{ route('admin.shops.edit', $shop) }}" class="action-button-muted">Edit</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $shops->links() }}
            </div>
        </article>
    </div>
</x-app-layout>
