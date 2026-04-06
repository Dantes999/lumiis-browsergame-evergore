<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Static monster template used by area assignments and combat generation.
 */
class Monster extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'level',
        'rank',
        'race',
        'damage_type',
        'sprite',
        'maximum_health',
        'minimum_attack',
        'maximum_attack',
        'defense',
        'experience_reward',
        'yang_min',
        'yang_max',
        'sort_order',
    ];

    public function areaAssignments(): HasMany
    {
        return $this->hasMany(LocationAreaMonster::class);
    }
}
