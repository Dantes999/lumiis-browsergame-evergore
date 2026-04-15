<?php

namespace App\Support\Game;

class WeaponTypeCatalog
{
    /**
     * @return array<string, array{label: string, races: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            'one_hand' => [
                'label' => __('One-Hand Sword'),
                'races' => ['warrior', 'ninja', 'sura'],
            ],
            'two_hand' => [
                'label' => __('Two-Hand Weapon'),
                'races' => ['warrior'],
            ],
            'dagger' => [
                'label' => __('Dagger'),
                'races' => ['ninja'],
            ],
            'bow' => [
                'label' => __('Bow'),
                'races' => ['ninja'],
            ],
            'bell' => [
                'label' => __('Bell'),
                'races' => ['shaman'],
            ],
            'fan' => [
                'label' => __('Fan'),
                'races' => ['shaman'],
            ],
        ];
    }

    public static function isKnown(?string $weaponType): bool
    {
        if ($weaponType === null || $weaponType === '') {
            return false;
        }

        return array_key_exists($weaponType, self::definitions());
    }

    public static function label(?string $weaponType): ?string
    {
        if (! self::isKnown($weaponType)) {
            return null;
        }

        return (string) data_get(self::definitions(), $weaponType.'.label');
    }

    public static function supportsRace(?string $weaponType, string $race): bool
    {
        if (! self::isKnown($weaponType)) {
            return true;
        }

        return in_array($race, (array) data_get(self::definitions(), $weaponType.'.races', []), true);
    }

    public static function starterWeaponSlugForRace(string $race): string
    {
        return match ($race) {
            'ninja' => 'dagger',
            'shaman' => 'fan',
            default => 'recruit-blade',
        };
    }

    public static function baselineWeaponSlugForRaceAndLevel(string $race, int $level): string
    {
        return match ($race) {
            'ninja' => match (true) {
                $level >= 20 => 'scissor-dagger',
                $level >= 8 => 'cobra-dagger',
                default => 'dagger',
            },
            'shaman' => match (true) {
                $level >= 20 => 'peacock-fan',
                $level >= 8 => 'black-tiger-fan',
                default => 'fan',
            },
            default => match (true) {
                $level >= 20 => 'valley-blade',
                $level >= 8 => 'frontier-blade',
                default => 'recruit-blade',
            },
        };
    }
}
