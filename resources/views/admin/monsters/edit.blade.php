<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-amber-300/60">Admin</p>
            <h1 class="font-[Cinzel] text-3xl font-semibold tracking-[0.16em] text-amber-50">Edit Monster</h1>
        </div>
    </x-slot>

    <div class="portal-stage">
        @include('admin.partials.nav')
        @include('admin.partials.flash')

        <article class="game-panel">
            <form method="POST" action="{{ route('admin.monsters.update', $monster) }}" class="space-y-6">
                @csrf
                @method('PATCH')

                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                    <div><x-input-label for="slug" value="Slug" /><x-text-input id="slug" name="slug" class="mt-2" :value="old('slug', $monster->slug)" required /></div>
                    <div><x-input-label for="name" value="Name" /><x-text-input id="name" name="name" class="mt-2" :value="old('name', $monster->name)" required /></div>
                    <div><x-input-label for="race" value="Race" /><x-text-input id="race" name="race" class="mt-2" :value="old('race', $monster->race)" required /></div>
                    <div><x-input-label for="damage_type" value="Damage Type" /><x-text-input id="damage_type" name="damage_type" class="mt-2" :value="old('damage_type', $monster->damage_type)" required /></div>
                    <div><x-input-label for="level" value="Level" /><x-text-input id="level" name="level" type="number" class="mt-2" :value="old('level', $monster->level)" required /></div>
                    <div><x-input-label for="rank" value="Rank" /><x-text-input id="rank" name="rank" type="number" class="mt-2" :value="old('rank', $monster->rank)" required /></div>
                    <div><x-input-label for="maximum_health" value="Max HP" /><x-text-input id="maximum_health" name="maximum_health" type="number" class="mt-2" :value="old('maximum_health', $monster->maximum_health)" required /></div>
                    <div><x-input-label for="defense" value="Defense" /><x-text-input id="defense" name="defense" type="number" class="mt-2" :value="old('defense', $monster->defense)" required /></div>
                    <div><x-input-label for="minimum_attack" value="Min Attack" /><x-text-input id="minimum_attack" name="minimum_attack" type="number" class="mt-2" :value="old('minimum_attack', $monster->minimum_attack)" required /></div>
                    <div><x-input-label for="maximum_attack" value="Max Attack" /><x-text-input id="maximum_attack" name="maximum_attack" type="number" class="mt-2" :value="old('maximum_attack', $monster->maximum_attack)" required /></div>
                    <div><x-input-label for="experience_reward" value="EXP Reward" /><x-text-input id="experience_reward" name="experience_reward" type="number" class="mt-2" :value="old('experience_reward', $monster->experience_reward)" required /></div>
                    <div><x-input-label for="sort_order" value="Sort Order" /><x-text-input id="sort_order" name="sort_order" type="number" class="mt-2" :value="old('sort_order', $monster->sort_order)" required /></div>
                    <div><x-input-label for="yang_min" value="Yang Min" /><x-text-input id="yang_min" name="yang_min" type="number" class="mt-2" :value="old('yang_min', $monster->yang_min)" required /></div>
                    <div><x-input-label for="yang_max" value="Yang Max" /><x-text-input id="yang_max" name="yang_max" type="number" class="mt-2" :value="old('yang_max', $monster->yang_max)" required /></div>
                    <div class="md:col-span-2"><x-input-label for="sprite" value="Sprite / Asset" /><x-text-input id="sprite" name="sprite" class="mt-2" :value="old('sprite', $monster->sprite)" /></div>
                </div>

                <x-primary-button>Save Monster</x-primary-button>
            </form>
        </article>
    </div>
</x-app-layout>
