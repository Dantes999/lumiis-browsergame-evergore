<?php

namespace App\Models;

use App\Support\Game\MetinClassCatalog;
use App\Support\Game\WeaponTypeCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

/**
 * Static game content row for both consumables and equippable items.
 */
class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'item_type',
        'equipment_slot',
        'weapon_type',
        'intrinsic_bonuses',
        'allowed_races',
        'description',
        'skill_slug',
        'price',
        'sell_price',
        'required_level',
        'stackable',
        'max_stack',
        'inventory_height',
        'icon_path',
        'attack_bonus',
        'defense_bonus',
        'health_bonus',
        'stamina_bonus',
        'speed_bonus',
        'travel_speed_percent_bonus',
        'heal_amount',
        'stamina_restore',
        'sp_restore',
    ];

    protected function casts(): array
    {
        return [
            'stackable' => 'boolean',
            'inventory_height' => 'integer',
            'intrinsic_bonuses' => 'array',
            'allowed_races' => 'array',
        ];
    }

    public function characterItems(): HasMany
    {
        return $this->hasMany(CharacterItem::class);
    }

    public function shopItems(): HasMany
    {
        return $this->hasMany(ShopItem::class);
    }

    public function isConsumable(): bool
    {
        return $this->item_type === 'consumable';
    }

    public function isSkillBook(): bool
    {
        return $this->item_type === 'skill-book' && filled($this->skill_slug);
    }

    public function isDirectlyUsable(): bool
    {
        return $this->isConsumable() || $this->isSkillBook();
    }

    /**
     * Skill books point back into the central catalog so Blade can render book targets
     * without hardcoding one-off inventory labels.
     *
     * @return array<string, mixed>|null
     */
    public function linkedSkillDefinition(): ?array
    {
        if (! $this->isSkillBook()) {
            return null;
        }

        return MetinClassCatalog::skillDefinition((string) $this->skill_slug);
    }

    /**
     * Equipment is identified by the presence of a target slot.
     */
    public function isEquippable(): bool
    {
        return $this->equipment_slot !== null;
    }

    public function isWeapon(): bool
    {
        return $this->equipment_slot === 'weapon';
    }

    public function isInventoryUpgradeItem(): bool
    {
        return in_array($this->slug, [
            MetinClassCatalog::soulStoneSlug(),
            'soul-prism',
            'soul-imprint',
            'soul-reweaver',
            'soul-anchor',
        ], true);
    }

    public function isInventorySmithItem(): bool
    {
        return in_array($this->slug, [
            'lumiis-shard',
        ], true);
    }

    public function isInventoryQuestItem(): bool
    {
        return in_array($this->slug, [
            'research-catalyst',
            'research-report',
            'orc-tooth',
            'orc-chief-seal',
            'black-orc-medallion',
            'ape-lord-emblem',
        ], true);
    }

    public function inventoryCategoryKey(): string
    {
        return match (true) {
            $this->isInventoryQuestItem() => 'quest',
            $this->isInventoryUpgradeItem() => 'soul-upgrade',
            $this->isInventorySmithItem() => 'smith-upgrade',
            $this->isSkillBook() => 'skill-books',
            $this->isConsumable() && (int) $this->heal_amount > 0 => 'hp-potions',
            $this->isConsumable() && (int) $this->sp_restore > 0 => 'sp-potions',
            $this->isConsumable() => 'consumables',
            $this->isEquippable() => 'equipment',
            $this->item_type === 'material' => 'materials',
            default => 'misc',
        };
    }

    public function inventoryCategoryLabel(): string
    {
        return match ($this->inventoryCategoryKey()) {
            'hp-potions' => __('HP Potions'),
            'sp-potions' => __('SP Potions'),
            'skill-books' => __('Books'),
            'soul-upgrade' => __('Soul Upgrade'),
            'smith-upgrade' => __('Smith Upgrade'),
            'consumables' => __('Potions & Use'),
            'equipment' => __('Equipment'),
            'quest' => __('Quest'),
            'materials' => __('Materials'),
            default => __('Misc'),
        };
    }

    /**
     * Most early items stay universal, but later class weapons need a readable ownership rule.
     *
     * @return list<string>
     */
    public function allowedRaces(): array
    {
        $allowedRaces = Arr::wrap($this->allowed_races);

        return collect($allowedRaces)
            ->filter(fn (mixed $race): bool => is_string($race) && MetinClassCatalog::hasRace($race))
            ->values()
            ->all();
    }

    public function canBeEquippedByRace(string $race): bool
    {
        if ($this->isWeapon() && ! WeaponTypeCatalog::supportsRace($this->weaponType(), $race)) {
            return false;
        }

        $allowedRaces = $this->allowedRaces();

        return $allowedRaces === [] || in_array($race, $allowedRaces, true);
    }

    public function weaponType(): ?string
    {
        $weaponType = (string) ($this->getAttribute('weapon_type') ?? '');

        return WeaponTypeCatalog::isKnown($weaponType) ? $weaponType : null;
    }

    public function weaponTypeLabel(): ?string
    {
        return WeaponTypeCatalog::label($this->weaponType());
    }

    /**
     * @return list<array{type?: ?string, label: string, value: int, suffix?: string}>
     */
    public function intrinsicBonuses(): array
    {
        return collect($this->intrinsic_bonuses ?? [])
            ->filter(fn (mixed $entry): bool => is_array($entry) && filled($entry['label'] ?? null))
            ->map(function (array $entry): array {
                return [
                    'type' => isset($entry['type']) ? (string) $entry['type'] : null,
                    'label' => (string) $entry['label'],
                    'value' => (int) ($entry['value'] ?? 0),
                    'suffix' => (string) ($entry['suffix'] ?? ''),
                ];
            })
            ->filter(fn (array $entry): bool => $entry['value'] !== 0)
            ->values()
            ->all();
    }

    public function intrinsicBonusValue(string $bonusType): int
    {
        return (int) collect($this->intrinsicBonuses())
            ->where('type', $bonusType)
            ->sum('value');
    }

    /**
     * @return list<string>
     */
    public function intrinsicBonusLines(): array
    {
        return collect($this->intrinsicBonuses())
            ->map(function (array $entry): string {
                return '+'.$entry['value'].($entry['suffix'] ?? '').' '.$entry['label'];
            })
            ->values()
            ->all();
    }

    /**
     * The bag grid only supports narrow vertical items for now.
     * We clamp here so broken admin data cannot create impossible footprints.
     */
    public function inventoryHeight(): int
    {
        return max(1, min(3, (int) $this->inventory_height));
    }
}
