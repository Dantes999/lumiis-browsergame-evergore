<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Lang;

/**
 * Static story mission definition.
 *
 * Quest rows are design-time content: title, objective, rewards and unlock
 * conditions. Per-player progress lives in CharacterQuest.
 */
class Quest extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id',
        'slug',
        'title',
        'story_text',
        'description',
        'feature_unlock_hint',
        'required_level',
        'prerequisite_slug',
        'objective_type',
        'objective_target',
        'objective_area_slug',
        'objective_item_slug',
        'objective_item_quantity',
        'objective_submission_cooldown_minutes',
        'objective_submission_success_rate',
        'reward_experience',
        'reward_yang',
        'reward_item_slug',
        'reward_item_quantity',
        'reward_bonus_type',
        'reward_bonus_value',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
            'objective_item_quantity' => 'int',
            'objective_submission_cooldown_minutes' => 'int',
            'objective_submission_success_rate' => 'int',
            'reward_bonus_value' => 'int',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function characterQuests(): HasMany
    {
        return $this->hasMany(CharacterQuest::class);
    }

    /**
     * Quest rows keep a plain-text fallback in the database, but visible copy should prefer
     * language files so the story can be localized without reseeding production data.
     */
    public function translated(string $field): string
    {
        $key = 'quests.'.$this->slug.'.'.$field;

        return Lang::has($key)
            ? (string) __($key)
            : (string) ($this->{$field} ?? '');
    }

    /**
     * Item hand-in quests use a different progress source than kill/level objectives.
     */
    public function requiresBiologistTurnIn(): bool
    {
        return $this->objective_type === 'deliver_item_to_biologist';
    }

    /**
     * Permanent quest reward bonuses write directly into the character's base columns.
     *
     * Keeping the mapping here makes admin forms, reward application and UI copy
     * all reference the same source of truth.
     *
     * @return array<string, string>
     */
    public static function rewardBonusOptions(): array
    {
        return [
            'vitality' => __('Vitality'),
            'intelligence' => __('Intelligence'),
            'strength' => __('Strength'),
            'dexterity' => __('Dexterity'),
            'max_health' => __('Max Health'),
            'max_sp' => __('Max SP'),
            'max_stamina' => __('Max Stamina'),
            'attack' => __('Attack'),
            'defense' => __('Defense'),
            'speed' => __('Speed'),
            'magic_attack' => __('Magic Attack'),
            'crit_chance' => __('Crit Chance'),
            'evade_chance' => __('Evade Chance'),
            'monster_damage' => __('Monster Damage'),
            'lumiis_damage' => __('Lumiis Damage'),
            'experience_rate' => __('EXP Rate'),
            'yang_rate' => __('Yang Rate'),
            'loot_rate' => __('Loot Rate'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function rewardBonusColumns(): array
    {
        return [
            'vitality' => 'vitality',
            'intelligence' => 'intelligence',
            'strength' => 'strength',
            'dexterity' => 'dexterity',
            'max_health' => 'max_health',
            'max_sp' => 'max_sp',
            'max_stamina' => 'max_stamina',
            'attack' => 'attack',
            'defense' => 'defense',
            'speed' => 'speed',
            'magic_attack' => 'magic_attack',
            'crit_chance' => 'crit_chance',
            'evade_chance' => 'evade_chance',
            'monster_damage' => 'monster_damage',
            'lumiis_damage' => 'lumiis_damage',
            'experience_rate' => 'experience_rate',
            'yang_rate' => 'yang_rate',
            'loot_rate' => 'loot_rate',
        ];
    }

    /**
     * Some permanent quest rewards are percentage modifiers rather than flat stats.
     *
     * The description helper needs this list so the archive, quest toasts and admin previews
     * all show the correct suffix for later Biologist rewards.
     *
     * @return list<string>
     */
    public static function percentBasedRewardBonuses(): array
    {
        return [
            'crit_chance',
            'evade_chance',
            'monster_damage',
            'lumiis_damage',
            'experience_rate',
            'yang_rate',
            'loot_rate',
        ];
    }

    public static function rewardBonusDescription(?string $bonusType, int $bonusValue): ?string
    {
        if ($bonusType === null || $bonusValue < 1) {
            return null;
        }

        $label = self::rewardBonusOptions()[$bonusType] ?? null;

        if ($label === null) {
            return null;
        }

        $valueLabel = '+'.$bonusValue;

        if (in_array($bonusType, self::percentBasedRewardBonuses(), true)) {
            $valueLabel .= '%';
        }

        return $valueLabel.' '.$label;
    }
}
