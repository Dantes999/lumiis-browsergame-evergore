@props([
    'rows' => [],
    'monsterSlugs' => [],
])

<div
    class="paper-note space-y-4"
    x-data="adminRowEditor({
        rows: {{ Illuminate\Support\Js::from($rows) }},
        defaultRow: {{ Illuminate\Support\Js::from([
            'monster_slug' => '',
            'weight' => 100,
            'minimum_character_level' => 1,
            'maximum_character_level' => '',
            'pack_size' => 3,
            'sort_order' => 10,
        ]) }},
        monsterSlugs: {{ Illuminate\Support\Js::from($monsterSlugs) }},
        sortKey: 'sort_order',
        sortStep: 10,
    })"
>
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
        <button type="button" class="action-button-muted" @click="addRow()">Add Row</button>
    </div>

    <div class="space-y-3">
        <template x-for="(row, index) in rows" :key="row._editorKey">
            <div
                class="admin-row-card"
                :class="{ 'admin-row-card-dragging': draggedIndex === index }"
                @dragover.prevent
                @drop="dropRow(index)"
            >
                <div class="admin-row-toolbar">
                    <span class="admin-row-index" x-text="rowLabel(index)"></span>
                    <div class="admin-row-actions">
                        <button
                            type="button"
                            class="admin-row-tool admin-row-handle"
                            draggable="true"
                            @dragstart="startDrag(index)"
                            @dragend="clearDrag()"
                        >
                            Drag To Sort
                        </button>
                        <button type="button" class="admin-row-tool" @click="duplicateRow(index)">Duplicate</button>
                        <button type="button" class="admin-row-tool" @click="removeRow(index)">Remove</button>
                    </div>
                </div>

                <div class="grid gap-3 xl:grid-cols-[2fr_1fr_1fr_1fr_1fr_1fr_auto]">
                    <div>
                        <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Monster</label>
                        <select class="theme-input mt-2" x-model="row.monster_slug" :name="`monster_assignment_rows[${index}][monster_slug]`">
                            <option value="">Select monster</option>
                            <template x-for="monsterSlug in monsterSlugs" :key="monsterSlug">
                                <option :value="monsterSlug" x-text="monsterSlug"></option>
                            </template>
                        </select>
                        <p class="admin-inline-hint">Use one row per monster band that should appear here.</p>
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Weight</label>
                        <input type="number" min="1" class="theme-input mt-2" x-model="row.weight" :name="`monster_assignment_rows[${index}][weight]`">
                        <p class="admin-inline-hint">Higher weight means more common inside this level range.</p>
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Min Lv</label>
                        <input type="number" min="1" class="theme-input mt-2" x-model="row.minimum_character_level" :name="`monster_assignment_rows[${index}][minimum_character_level]`">
                        <p class="admin-inline-hint">Lowest player level that can roll this row.</p>
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Max Lv</label>
                        <input type="number" min="1" class="theme-input mt-2" x-model="row.maximum_character_level" :name="`monster_assignment_rows[${index}][maximum_character_level]`" placeholder="Open">
                        <p class="admin-inline-hint">Leave empty to keep the row valid forever.</p>
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Pack Size</label>
                        <input type="number" min="1" class="theme-input mt-2" x-model="row.pack_size" :name="`monster_assignment_rows[${index}][pack_size]`">
                        <p class="admin-inline-hint">Most packs feel good between 2 and 5.</p>
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Sort</label>
                        <input type="number" min="0" class="theme-input mt-2" x-model="row.sort_order" :name="`monster_assignment_rows[${index}][sort_order]`">
                        <p class="admin-inline-hint">Auto-renumbered when you drag rows.</p>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="rows.length === 0">
            <div class="theme-empty-state px-4 py-5 text-sm">
                No spawn rows configured yet.
            </div>
        </template>
    </div>
</div>
