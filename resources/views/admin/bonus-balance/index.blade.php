<x-app-layout>
    <x-slot name="header">
        <x-page-heading :eyebrow="__('Admin')" :title="__('Bonus Balance')" />
    </x-slot>

    <div class="portal-stage">
        @include('admin.partials.nav')
        @include('admin.partials.flash')

        <article class="game-panel">
            <form method="POST" action="{{ route('admin.bonus-balance.update') }}" class="space-y-8">
                @csrf
                @method('PATCH')

                <section class="space-y-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.35em] text-amber-300/60">Bonus Pools</p>
                        <h2 class="mt-2 font-[Cinzel] text-2xl text-amber-50">Rollable Bonus Definitions</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-7 text-stone-300">
                            Edit which bonus lines can roll, how strong they are, and on which equipment slots they are allowed.
                        </p>
                        <p class="mt-2 max-w-3xl text-xs uppercase tracking-[0.22em] text-amber-200/70">
                            Keep early-game ranges tight. Big value jumps here change the whole economy and combat loop.
                        </p>
                    </div>

                    <div class="overflow-x-auto theme-table-wrap">
                        <table class="theme-table">
                            <thead class="text-xs uppercase tracking-[0.25em] text-amber-200/60">
                                <tr>
                                    <th class="px-4 py-3">Type</th>
                                    <th class="px-4 py-3">Label</th>
                                    <th class="px-4 py-3">Min</th>
                                    <th class="px-4 py-3">Max</th>
                                    <th class="px-4 py-3">Suffix</th>
                                    <th class="px-4 py-3">Allowed Slots</th>
                                    <th class="px-4 py-3">Enabled</th>
                                    <th class="px-4 py-3">Sort</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($bonusDefinitions as $index => $definition)
                                    <tr class="border-t border-stone-800">
                                        <td class="px-4 py-3">
                                            <span class="font-semibold text-amber-100">{{ $definition->bonus_type }}</span>
                                            <input type="hidden" name="bonus_definitions[{{ $index }}][id]" value="{{ $definition->id }}">
                                            <input type="hidden" name="bonus_definitions[{{ $index }}][bonus_type]" value="{{ $definition->bonus_type }}">
                                        </td>
                                        <td class="px-4 py-3">
                                            <x-text-input name="bonus_definitions[{{ $index }}][label]" :value="old('bonus_definitions.'.$index.'.label', $definition->label)" />
                                        </td>
                                        <td class="px-4 py-3">
                                            <x-text-input name="bonus_definitions[{{ $index }}][minimum_value]" type="number" :value="old('bonus_definitions.'.$index.'.minimum_value', $definition->minimum_value)" />
                                        </td>
                                        <td class="px-4 py-3">
                                            <x-text-input name="bonus_definitions[{{ $index }}][maximum_value]" type="number" :value="old('bonus_definitions.'.$index.'.maximum_value', $definition->maximum_value)" />
                                        </td>
                                        <td class="px-4 py-3">
                                            <x-text-input name="bonus_definitions[{{ $index }}][suffix]" :value="old('bonus_definitions.'.$index.'.suffix', $definition->suffix)" />
                                        </td>
                                        <td class="px-4 py-3">
                                            @php($selectedSlots = collect(old('bonus_definitions.'.$index.'.allowed_slots', $definition->allowed_slots ?? []))->map(fn ($slot) => (string) $slot)->all())
                                            <div class="grid gap-2 sm:grid-cols-2">
                                                @foreach ($slotOptions as $slotOption)
                                                    <label class="theme-checkbox-label flex items-center gap-2 text-xs uppercase tracking-[0.2em]">
                                                        <input
                                                            type="checkbox"
                                                            name="bonus_definitions[{{ $index }}][allowed_slots][]"
                                                            value="{{ $slotOption }}"
                                                            class="theme-checkbox"
                                                            @checked(in_array($slotOption, $selectedSlots, true))
                                                        >
                                                        <span>{{ $slotOption }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="hidden" name="bonus_definitions[{{ $index }}][enabled]" value="0">
                                            <input type="checkbox" name="bonus_definitions[{{ $index }}][enabled]" value="1" class="theme-checkbox" @checked(old('bonus_definitions.'.$index.'.enabled', $definition->enabled))>
                                        </td>
                                        <td class="px-4 py-3">
                                            <x-text-input name="bonus_definitions[{{ $index }}][sort_order]" type="number" :value="old('bonus_definitions.'.$index.'.sort_order', $definition->sort_order)" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="space-y-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.35em] text-amber-300/60">Soul Items</p>
                        <h2 class="mt-2 font-[Cinzel] text-2xl text-amber-50">Bonus Consumables</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-7 text-stone-300">
                            These are the special soul items players use for item bonuses and later skill mastery progression.
                        </p>
                        <p class="mt-2 max-w-3xl text-xs uppercase tracking-[0.22em] text-amber-200/70">
                            Required level should usually mirror the point where players first meet the related content layer.
                        </p>
                    </div>

                    <div class="grid gap-6 xl:grid-cols-2">
                        @foreach ($soulItems as $index => $item)
                            <div class="paper-note space-y-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.3em] text-stone-700/70">{{ $item->slug }}</p>
                                        <h3 class="mt-2 font-[Cinzel] text-2xl text-stone-900">{{ $item->name }}</h3>
                                    </div>
                                    <span class="cozy-chip">{{ str_replace('soul-', '', $item->slug) }}</span>
                                </div>

                                <input type="hidden" name="soul_items[{{ $index }}][id]" value="{{ $item->id }}">

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <x-input-label for="soul_item_name_{{ $index }}" value="Name" />
                                        <x-text-input id="soul_item_name_{{ $index }}" name="soul_items[{{ $index }}][name]" class="mt-2" :value="old('soul_items.'.$index.'.name', $item->name)" />
                                    </div>
                                    <div>
                                        <x-input-label for="soul_item_sell_{{ $index }}" value="Sell Price" />
                                        <x-text-input id="soul_item_sell_{{ $index }}" name="soul_items[{{ $index }}][sell_price]" type="number" class="mt-2" :value="old('soul_items.'.$index.'.sell_price', $item->sell_price)" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <x-input-label for="soul_item_description_{{ $index }}" value="Description" />
                                        <textarea id="soul_item_description_{{ $index }}" name="soul_items[{{ $index }}][description]" rows="3" class="theme-input theme-textarea mt-2 w-full">{{ old('soul_items.'.$index.'.description', $item->description) }}</textarea>
                                    </div>
                                    <div>
                                        <x-input-label for="soul_item_level_{{ $index }}" value="Required Level" />
                                        <x-text-input id="soul_item_level_{{ $index }}" name="soul_items[{{ $index }}][required_level]" type="number" class="mt-2" :value="old('soul_items.'.$index.'.required_level', $item->required_level)" />
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="space-y-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.35em] text-amber-300/60">Area Drop Chances</p>
                        <h2 class="mt-2 font-[Cinzel] text-2xl text-amber-50">Soul Item Sources</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-7 text-stone-300">
                            Use basis points like the normal loot tables: <span class="font-semibold text-amber-100">10000 = 100%</span>.
                            Setting a field to 0 removes that soul-item entry from the area loot table.
                        </p>
                        <p class="mt-2 max-w-3xl text-xs uppercase tracking-[0.22em] text-amber-200/70">
                            Lumiis helper items usually stay low. Boss chase items should remain rare enough to keep them special.
                        </p>
                    </div>

                    <div class="overflow-x-auto theme-table-wrap">
                        <table class="theme-table">
                            <thead class="text-xs uppercase tracking-[0.25em] text-amber-200/60">
                                <tr>
                                    <th class="px-4 py-3">Area</th>
                                    <th class="px-4 py-3">Lumiis Imprint</th>
                                    <th class="px-4 py-3">Lumiis Reweaver</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($areaDrops as $index => $dropRow)
                                    @php($area = $dropRow['area'])
                                    <tr class="border-t border-stone-800">
                                        <td class="px-4 py-3">
                                            <span class="font-semibold text-amber-100">{{ $area->name }}</span>
                                            <p class="mt-1 text-xs uppercase tracking-[0.25em] text-stone-400">{{ $area->location?->name }}</p>
                                            <input type="hidden" name="area_drops[{{ $index }}][area_id]" value="{{ $area->id }}">
                                        </td>
                                        <td class="px-4 py-3"><x-text-input name="area_drops[{{ $index }}][lumiis_soul_imprint_chance]" type="number" :value="old('area_drops.'.$index.'.lumiis_soul_imprint_chance', $dropRow['lumiis_soul_imprint_chance'])" /></td>
                                        <td class="px-4 py-3"><x-text-input name="area_drops[{{ $index }}][lumiis_soul_reweaver_chance]" type="number" :value="old('area_drops.'.$index.'.lumiis_soul_reweaver_chance', $dropRow['lumiis_soul_reweaver_chance'])" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="overflow-x-auto theme-table-wrap">
                        <table class="theme-table">
                            <thead class="text-xs uppercase tracking-[0.25em] text-amber-200/60">
                                <tr>
                                    <th class="px-4 py-3">Area</th>
                                    <th class="px-4 py-3">Boss Imprint</th>
                                    <th class="px-4 py-3">Boss Reweaver</th>
                                    <th class="px-4 py-3">Boss Anchor</th>
                                    <th class="px-4 py-3">Boss Soul Stone</th>
                                    <th class="px-4 py-3">Boss Prism</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($areaDrops as $index => $dropRow)
                                    @php($area = $dropRow['area'])
                                    <tr class="border-t border-stone-800">
                                        <td class="px-4 py-3">
                                            <span class="font-semibold text-amber-100">{{ $area->name }}</span>
                                            <p class="mt-1 text-xs uppercase tracking-[0.25em] text-stone-400">{{ $area->location?->name }}</p>
                                        </td>
                                        <td class="px-4 py-3"><x-text-input name="area_drops[{{ $index }}][boss_soul_imprint_chance]" type="number" :value="old('area_drops.'.$index.'.boss_soul_imprint_chance', $dropRow['boss_soul_imprint_chance'])" /></td>
                                        <td class="px-4 py-3"><x-text-input name="area_drops[{{ $index }}][boss_soul_reweaver_chance]" type="number" :value="old('area_drops.'.$index.'.boss_soul_reweaver_chance', $dropRow['boss_soul_reweaver_chance'])" /></td>
                                        <td class="px-4 py-3"><x-text-input name="area_drops[{{ $index }}][boss_soul_anchor_chance]" type="number" :value="old('area_drops.'.$index.'.boss_soul_anchor_chance', $dropRow['boss_soul_anchor_chance'])" /></td>
                                        <td class="px-4 py-3"><x-text-input name="area_drops[{{ $index }}][boss_soul_stone_chance]" type="number" :value="old('area_drops.'.$index.'.boss_soul_stone_chance', $dropRow['boss_soul_stone_chance'])" /></td>
                                        <td class="px-4 py-3"><x-text-input name="area_drops[{{ $index }}][boss_soul_prism_chance]" type="number" :value="old('area_drops.'.$index.'.boss_soul_prism_chance', $dropRow['boss_soul_prism_chance'])" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <x-primary-button>Save Bonus Balance</x-primary-button>
            </form>
        </article>
    </div>
</x-app-layout>
