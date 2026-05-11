@props([
    'rows' => [],
    'itemSlugs' => [],
])

<div class="paper-note space-y-4" data-admin-row-editor='@json([
        'rows' => $rows,
        'defaultRow' => [
            'item_slug' => '',
            'price' => 0,
            'stock' => '',
            'sort_order' => 10,
        ],
        'itemSlugs' => $itemSlugs,
        'sortKey' => 'sort_order',
        'sortStep' => 10,
        'namePrefix' => 'shop_item_rows',
    ])'>
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
        <button type="button" class="action-button-muted" data-admin-add-row>Add Row</button>
    </div>

        <div class="space-y-3">
            <div data-admin-rows>
                @forelse($rows as $index => $row)
                    <div class="admin-row-card" data-admin-row draggable="true">
                        <div class="admin-row-toolbar">
                            <span class="admin-row-index"></span>
                            <div class="admin-row-actions">
                                <button type="button" class="action-button-muted admin-row-tool admin-row-handle" draggable="true">Drag To Sort</button>
                                <button type="button" class="action-button-muted admin-row-tool" data-admin-duplicate-row>Duplicate</button>
                                <button type="button" class="action-button-muted admin-row-tool" data-admin-remove-row>Remove</button>
                            </div>
                        </div>

                        <div class="grid gap-3 xl:grid-cols-[2fr_1fr_1fr_1fr_auto]">
                            <div>
                                <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Item</label>
                                <select class="theme-input mt-2" name="shop_item_rows[{{ $index }}][item_slug]">
                                    <option value="">Select item</option>
                                    @foreach($itemSlugs as $itemSlug)
                                        <option value="{{ $itemSlug }}"{{ ($row['item_slug'] ?? '') === $itemSlug ? ' selected' : '' }}>{{ $itemSlug }}</option>
                                    @endforeach
                                </select>
                                <p class="admin-inline-hint">Only items listed here can be bought from this vendor.</p>
                            </div>

                            <div>
                                <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Price</label>
                                <input type="number" min="0" class="theme-input mt-2" name="shop_item_rows[{{ $index }}][price]" value="{{ $row['price'] ?? 0 }}">
                                <p class="admin-inline-hint">Keep early-game potion prices in the low double digits.</p>
                            </div>

                            <div>
                                <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Stock</label>
                                <input type="number" min="0" class="theme-input mt-2" name="shop_item_rows[{{ $index }}][stock]" value="{{ $row['stock'] ?? '' }}" placeholder="Unlimited">
                                <p class="admin-inline-hint">Leave empty for unlimited stock.</p>
                            </div>

                            <div>
                                <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Sort</label>
                                <input type="number" min="0" class="theme-input mt-2" name="shop_item_rows[{{ $index }}][sort_order]" value="{{ $row['sort_order'] ?? 10 }}">
                                <p class="admin-inline-hint">Auto-renumbered when you drag rows.</p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="theme-empty-state px-4 py-5 text-sm">No shop rows configured yet.</div>
                @endforelse
            </div>

            <template data-admin-row-template>
                <div class="admin-row-card" data-admin-row draggable="true">
                    <div class="admin-row-toolbar">
                        <span class="admin-row-index"></span>
                        <div class="admin-row-actions">
                            <button type="button" class="action-button-muted admin-row-tool admin-row-handle" draggable="true">Drag To Sort</button>
                            <button type="button" class="action-button-muted admin-row-tool" data-admin-duplicate-row>Duplicate</button>
                            <button type="button" class="action-button-muted admin-row-tool" data-admin-remove-row>Remove</button>
                        </div>
                    </div>

                    <div class="grid gap-3 xl:grid-cols-[2fr_1fr_1fr_1fr_auto]">
                        <div>
                            <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Item</label>
                            <select class="theme-input mt-2" name="shop_item_rows[__INDEX__][item_slug]">
                                <option value="">Select item</option>
                                @foreach($itemSlugs as $itemSlug)
                                    <option value="{{ $itemSlug }}">{{ $itemSlug }}</option>
                                @endforeach
                            </select>
                            <p class="admin-inline-hint">Only items listed here can be bought from this vendor.</p>
                        </div>

                        <div>
                            <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Price</label>
                            <input type="number" min="0" class="theme-input mt-2" name="shop_item_rows[__INDEX__][price]" value="0">
                            <p class="admin-inline-hint">Keep early-game potion prices in the low double digits.</p>
                        </div>

                        <div>
                            <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Stock</label>
                            <input type="number" min="0" class="theme-input mt-2" name="shop_item_rows[__INDEX__][stock]" placeholder="Unlimited">
                            <p class="admin-inline-hint">Leave empty for unlimited stock.</p>
                        </div>

                        <div>
                            <label class="text-xs uppercase tracking-[0.24em] text-stone-700/70">Sort</label>
                            <input type="number" min="0" class="theme-input mt-2" name="shop_item_rows[__INDEX__][sort_order]" value="10">
                            <p class="admin-inline-hint">Auto-renumbered when you drag rows.</p>
                        </div>
                    </div>
                </div>
            </template>
        </div>
</div>
