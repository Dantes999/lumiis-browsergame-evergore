<?php

namespace App\Enums;

/**
 * High-level player progression state.
 *
 * The game currently distinguishes only between the forced onboarding flow
 * and the normal free-play state.
 */
enum GameCharacterState: string
{
    case Tutorial = 'tutorial';
    case Active = 'active';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases(),
        );
    }
}
