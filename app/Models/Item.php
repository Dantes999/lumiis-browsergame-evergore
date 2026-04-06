<?php

namespace App\Models;

use App\Support\Game\MetinClassCatalog;
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
        $allowedRaces = $this->allowedRaces();

        return $allowedRaces === [] || in_array($race, $allowedRaces, true);
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
