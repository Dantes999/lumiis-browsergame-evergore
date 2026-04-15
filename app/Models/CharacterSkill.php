<?php

namespace App\Models;

use App\Support\Game\MetinClassCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Learned-skill row storing the current rank of one skill on one character.
 */
class CharacterSkill extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_character_id',
        'skill_id',
        'rank',
        'mastery_rank',
        'mastery_progress',
        'grand_mastery_rank',
        'book_available_at',
        'perfect_mastered_at',
    ];

    protected function casts(): array
    {
        return [
            'mastery_rank' => 'integer',
            'mastery_progress' => 'integer',
            'grand_mastery_rank' => 'integer',
            'book_available_at' => 'datetime',
            'perfect_mastered_at' => 'datetime',
        ];
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(GameCharacter::class, 'game_character_id');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    /**
     * Skills move through the familiar Metin2-like labels:
     * base ranks -> M stages -> G stages -> P.
     */
    public function stageLabel(): string
    {
        if ($this->hasReachedPerfectMastery()) {
            return 'P';
        }

        if ((int) $this->grand_mastery_rank > 0) {
            return 'G'.(int) $this->grand_mastery_rank;
        }

        if ((int) $this->mastery_rank > 0) {
            return 'M'.(int) $this->mastery_rank;
        }

        return 'Rank '.(int) $this->rank;
    }

    public function masteryLabel(): string
    {
        return $this->stageLabel();
    }

    public function bookCooldownRemainingMinutes(?Carbon $now = null): int
    {
        $now ??= now();

        if ($this->book_available_at === null || $this->book_available_at->lte($now)) {
            return 0;
        }

        return max(1, (int) ceil($now->diffInSeconds($this->book_available_at) / 60));
    }

    public function nextBookSuccessChance(): int
    {
        return MetinClassCatalog::skillBookSuccessChance((int) $this->mastery_rank);
    }

    public function requiredBookSuccessesForNextMastery(): int
    {
        if (! $this->needsSkillBookProgression()) {
            return 0;
        }

        return MetinClassCatalog::skillBookSuccessesRequired((int) $this->mastery_rank);
    }

    public function nextSoulStoneSuccessChance(): int
    {
        return MetinClassCatalog::soulStoneSuccessChance((int) $this->grand_mastery_rank);
    }

    public function needsSkillBookProgression(): bool
    {
        return (int) $this->mastery_rank > 0
            && (int) $this->grand_mastery_rank === 0
            && ! $this->hasReachedPerfectMastery()
            && ! $this->hasFinishedMasteryBooks();
    }

    public function needsSoulStoneProgression(): bool
    {
        return (int) $this->grand_mastery_rank > 0 && ! $this->hasReachedPerfectMastery();
    }

    public function hasFinishedMasteryBooks(): bool
    {
        return (int) $this->grand_mastery_rank > 0;
    }

    public function hasFinishedGrandMastery(): bool
    {
        return (int) $this->grand_mastery_rank >= 10;
    }

    public function hasReachedPerfectMastery(): bool
    {
        return $this->perfect_mastered_at !== null;
    }
}
