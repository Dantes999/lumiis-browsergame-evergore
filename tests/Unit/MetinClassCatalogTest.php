<?php

namespace Tests\Unit;

use App\Models\CharacterSkill;
use App\Models\GameCharacter;
use App\Models\Skill;
use App\Support\Game\MetinClassCatalog;
use Illuminate\Support\Collection;
use Tests\TestCase;

class MetinClassCatalogTest extends TestCase
{
    public function test_warrior_skill_groups_have_distinct_combat_profiles(): void
    {
        $this->assertSame([
            'attack_floor_offset' => 0,
            'attack_ceiling_offset' => 2,
            'crit_chance_bonus' => 2,
            'guard_bonus' => 0,
            'basic_damage_percent' => 6,
            'skill_damage_percent' => 8,
            'incoming_damage_percent' => 8,
            'opening_damage_percent' => 0,
            'opening_crit_bonus' => 0,
            'guard_retention_percent' => 0,
            'lifesteal_percent' => 0,
            'damage_to_sp_percent' => 0,
            'reflect_percent' => 0,
        ], MetinClassCatalog::skillGroupCombatProfile('warrior', 'body'));

        $this->assertSame([
            'attack_floor_offset' => 1,
            'attack_ceiling_offset' => -1,
            'crit_chance_bonus' => 0,
            'guard_bonus' => 4,
            'basic_damage_percent' => 0,
            'skill_damage_percent' => 2,
            'incoming_damage_percent' => -14,
            'opening_damage_percent' => 0,
            'opening_crit_bonus' => 0,
            'guard_retention_percent' => 50,
            'lifesteal_percent' => 0,
            'damage_to_sp_percent' => 0,
            'reflect_percent' => 0,
        ], MetinClassCatalog::skillGroupCombatProfile('warrior', 'mental'));
    }

    public function test_unknown_skill_group_returns_neutral_combat_profile(): void
    {
        $this->assertSame([
            'attack_floor_offset' => 0,
            'attack_ceiling_offset' => 0,
            'crit_chance_bonus' => 0,
            'guard_bonus' => 0,
            'basic_damage_percent' => 0,
            'skill_damage_percent' => 0,
            'incoming_damage_percent' => 0,
            'opening_damage_percent' => 0,
            'opening_crit_bonus' => 0,
            'guard_retention_percent' => 0,
            'lifesteal_percent' => 0,
            'damage_to_sp_percent' => 0,
            'reflect_percent' => 0,
        ], MetinClassCatalog::skillGroupCombatProfile('warrior', ''));
    }

    public function test_signature_school_mechanics_follow_their_metin2_roles(): void
    {
        $this->assertSame(12, MetinClassCatalog::skillGroupCombatProfile('ninja', 'archery')['opening_damage_percent']);
        $this->assertSame(8, MetinClassCatalog::skillGroupCombatProfile('sura', 'weaponary-magic')['lifesteal_percent']);
        $this->assertSame(30, MetinClassCatalog::skillGroupCombatProfile('sura', 'black-magic')['damage_to_sp_percent']);
        $this->assertSame(12, MetinClassCatalog::skillGroupCombatProfile('shaman', 'dragon')['reflect_percent']);
    }

    public function test_help_of_the_dragon_scales_with_intelligence(): void
    {
        $character = $this->makeCharacter([
            'race' => 'shaman',
            'skill_group' => 'dragon',
            'intelligence' => 18,
        ]);

        $profile = MetinClassCatalog::combatProfileForLearnedSkill(
            $this->makeLearnedSkill('dragons-strength', 1, $character),
            $character,
        );

        $this->assertSame(17, (int) ($profile['crit_bonus'] ?? 0));
        $this->assertSame(8, (int) ($profile['attack_buff'] ?? 0));
    }

    public function test_enchanted_blade_scales_attack_and_lifesteal_with_intelligence(): void
    {
        $character = $this->makeCharacter([
            'race' => 'sura',
            'skill_group' => 'weaponary-magic',
            'intelligence' => 16,
        ]);

        $profile = MetinClassCatalog::combatProfileForLearnedSkill(
            $this->makeLearnedSkill('enchanted-blade', 1, $character),
            $character,
        );

        $this->assertSame(16, (int) ($profile['attack_buff'] ?? 0));
        $this->assertSame(14, (int) ($profile['lifesteal_percent'] ?? 0));
    }

    public function test_strong_body_scales_guard_and_damage_reduction_with_vitality(): void
    {
        $character = $this->makeCharacter([
            'race' => 'warrior',
            'skill_group' => 'mental',
            'vitality' => 20,
        ]);

        $profile = MetinClassCatalog::combatProfileForLearnedSkill(
            $this->makeLearnedSkill('strong-body', 1, $character),
            $character,
        );

        $this->assertSame(26, (int) ($profile['guard'] ?? 0));
        $this->assertSame(-23, (int) ($profile['incoming_damage_percent'] ?? 0));
    }

    public function test_skill_progression_constants_follow_the_metin2_like_flow(): void
    {
        $this->assertSame(16, MetinClassCatalog::normalSkillRankCap());
        $this->assertTrue(MetinClassCatalog::earnsSkillPointAtLevel(2));
        $this->assertFalse(MetinClassCatalog::earnsSkillPointAtLevel(1));
        $this->assertSame(100, MetinClassCatalog::skillBookSuccessChance(1));
        $this->assertSame(95, MetinClassCatalog::skillBookSuccessChance(2));
        $this->assertSame(80, MetinClassCatalog::skillBookSuccessChance(5));
        $this->assertSame(25, MetinClassCatalog::skillBookSuccessChance(10));
        $this->assertSame(1, MetinClassCatalog::skillBookSuccessesRequired(1));
        $this->assertSame(1, MetinClassCatalog::skillBookSuccessesRequired(10));
    }

    private function makeCharacter(array $attributes): GameCharacter
    {
        $character = new GameCharacter($attributes);
        $character->setRelation('equipment', new Collection);
        $character->setRelation('learnedSkills', new Collection);

        return $character;
    }

    private function makeLearnedSkill(string $slug, int $rank, GameCharacter $character): CharacterSkill
    {
        $skill = new Skill([
            'slug' => $slug,
            'name' => ucwords(str_replace('-', ' ', $slug)),
            'effect_type' => 'combat',
        ]);

        $characterSkill = new CharacterSkill([
            'rank' => $rank,
            'mastery_rank' => 0,
            'grand_mastery_rank' => 0,
        ]);
        $characterSkill->setRelation('skill', $skill);
        $characterSkill->setRelation('character', $character);

        return $characterSkill;
    }
}
