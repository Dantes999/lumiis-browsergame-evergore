<?php

namespace App\Models;

use App\Support\Game\ItemBonusCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Inventory row owned by a character.
 */
class CharacterItem extends Model
{
    use HasFactory;

    public const INVENTORY_COLUMNS = 5;

    public const INVENTORY_ROWS = 9;

    protected $fillable = [
        'game_character_id',
        'item_id',
        'quantity',
        'upgrade_level',
        'bonus_slot_limit',
        'inventory_column',
        'inventory_row',
    ];

    protected function casts(): array
    {
        return [
            'upgrade_level' => 'integer',
            'bonus_slot_limit' => 'integer',
            'inventory_column' => 'integer',
            'inventory_row' => 'integer',
        ];
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(GameCharacter::class, 'game_character_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function equipment(): HasOne
    {
        return $this->hasOne(CharacterEquipment::class);
    }

    public function bonuses(): HasMany
    {
        return $this->hasMany(CharacterItemBonus::class)->orderBy('slot_number');
    }

    public function isEquipped(): bool
    {
        return $this->equipment !== null;
    }

    /**
     * Equipped items intentionally sit outside the bag grid.
     */
    public function isStoredInInventory(): bool
    {
        return $this->equipment === null
            && $this->inventory_column !== null
            && $this->inventory_row !== null;
    }

    /**
     * Player-facing label for one owned copy of an item.
     * Enhancement lives on the inventory row, so the name should reflect that.
     */
    public function displayName(): string
    {
        $baseName = $this->item?->name ?? 'Unknown Item';

        if (! $this->item?->isEquippable()) {
            return $baseName;
        }

        return $baseName.' +'.$this->upgrade_level;
    }

    /**
     * Only smith-upgradable equipment should use the plus system for now.
     * We keep the first version narrow on purpose: starter weapon flow first, other gear later.
     */
    public function canBeSmithUpgraded(): bool
    {
        return $this->item?->equipment_slot === 'weapon';
    }

    public function smithUpgradeCost(): int
    {
        return (int) SmithRefineRate::forUpgradeLevel($this->upgrade_level)?->yang_cost;
    }

    public function smithUpgradeShardCost(): int
    {
        return (int) SmithRefineRate::forUpgradeLevel($this->upgrade_level)?->lumiis_shard_cost;
    }

    public function smithUpgradeSuccessPercent(): int
    {
        return (int) SmithRefineRate::forUpgradeLevel($this->upgrade_level)?->success_percent;
    }

    public function bonusCount(): int
    {
        if (! $this->exists && ! $this->relationLoaded('bonuses')) {
            return 0;
        }

        $bonuses = $this->relationLoaded('bonuses')
            ? $this->bonuses
            : $this->bonuses()->get();

        return $bonuses->count();
    }

    public function canUnlockFifthBonus(): bool
    {
        return $this->item?->isEquippable() === true
            && (int) $this->bonus_slot_limit < 5
            && $this->bonusCount() >= 4;
    }

    public function hasOpenBonusSlot(): bool
    {
        return $this->item?->isEquippable() === true
            && $this->bonusCount() < max(0, (int) $this->bonus_slot_limit);
    }

    public function canRerollBonuses(): bool
    {
        return $this->item?->isEquippable() === true
            && $this->bonusCount() > 0;
    }

    public function hasBonusType(string $bonusType): bool
    {
        return $this->bonusValue($bonusType) > 0;
    }

    public function bonusValue(string $bonusType): int
    {
        if (! $this->exists && ! $this->relationLoaded('bonuses')) {
            return 0;
        }

        $bonuses = $this->relationLoaded('bonuses')
            ? $this->bonuses
            : $this->bonuses()->get();

        return (int) $bonuses
            ->where('bonus_type', $bonusType)
            ->sum('bonus_value');
    }

    /**
     * Bonus display stays on the model so Blade does not need to know catalog internals.
     *
     * @return list<string>
     */
    public function bonusLines(): array
    {
        if (! $this->exists && ! $this->relationLoaded('bonuses')) {
            return [];
        }

        $bonuses = $this->relationLoaded('bonuses')
            ? $this->bonuses
            : $this->bonuses()->get();

        return $bonuses
            ->map(fn (CharacterItemBonus $bonus): string => $bonus->displayLine())
            ->values()
            ->all();
    }

    public function rollableBonusTypes(): array
    {
        return $this->item === null ? [] : ItemBonusCatalog::rollableTypesForItem($this->item);
    }

    /**
     * Upgrade bonuses are derived per owned item because the plus level is not global content.
     */
    public function upgradeBonusFor(string $column): int
    {
        if ($this->upgrade_level < 1 || ! $this->item?->isEquippable()) {
            return 0;
        }

        return match ($column) {
            'attack_bonus' => $this->item->equipment_slot === 'weapon' ? $this->upgrade_level : 0,
            'defense_bonus' => in_array($this->item->equipment_slot, ['armor', 'offhand'], true) ? $this->upgrade_level : 0,
            'health_bonus' => $this->item->equipment_slot === 'armor' ? $this->upgrade_level * 2 : 0,
            'speed_bonus' => $this->item->equipment_slot === 'boots' ? intdiv($this->upgrade_level, 3) : 0,
            default => 0,
        };
    }

    /**
     * UI helpers should render the real effective stat contribution of this owned item,
     * including enhancement bonuses.
     */
    public function effectiveBonus(string $column): int
    {
        return (int) data_get($this->item, $column, 0) + $this->upgradeBonusFor($column);
    }

    /**
     * The first bag implementation keeps every item one cell wide and only varies by height.
     */
    public function inventoryHeight(): int
    {
        return $this->item?->inventoryHeight() ?? 1;
    }

    public function inventoryWidth(): int
    {
        return 1;
    }

    /**
     * UI helpers use this path directly so the inventory board can render item art without
     * hardcoding per-slug image rules in Blade.
     */
    public function iconPath(): ?string
    {
        return $this->item?->icon_path;
    }

    /**
     * @return list<array{column: int, row: int}>
     */
    public function occupiedInventoryCells(): array
    {
        if (! $this->isStoredInInventory()) {
            return [];
        }

        $cells = [];

        for ($offset = 0; $offset < $this->inventoryHeight(); $offset++) {
            $cells[] = [
                'column' => (int) $this->inventory_column,
                'row' => (int) $this->inventory_row + $offset,
            ];
        }

        return $cells;
    }

    public function occupiesInventoryCell(int $column, int $row): bool
    {
        if (! $this->isStoredInInventory()) {
            return false;
        }

        return $column === (int) $this->inventory_column
            && $row >= (int) $this->inventory_row
            && $row < ((int) $this->inventory_row + $this->inventoryHeight());
    }
}
