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

    private const MANUAL_FAMILY_LABELS = [
        'wolves' => 'Wolves',
        'boars' => 'Boars',
        'bears' => 'Bears',
        'tigers' => 'Tigers',
        'orcs' => 'Orcs',
        'apes' => 'Apes',
        'beasts' => 'Beasts',
    ];

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

    public function manualFamilyKey(): string
    {
        $slug = mb_strtolower((string) $this->slug);
        $name = mb_strtolower((string) $this->name);

        return match (true) {
            str_contains($slug, 'wolf'), str_contains($name, 'wolf') => 'wolves',
            str_contains($slug, 'keiler'), str_contains($slug, 'scrofa'), str_contains($name, 'keiler'), str_contains($name, 'boar') => 'boars',
            str_contains($slug, 'baer'), str_contains($slug, 'bär'), str_contains($slug, 'bera'), str_contains($name, 'bär'), str_contains($name, 'bear') => 'bears',
            str_contains($slug, 'tiger'), str_contains($slug, 'tigris'), str_contains($name, 'tiger') => 'tigers',
            str_contains($slug, 'orc'), str_contains($name, 'orc') => 'orcs',
            str_contains($slug, 'ape'), str_contains($name, 'ape') => 'apes',
            default => 'beasts',
        };
    }

    public function manualFamilyLabel(): string
    {
        return self::manualFamilyLabelFor($this->manualFamilyKey());
    }

    public static function manualFamilyLabelFor(string $familyKey): string
    {
        return __(self::MANUAL_FAMILY_LABELS[$familyKey] ?? 'Beasts');
    }
}
