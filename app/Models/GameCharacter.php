<?php

namespace App\Models;

use App\Enums\GameCharacterState;
use App\Support\Game\ExperienceCurve;
use App\Support\Game\ItemBonusCatalog;
use App\Support\Game\MetinClassCatalog;
use App\Support\Game\TutorialFlow;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use App\Support\Game\FeatureUnlocks;

/**
 * Main player domain model.
 *
 * The raw columns hold the permanent base stats, while helper methods calculate
 * effective values after equipment and learned skills are applied.
 */
class GameCharacter extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'location_id',
        'name',
        'faction',
        'race',
        'skill_group',
        'game_state',
        'tutorial_step',
        'level',
        'experience',
        'yang',
        'health',
        'max_health',
        'stamina',
        'max_stamina',
        'attack',
        'defense',
        'speed',
        'magic_attack',
        'crit_chance',
        'evade_chance',
        'monster_damage',
        'lumiis_damage',
        'experience_rate',
        'yang_rate',
        'loot_rate',
        'vitality',
        'intelligence',
        'strength',
        'dexterity',
        'sp',
        'max_sp',
        'unspent_skill_points',
        'unspent_status_points',
        'action_available_at',
        'last_active_at',
        'stamina_updated_at',
        'health_updated_at',
        'sp_updated_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'game_state' => GameCharacterState::class,
            'action_available_at' => 'datetime',
            'last_active_at' => 'datetime',
            'stamina_updated_at' => 'datetime',
            'health_updated_at' => 'datetime',
            'sp_updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function actionLogs(): HasMany
    {
        return $this->hasMany(GameActionLog::class);
    }

    public function battles(): HasMany
    {
        return $this->hasMany(GameBattle::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(CharacterItem::class);
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(CharacterEquipment::class);
    }

    public function learnedSkills(): HasMany
    {
        return $this->hasMany(CharacterSkill::class);
    }

    public function areaProgress(): HasMany
    {
        return $this->hasMany(CharacterAreaProgress::class);
    }

    public function characterQuests(): HasMany
    {
        return $this->hasMany(CharacterQuest::class);
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(GameMessage::class, 'sender_character_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(GameMessage::class, 'recipient_character_id');
    }

    public function isOnCooldown(): bool
    {
        return $this->action_available_at?->isFuture() ?? false;
    }

    /**
     * Used by the UI countdown and by gameplay guard messages.
     */
    public function cooldownSecondsRemaining(): int
    {
        if (! $this->isOnCooldown()) {
            return 0;
        }

        return max(0, now()->diffInSeconds($this->action_available_at, false));
    }

    public function nextLevelExperience(): int
    {
        return ExperienceCurve::requiredForLevel($this->level + 1);
    }

    public function isInTutorial(): bool
    {
        return TutorialFlow::enabled() && $this->game_state === GameCharacterState::Tutorial;
    }

    public function currentTutorialStep(): int
    {
        return TutorialFlow::currentStep($this);
    }

    public function advancedTownServicesUnlocked(): bool
    {
        return FeatureUnlocks::isUnlocked($this, 'advanced_town_services');
    }

    public function lumiisUnlocked(): bool
    {
        return FeatureUnlocks::isUnlocked($this, 'lumiis');
    }

    public function globalChatUnlocked(): bool
    {
        return FeatureUnlocks::isUnlocked($this, 'global_chat');
    }

    public function whispersUnlocked(): bool
    {
        return FeatureUnlocks::isUnlocked($this, 'whispers');
    }

    public function travelRoutesUnlocked(): bool
    {
        return false;
    }

    public function bossFightsUnlocked(): bool
    {
        return FeatureUnlocks::isUnlocked($this, 'boss_fights');
    }

    public function tradeSquareUnlocked(): bool
    {
        return FeatureUnlocks::isUnlocked($this, 'trade_square');
    }

    /**
     * Calculates how much stamina should exist at a given moment without mutating the model yet.
     * The service layer decides when this preview becomes the actual persisted state.
     *
     * @return array{stamina: int, recovered: int, next_tick_seconds: ?int, applied_at: \Illuminate\Support\Carbon}
     */
    public function staminaRecoveryState(?CarbonInterface $moment = null): array
    {
        $state = $this->resourceRecoveryState(
            currentValue: (int) $this->stamina,
            maximumValue: $this->effectiveMaxStamina(),
            baseTimestamp: $this->stamina_updated_at,
            intervalSeconds: $this->staminaRecoveryIntervalSeconds(),
            pointsPerTick: 1,
            moment: $moment,
        );

        return [
            'stamina' => $state['value'],
            'recovered' => $state['recovered'],
            'next_tick_seconds' => $state['next_tick_seconds'],
            'applied_at' => $state['applied_at'],
        ];
    }

    /**
     * Health regenerates more slowly than stamina so combat damage still matters,
     * but natural recovery prevents characters from getting stuck at low life forever.
     *
     * @return array{health: int, recovered: int, next_tick_seconds: ?int, applied_at: \Illuminate\Support\Carbon}
     */
    public function healthRecoveryState(?CarbonInterface $moment = null): array
    {
        $state = $this->resourceRecoveryState(
            currentValue: (int) $this->health,
            maximumValue: $this->effectiveMaxHealth(),
            baseTimestamp: $this->health_updated_at,
            intervalSeconds: $this->healthRecoveryIntervalSeconds(),
            pointsPerTick: $this->healthRecoveryPointsPerTick(),
            moment: $moment,
        );

        return [
            'health' => $state['value'],
            'recovered' => $state['recovered'],
            'next_tick_seconds' => $state['next_tick_seconds'],
            'applied_at' => $state['applied_at'],
        ];
    }

    /**
     * SP should recover faster than HP so skill-driven builds remain playable
     * without forcing potion use after every short exchange.
     *
     * @return array{sp: int, recovered: int, next_tick_seconds: ?int, applied_at: \Illuminate\Support\Carbon}
     */
    public function spRecoveryState(?CarbonInterface $moment = null): array
    {
        $state = $this->resourceRecoveryState(
            currentValue: $this->effectiveCurrentSp(),
            maximumValue: $this->effectiveMaxSp(),
            baseTimestamp: $this->sp_updated_at,
            intervalSeconds: $this->spRecoveryIntervalSeconds(),
            pointsPerTick: $this->spRecoveryPointsPerTick(),
            moment: $moment,
        );

        return [
            'sp' => $state['value'],
            'recovered' => $state['recovered'],
            'next_tick_seconds' => $state['next_tick_seconds'],
            'applied_at' => $state['applied_at'],
        ];
    }

    /**
     * Early progression is intentionally less restrictive.
     * New players get faster stamina recovery until level 5, after that the normal pacing returns.
     */
    public function newPlayerStaminaBoostActive(): bool
    {
        $level = (int) ($this->level ?? 0);

        return $level >= 1 && $level < 5;
    }

    public function skillsUnlocked(): bool
    {
        return FeatureUnlocks::isUnlocked($this, 'skills');
    }

    public function chosenRace(): string
    {
        $race = (string) ($this->getAttribute('race') ?? '');

        return MetinClassCatalog::hasRace($race) ? $race : MetinClassCatalog::defaultRace();
    }

    public function hasChosenSkillGroup(): bool
    {
        return MetinClassCatalog::hasSkillGroup($this->chosenRace(), $this->skill_group);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function availableSkillGroups(): array
    {
        return MetinClassCatalog::groupsForRace($this->chosenRace());
    }

    public function skillGroupPassiveBonuses(): array
    {
        return MetinClassCatalog::skillGroupPassiveBonuses(
            $this->chosenRace(),
            $this->hasChosenSkillGroup() ? (string) $this->skill_group : null,
        );
    }

    public function biologistResearchUnlocked(): bool
    {
        return FeatureUnlocks::isUnlocked($this, 'biologist_research');
    }

    /**
     * Effective stats are what combat and travel should use.
     * They combine base character growth with equipment and passive skills.
     */
    public function effectiveAttack(): int
    {
        return max(
            0,
            $this->attack
            + $this->equippedBonus('attack_bonus')
            + $this->skillBonus('attack')
            + $this->effectiveStrength()
            + intdiv($this->effectiveDexterity(), 4)
            + $this->skillGroupPassiveBonus('attack'),
        );
    }

    public function effectiveDefense(): int
    {
        return max(
            0,
            $this->defense
            + $this->equippedBonus('defense_bonus')
            + $this->skillBonus('defense')
            + intdiv($this->effectiveVitality(), 2)
            + $this->skillGroupPassiveBonus('defense'),
        );
    }

    public function effectiveSpeed(): int
    {
        return max(
            0,
            $this->speed
            + $this->equippedBonus('speed_bonus')
            + $this->skillBonus('speed')
            + intdiv($this->effectiveDexterity(), 5)
            + $this->skillGroupPassiveBonus('speed'),
        );
    }

    public function effectiveMaxHealth(): int
    {
        return max(
            1,
            $this->max_health
            + $this->equippedBonus('health_bonus')
            + ($this->effectiveVitality() * 10)
            + $this->equippedBonusType('max_health')
            + $this->skillGroupPassiveBonus('max_health'),
        );
    }

    public function effectiveMaxStamina(): int
    {
        return max(
            1,
            $this->max_stamina
            + $this->equippedBonus('stamina_bonus')
            + $this->equippedBonusType('max_stamina'),
        );
    }

    public function travelSpeedPercentBonus(): int
    {
        return max(0, $this->equippedBonus('travel_speed_percent_bonus') + $this->skillBonus('travel_speed_percent'));
    }

    /**
     * The attribute values themselves can come from future level-up allocation, quests and items.
     * Centralizing the derived getters keeps future balance work contained.
     */
    public function effectiveVitality(): int
    {
        return max(0, $this->intAttribute('vitality') + $this->equippedBonusType('vitality'));
    }

    public function effectiveIntelligence(): int
    {
        return max(0, $this->intAttribute('intelligence') + $this->equippedBonusType('intelligence'));
    }

    public function effectiveStrength(): int
    {
        return max(0, $this->intAttribute('strength') + $this->equippedBonusType('strength'));
    }

    public function effectiveDexterity(): int
    {
        return max(0, $this->intAttribute('dexterity') + $this->equippedBonusType('dexterity'));
    }

    public function effectiveMaxSp(): int
    {
        return max(
            1,
            $this->intAttribute('max_sp', 24)
            + ($this->effectiveIntelligence() * 6)
            + $this->equippedBonusType('max_sp')
            + $this->skillGroupPassiveBonus('max_sp'),
        );
    }

    public function effectiveCurrentSp(): int
    {
        return max(0, min($this->intAttribute('sp', 24), $this->effectiveMaxSp()));
    }

    public function effectiveMagicAttack(): int
    {
        return max(
            0,
            $this->intAttribute('magic_attack')
            + ($this->effectiveIntelligence() * 2)
            + $this->equippedBonusType('magic_attack')
            + $this->skillGroupPassiveBonus('magic_attack'),
        );
    }

    public function criticalChancePercent(): int
    {
        return min(
            35,
            max(
                0,
                $this->intAttribute('crit_chance')
                + intdiv($this->effectiveDexterity(), 3)
                + $this->equippedBonusType('crit_chance')
                + $this->skillGroupPassiveBonus('crit_chance'),
            ),
        );
    }

    public function evadeChancePercent(): int
    {
        return min(
            40,
            max(
                0,
                $this->intAttribute('evade_chance')
                + intdiv($this->effectiveDexterity(), 2)
                + $this->equippedBonusType('evade_chance')
                + $this->skillGroupPassiveBonus('evade_chance'),
            ),
        );
    }

    public function monsterDamagePercentBonus(): int
    {
        return max(0, $this->intAttribute('monster_damage') + $this->equippedBonusType('monster_damage'));
    }

    public function lumiisDamagePercentBonus(): int
    {
        return max(0, $this->intAttribute('lumiis_damage') + $this->equippedBonusType('lumiis_damage'));
    }

    public function experienceRatePercentBonus(): int
    {
        return max(0, $this->intAttribute('experience_rate') + $this->equippedBonusType('experience_rate'));
    }

    public function yangRatePercentBonus(): int
    {
        return max(0, $this->intAttribute('yang_rate') + $this->equippedBonusType('yang_rate'));
    }

    public function lootRatePercentBonus(): int
    {
        return max(0, $this->intAttribute('loot_rate') + $this->equippedBonusType('loot_rate'));
    }

    public function averageDamagePercentBonus(): int
    {
        return max(0, $this->equippedBonusType('average_damage') + $this->skillGroupPassiveBonus('average_damage'));
    }

    public function skillDamagePercentBonus(): int
    {
        return max(0, $this->equippedBonusType('skill_damage') + $this->skillGroupPassiveBonus('skill_damage'));
    }

    public function damageMultiplierForEnemyCategory(?string $category): float
    {
        $normalizedCategory = strtolower((string) $category);
        $percentBonus = str_contains($normalizedCategory, 'lumiis')
            ? $this->lumiisDamagePercentBonus()
            : $this->monsterDamagePercentBonus();

        return 1 + ($percentBonus / 100);
    }

    public function travelCooldownFor(int $baseSeconds): int
    {
        $bonusPercent = min(60, $this->travelSpeedPercentBonus());

        return max(10, (int) ceil($baseSeconds * (1 - ($bonusPercent / 100))));
    }

    public function equipmentBySlot(): Collection
    {
        $equipment = $this->relationLoaded('equipment')
            ? $this->equipment
            : $this->equipment()->with('characterItem.item', 'characterItem.bonuses')->get();

        return $equipment->keyBy('slot');
    }

    public function equippedWeaponItem(): ?Item
    {
        $this->loadMissing('equipment.characterItem.item');

        $weapon = $this->equipment->firstWhere('slot', 'weapon');
        $item = $weapon?->characterItem?->item;

        return $item instanceof Item ? $item : null;
    }

    public function equippedWeaponType(): ?string
    {
        return $this->equippedWeaponItem()?->weaponType();
    }

    /**
     * Inventory rendering should only show bag items that still occupy grid space.
     * Equipped rows remain owned by the character, but they live on the equipment board instead.
     */
    public function storedInventoryItems(): Collection
    {
        $inventoryItems = $this->relationLoaded('inventoryItems')
            ? $this->inventoryItems
            : $this->inventoryItems()->with(['item', 'equipment', 'bonuses'])->get();

        return $inventoryItems
            ->filter(fn (CharacterItem $inventoryItem): bool => $inventoryItem->isStoredInInventory())
            ->sortBy([
                fn (CharacterItem $inventoryItem): int => (int) $inventoryItem->inventory_row,
                fn (CharacterItem $inventoryItem): int => (int) $inventoryItem->inventory_column,
                fn (CharacterItem $inventoryItem): int => $inventoryItem->id,
            ])
            ->values();
    }

    /**
     * Internal helper for derived stats.
     * We keep it private because outside callers should use the effective* methods instead.
     */
    private function equippedBonus(string $column): int
    {
        $equipment = $this->relationLoaded('equipment')
            ? $this->equipment
            : $this->equipment()->with('characterItem.item', 'characterItem.bonuses')->get();

        return (int) $equipment->sum(
            static function (CharacterEquipment $equipment) use ($column): int {
                $inventoryItem = $equipment->characterItem;

                return (int) data_get($inventoryItem, "item.$column", 0)
                    + (int) $inventoryItem?->upgradeBonusFor($column);
            },
        );
    }

    private function equippedBonusType(string $bonusType): int
    {
        if (! ItemBonusCatalog::isKnownType($bonusType)) {
            return 0;
        }

        $equipment = $this->relationLoaded('equipment')
            ? $this->equipment
            : $this->equipment()->with('characterItem.bonuses')->get();

        return (int) $equipment->sum(
            static fn (CharacterEquipment $equipment): int => (int) $equipment->characterItem?->bonusValue($bonusType),
        );
    }

    private function skillGroupPassiveBonus(string $key): int
    {
        return (int) ($this->skillGroupPassiveBonuses()[$key] ?? 0);
    }

    private function skillBonus(string $effectType): int
    {
        $skills = $this->relationLoaded('learnedSkills')
            ? $this->learnedSkills
            : $this->learnedSkills()->with('skill')->get();

        return (int) $skills->sum(function (CharacterSkill $characterSkill) use ($effectType): int {
            if (data_get($characterSkill, 'skill.effect_type') !== $effectType) {
                return 0;
            }

            return $characterSkill->rank * (int) data_get($characterSkill, 'skill.effect_value', 0);
        });
    }

    /**
     * One stamina every ten minutes is the default pacing.
     * Characters below level 5 recover twice as fast so the opening hours feel less restrictive.
     */
    private function staminaRecoveryIntervalSeconds(): int
    {
        return $this->newPlayerStaminaBoostActive() ? 300 : 600;
    }

    /**
     * HP/SP/Stamina all use the same time-based recovery shape:
     * resolve the current value, count elapsed ticks, clamp to the cap and tell the UI when the
     * next point arrives. The public wrappers keep resource-specific naming readable.
     *
     * @return array{value: int, recovered: int, next_tick_seconds: ?int, applied_at: \Illuminate\Support\Carbon}
     */
    private function resourceRecoveryState(
        int $currentValue,
        int $maximumValue,
        ?CarbonInterface $baseTimestamp,
        int $intervalSeconds,
        int $pointsPerTick,
        ?CarbonInterface $moment = null,
    ): array {
        $resolvedMoment = $moment ?? now();
        $clampedMaximum = max(1, $maximumValue);
        $clampedCurrent = min($currentValue, $clampedMaximum);
        $resolvedBaseTimestamp = $baseTimestamp ?? $this->last_active_at ?? $resolvedMoment;
        $resolvedPointsPerTick = max(1, $pointsPerTick);
        $resolvedIntervalSeconds = max(1, $intervalSeconds);

        if ($clampedCurrent >= $clampedMaximum) {
            return [
                'value' => $clampedMaximum,
                'recovered' => 0,
                'next_tick_seconds' => null,
                'applied_at' => $resolvedMoment->copy(),
            ];
        }

        $elapsedSeconds = max(0, $resolvedBaseTimestamp->diffInSeconds($resolvedMoment, false));
        $recoveryTicks = intdiv($elapsedSeconds, $resolvedIntervalSeconds);
        $recoverablePoints = $recoveryTicks * $resolvedPointsPerTick;
        $recovered = min($clampedMaximum - $clampedCurrent, $recoverablePoints);
        $previewValue = $clampedCurrent + $recovered;
        $appliedTicks = $recovered === 0
            ? 0
            : min($recoveryTicks, (int) ceil($recovered / $resolvedPointsPerTick));
        $appliedAt = $resolvedBaseTimestamp->copy()->addSeconds($appliedTicks * $resolvedIntervalSeconds);

        if ($previewValue >= $clampedMaximum) {
            $nextTickSeconds = null;
            $appliedAt = $resolvedMoment->copy();
        } else {
            $remainder = $elapsedSeconds % $resolvedIntervalSeconds;
            $nextTickSeconds = $resolvedIntervalSeconds - $remainder;
        }

        return [
            'value' => $previewValue,
            'recovered' => $recovered,
            'next_tick_seconds' => $nextTickSeconds,
            'applied_at' => $appliedAt,
        ];
    }

    private function healthRecoveryIntervalSeconds(): int
    {
        return 300;
    }

    private function healthRecoveryPointsPerTick(): int
    {
        return max(2, (int) ceil($this->effectiveMaxHealth() * 0.05));
    }

    private function spRecoveryIntervalSeconds(): int
    {
        return 180;
    }

    private function spRecoveryPointsPerTick(): int
    {
        return max(2, (int) ceil($this->effectiveMaxSp() * 0.15));
    }

    /**
     * Late-added progression columns may be absent on lightweight read models.
     * Defaulting these to a safe value keeps UI surfaces from crashing on stale partial relations.
     */
    private function intAttribute(string $key, int $default = 0): int
    {
        if (! array_key_exists($key, $this->attributes)) {
            return $default;
        }

        return (int) $this->attributes[$key];
    }
}
