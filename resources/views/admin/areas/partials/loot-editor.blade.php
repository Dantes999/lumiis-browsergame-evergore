@props([
    'title',
    'name',
    'rows' => [],
    'itemSlugs' => [],
])

<div
    class="paper-note space-y-4"
    x-data="adminRowEditor({
        rows: {{ Illuminate\Support\Js::from($rows) }},
        defaultRow: {{ Illuminate\Support\Js::from(['item_slug' => '', 'chance' => 1000, 'quantity_min' => 1, 'quantity_max' => 1]) }},
        itemSlugs: {{ Illuminate\Support\Js::from($itemSlugs) }},
    })"
>
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

                <div class="grid gap-3 md:grid-cols-[2fr_1fr_1fr_1fr_auto]">
                    <div>
                        <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Item</label>
                        <select class="theme-input mt-2" x-model="row.item_slug" :name="`{{ $name }}[${index}][item_slug]`">
                            <option value="">Select item</option>
                            <template x-for="itemSlug in itemSlugs" :key="itemSlug">
                                <option :value="itemSlug" x-text="itemSlug"></option>
                            </template>
                        </select>
                        <p class="admin-inline-hint">Pick the item slug exactly as it should drop in this area.</p>
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Chance</label>
                        <input type="number" min="0" max="10000" class="theme-input mt-2" x-model="row.chance" :name="`{{ $name }}[${index}][chance]`">
                        <p class="admin-inline-hint">Basis points from 0 to 10000.</p>
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Min</label>
                        <input type="number" min="1" class="theme-input mt-2" x-model="row.quantity_min" :name="`{{ $name }}[${index}][quantity_min]`">
                        <p class="admin-inline-hint">Lowest stack size when the row drops.</p>
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Max</label>
                        <input type="number" min="1" class="theme-input mt-2" x-model="row.quantity_max" :name="`{{ $name }}[${index}][quantity_max]`">
                        <p class="admin-inline-hint">Highest stack size. Keep close to Min for stable pacing.</p>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="rows.length === 0">
            <div class="theme-empty-state px-4 py-5 text-sm">
                No drops configured yet.
            </div>
        </template>
    </div>
</div>
