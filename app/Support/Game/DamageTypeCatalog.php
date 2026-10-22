<?php

namespace App\Support\Game;

use App\Models\GameCharacter;
use Illuminate\Support\Str;

class DamageTypeCatalog
{
    /**
     * @var array<string, array{label: string}>
     */
    private const TYPES = [
        'physical' => ['label' => 'Physical'],
        'bow' => ['label' => 'Bow'],
        'bell' => ['label' => 'Bell'],
        'fan' => ['label' => 'Fan'],
        'weaponary_magic' => ['label' => 'Weaponary Magic'],
        'black_magic' => ['label' => 'Black Magic'],
        'dragon_force' => ['label' => 'Dragon Force'],
    ];

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return array_keys(self::TYPES);
    }

    public static function normalize(?string $damageType): string
    {
        $normalized = Str::of((string) $damageType)
            ->lower()
            ->replace(['-', ' '], '_')
            ->ascii()
            ->value();

        return match ($normalized) {
            'bow', 'range', 'ranged' => 'bow',
            'bell' => 'bell',
            'fan' => 'fan',
            'weaponary_magic', 'weapon_magic', 'weaponary', 'weapon' => 'weaponary_magic',
            'black_magic', 'dark_magic', 'darkness' => 'black_magic',
            'dragon_force', 'dragon', 'dragon_magic' => 'dragon_force',
            'physisch', 'physical', 'melee', 'blade', 'sword', 'dagger', 'two_hand', 'one_hand' => 'physical',
            default => array_key_exists($normalized, self::TYPES) ? $normalized : 'physical',
        };
    }

    public static function label(string $damageType): string
    {
        $normalized = self::normalize($damageType);

        return __(self::TYPES[$normalized]['label'] ?? Str::headline($normalized));
    }

    public static function basicAttackTypeForWeapon(?string $weaponType): string
    {
        return match ($weaponType) {
            'bow' => 'bow',
            'bell' => 'bell',
            'fan' => 'fan',
            default => 'physical',
        };
    }

    public static function skillDamageType(?string $race, ?string $skillGroup, ?string $weaponType, bool $usesMagicAttack): string
    {
        return match ((string) $skillGroup) {
            'archery' => 'bow',
            'weaponary-magic' => 'weaponary_magic',
            'black-magic' => 'black_magic',
            'dragon' => 'dragon_force',
            default => $usesMagicAttack && in_array($weaponType, ['bell', 'fan'], true)
                ? self::basicAttackTypeForWeapon($weaponType)
                : self::basicAttackTypeForWeapon($weaponType),
        };
    }

    public static function primaryCharacterDamageType(GameCharacter $character): string
    {
        return self::skillDamageType(
            $character->chosenRace(),
            $character->hasChosenSkillGroup() ? (string) $character->skill_group : null,
            $character->equippedWeaponType(),
            in_array((string) $character->skill_group, ['weaponary-magic', 'black-magic', 'dragon', 'healing'], true),
        );
    }

    public static function enemyDamageType(
        ?string $damageType,
        ?string $encounterType = null,
        ?string $category = null,
        ?string $family = null,
    ): string {
        if ($damageType !== null && trim($damageType) !== '') {
            return self::normalize($damageType);
        }

        $normalizedCategory = Str::lower((string) $category);

        if ($encounterType === 'lumiis' || str_contains($normalizedCategory, 'lumiis')) {
            return 'black_magic';
        }

        if ($family === 'orcs' && str_contains($normalizedCategory, 'boss')) {
            return 'weaponary_magic';
        }

        return 'physical';
    }

    /**
     * @return array<string, int>
     */
    public static function enemyResistanceProfile(
        ?string $encounterType = null,
        ?string $category = null,
        ?string $family = null,
        ?string $damageType = null,
    ): array {
        $profile = [];
        $normalizedCategory = Str::lower((string) $category);
        $resolvedDamageType = self::enemyDamageType($damageType, $encounterType, $category, $family);

        if ($encounterType === 'lumiis' || str_contains($normalizedCategory, 'lumiis')) {
            self::mergeProfile($profile, [
                'physical' => 8,
                'bow' => 15,
                'bell' => -18,
                'fan' => -12,
                'weaponary_magic' => -8,
                'black_magic' => 12,
                'dragon_force' => -20,
            ]);
        } elseif (str_contains($normalizedCategory, 'freed soul') || str_contains($normalizedCategory, 'soul')) {
            self::mergeProfile($profile, [
                'physical' => 4,
                'bow' => 8,
                'bell' => -10,
                'fan' => -8,
                'weaponary_magic' => -4,
                'black_magic' => 6,
                'dragon_force' => -12,
            ]);
        } elseif (str_contains($normalizedCategory, 'boss')) {
            self::mergeProfile($profile, [
                'physical' => 6,
                'bow' => 10,
                'bell' => -6,
                'fan' => -4,
                'weaponary_magic' => -10,
                'black_magic' => 4,
                'dragon_force' => -8,
            ]);
        } elseif ($family === 'orcs') {
            self::mergeProfile($profile, [
                'physical' => 8,
                'bow' => -6,
                'weaponary_magic' => -14,
                'black_magic' => 10,
                'dragon_force' => -4,
                'fan' => -2,
            ]);
        } elseif ($family === 'apes') {
            self::mergeProfile($profile, [
                'physical' => 10,
                'bow' => 4,
                'weaponary_magic' => -10,
                'black_magic' => -4,
                'dragon_force' => -6,
                'bell' => 2,
                'fan' => 2,
            ]);
        } elseif (in_array($family, ['wolves', 'boars', 'bears', 'tigers', 'beasts'], true)) {
            self::mergeProfile($profile, [
                'physical' => -4,
                'bow' => -14,
                'bell' => 2,
                'fan' => 4,
                'weaponary_magic' => 2,
                'black_magic' => -6,
                'dragon_force' => 6,
            ]);
        }

        self::mergeProfile($profile, match ($resolvedDamageType) {
            'bow' => ['bow' => 6],
            'bell' => ['bell' => 5],
            'fan' => ['fan' => 5],
            'weaponary_magic' => ['weaponary_magic' => 6],
            'black_magic' => ['black_magic' => 6, 'dragon_force' => -4],
            'dragon_force' => ['dragon_force' => 6, 'black_magic' => -2],
            default => ['physical' => 4],
        });

        return array_filter($profile, static fn (int $value): bool => $value !== 0);
    }

    public static function resistancePercent(array $resistances, string $damageType): int
    {
        return (int) ($resistances[self::normalize($damageType)] ?? 0);
    }

    public static function damageAfterResistance(int $damage, string $damageType, array $resistances): int
    {
        if ($damage <= 0) {
            return 0;
        }

        $percent = self::resistancePercent($resistances, $damageType);

        return max(1, (int) floor($damage * max(0.1, 1 - ($percent / 100))));
    }

    public static function itemResistanceBonusType(string $damageType): ?string
    {
        $normalized = self::normalize($damageType);

        return array_key_exists($normalized, self::TYPES)
            ? 'resist_'.$normalized
            : null;
    }

    /**
     * @param  array<string, int>  $profile
     * @return list<string>
     */
    public static function resistanceBadges(array $profile): array
    {
        return collect($profile)
            ->mapWithKeys(fn (mixed $value, mixed $type): array => [self::normalize((string) $type) => (int) $value])
            ->filter(fn (int $value): bool => $value !== 0)
            ->sortByDesc(fn (int $value): int => abs($value))
            ->map(function (int $value, string $type): string {
                $label = self::label($type);

                return $value > 0
                    ? __('Res :type :value%', ['type' => $label, 'value' => $value])
                    : __('Weak :type :value%', ['type' => $label, 'value' => abs($value)]);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, int>  $profile
     * @param  array<string, int>  $adjustments
     */
    private static function mergeProfile(array &$profile, array $adjustments): void
    {
        foreach ($adjustments as $type => $value) {
            $normalized = self::normalize($type);
            $profile[$normalized] = max(-30, min(30, (int) ($profile[$normalized] ?? 0) + $value));
        }
    }
}
