@props([
    'rows' => [],
    'itemSlugs' => [],
])

<div
    class="paper-note space-y-4"
    x-data="adminRowEditor({
        rows: {{ Illuminate\Support\Js::from($rows) }},
        defaultRow: {{ Illuminate\Support\Js::from([
            'item_slug' => '',
            'price' => 0,
            'stock' => '',
            'sort_order' => 10,
        ]) }},
        itemSlugs: {{ Illuminate\Support\Js::from($itemSlugs) }},
        sortKey: 'sort_order',
        sortStep: 10,
    })"
>
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-sm uppercase tracking-[0.22em] text-amber-900/70">Shop Inventory</p>
            <p class="mt-2 text-sm leading-6 text-stone-700">
                Configure vendor stock directly. Leave stock empty for unlimited availability.
            </p>
            <p class="admin-inline-hint">
                Starter consumables usually sit low, while gear vendors should stay rare and priced around the first 10-level Yang curve.
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
                            class="action-button-muted admin-row-tool admin-row-handle"
                            draggable="true"
                            @dragstart="startDrag(index)"
                            @dragend="clearDrag()"
                        >
                            Drag To Sort
                        </button>
                        <button type="button" class="action-button-muted admin-row-tool" @click="duplicateRow(index)">Duplicate</button>
                        <button type="button" class="action-button-muted admin-row-tool" @click="removeRow(index)">Remove</button>
                    </div>
                </div>

                <div class="grid gap-3 xl:grid-cols-[2fr_1fr_1fr_1fr_auto]">
                    <div>
                        <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Item</label>
                        <select class="theme-input mt-2" x-model="row.item_slug" :name="`shop_item_rows[${index}][item_slug]`">
                            <option value="">Select item</option>
                            <template x-for="itemSlug in itemSlugs" :key="itemSlug">
                                <option :value="itemSlug" x-text="itemSlug"></option>
                            </template>
                        </select>
                        <p class="admin-inline-hint">Only items listed here can be bought from this vendor.</p>
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Price</label>
                        <input type="number" min="0" class="theme-input mt-2" x-model="row.price" :name="`shop_item_rows[${index}][price]`">
                        <p class="admin-inline-hint">Keep early-game potion prices in the low double digits.</p>
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Stock</label>
                        <input type="number" min="0" class="theme-input mt-2" x-model="row.stock" :name="`shop_item_rows[${index}][stock]`" placeholder="Unlimited">
                        <p class="admin-inline-hint">Leave empty for unlimited stock.</p>
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Sort</label>
                        <input type="number" min="0" class="theme-input mt-2" x-model="row.sort_order" :name="`shop_item_rows[${index}][sort_order]`">
                        <p class="admin-inline-hint">Auto-renumbered when you drag rows.</p>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="rows.length === 0">
            <div class="theme-empty-state px-4 py-5 text-sm">
                No shop rows configured yet.
            </div>
        </template>
    </div>
</div>
