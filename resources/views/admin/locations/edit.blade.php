<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-amber-300/60">Admin</p>
            <h1 class="font-[Cinzel] text-3xl font-semibold tracking-[0.16em] text-amber-50">Edit Location</h1>
        </div>
    </x-slot>

    <div class="portal-stage">
        @include('admin.partials.nav')
        @include('admin.partials.flash')

        <article class="game-panel">
            <form method="POST" action="{{ route('admin.locations.update', $location) }}" class="space-y-6">
                @csrf
                @method('PATCH')

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <x-input-label for="slug" value="Slug" />
                        <x-text-input id="slug" name="slug" class="mt-2" :value="old('slug', $location->slug)" required />
                    </div>

                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" name="name" class="mt-2" :value="old('name', $location->name)" required />
                    </div>

                    <div>
                        <x-input-label for="region" value="Region" />
                        <x-text-input id="region" name="region" class="mt-2" :value="old('region', $location->region)" required />
                    </div>

                    <div>
                        <x-input-label for="threat_level" value="Threat Level" />
                        <x-text-input id="threat_level" name="threat_level" type="number" class="mt-2" :value="old('threat_level', $location->threat_level)" required />
                    </div>
                </div>

                <div>
                    <x-input-label for="description" value="Description" />
                    <textarea id="description" name="description" rows="5" class="mt-2 w-full rounded-[1.1rem] border border-amber-200/20 bg-gradient-to-b from-amber-50/95 to-amber-200/85 px-4 py-3 text-stone-900 focus:border-amber-400 focus:ring-amber-400" required>{{ old('description', $location->description) }}</textarea>
                </div>

                <div class="paper-note">
                    <label class="theme-checkbox-label flex items-center gap-3 text-sm">
                        <input type="checkbox" name="is_safe_zone" value="1" class="theme-checkbox" @checked(old('is_safe_zone', $location->is_safe_zone))>
                        Mark as safe zone
                    </label>
                </div>

                <x-primary-button>Save Location</x-primary-button>
            </form>
        </article>
    </div>
</x-app-layout>
