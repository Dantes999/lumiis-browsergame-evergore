<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persistent state container for manual battles.
 *
 * Auto-fights resolve immediately, but manual fights store their evolving state here
 * so each request can advance exactly one turn.
 */
class GameBattle extends Model
{
    use HasFactory;
    use MassPrunable;

    protected $fillable = [
        'game_character_id',
        'location_area_id',
        'mode',
        'requested_encounter',
        'encounter_type',
        'encounter_name',
        'status',
        'round',
        'state',
        'rewards',
        'started_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => 'array',
            'rewards' => 'array',
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Ongoing fights are never pruned because they are live state. Only battles
     * that were resolved more than 30 days ago are eligible.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->where('status', '!=', 'ongoing')
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '<=', now()->subDays(30));
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(GameCharacter::class, 'game_character_id');
    }

    public function locationArea(): BelongsTo
    {
        return $this->belongsTo(LocationArea::class);
    }
}
