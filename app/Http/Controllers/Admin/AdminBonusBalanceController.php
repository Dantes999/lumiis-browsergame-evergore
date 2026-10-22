<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBonusBalanceRequest;
use App\Models\Item;
use App\Models\ItemBonusDefinition;
use App\Models\LocationArea;
use App\Support\Game\ItemBonusCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Admin editor for item bonus rules, soul consumables and soul-item drop chances.
 */
class AdminBonusBalanceController extends Controller
{
    /**
     * @var list<string>
     */
    private const SOUL_ITEM_SLUGS = [
        'soul-stone',
        'soul-imprint',
        'soul-reweaver',
        'soul-anchor',
        'soul-prism',
    ];

    public function index(): View
    {
        $areas = LocationArea::query()
            ->with('location')
            ->where('area_kind', 'hunting')
            ->orderBy('location_id')
            ->orderBy('sort_order')
            ->get();

        return view('admin.bonus-balance.index', [
            'bonusDefinitions' => ItemBonusDefinition::query()
                ->orderBy('sort_order')
                ->orderBy('bonus_type')
                ->get(),
            'slotOptions' => ItemBonusCatalog::supportedSlots(),
            'soulItems' => $this->orderedSoulItems(),
            'areaDrops' => $areas->map(function (LocationArea $area): array {
                return [
                    'area' => $area,
                    'lumiis_soul_imprint_chance' => $this->lootChance($area->lumiis_loot_table ?? [], 'soul-imprint'),
                    'lumiis_soul_reweaver_chance' => $this->lootChance($area->lumiis_loot_table ?? [], 'soul-reweaver'),
                    'boss_soul_imprint_chance' => $this->lootChance($area->boss_loot_table ?? [], 'soul-imprint'),
                    'boss_soul_reweaver_chance' => $this->lootChance($area->boss_loot_table ?? [], 'soul-reweaver'),
                    'boss_soul_anchor_chance' => $this->lootChance($area->boss_loot_table ?? [], 'soul-anchor'),
                    'boss_soul_stone_chance' => $this->lootChance($area->boss_loot_table ?? [], 'soul-stone'),
                    'boss_soul_prism_chance' => $this->lootChance($area->boss_loot_table ?? [], 'soul-prism'),
                ];
            })->values(),
        ]);
    }

    public function update(UpdateBonusBalanceRequest $request): RedirectResponse
    {
        foreach ($request->validated('bonus_definitions') as $definitionData) {
            $definition = ItemBonusDefinition::query()->findOrFail($definitionData['id']);
            $minimumValue = max(0, (int) $definitionData['minimum_value']);

            $definition->fill([
                'label' => $definitionData['label'],
                'minimum_value' => $minimumValue,
                'maximum_value' => max($minimumValue, (int) $definitionData['maximum_value']),
                'suffix' => (string) ($definitionData['suffix'] ?? ''),
                'allowed_slots' => $this->normalizeSlots($definitionData['allowed_slots'] ?? []),
                'enabled' => (bool) ($definitionData['enabled'] ?? false),
                'sort_order' => max(0, (int) $definitionData['sort_order']),
            ]);
            $definition->save();
        }

        ItemBonusDefinition::flushRuntimeCache();

        foreach ($request->validated('soul_items') as $itemData) {
            $item = Item::query()->findOrFail($itemData['id']);

            $item->fill([
                'name' => $itemData['name'],
                'description' => $itemData['description'],
                'sell_price' => max(0, (int) $itemData['sell_price']),
                'required_level' => max(1, (int) $itemData['required_level']),
            ]);
            $item->save();
        }

        foreach ($request->validated('area_drops') as $dropData) {
            $area = LocationArea::query()->findOrFail($dropData['area_id']);

            $area->lumiis_loot_table = $this->syncLootChance(
                $this->syncLootChance($area->lumiis_loot_table ?? [], 'soul-imprint', (int) $dropData['lumiis_soul_imprint_chance']),
                'soul-reweaver',
                (int) $dropData['lumiis_soul_reweaver_chance'],
            );

            $bossLootTable = $area->boss_loot_table ?? [];
            $bossLootTable = $this->syncLootChance($bossLootTable, 'soul-imprint', (int) $dropData['boss_soul_imprint_chance']);
            $bossLootTable = $this->syncLootChance($bossLootTable, 'soul-reweaver', (int) $dropData['boss_soul_reweaver_chance']);
            $bossLootTable = $this->syncLootChance($bossLootTable, 'soul-anchor', (int) $dropData['boss_soul_anchor_chance']);
            $bossLootTable = $this->syncLootChance($bossLootTable, 'soul-stone', (int) $dropData['boss_soul_stone_chance']);
            $bossLootTable = $this->syncLootChance($bossLootTable, 'soul-prism', (int) $dropData['boss_soul_prism_chance']);

            $area->boss_loot_table = $bossLootTable;
            $area->save();
        }

        return to_route('admin.bonus-balance.index')->with('status', 'Bonus balancing updated.');
    }

    /**
     * @return Collection<int, Item>
     */
    private function orderedSoulItems()
    {
        $sortLookup = array_flip(self::SOUL_ITEM_SLUGS);

        return Item::query()
            ->whereIn('slug', self::SOUL_ITEM_SLUGS)
            ->get()
            ->sortBy(fn (Item $item): int => $sortLookup[$item->slug] ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * @param  array<int, array<string, mixed>>  $lootTable
     */
    private function lootChance(array $lootTable, string $itemSlug): int
    {
        foreach ($lootTable as $entry) {
            if ((string) ($entry['slug'] ?? $entry['item_slug'] ?? '') === $itemSlug) {
                return max(0, (int) ($entry['chance'] ?? 0));
            }
        }

        return 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lootTable
     * @return array<int, array<string, mixed>>
     */
    private function syncLootChance(array $lootTable, string $itemSlug, int $chance): array
    {
        $chance = max(0, $chance);
        $updatedTable = [];
        $handled = false;

        foreach ($lootTable as $entry) {
            if ((string) ($entry['slug'] ?? $entry['item_slug'] ?? '') !== $itemSlug) {
                $updatedTable[] = $entry;

                continue;
            }

            if ($handled || $chance === 0) {
                continue;
            }

            $entry['slug'] = $itemSlug;
            $entry['chance'] = $chance;
            $entry['min'] = max(1, (int) ($entry['min'] ?? $entry['quantity_min'] ?? 1));
            $entry['max'] = max($entry['min'], (int) ($entry['max'] ?? $entry['quantity_max'] ?? 1));
            unset($entry['item_slug'], $entry['quantity_min'], $entry['quantity_max']);
            $updatedTable[] = $entry;
            $handled = true;
        }

        if (! $handled && $chance > 0) {
            $updatedTable[] = [
                'slug' => $itemSlug,
                'chance' => $chance,
                'min' => 1,
                'max' => 1,
            ];
        }

        return array_values($updatedTable);
    }

    /**
     * @return list<string>
     */
    private function normalizeSlots(array $slots): array
    {
        $supportedSlots = array_flip(ItemBonusCatalog::supportedSlots());

        return collect($slots)
            ->map(fn (mixed $slot): string => trim((string) $slot))
            ->filter(fn (string $slot): bool => $slot !== '' && isset($supportedSlots[$slot]))
            ->values()
            ->all();
    }
}
