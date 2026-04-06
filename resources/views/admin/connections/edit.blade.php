<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-amber-300/60">Admin</p>
            <h1 class="font-[Cinzel] text-3xl font-semibold tracking-[0.16em] text-amber-50">Edit Route</h1>
        </div>
    </x-slot>

    <div class="portal-stage">
        @include('admin.partials.nav')
        @include('admin.partials.flash')

        <article class="game-panel">
            <form method="POST" action="{{ route('admin.connections.update', $connection) }}" class="space-y-6">
                @csrf
                @method('PATCH')

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <x-input-label for="from_location_id" value="From Location" />
                        <select id="from_location_id" name="from_location_id" class="mt-2 w-full rounded-[1.1rem] border border-amber-200/20 bg-gradient-to-b from-amber-50/95 to-amber-200/85 px-4 py-3 text-stone-900 focus:border-amber-400 focus:ring-amber-400">
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected((int) old('from_location_id', $connection->from_location_id) === $location->id)>
                                    {{ $location->region }} / {{ $location->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="to_location_id" value="To Location" />
                        <select id="to_location_id" name="to_location_id" class="mt-2 w-full rounded-[1.1rem] border border-amber-200/20 bg-gradient-to-b from-amber-50/95 to-amber-200/85 px-4 py-3 text-stone-900 focus:border-amber-400 focus:ring-amber-400">
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected((int) old('to_location_id', $connection->to_location_id) === $location->id)>
                                    {{ $location->region }} / {{ $location->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="travel_seconds" value="Travel Seconds" />
                        <x-text-input id="travel_seconds" name="travel_seconds" type="number" class="mt-2" :value="old('travel_seconds', $connection->travel_seconds)" required />
                    </div>

                    <div>
                        <x-input-label for="stamina_cost" value="Stamina Cost" />
                        <x-text-input id="stamina_cost" name="stamina_cost" type="number" class="mt-2" :value="old('stamina_cost', $connection->stamina_cost)" required />
                    </div>

                    <div>
                        <x-input-label for="required_level" value="Required Level" />
                        <x-text-input id="required_level" name="required_level" type="number" class="mt-2" :value="old('required_level', $connection->required_level)" required />
                    </div>
                </div>

                <x-primary-button>Save Route</x-primary-button>
            </form>
        </article>
    </div>
</x-app-layout>
