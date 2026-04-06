<?php

namespace App\Services\Game;

use App\Exceptions\GameActionException;
use App\Models\CharacterItem;
use App\Models\CharacterItemBonus;
use App\Models\GameCharacter;
use App\Models\Item;
use App\Support\Game\ItemBonusCatalog;
use Illuminate\Support\Collection;

/**
 * Small helper around inventory persistence and bag layout.
 *
 * The important rule is that the server owns every slot position.
 * The browser may suggest a move, but collision checks and fallback placement
 * always happen here so drag-and-drop cannot create overlaps, dupes or ghost items.
 */
class CharacterInventoryService
{
    /**
     * Grants an item to a character.
     * Stackable items merge into one row, while equipment gets one row per copy.
     *
     * @param  array<string, mixed>  $options
     */
    public function grant(GameCharacter $character, Item|string $item, int $quantity = 1, array $options = []): void
    {
        $itemModel = $item instanceof Item
            ? $item
            : Item::query()->where('slug', $item)->firstOrFail();

        if ($quantity < 1) {
            return;
        }

        if ($itemModel->stackable) {
            $inventoryItem = CharacterItem::query()->firstOrCreate(
                [
                    'game_character_id' => $character->id,
                    'item_id' => $itemModel->id,
                ],
                ['quantity' => 0],
            );

            // New stack rows need a physical bag position before they can be rendered.
            if ($inventoryItem->wasRecentlyCreated || ! $inventoryItem->isStoredInInventory()) {
                $this->storeInFirstFreeSlot($character, $inventoryItem);
            }

            $inventoryItem->increment('quantity', $quantity);
            $this->refreshCharacterInventoryRelation($character);

            return;
        }

        for ($index = 0; $index < $quantity; $index++) {
            $inventoryItem = CharacterItem::query()->create([
                'game_character_id' => $character->id,
                'item_id' => $itemModel->id,
                'quantity' => 1,
                'bonus_slot_limit' => 4,
            ]);

            $this->applyGrantedItemBonuses($inventoryItem, $itemModel, $options);
            $this->storeInFirstFreeSlot($character, $inventoryItem);
        }
    }

    /**
     * Consumes items from inventory and deletes the row when the stack reaches zero.
     */
    public function consume(CharacterItem $inventoryItem, int $quantity = 1): void
    {
        if ($quantity < 1) {
            return;
        }

        if ($inventoryItem->quantity <= $quantity) {
            $inventoryItem->delete();

            return;
        }

        $inventoryItem->decrement('quantity', $quantity);
    }

    /**
     * Convenience lookup for inventory-driven systems such as smith materials or
     * boss-drop consumables. The returned row is always owned by the given character.
     */
    public function stackBySlug(GameCharacter $character, string $itemSlug): ?CharacterItem
    {
        return CharacterItem::query()
            ->where('game_character_id', $character->id)
            ->whereHas('item', fn ($query) => $query->where('slug', $itemSlug))
            ->with(['item', 'equipment', 'bonuses'])
            ->orderByDesc('quantity')
            ->orderBy('id')
            ->first();
    }

    public function quantityForSlug(GameCharacter $character, string $itemSlug): int
    {
        return (int) CharacterItem::query()
            ->where('game_character_id', $character->id)
            ->whereHas('item', fn ($query) => $query->where('slug', $itemSlug))
            ->sum('quantity');
    }

    /**
     * Item turn-in quests and future crafting systems often want to consume by content slug
     * instead of by a hand-picked inventory row.
     *
     * The server still controls exactly which rows are decremented so the browser cannot
     * target another player's item or bypass stack counts.
     */
    public function consumeBySlug(GameCharacter $character, string $itemSlug, int $quantity = 1): void
    {
        if ($quantity < 1) {
            return;
        }

        $inventoryItems = CharacterItem::query()
            ->where('game_character_id', $character->id)
            ->whereHas('item', fn ($query) => $query->where('slug', $itemSlug))
            ->with(['item', 'equipment', 'bonuses'])
            ->lockForUpdate()
            ->orderByDesc('quantity')
            ->orderBy('id')
            ->get();

        if ((int) $inventoryItems->sum('quantity') < $quantity) {
            throw new GameActionException(__('You do not carry enough of that item.'));
        }

        $remaining = $quantity;

        foreach ($inventoryItems as $inventoryItem) {
            $consumeQuantity = min($remaining, (int) $inventoryItem->quantity);

            $this->consume($inventoryItem, $consumeQuantity);
            $remaining -= $consumeQuantity;

            if ($remaining <= 0) {
                break;
            }
        }

        $this->refreshCharacterInventoryRelation($character);
    }

    /**
     * Boss consumables can extend one owned item with one extra legal bonus line.
     * The service keeps the slot/type checks together so controllers do not need
     * to understand bonus catalog internals.
     */
    public function unlockAdditionalBonus(CharacterItem $inventoryItem, string $source = 'boss'): ?CharacterItemBonus
    {
        $inventoryItem->loadMissing('item', 'bonuses');

        if ($inventoryItem->item === null || ! $inventoryItem->item->isEquippable()) {
            throw new GameActionException(__('Only equippable items can receive extra bonuses.'));
        }

        $currentBonusCount = $inventoryItem->bonusCount();
        $slotLimit = max(0, (int) $inventoryItem->bonus_slot_limit);

        if ($slotLimit >= 5) {
            throw new GameActionException(__('That item already unlocked its fifth bonus slot.'));
        }

        if ($currentBonusCount < 4) {
            throw new GameActionException(__('Fill the first four bonus slots before unlocking the fifth bonus.'));
        }

        $availableTypes = array_values(array_diff(
            ItemBonusCatalog::rollableTypesForItem($inventoryItem->item),
            $inventoryItem->bonuses->pluck('bonus_type')->all(),
        ));

        if ($availableTypes === []) {
            throw new GameActionException(__('No additional compatible bonus remains for that item.'));
        }

        shuffle($availableTypes);
        $bonusType = $availableTypes[0];

        $inventoryItem->forceFill([
            'bonus_slot_limit' => 5,
        ])->save();

        $bonus = CharacterItemBonus::query()->create([
            'character_item_id' => $inventoryItem->id,
            'slot_number' => $currentBonusCount + 1,
            'bonus_type' => $bonusType,
            'bonus_value' => ItemBonusCatalog::rollValue($bonusType, $source),
        ]);

        $inventoryItem->unsetRelation('bonuses');
        $inventoryItem->load('bonuses');

        return $bonus;
    }

    /**
     * Bonus-add consumables fill one still-open slot with a new compatible line.
     */
    public function addRandomBonus(CharacterItem $inventoryItem, string $source = 'lumiis'): CharacterItemBonus
    {
        $inventoryItem->loadMissing('item', 'bonuses');

        if ($inventoryItem->item === null || ! $inventoryItem->item->isEquippable()) {
            throw new GameActionException(__('Only equippable items can receive extra bonuses.'));
        }

        if (! $inventoryItem->hasOpenBonusSlot()) {
            throw new GameActionException(__('That item has no open bonus slot right now.'));
        }

        $availableTypes = array_values(array_diff(
            ItemBonusCatalog::rollableTypesForItem($inventoryItem->item),
            $inventoryItem->bonuses->pluck('bonus_type')->all(),
        ));

        if ($availableTypes === []) {
            throw new GameActionException(__('No additional compatible bonus remains for that item.'));
        }

        shuffle($availableTypes);
        $bonusType = $availableTypes[0];

        $bonus = CharacterItemBonus::query()->create([
            'character_item_id' => $inventoryItem->id,
            'slot_number' => $inventoryItem->bonusCount() + 1,
            'bonus_type' => $bonusType,
            'bonus_value' => ItemBonusCatalog::rollValue($bonusType, $source),
        ]);

        $inventoryItem->unsetRelation('bonuses');
        $inventoryItem->load('bonuses');

        return $bonus;
    }

    /**
     * Rebuild the existing bonus set from scratch while optionally preserving one chosen line.
     * This mirrors the typical browser-MMO reroll flow without letting duplicate bonus types slip in.
     */
    public function rerollBonuses(CharacterItem $inventoryItem, ?int $lockedBonusId = null, string $source = 'lumiis'): void
    {
        $inventoryItem->loadMissing('item', 'bonuses');

        if ($inventoryItem->item === null || ! $inventoryItem->item->isEquippable()) {
            throw new GameActionException(__('Only equippable items can reroll their bonuses.'));
        }

        $currentBonuses = $inventoryItem->bonuses->sortBy('slot_number')->values();
        $currentBonusCount = $currentBonuses->count();

        if ($currentBonusCount < 1) {
            throw new GameActionException(__('That item has no bonuses to reroll yet.'));
        }

        /** @var CharacterItemBonus|null $lockedBonus */
        $lockedBonus = $lockedBonusId === null
            ? null
            : $currentBonuses->firstWhere('id', $lockedBonusId);

        if ($lockedBonusId !== null && $lockedBonus === null) {
            throw new GameActionException(__('That locked bonus does not belong to the chosen item.'));
        }

        if ($lockedBonus !== null && $currentBonusCount < 2) {
            throw new GameActionException(__('Locking only makes sense once the item has multiple bonuses.'));
        }

        $requiredFreshBonusCount = $currentBonusCount - ($lockedBonus !== null ? 1 : 0);
        $availableTypes = array_values(array_diff(
            ItemBonusCatalog::rollableTypesForItem($inventoryItem->item),
            $lockedBonus !== null ? [$lockedBonus->bonus_type] : [],
        ));

        if (count($availableTypes) < $requiredFreshBonusCount) {
            throw new GameActionException(__('Not enough compatible bonus types remain for that reroll.'));
        }

        shuffle($availableTypes);
        $freshTypes = array_slice($availableTypes, 0, $requiredFreshBonusCount);
        $freshBlueprints = array_map(
            fn (string $bonusType): array => [
                'bonus_type' => $bonusType,
                'bonus_value' => ItemBonusCatalog::rollValue($bonusType, $source),
            ],
            $freshTypes,
        );

        $rebuiltBonuses = [];
        $freshIndex = 0;

        foreach ($currentBonuses as $originalBonus) {
            if ($lockedBonus !== null && $originalBonus->id === $lockedBonus->id) {
                $rebuiltBonuses[] = [
                    'slot_number' => $originalBonus->slot_number,
                    'bonus_type' => $originalBonus->bonus_type,
                    'bonus_value' => $originalBonus->bonus_value,
                ];

                continue;
            }

            $freshBlueprint = $freshBlueprints[$freshIndex] ?? null;
            $freshIndex++;

            if ($freshBlueprint === null) {
                throw new GameActionException(__('The reroll could not generate enough replacement bonuses.'));
            }

            $rebuiltBonuses[] = [
                'slot_number' => $originalBonus->slot_number,
                'bonus_type' => $freshBlueprint['bonus_type'],
                'bonus_value' => $freshBlueprint['bonus_value'],
            ];
        }

        CharacterItemBonus::query()
            ->where('character_item_id', $inventoryItem->id)
            ->delete();

        foreach ($rebuiltBonuses as $bonusData) {
            CharacterItemBonus::query()->create([
                'character_item_id' => $inventoryItem->id,
                'slot_number' => $bonusData['slot_number'],
                'bonus_type' => $bonusData['bonus_type'],
                'bonus_value' => $bonusData['bonus_value'],
            ]);
        }

        $inventoryItem->unsetRelation('bonuses');
        $inventoryItem->load('bonuses');
    }

    /**
     * Drag-and-drop only proposes a destination.
     * The move is accepted only if the full item footprint fits and no other item already occupies it.
     */
    public function move(GameCharacter $character, CharacterItem $inventoryItem, int $column, int $row): void
    {
        if ($inventoryItem->isEquipped()) {
            throw new GameActionException(__('Equipped items must be unequipped before they can be moved in the bag.'));
        }

        $this->ensureLayout($character);

        if (! $this->fitsWithinGrid($inventoryItem, $column, $row)) {
            throw new GameActionException(__('That inventory move would place the item outside the bag.'));
        }

        $occupiedCells = $this->claimedCellsForCharacter($character, [$inventoryItem->id]);

        if (! $this->cellsAreFree($inventoryItem, $column, $row, $occupiedCells)) {
            throw new GameActionException(__('That inventory slot is already occupied by another item.'));
        }

        $inventoryItem->forceFill([
            'inventory_column' => $column,
            'inventory_row' => $row,
        ])->save();

        $this->refreshCharacterInventoryRelation($character);
    }

    /**
     * Equipped rows intentionally leave the bag grid.
     */
    public function detachFromGrid(CharacterItem $inventoryItem): void
    {
        $inventoryItem->forceFill([
            'inventory_column' => null,
            'inventory_row' => null,
        ])->save();
    }

    /**
     * Used when an item is unequipped or when a newly looted copy needs the next legal spot.
     */
    public function storeInFirstFreeSlot(GameCharacter $character, CharacterItem $inventoryItem): void
    {
        $this->ensureLayout($character);

        $occupiedCells = $this->claimedCellsForCharacter($character, [$inventoryItem->id]);
        $position = $this->firstFreePosition($inventoryItem, $occupiedCells);

        if ($position === null) {
            throw new GameActionException(__('Your inventory is full. Free some space before taking or moving more items.'));
        }

        $inventoryItem->forceFill([
            'inventory_column' => $position['column'],
            'inventory_row' => $position['row'],
        ])->save();

        $this->refreshCharacterInventoryRelation($character);
    }

    /**
     * Repairs stale or invalid layouts without destroying valid player-made arrangements.
     *
     * This is important after schema changes or future admin edits: already valid placements stay
     * where they are, and only missing/overlapping/out-of-bounds rows get moved.
     */
    public function ensureLayout(GameCharacter $character): void
    {
        $inventoryItems = $this->inventoryItemsForCharacter($character);
        $claimedCells = [];
        $itemsNeedingPlacement = collect();
        $layoutChanged = false;

        foreach ($inventoryItems as $inventoryItem) {
            if ($inventoryItem->isEquipped()) {
                if ($inventoryItem->inventory_column !== null || $inventoryItem->inventory_row !== null) {
                    $this->detachFromGrid($inventoryItem);
                    $layoutChanged = true;
                }

                continue;
            }

            if (
                $inventoryItem->inventory_column === null
                || $inventoryItem->inventory_row === null
                || ! $this->fitsWithinGrid(
                    $inventoryItem,
                    (int) $inventoryItem->inventory_column,
                    (int) $inventoryItem->inventory_row,
                )
                || ! $this->cellsAreFree(
                    $inventoryItem,
                    (int) $inventoryItem->inventory_column,
                    (int) $inventoryItem->inventory_row,
                    $claimedCells,
                )
            ) {
                $itemsNeedingPlacement->push($inventoryItem);

                continue;
            }

            $this->claimCells($inventoryItem, (int) $inventoryItem->inventory_column, (int) $inventoryItem->inventory_row, $claimedCells);
        }

        foreach ($itemsNeedingPlacement as $inventoryItem) {
            $position = $this->firstFreePosition($inventoryItem, $claimedCells);

            if ($position === null) {
                throw new GameActionException(__('Your inventory layout cannot be repaired because the bag is full.'));
            }

            $inventoryItem->forceFill([
                'inventory_column' => $position['column'],
                'inventory_row' => $position['row'],
            ])->save();

            $this->claimCells($inventoryItem, $position['column'], $position['row'], $claimedCells);
            $layoutChanged = true;
        }

        if ($layoutChanged) {
            $this->refreshCharacterInventoryRelation($character);
        }
    }

    /**
     * @return Collection<int, CharacterItem>
     */
    private function inventoryItemsForCharacter(GameCharacter $character): Collection
    {
        // Layout-sensitive operations should never rely on a stale eager-loaded relation because
        // the current request may already have added, removed or detached items earlier in the same action.
        $character->unsetRelation('inventoryItems');
        $character->load('inventoryItems.item', 'inventoryItems.equipment', 'inventoryItems.bonuses');

        return $character->inventoryItems
            ->sortBy([
                fn (CharacterItem $inventoryItem): int => $inventoryItem->isEquipped() ? 1 : 0,
                fn (CharacterItem $inventoryItem): int => $inventoryItem->inventory_row ?? CharacterItem::INVENTORY_ROWS,
                fn (CharacterItem $inventoryItem): int => $inventoryItem->inventory_column ?? CharacterItem::INVENTORY_COLUMNS,
                fn (CharacterItem $inventoryItem): int => $inventoryItem->id,
            ])
            ->values();
    }

    /**
     * @param  array<string, int>  $claimedCells
     * @return array{column: int, row: int}|null
     */
    private function firstFreePosition(CharacterItem $inventoryItem, array $claimedCells): ?array
    {
        for ($row = 0; $row < CharacterItem::INVENTORY_ROWS; $row++) {
            for ($column = 0; $column < CharacterItem::INVENTORY_COLUMNS; $column++) {
                if (! $this->fitsWithinGrid($inventoryItem, $column, $row)) {
                    continue;
                }

                if (! $this->cellsAreFree($inventoryItem, $column, $row, $claimedCells)) {
                    continue;
                }

                return [
                    'column' => $column,
                    'row' => $row,
                ];
            }
        }

        return null;
    }

    private function fitsWithinGrid(CharacterItem $inventoryItem, int $column, int $row): bool
    {
        return $column >= 0
            && $row >= 0
            && $column < CharacterItem::INVENTORY_COLUMNS
            && ($row + $inventoryItem->inventoryHeight()) <= CharacterItem::INVENTORY_ROWS;
    }

    /**
     * @param  array<string, int>  $claimedCells
     */
    private function cellsAreFree(CharacterItem $inventoryItem, int $column, int $row, array $claimedCells): bool
    {
        for ($offset = 0; $offset < $inventoryItem->inventoryHeight(); $offset++) {
            $cellKey = $this->cellKey($column, $row + $offset);

            if (array_key_exists($cellKey, $claimedCells)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, int>  $claimedCells
     */
    private function claimCells(CharacterItem $inventoryItem, int $column, int $row, array &$claimedCells): void
    {
        for ($offset = 0; $offset < $inventoryItem->inventoryHeight(); $offset++) {
            $claimedCells[$this->cellKey($column, $row + $offset)] = $inventoryItem->id;
        }
    }

    /**
     * @param  list<int>  $ignoredItemIds
     * @return array<string, int>
     */
    private function claimedCellsForCharacter(GameCharacter $character, array $ignoredItemIds = []): array
    {
        $claimedCells = [];

        foreach ($this->inventoryItemsForCharacter($character) as $inventoryItem) {
            if ($inventoryItem->isEquipped() || ! $inventoryItem->isStoredInInventory()) {
                continue;
            }

            if (in_array($inventoryItem->id, $ignoredItemIds, true)) {
                continue;
            }

            $this->claimCells(
                $inventoryItem,
                (int) $inventoryItem->inventory_column,
                (int) $inventoryItem->inventory_row,
                $claimedCells,
            );
        }

        return $claimedCells;
    }

    private function cellKey(int $column, int $row): string
    {
        return $column.':'.$row;
    }

    private function refreshCharacterInventoryRelation(GameCharacter $character): void
    {
        $character->unsetRelation('inventoryItems');
        $character->load('inventoryItems.item', 'inventoryItems.equipment', 'inventoryItems.bonuses');
    }

    /**
     * Combat and future boss-drop systems can ask for individualized bonus lines here.
     * Shops, starter items and quest rewards can stay clean by simply omitting the option.
     *
     * @param  array<string, mixed>  $options
     */
    private function applyGrantedItemBonuses(CharacterItem $inventoryItem, Item $itemModel, array $options): void
    {
        if (! $itemModel->isEquippable()) {
            return;
        }

        $bonusBlueprint = collect(data_get($options, 'bonus_blueprint', []))
            ->filter(fn (mixed $entry): bool => is_array($entry))
            ->values();
        $rollBonuses = (bool) data_get($options, 'roll_bonuses', false);

        if ($bonusBlueprint->isEmpty() && ! $rollBonuses) {
            return;
        }

        $bonusEntries = $bonusBlueprint->isNotEmpty()
            ? $bonusBlueprint->all()
            : $this->rollBonusBlueprintsForItem(
                $itemModel,
                (string) data_get($options, 'bonus_source', 'monster'),
                (int) $inventoryItem->bonus_slot_limit,
            );
        $allowedBonusTypes = array_flip(ItemBonusCatalog::rollableTypesForItem($itemModel));
        $slotLimit = max(0, (int) $inventoryItem->bonus_slot_limit);
        $bonusEntries = array_values(array_filter(
            $bonusEntries,
            static fn (array $bonusEntry): bool => isset($allowedBonusTypes[(string) data_get($bonusEntry, 'type', '')]),
        ));
        $bonusEntries = array_slice($bonusEntries, 0, $slotLimit);

        foreach (array_values($bonusEntries) as $index => $bonusEntry) {
            $bonusType = (string) data_get($bonusEntry, 'type', '');
            $bonusValue = (int) data_get($bonusEntry, 'value', 0);

            if (! ItemBonusCatalog::isKnownType($bonusType) || $bonusValue <= 0) {
                continue;
            }

            CharacterItemBonus::query()->create([
                'character_item_id' => $inventoryItem->id,
                'slot_number' => $index + 1,
                'bonus_type' => $bonusType,
                'bonus_value' => $bonusValue,
            ]);
        }
    }

    /**
     * @return list<array{type: string, value: int}>
     */
    private function rollBonusBlueprintsForItem(Item $itemModel, string $source, int $slotLimit): array
    {
        $availableTypes = ItemBonusCatalog::rollableTypesForItem($itemModel);

        if ($availableTypes === []) {
            return [];
        }

        $preferredTypes = ItemBonusCatalog::preferredTypesForItem($itemModel);
        $rollCount = min($slotLimit, count($availableTypes), ItemBonusCatalog::rollCountForSource($source));
        $rollCount = max($rollCount, min($slotLimit, count($preferredTypes)));

        if ($rollCount < 1) {
            return [];
        }

        $selectedTypes = [];

        foreach ($preferredTypes as $preferredType) {
            if (! in_array($preferredType, $availableTypes, true)) {
                continue;
            }

            $selectedTypes[] = $preferredType;
        }

        $remainingTypes = array_values(array_diff($availableTypes, $selectedTypes));
        shuffle($remainingTypes);
        $selectedTypes = array_values(array_unique([
            ...$selectedTypes,
            ...array_slice($remainingTypes, 0, max(0, $rollCount - count($selectedTypes))),
        ]));

        return array_map(
            fn (string $bonusType): array => [
                'type' => $bonusType,
                'value' => ItemBonusCatalog::rollValue($bonusType, $source),
            ],
            $selectedTypes,
        );
    }
}
