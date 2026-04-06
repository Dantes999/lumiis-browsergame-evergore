<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-character progress for one hunting area.
 *
 * Pressure, fight counts and boss history live here instead of on the area itself
 * so every character can progress independently.
 */
class CharacterAreaProgress extends Model
{
    use HasFactory;

    protected $table = 'character_area_progress';

    protected $fillable = [
        'game_character_id',
        'location_area_id',
        'pressure',
        'monster_fights',
        'lumiis_searches',
        'boss_fights',
        'last_boss_at',
    ];

    protected function casts(): array
    {
        return [
            'last_boss_at' => 'datetime',
        ];
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
