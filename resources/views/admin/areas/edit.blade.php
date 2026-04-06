<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs uppercase tracking-[0.35em] text-amber-300/60">Admin</p>
            <h1 class="font-[Cinzel] text-3xl font-semibold tracking-[0.16em] text-amber-50">Edit Area</h1>
        </div>
    </x-slot>

    <div class="portal-stage">
        @include('admin.partials.nav')
        @include('admin.partials.flash')

        <article class="game-panel">
            <form method="POST" action="{{ route('admin.areas.update', $area) }}" class="space-y-6">
                @csrf
                @method('PATCH')

                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                    <div><x-input-label for="slug" value="Slug" /><x-text-input id="slug" name="slug" class="mt-2" :value="old('slug', $area->slug)" required /></div>
                    <div><x-input-label for="name" value="Name" /><x-text-input id="name" name="name" class="mt-2" :value="old('name', $area->name)" required /></div>
                    <div><x-input-label for="area_kind" value="Area Kind" /><x-text-input id="area_kind" name="area_kind" class="mt-2" :value="old('area_kind', $area->area_kind)" required /></div>
                    <div><x-input-label for="service_type" value="Service Type" /><x-text-input id="service_type" name="service_type" class="mt-2" :value="old('service_type', $area->service_type)" /></div>
                    <div><x-input-label for="hotspot_x" value="Hotspot X" /><x-text-input id="hotspot_x" name="hotspot_x" type="number" step="0.01" class="mt-2" :value="old('hotspot_x', $area->hotspot_x)" required /></div>
                    <div><x-input-label for="hotspot_y" value="Hotspot Y" /><x-text-input id="hotspot_y" name="hotspot_y" type="number" step="0.01" class="mt-2" :value="old('hotspot_y', $area->hotspot_y)" required /></div>
                    <div><x-input-label for="sort_order" value="Sort Order" /><x-text-input id="sort_order" name="sort_order" type="number" class="mt-2" :value="old('sort_order', $area->sort_order)" required /></div>
                    <div><x-input-label for="recommended_level" value="Recommended Level" /><x-text-input id="recommended_level" name="recommended_level" type="number" class="mt-2" :value="old('recommended_level', $area->recommended_level)" /></div>
                </div>

                <div>
                    <x-input-label for="description" value="Description" />
                    <textarea id="description" name="description" rows="4" class="mt-2 w-full rounded-[1.1rem] border border-amber-200/20 bg-gradient-to-b from-amber-50/95 to-amber-200/85 px-4 py-3 text-stone-900 focus:border-amber-400 focus:ring-amber-400" required>{{ old('description', $area->description) }}</textarea>
                </div>

                <div class="grid gap-6 md:grid-cols-3">
                    <div><x-input-label for="monster_name" value="Monster Encounter Name" /><x-text-input id="monster_name" name="monster_name" class="mt-2" :value="old('monster_name', $area->monster_name)" /></div>
                    <div><x-input-label for="lumiis_name" value="Lumiis Encounter Name" /><x-text-input id="lumiis_name" name="lumiis_name" class="mt-2" :value="old('lumiis_name', $area->lumiis_name)" /></div>
                    <div><x-input-label for="boss_name" value="Boss Encounter Name" /><x-text-input id="boss_name" name="boss_name" class="mt-2" :value="old('boss_name', $area->boss_name)" /></div>
                </div>

                <div class="grid gap-6 md:grid-cols-3">
                    <div><x-input-label for="monster_cooldown_seconds" value="Monster Cooldown" /><x-text-input id="monster_cooldown_seconds" name="monster_cooldown_seconds" type="number" class="mt-2" :value="old('monster_cooldown_seconds', $area->monster_cooldown_seconds)" /></div>
                    <div><x-input-label for="lumiis_cooldown_seconds" value="Lumiis Cooldown" /><x-text-input id="lumiis_cooldown_seconds" name="lumiis_cooldown_seconds" type="number" class="mt-2" :value="old('lumiis_cooldown_seconds', $area->lumiis_cooldown_seconds)" /></div>
                    <div><x-input-label for="boss_cooldown_seconds" value="Boss Cooldown" /><x-text-input id="boss_cooldown_seconds" name="boss_cooldown_seconds" type="number" class="mt-2" :value="old('boss_cooldown_seconds', $area->boss_cooldown_seconds)" /></div>
                </div>

                <div class="grid gap-6 lg:grid-cols-3">
                    <div class="paper-note">
                        <p class="text-sm uppercase tracking-[0.22em] text-amber-900/70">Monster Rewards / Damage</p>
                        <div class="mt-4 grid gap-4">
                            <x-text-input name="monster_exp_min" type="number" :value="old('monster_exp_min', $area->monster_exp_min)" placeholder="EXP min" />
                            <x-text-input name="monster_exp_max" type="number" :value="old('monster_exp_max', $area->monster_exp_max)" placeholder="EXP max" />
                            <x-text-input name="monster_yang_min" type="number" :value="old('monster_yang_min', $area->monster_yang_min)" placeholder="Yang min" />
                            <x-text-input name="monster_yang_max" type="number" :value="old('monster_yang_max', $area->monster_yang_max)" placeholder="Yang max" />
                            <x-text-input name="monster_damage_min" type="number" :value="old('monster_damage_min', $area->monster_damage_min)" placeholder="Damage min" />
                            <x-text-input name="monster_damage_max" type="number" :value="old('monster_damage_max', $area->monster_damage_max)" placeholder="Damage max" />
                        </div>
                    </div>

                    <div class="paper-note">
                        <p class="text-sm uppercase tracking-[0.22em] text-amber-900/70">Lumiis Rewards / Damage</p>
                        <div class="mt-4 grid gap-4">
                            <x-text-input name="lumiis_exp_min" type="number" :value="old('lumiis_exp_min', $area->lumiis_exp_min)" placeholder="EXP min" />
                            <x-text-input name="lumiis_exp_max" type="number" :value="old('lumiis_exp_max', $area->lumiis_exp_max)" placeholder="EXP max" />
                            <x-text-input name="lumiis_yang_min" type="number" :value="old('lumiis_yang_min', $area->lumiis_yang_min)" placeholder="Yang min" />
                            <x-text-input name="lumiis_yang_max" type="number" :value="old('lumiis_yang_max', $area->lumiis_yang_max)" placeholder="Yang max" />
                            <x-text-input name="lumiis_damage_min" type="number" :value="old('lumiis_damage_min', $area->lumiis_damage_min)" placeholder="Damage min" />
                            <x-text-input name="lumiis_damage_max" type="number" :value="old('lumiis_damage_max', $area->lumiis_damage_max)" placeholder="Damage max" />
                        </div>
                    </div>

                    <div class="paper-note">
                        <p class="text-sm uppercase tracking-[0.22em] text-amber-900/70">Boss / Pressure</p>
                        <div class="mt-4 grid gap-4">
                            <x-text-input name="boss_exp_min" type="number" :value="old('boss_exp_min', $area->boss_exp_min)" placeholder="EXP min" />
                            <x-text-input name="boss_exp_max" type="number" :value="old('boss_exp_max', $area->boss_exp_max)" placeholder="EXP max" />
                            <x-text-input name="boss_yang_min" type="number" :value="old('boss_yang_min', $area->boss_yang_min)" placeholder="Yang min" />
                            <x-text-input name="boss_yang_max" type="number" :value="old('boss_yang_max', $area->boss_yang_max)" placeholder="Yang max" />
                            <x-text-input name="boss_damage_min" type="number" :value="old('boss_damage_min', $area->boss_damage_min)" placeholder="Damage min" />
                            <x-text-input name="boss_damage_max" type="number" :value="old('boss_damage_max', $area->boss_damage_max)" placeholder="Damage max" />
                            <x-text-input name="monster_pressure_gain" type="number" :value="old('monster_pressure_gain', $area->monster_pressure_gain)" placeholder="Monster pressure gain" />
                            <x-text-input name="lumiis_pressure_gain" type="number" :value="old('lumiis_pressure_gain', $area->lumiis_pressure_gain)" placeholder="Lumiis pressure gain" />
                            <x-text-input name="boss_pressure_threshold" type="number" :value="old('boss_pressure_threshold', $area->boss_pressure_threshold)" placeholder="Boss threshold" />
                            <x-text-input name="boss_base_chance" type="number" :value="old('boss_base_chance', $area->boss_base_chance)" placeholder="Boss base chance" />
                            <x-text-input name="boss_pressure_chance_per_point" type="number" :value="old('boss_pressure_chance_per_point', $area->boss_pressure_chance_per_point)" placeholder="Chance per pressure" />
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    @include('admin.areas.partials.loot-editor', [
                        'title' => 'Monster Loot Table',
                        'name' => 'monster_loot_rows',
                        'rows' => $monsterLootRows,
                        'itemSlugs' => $itemSlugs,
                    ])

                    @include('admin.areas.partials.loot-editor', [
                        'title' => 'Lumiis Loot Table',
                        'name' => 'lumiis_loot_rows',
                        'rows' => $lumiisLootRows,
                        'itemSlugs' => $itemSlugs,
                    ])
                </div>

                @include('admin.areas.partials.loot-editor', [
                    'title' => 'Boss Loot Table',
                    'name' => 'boss_loot_rows',
                    'rows' => $bossLootRows,
                    'itemSlugs' => $itemSlugs,
                ])

                @include('admin.areas.partials.monster-assignment-editor', [
                    'rows' => $monsterAssignmentRows,
                    'monsterSlugs' => $monsterSlugs,
                ])

                <x-primary-button>Save Area</x-primary-button>
            </form>
        </article>
    </div>
</x-app-layout>
