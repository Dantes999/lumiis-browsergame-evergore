<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Static skill definition that characters can learn and rank up.
 */
class Skill extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'faction',
        'race',
        'skill_group',
        'name',
        'description',
        'required_level',
        'max_rank',
        'effect_type',
        'effect_value',
    ];

    public function characterSkills(): HasMany
    {
        return $this->hasMany(CharacterSkill::class);
    }
}
