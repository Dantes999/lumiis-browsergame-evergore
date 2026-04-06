<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A clickable hotspot on a map scene.
 *
 * Areas can either be service nodes such as town/shop/inn or hunting nodes
 * that define encounter values, pressure rules and loot tables.
 */
class LocationArea extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id',
        'slug',
        'name',
        'description',
        'area_kind',
        'service_type',
        'hotspot_x',
        'hotspot_y',
        'sort_order',
        'recommended_level',
        'monster_name',
        'lumiis_name',
        'boss_name',
        'monster_cooldown_seconds',
        'lumiis_cooldown_seconds',
        'boss_cooldown_seconds',
        'monster_exp_min',
        'monster_exp_max',
        'monster_yang_min',
        'monster_yang_max',
        'monster_damage_min',
        'monster_damage_max',
        'lumiis_exp_min',
        'lumiis_exp_max',
        'lumiis_yang_min',
        'lumiis_yang_max',
        'lumiis_damage_min',
        'lumiis_damage_max',
        'boss_exp_min',
        'boss_exp_max',
        'boss_yang_min',
        'boss_yang_max',
        'boss_damage_min',
        'boss_damage_max',
        'monster_pressure_gain',
        'lumiis_pressure_gain',
        'boss_pressure_threshold',
        'boss_base_chance',
        'boss_pressure_chance_per_point',
        'monster_loot_table',
        'lumiis_loot_table',
        'boss_loot_table',
    ];

    protected function casts(): array
    {
        return [
            'hotspot_x' => 'float',
            'hotspot_y' => 'float',
            'monster_loot_table' => 'array',
            'lumiis_loot_table' => 'array',
            'boss_loot_table' => 'array',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function characterProgress(): HasMany
    {
        return $this->hasMany(CharacterAreaProgress::class);
    }

    public function monsterAssignments(): HasMany
    {
        return $this->hasMany(LocationAreaMonster::class)->orderBy('sort_order');
    }

    /**
     * Service hotspots such as vendors can own one shop definition.
     */
    public function shop(): HasOne
    {
        return $this->hasOne(Shop::class);
    }

    /**
     * Service areas drive town interactions instead of combat.
     */
    public function isService(): bool
    {
        return $this->area_kind === 'service';
    }

    public function isHunting(): bool
    {
        return $this->area_kind === 'hunting';
    }

    public function encounterName(string $encounterType): ?string
    {
        return match ($encounterType) {
            'monster' => $this->monster_name,
            'lumiis' => $this->lumiis_name,
            'boss' => $this->boss_name,
            default => null,
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function lootTable(string $encounterType): array
    {
        return match ($encounterType) {
            'monster' => $this->monster_loot_table ?? [],
            'lumiis' => $this->lumiis_loot_table ?? [],
            'boss' => $this->boss_loot_table ?? [],
            default => [],
        };
    }

    public function bossChancePercent(int $pressure): float
    {
        if (! $this->isHunting()) {
            return 0.0;
        }

        // Bosses now emerge once the corruption pressure is filled,
        // so the UI presents progress toward that threshold rather than a random roll chance.
        if ($pressure >= $this->boss_pressure_threshold) {
            return 100.0;
        }

        return round(($pressure / max(1, $this->boss_pressure_threshold)) * 100, 2);
    }

    /**
     * Some hunting areas use explicit monster level bands instead of only broad area tuning.
     * The minimum configured assignment level is the safe server-side source of truth for
     * "can this character meaningfully hunt here yet?" checks.
     */
    public function minimumMonsterAssignmentLevel(): ?int
    {
        if (! $this->isHunting()) {
            return null;
        }

        $assignments = $this->relationLoaded('monsterAssignments')
            ? $this->monsterAssignments
            : $this->monsterAssignments()->get();

        $minimumLevel = $assignments
            ->pluck('minimum_character_level')
            ->filter(fn ($level): bool => $level !== null)
            ->min();

        return $minimumLevel === null ? null : (int) $minimumLevel;
    }
}
