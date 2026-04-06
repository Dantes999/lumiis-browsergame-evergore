<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable record of every processed action.
 *
 * This is used for idempotency, replay payloads, debugging and later audit/anti-cheat analysis.
 */
class GameActionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_character_id',
        'idempotency_key',
        'action',
        'source_location_id',
        'target_location_id',
        'request_payload',
        'outcome',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'outcome' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(GameCharacter::class, 'game_character_id');
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'source_location_id');
    }

    public function targetLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'target_location_id');
    }
}
