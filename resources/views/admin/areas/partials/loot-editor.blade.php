@props([
    'title',
    'name',
    'rows' => [],
    'itemSlugs' => [],
])

<div class="paper-note space-y-4" data-admin-row-editor='@json([
        'rows' => $rows,
        'defaultRow' => ['item_slug' => '', 'chance' => 1000, 'quantity_min' => 1, 'quantity_max' => 1],
        'itemSlugs' => $itemSlugs,
        'namePrefix' => $name,
    ])'>
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-sm uppercase tracking-[0.22em] text-amber-900/70">{{ $title }}</p>
            <p class="mt-2 text-sm leading-6 text-stone-700">
                Configure actual drop rows without editing raw JSON. Chances use basis points: <span class="font-semibold">10000 = 100%</span>.
            </p>
            <p class="admin-inline-hint">
                Typical early-game drops live around <span class="font-semibold">200-2500</span>. Guaranteed rows should only be used for tests or tutorial content.
            </p>
        </div>
        <button type="button" class="action-button-muted" data-admin-add-row>Add Row</button>
    </div>

    <div class="space-y-3">
        <div data-admin-rows>
            @forelse($rows as $index => $row)
                <div class="admin-row-card" data-admin-row draggable="true">
                    <div class="admin-row-toolbar">
                        <span class="admin-row-index"></span>
                        <div class="admin-row-actions">
                            <button type="button" class="admin-row-tool admin-row-handle" draggable="true">Drag To Sort</button>
                            <button type="button" class="admin-row-tool" data-admin-duplicate-row>Duplicate</button>
                            <button type="button" class="admin-row-tool" data-admin-remove-row>Remove</button>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-[2fr_1fr_1fr_1fr_auto]">
                        <div>
                            <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Item</label>
                            <select class="theme-input mt-2" name="{{ $name }}[{{ $index }}][item_slug]">
                                <option value="">Select item</option>
                                @foreach($itemSlugs as $itemSlug)
                                    <option value="{{ $itemSlug }}"{{ ($row['item_slug'] ?? '') === $itemSlug ? ' selected' : '' }}>{{ $itemSlug }}</option>
                                @endforeach
                            </select>
                            <p class="admin-inline-hint">Pick the item slug exactly as it should drop in this area.</p>
                        </div>

                        <div>
                            <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Chance</label>
                            <input type="number" min="0" max="10000" class="theme-input mt-2" name="{{ $name }}[{{ $index }}][chance]" value="{{ $row['chance'] ?? 1000 }}">
                            <p class="admin-inline-hint">Basis points from 0 to 10000.</p>
                        </div>

                        <div>
                            <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Min</label>
                            <input type="number" min="1" class="theme-input mt-2" name="{{ $name }}[{{ $index }}][quantity_min]" value="{{ $row['quantity_min'] ?? 1 }}">
                            <p class="admin-inline-hint">Lowest stack size when the row drops.</p>
                        </div>

                        <div>
                            <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Max</label>
                            <input type="number" min="1" class="theme-input mt-2" name="{{ $name }}[{{ $index }}][quantity_max]" value="{{ $row['quantity_max'] ?? 1 }}">
                            <p class="admin-inline-hint">Highest stack size. Keep close to Min for stable pacing.</p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="theme-empty-state px-4 py-5 text-sm">No drops configured yet.</div>
            @endforelse
        </div>

        <template data-admin-row-template>
            <div class="admin-row-card" data-admin-row draggable="true">
                <div class="admin-row-toolbar">
                    <span class="admin-row-index"></span>
                    <div class="admin-row-actions">
                        <button type="button" class="admin-row-tool admin-row-handle" draggable="true">Drag To Sort</button>
                        <button type="button" class="admin-row-tool" data-admin-duplicate-row>Duplicate</button>
                        <button type="button" class="admin-row-tool" data-admin-remove-row>Remove</button>
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-[2fr_1fr_1fr_1fr_auto]">
                    <div>
                        <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Item</label>
                        <select class="theme-input mt-2" name="{{ $name }}[__INDEX__][item_slug]">
                            <option value="">Select item</option>
                            @foreach($itemSlugs as $itemSlug)
                                <option value="{{ $itemSlug }}">{{ $itemSlug }}</option>
                            @endforeach
                        </select>
                        <p class="admin-inline-hint">Pick the item slug exactly as it should drop in this area.</p>
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Chance</label>
                        <input type="number" min="0" max="10000" class="theme-input mt-2" name="{{ $name }}[__INDEX__][chance]" value="1000">
                        <p class="admin-inline-hint">Basis points from 0 to 10000.</p>
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Min</label>
                        <input type="number" min="1" class="theme-input mt-2" name="{{ $name }}[__INDEX__][quantity_min]" value="1">
                        <p class="admin-inline-hint">Lowest stack size when the row drops.</p>
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Max</label>
                        <input type="number" min="1" class="theme-input mt-2" name="{{ $name }}[__INDEX__][quantity_max]" value="1">
                        <p class="admin-inline-hint">Highest stack size. Keep close to Min for stable pacing.</p>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
