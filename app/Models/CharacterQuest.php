<?php

namespace App\Models;

use App\Enums\CharacterQuestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One quest row for one character.
 *
 * We persist progress so support/admin tools can inspect where a player is
 * stuck and so rewards are granted exactly once.
 */
class CharacterQuest extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_character_id',
        'quest_id',
        'status',
        'progress',
        'started_at',
        'completed_at',
        'next_submission_at',
        'submission_bonus_percent',
    ];

    protected function casts(): array
    {
        return [
            'status' => CharacterQuestStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'next_submission_at' => 'datetime',
            'submission_bonus_percent' => 'int',
        ];
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(GameCharacter::class, 'game_character_id');
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    /**
     * Biologist support items add a temporary bonus to the next submission roll.
     * Keeping the calculation on the model avoids scattering the cap logic.
     */
    public function effectiveSubmissionSuccessRate(int $baseRate): int
    {
        return min(100, max(0, $baseRate + (int) $this->submission_bonus_percent));
    }
}
