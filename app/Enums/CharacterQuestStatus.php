<?php

namespace App\Enums;

/**
 * Character-local quest state.
 *
 * Quests are auto-claimed on completion, so we only need to distinguish
 * between work-in-progress and finished content.
 */
enum CharacterQuestStatus: string
{
    case Active = 'active';
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
