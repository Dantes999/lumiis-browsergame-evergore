<?php

namespace App\Support\Game;

use App\Models\GameCharacter;
use App\Models\LocationArea;

/**
 * Central level-based feature gating.
 *
 * This keeps UI visibility and server-side write guards in sync so unlock rules
 * are readable in one place instead of being scattered across Blade, requests
 * and action handlers.
 */
class FeatureUnlocks
{
    /**
     * @return array<string, array{level: int, label: string, description: string}>
     */
    public static function definitions(): array
    {
        return [
            'monster_hunts' => [
                'level' => 1,
                'label' => __('Monster Hunts'),
                'description' => __('Basic pack fights stay available right after the tutorial.'),
            ],
            'general_store' => [
                'level' => 1,
                'label' => __('General Store'),
                'description' => __('Potions and starter supplies remain available immediately.'),
            ],
            'lumiis' => [
                'level' => 2,
                'label' => __('Lumiis Hunts'),
                'description' => __('Glowing stones and trapped soul waves unlock at level 2.'),
            ],
            'global_chat' => [
                'level' => 2,
                'label' => __('Global Chat'),
                'description' => __('Realm chat opens once the player has survived the first steps alone.'),
            ],
            'whispers' => [
                'level' => 3,
                'label' => __('Whispers'),
                'description' => __('Direct player messages unlock at level 3.'),
            ],
            'trade_square' => [
                'level' => 3,
                'label' => __('Trade Square'),
                'description' => __('Player-to-player social space unlocks at level 3.'),
            ],
            'routes' => [
                'level' => 4,
                'label' => __('Travel Routes'),
                'description' => __('Leaving the starter map opens at level 4.'),
            ],
            'boss_fights' => [
                'level' => 4,
                'label' => __('Boss Souls'),
                'description' => __('Boss releases become available at level 4.'),
            ],
            'advanced_town_services' => [
                'level' => 5,
                'label' => __('Dealers and Smith'),
                'description' => __('Weapon dealer, armor dealer and smith unlock at level 5.'),
            ],
            'biologist_research' => [
                'level' => 5,
                'label' => __('Biologist Research'),
                'description' => __('Permanent research quests open at level 5 and trade field samples for lasting stats.'),
            ],
            'skills' => [
                'level' => 5,
                'label' => __('Skills'),
                'description' => __('Combat schools open at level 5. You then choose one of the two skill groups of your race.'),
            ],
        ];
    }

    public static function isUnlocked(GameCharacter $character, string $feature): bool
    {
        if ($character->isInTutorial()) {
            return false;
        }

        $requiredLevel = self::requiredLevel($feature);

        return $character->level >= $requiredLevel;
    }

    public static function requiredLevel(string $feature): int
    {
        return (int) data_get(self::definitions(), $feature.'.level', 99);
    }

    public static function areaIsVisible(GameCharacter $character, LocationArea $area): bool
    {
        return match (true) {
            // The alpha map should not expose the old town hub anymore.
            // Individual town services remain as their own hotspots instead.
            $area->slug === 'town-square' => false,
            // Trading is not part of the current alpha scope.
            // Hiding unfinished hubs is better than exposing dead-end hotspots.
            $area->service_type === 'trade' => false,
            in_array($area->slug, ['weapon-dealer', 'armor-dealer', 'town-smith'], true) => self::isUnlocked($character, 'advanced_town_services'),
            $area->service_type === 'biologist' => self::isUnlocked($character, 'biologist_research'),
            $area->service_type === 'routes' => self::isUnlocked($character, 'routes'),
            default => true,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function dashboardData(GameCharacter $character): array
    {
        return collect(self::definitions())
            ->map(function (array $definition, string $feature) use ($character): array {
                return [
                    'feature' => $feature,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'required_level' => $definition['level'],
                    'unlocked' => self::isUnlocked($character, $feature),
                ];
            })
            ->values()
            ->all();
    }
}
