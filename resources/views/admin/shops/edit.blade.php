<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-amber-300/60">Admin</p>
            <h1 class="font-[Cinzel] text-3xl font-semibold tracking-[0.16em] text-amber-50">Edit Shop</h1>
        </div>
    </x-slot>

    <div class="portal-stage">
        @include('admin.partials.nav')
        @include('admin.partials.flash')

        <article class="game-panel">
            <form method="POST" action="{{ route('admin.shops.update', $shop) }}" class="space-y-6">
                @csrf
                @method('PATCH')

                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                    <div><x-input-label for="slug" value="Slug" /><x-text-input id="slug" name="slug" class="mt-2" :value="old('slug', $shop->slug)" required /></div>
                    <div><x-input-label for="name" value="Name" /><x-text-input id="name" name="name" class="mt-2" :value="old('name', $shop->name)" required /></div>
                    <div><x-input-label for="location_id" value="Location ID" /><x-text-input id="location_id" name="location_id" type="number" class="mt-2" :value="old('location_id', $shop->location_id)" required /></div>
                    <div><x-input-label for="location_area_id" value="Location Area ID" /><x-text-input id="location_area_id" name="location_area_id" type="number" class="mt-2" :value="old('location_area_id', $shop->location_area_id)" /></div>
                </div>

                <div>
                    <x-input-label for="description" value="Description" />
                    <textarea id="description" name="description" rows="4" class="mt-2 w-full rounded-[1.1rem] border border-amber-200/20 bg-gradient-to-b from-amber-50/95 to-amber-200/85 px-4 py-3 text-stone-900 focus:border-amber-400 focus:ring-amber-400" required>{{ old('description', $shop->description) }}</textarea>
                </div>

                @include('admin.shops.partials.shop-item-editor', [
                    'rows' => $shopItemRows,
                    'itemSlugs' => $itemSlugs,
                ])

                <x-primary-button>Save Shop</x-primary-button>
            </form>
        </article>
    </div>
</x-app-layout>
