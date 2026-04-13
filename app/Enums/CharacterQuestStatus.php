<?php

namespace App\Enums;

/**
 * Character-local quest state.
 *
 * Objectives can be finished before the player explicitly claims the reward.
 */
enum CharacterQuestStatus: string
{
    case Active = 'active';
    case ReadyToClaim = 'ready_to_claim';
    case Completed = 'completed';

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
