@props([
    'rows' => [],
    'monsterSlugs' => [],
])

<div class="paper-note space-y-4" data-admin-row-editor='@json([
        'rows' => $rows,
        'defaultRow' => [
            'monster_slug' => '',
            'weight' => 100,
            'minimum_character_level' => 1,
            'maximum_character_level' => '',
            'pack_size' => 3,
            'sort_order' => 10,
        ],
        'monsterSlugs' => $monsterSlugs,
        'sortKey' => 'sort_order',
        'sortStep' => 10,
        'namePrefix' => 'monster_assignment_rows',
    ])'>
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-sm uppercase tracking-[0.22em] text-amber-900/70">Monster Spawn Pool</p>
            <p class="mt-2 text-sm leading-6 text-stone-700">
                Configure the monster bands that can appear in this hunting area. Weight controls how often a row is rolled inside its level band.
            </p>
            <p class="admin-inline-hint">
                Early hunting areas usually use <span class="font-semibold">pack sizes 2-4</span> and broad weights like <span class="font-semibold">50 / 100 / 150</span>.
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

                    <div class="grid gap-3 xl:grid-cols-[2fr_1fr_1fr_1fr_1fr_1fr_auto]">
                        <div>
                            <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Monster</label>
                            <select class="theme-input mt-2" name="monster_assignment_rows[{{ $index }}][monster_slug]">
                                <option value="">Select monster</option>
                                @foreach($monsterSlugs as $monsterSlug)
                                    <option value="{{ $monsterSlug }}"{{ ($row['monster_slug'] ?? '') === $monsterSlug ? ' selected' : '' }}>{{ $monsterSlug }}</option>
                                @endforeach
                            </select>
                            <p class="admin-inline-hint">Use one row per monster band that should appear here.</p>
                        </div>

                        <div>
                            <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Weight</label>
                            <input type="number" min="1" class="theme-input mt-2" name="monster_assignment_rows[{{ $index }}][weight]" value="{{ $row['weight'] ?? 100 }}">
                            <p class="admin-inline-hint">Higher weight means more common inside this level range.</p>
                        </div>

                        <div>
                            <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Min Lv</label>
                            <input type="number" min="1" class="theme-input mt-2" name="monster_assignment_rows[{{ $index }}][minimum_character_level]" value="{{ $row['minimum_character_level'] ?? 1 }}">
                            <p class="admin-inline-hint">Lowest player level that can roll this row.</p>
                        </div>

                        <div>
                            <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Max Lv</label>
                            <input type="number" min="1" class="theme-input mt-2" name="monster_assignment_rows[{{ $index }}][maximum_character_level]" value="{{ $row['maximum_character_level'] ?? '' }}" placeholder="Open">
                            <p class="admin-inline-hint">Leave empty to keep the row valid forever.</p>
                        </div>

                        <div>
                            <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Pack Size</label>
                            <input type="number" min="1" class="theme-input mt-2" name="monster_assignment_rows[{{ $index }}][pack_size]" value="{{ $row['pack_size'] ?? 3 }}">
                            <p class="admin-inline-hint">Most packs feel good between 2 and 5.</p>
                        </div>

                        <div>
                            <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Sort</label>
                            <input type="number" min="0" class="theme-input mt-2" name="monster_assignment_rows[{{ $index }}][sort_order]" value="{{ $row['sort_order'] ?? 10 }}">
                            <p class="admin-inline-hint">Auto-renumbered when you drag rows.</p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="theme-empty-state px-4 py-5 text-sm">No spawn rows configured yet.</div>
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

                <div class="grid gap-3 xl:grid-cols-[2fr_1fr_1fr_1fr_1fr_1fr_auto]">
                    <div>
                        <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Monster</label>
                        <select class="theme-input mt-2" name="monster_assignment_rows[__INDEX__][monster_slug]">
                            <option value="">Select monster</option>
                            @foreach($monsterSlugs as $monsterSlug)
                                <option value="{{ $monsterSlug }}">{{ $monsterSlug }}</option>
                            @endforeach
                        </select>
                        <p class="admin-inline-hint">Use one row per monster band that should appear here.</p>
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Weight</label>
                        <input type="number" min="1" class="theme-input mt-2" name="monster_assignment_rows[__INDEX__][weight]" value="100">
                        <p class="admin-inline-hint">Higher weight means more common inside this level range.</p>
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Min Lv</label>
                        <input type="number" min="1" class="theme-input mt-2" name="monster_assignment_rows[__INDEX__][minimum_character_level]" value="1">
                        <p class="admin-inline-hint">Lowest player level that can roll this row.</p>
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Max Lv</label>
                        <input type="number" min="1" class="theme-input mt-2" name="monster_assignment_rows[__INDEX__][maximum_character_level]" value="" placeholder="Open">
                        <p class="admin-inline-hint">Leave empty to keep the row valid forever.</p>
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Pack Size</label>
                        <input type="number" min="1" class="theme-input mt-2" name="monster_assignment_rows[__INDEX__][pack_size]" value="3">
                        <p class="admin-inline-hint">Most packs feel good between 2 and 5.</p>
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Sort</label>
                        <input type="number" min="0" class="theme-input mt-2" name="monster_assignment_rows[__INDEX__][sort_order]" value="10">
                        <p class="admin-inline-hint">Auto-renumbered when you drag rows.</p>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
