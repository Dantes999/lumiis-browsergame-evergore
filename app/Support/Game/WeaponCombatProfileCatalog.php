<?php

namespace App\Support\Game;

class WeaponCombatProfileCatalog
{
    /**
     * @return array{
     *     attack_floor_offset: int,
     *     attack_ceiling_offset: int,
     *     crit_chance_bonus: int,
     *     guard_bonus: int,
     *     basic_damage_percent: int,
     *     skill_damage_percent: int,
     *     opening_damage_percent: int
     * }
     */
    public static function profile(?string $weaponType): array
    {
        return match ($weaponType) {
            'one_hand' => [
                'attack_floor_offset' => 1,
                'attack_ceiling_offset' => -1,
                'crit_chance_bonus' => 1,
                'guard_bonus' => 0,
                'basic_damage_percent' => 0,
                'skill_damage_percent' => 0,
                'opening_damage_percent' => 0,
            ],
            'two_hand' => [
                'attack_floor_offset' => -2,
                'attack_ceiling_offset' => 4,
                'crit_chance_bonus' => 0,
                'guard_bonus' => 0,
                'basic_damage_percent' => 6,
                'skill_damage_percent' => 0,
                'opening_damage_percent' => 0,
            ],
            'dagger' => [
                'attack_floor_offset' => -1,
                'attack_ceiling_offset' => 1,
                'crit_chance_bonus' => 5,
                'guard_bonus' => 0,
                'basic_damage_percent' => 0,
                'skill_damage_percent' => 0,
                'opening_damage_percent' => 0,
            ],
            'bow' => [
                'attack_floor_offset' => -1,
                'attack_ceiling_offset' => 2,
                'crit_chance_bonus' => 2,
                'guard_bonus' => 0,
                'basic_damage_percent' => 0,
                'skill_damage_percent' => 0,
                'opening_damage_percent' => 10,
            ],
            'bell' => [
                'attack_floor_offset' => 0,
                'attack_ceiling_offset' => 1,
                'crit_chance_bonus' => 0,
                'guard_bonus' => 2,
                'basic_damage_percent' => 2,
                'skill_damage_percent' => 4,
                'opening_damage_percent' => 0,
            ],
            'fan' => [
                'attack_floor_offset' => -1,
                'attack_ceiling_offset' => 1,
                'crit_chance_bonus' => 1,
                'guard_bonus' => 0,
                'basic_damage_percent' => 0,
                'skill_damage_percent' => 10,
                'opening_damage_percent' => 0,
            ],
            default => [
                'attack_floor_offset' => 0,
                'attack_ceiling_offset' => 0,
                'crit_chance_bonus' => 0,
                'guard_bonus' => 0,
                'basic_damage_percent' => 0,
                'skill_damage_percent' => 0,
                'opening_damage_percent' => 0,
            ],
        };
    }

    /**
     * @return array{minimum: int, maximum: int}
     */
    public static function attackRollBounds(?string $weaponType, int $attackBaseStat, int $effectiveSpeed): array
    {
        $profile = self::profile($weaponType);
        $minimum = max(
            1,
            $attackBaseStat - 2 + intdiv($effectiveSpeed, 4) + $profile['attack_floor_offset'],
        );
        $maximum = max(
            $minimum,
            $attackBaseStat + 4 + intdiv($effectiveSpeed, 2) + $profile['attack_ceiling_offset'],
        );

        return [
            'minimum' => $minimum,
            'maximum' => $maximum,
        ];
    }

    public static function critChanceBonus(?string $weaponType): int
    {
        return self::profile($weaponType)['crit_chance_bonus'];
    }

    public static function guardBonus(?string $weaponType): int
    {
        return self::profile($weaponType)['guard_bonus'];
    }

    public static function damagePercentBonus(?string $weaponType, bool $skillHit, int $round): int
    {
        $profile = self::profile($weaponType);

        return $profile['basic_damage_percent']
            + ($skillHit ? $profile['skill_damage_percent'] : 0)
            + ($round === 1 ? $profile['opening_damage_percent'] : 0);
    }
}
