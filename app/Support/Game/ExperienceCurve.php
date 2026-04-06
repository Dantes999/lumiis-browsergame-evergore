<?php

namespace App\Support\Game;

/**
 * Central EXP curve for the whole game.
 *
 * Design goal:
 * - levels 1-3 should feel quick and welcoming
 * - from level 4 onward progression should slow down instead of staying flat
 *
 * The value returned here is the total cumulative EXP required to reach the
 * given level. Keeping this in one file avoids drift between UI previews and
 * server-side level-up logic.
 */
class ExperienceCurve
{
    public static function requiredForLevel(int $level): int
    {
        if ($level <= 1) {
            return 0;
        }

        $starterThresholds = [
            2 => 120,
            3 => 240,
            4 => 380,
            5 => 540,
        ];

        if (array_key_exists($level, $starterThresholds)) {
            return $starterThresholds[$level];
        }

        // From level 6 onward the curve ramps much harder than the starter band.
        // This keeps level 1-5 readable and rewarding, while stopping level-per-fight
        // behavior once the character reaches the real midgame.
        $midgameLevels = $level - 5;

        return 540
            + (30 * $midgameLevels * $midgameLevels)
            + (210 * $midgameLevels);
    }
}
