<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Join model that assigns a monster template to a hunting area.
 */
class LocationAreaMonster extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_area_id',
        'monster_id',
        'weight',
        'minimum_character_level',
        'maximum_character_level',
        'pack_size',
        'sort_order',
    ];

    public function locationArea(): BelongsTo
    {
        return $this->belongsTo(LocationArea::class);
    }

    public function monster(): BelongsTo
    {
        return $this->belongsTo(Monster::class);
    }

    public function isEligibleForLevel(int $level): bool
    {
        // This allows areas to stay relevant across level bands without hard-coding logic in the service layer.
        if ($level < $this->minimum_character_level) {
            return false;
        }

        return $this->maximum_character_level === null || $level <= $this->maximum_character_level;
    }
}
