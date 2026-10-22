<?php

namespace Tests\Unit;

use App\Models\CharacterEquipment;
use App\Models\CharacterItem;
use App\Models\CharacterItemBonus;
use App\Models\CharacterSkill;
use App\Models\GameCharacter;
use App\Models\Item;
use App\Models\Skill;
use Illuminate\Support\Collection;
use Tests\TestCase;

class GameCharacterTest extends TestCase
{
    public function test_effective_stats_include_equipment_and_skill_bonuses(): void
    {
        $character = new GameCharacter([
            'name' => 'Tester',
            'attack' => 8,
            'defense' => 6,
            'speed' => 5,
            'max_health' => 100,
            'max_stamina' => 12,
        ]);

        $character->setRelation('equipment', new Collection([
            $this->equippedItem([
                'attack_bonus' => 3,
                'defense_bonus' => 2,
                'speed_bonus' => 1,
                'health_bonus' => 20,
                'stamina_bonus' => 2,
                'travel_speed_percent_bonus' => 15,
            ]),
        ]));

        $character->setRelation('learnedSkills', new Collection([
            $this->learnedSkill('attack', 1, 2),
            $this->learnedSkill('defense', 2, 1),
            $this->learnedSkill('speed', 1, 1),
            $this->learnedSkill('travel_speed_percent', 1, 5),
        ]));

        $this->assertSame(13, $character->effectiveAttack());
        $this->assertSame(10, $character->effectiveDefense());
        $this->assertSame(7, $character->effectiveSpeed());
        $this->assertSame(120, $character->effectiveMaxHealth());
        $this->assertSame(14, $character->effectiveMaxStamina());
        $this->assertSame(20, $character->travelSpeedPercentBonus());
    }

    public function test_stamina_recovery_state_caps_at_max_and_reports_next_tick(): void
    {
        $character = new GameCharacter([
            'stamina' => 3,
            'max_stamina' => 12,
            'last_active_at' => now()->subMinutes(35),
            'stamina_updated_at' => now()->subMinutes(35),
        ]);
        $character->setRelation('equipment', new Collection);
        $character->setRelation('learnedSkills', new Collection);

        $preview = $character->staminaRecoveryState(now());

        $this->assertSame(6, $preview['stamina']);
        $this->assertSame(3, $preview['recovered']);
        $this->assertSame(300, $preview['next_tick_seconds']);

        $fullCharacter = new GameCharacter([
            'stamina' => 11,
            'max_stamina' => 12,
            'last_active_at' => now()->subMinutes(30),
            'stamina_updated_at' => now()->subMinutes(30),
        ]);
        $fullCharacter->setRelation('equipment', new Collection);
        $fullCharacter->setRelation('learnedSkills', new Collection);

        $fullPreview = $fullCharacter->staminaRecoveryState(now());

        $this->assertSame(12, $fullPreview['stamina']);
        $this->assertSame(1, $fullPreview['recovered']);
        $this->assertNull($fullPreview['next_tick_seconds']);
    }

    public function test_health_and_sp_recovery_states_scale_with_their_respective_caps(): void
    {
        $character = new GameCharacter([
            'health' => 40,
            'max_health' => 100,
            'sp' => 3,
            'max_sp' => 24,
            'last_active_at' => now()->subMinutes(30),
            'health_updated_at' => now()->subMinutes(30),
            'sp_updated_at' => now()->subMinutes(30),
        ]);
        $character->setRelation('equipment', new Collection);
        $character->setRelation('learnedSkills', new Collection);

        $healthPreview = $character->healthRecoveryState(now());
        $spPreview = $character->spRecoveryState(now());

        $this->assertSame(70, $healthPreview['health']);
        $this->assertSame(30, $healthPreview['recovered']);
        $this->assertSame(300, $healthPreview['next_tick_seconds']);
        $this->assertSame(24, $spPreview['sp']);
        $this->assertSame(21, $spPreview['recovered']);
        $this->assertNull($spPreview['next_tick_seconds']);
    }

    public function test_travel_cooldown_bonus_is_capped_at_sixty_percent(): void
    {
        $character = new GameCharacter([
            'attack' => 8,
            'defense' => 6,
            'speed' => 5,
            'max_health' => 100,
            'max_stamina' => 12,
        ]);

        $character->setRelation('equipment', new Collection([
            $this->equippedItem([
                'travel_speed_percent_bonus' => 80,
            ]),
        ]));
        $character->setRelation('learnedSkills', new Collection([
            $this->learnedSkill('travel_speed_percent', 2, 10),
        ]));

        $this->assertSame(100, $character->travelSpeedPercentBonus());
        $this->assertSame(40, $character->travelCooldownFor(100));
    }

    public function test_weapon_upgrade_level_increases_effective_attack(): void
    {
        $character = new GameCharacter([
            'attack' => 8,
            'defense' => 6,
            'speed' => 5,
            'max_health' => 100,
            'max_stamina' => 12,
        ]);

        $character->setRelation('equipment', new Collection([
            $this->equippedItem([
                'attack_bonus' => 2,
            ], 3),
        ]));
        $character->setRelation('learnedSkills', new Collection);

        $this->assertSame(18, $character->effectiveAttack());
    }

    public function test_equipped_weapon_type_reads_the_weapon_slot_item(): void
    {
        $character = new GameCharacter([
            'attack' => 8,
            'defense' => 6,
            'speed' => 5,
            'max_health' => 100,
            'max_stamina' => 12,
        ]);

        $character->setRelation('equipment', new Collection([
            $this->equippedItem([
                'weapon_type' => 'dagger',
            ]),
            $this->equippedItem([
                'equipment_slot' => 'armor',
            ]),
        ]));
        $character->setRelation('learnedSkills', new Collection);

        $this->assertSame('dagger', $character->equippedWeaponType());
    }

    public function test_warrior_skill_groups_split_into_offense_and_defense_profiles(): void
    {
        $bodyWarrior = new GameCharacter([
            'race' => 'warrior',
            'skill_group' => 'body',
            'attack' => 8,
            'defense' => 6,
            'speed' => 5,
            'max_health' => 100,
            'max_stamina' => 12,
            'strength' => 4,
            'dexterity' => 6,
            'vitality' => 3,
        ]);
        $bodyWarrior->setRelation('equipment', new Collection);
        $bodyWarrior->setRelation('learnedSkills', new Collection);

        $mentalWarrior = new GameCharacter([
            'race' => 'warrior',
            'skill_group' => 'mental',
            'attack' => 8,
            'defense' => 6,
            'speed' => 5,
            'max_health' => 100,
            'max_stamina' => 12,
            'strength' => 4,
            'dexterity' => 6,
            'vitality' => 3,
        ]);
        $mentalWarrior->setRelation('equipment', new Collection);
        $mentalWarrior->setRelation('learnedSkills', new Collection);

        $this->assertSame(15, $bodyWarrior->effectiveAttack());
        $this->assertSame(7, $bodyWarrior->effectiveDefense());
        $this->assertSame(7, $bodyWarrior->effectiveSpeed());
        $this->assertSame(4, $bodyWarrior->criticalChancePercent());
        $this->assertSame(4, $bodyWarrior->averageDamagePercentBonus());

        $this->assertSame(13, $mentalWarrior->effectiveAttack());
        $this->assertSame(9, $mentalWarrior->effectiveDefense());
        $this->assertSame(6, $mentalWarrior->effectiveSpeed());
        $this->assertSame(148, $mentalWarrior->effectiveMaxHealth());
        $this->assertSame(4, $mentalWarrior->evadeChancePercent());
    }

    public function test_magic_skill_groups_split_between_hybrid_and_caster_profiles(): void
    {
        $weaponarySura = new GameCharacter([
            'race' => 'sura',
            'skill_group' => 'weaponary-magic',
            'attack' => 8,
            'defense' => 6,
            'speed' => 5,
            'max_health' => 100,
            'max_stamina' => 12,
            'max_sp' => 28,
            'magic_attack' => 4,
            'intelligence' => 5,
            'strength' => 4,
            'dexterity' => 3,
            'vitality' => 4,
        ]);
        $weaponarySura->setRelation('equipment', new Collection);
        $weaponarySura->setRelation('learnedSkills', new Collection);

        $blackMagicSura = new GameCharacter([
            'race' => 'sura',
            'skill_group' => 'black-magic',
            'attack' => 8,
            'defense' => 6,
            'speed' => 5,
            'max_health' => 100,
            'max_stamina' => 12,
            'max_sp' => 28,
            'magic_attack' => 4,
            'intelligence' => 5,
            'strength' => 4,
            'dexterity' => 3,
            'vitality' => 4,
        ]);
        $blackMagicSura->setRelation('equipment', new Collection);
        $blackMagicSura->setRelation('learnedSkills', new Collection);

        $this->assertSame(13, $weaponarySura->effectiveAttack());
        $this->assertSame(9, $weaponarySura->effectiveDefense());
        $this->assertSame(16, $weaponarySura->effectiveMagicAttack());
        $this->assertSame(2, $weaponarySura->averageDamagePercentBonus());

        $this->assertSame(12, $blackMagicSura->effectiveAttack());
        $this->assertSame(8, $blackMagicSura->effectiveDefense());
        $this->assertSame(18, $blackMagicSura->effectiveMagicAttack());
        $this->assertSame(68, $blackMagicSura->effectiveMaxSp());
        $this->assertSame(5, $blackMagicSura->skillDamagePercentBonus());
    }

    public function test_next_level_experience_curve_stays_fast_early_and_slows_after_level_three(): void
    {
        $starter = new GameCharacter(['level' => 1]);
        $apprentice = new GameCharacter(['level' => 2]);
        $fieldHand = new GameCharacter(['level' => 3]);
        $midgame = new GameCharacter(['level' => 5]);

        $this->assertSame(120, $starter->nextLevelExperience());
        $this->assertSame(240, $apprentice->nextLevelExperience());
        $this->assertSame(380, $fieldHand->nextLevelExperience());
        $this->assertSame(780, $midgame->nextLevelExperience());
    }

    public function test_attribute_and_item_bonuses_expand_the_combat_profile(): void
    {
        $character = new GameCharacter([
            'name' => 'Bonus Tester',
            'attack' => 8,
            'defense' => 6,
            'speed' => 5,
            'max_health' => 100,
            'max_stamina' => 12,
            'max_sp' => 24,
            'sp' => 24,
            'vitality' => 1,
            'intelligence' => 1,
            'strength' => 2,
            'dexterity' => 3,
        ]);

        $weapon = $this->equippedItem([], 0, [
            ['bonus_type' => 'strength', 'bonus_value' => 3],
            ['bonus_type' => 'dexterity', 'bonus_value' => 2],
            ['bonus_type' => 'crit_chance', 'bonus_value' => 4],
            ['bonus_type' => 'monster_damage', 'bonus_value' => 6],
            ['bonus_type' => 'lumiis_damage', 'bonus_value' => 5],
            ['bonus_type' => 'magic_attack', 'bonus_value' => 4],
        ]);
        $armor = $this->equippedItem([
            'equipment_slot' => 'armor',
        ], 0, [
            ['bonus_type' => 'vitality', 'bonus_value' => 2],
            ['bonus_type' => 'max_health', 'bonus_value' => 25],
            ['bonus_type' => 'max_sp', 'bonus_value' => 10],
            ['bonus_type' => 'evade_chance', 'bonus_value' => 3],
        ]);

        $character->setRelation('equipment', new Collection([$weapon, $armor]));
        $character->setRelation('learnedSkills', new Collection);

        $this->assertSame(3, $character->effectiveVitality());
        $this->assertSame(1, $character->effectiveIntelligence());
        $this->assertSame(5, $character->effectiveStrength());
        $this->assertSame(5, $character->effectiveDexterity());
        $this->assertSame(14, $character->effectiveAttack());
        $this->assertSame(7, $character->effectiveDefense());
        $this->assertSame(6, $character->effectiveSpeed());
        $this->assertSame(155, $character->effectiveMaxHealth());
        $this->assertSame(12, $character->effectiveMaxStamina());
        $this->assertSame(40, $character->effectiveMaxSp());
        $this->assertSame(24, $character->effectiveCurrentSp());
        $this->assertSame(6, $character->effectiveMagicAttack());
        $this->assertSame(5, $character->criticalChancePercent());
        $this->assertSame(5, $character->evadeChancePercent());
        $this->assertSame(6, $character->monsterDamagePercentBonus());
        $this->assertSame(5, $character->lumiisDamagePercentBonus());
        $this->assertEquals(1.06, $character->damageMultiplierForEnemyCategory('Monster'));
        $this->assertEquals(1.05, $character->damageMultiplierForEnemyCategory('Lumiis Core'));
    }

    public function test_base_attributes_drive_the_expected_derived_character_values(): void
    {
        $character = new GameCharacter([
            'attack' => 8,
            'defense' => 6,
            'speed' => 5,
            'max_health' => 100,
            'max_stamina' => 12,
            'max_sp' => 24,
            'vitality' => 3,
            'intelligence' => 2,
            'strength' => 4,
            'dexterity' => 6,
        ]);

        $character->setRelation('equipment', new Collection);
        $character->setRelation('learnedSkills', new Collection);

        $this->assertSame(13, $character->effectiveAttack());
        $this->assertSame(7, $character->effectiveDefense());
        $this->assertSame(6, $character->effectiveSpeed());
        $this->assertSame(130, $character->effectiveMaxHealth());
        $this->assertSame(36, $character->effectiveMaxSp());
        $this->assertSame(4, $character->effectiveMagicAttack());
        $this->assertSame(2, $character->criticalChancePercent());
        $this->assertSame(3, $character->evadeChancePercent());
    }

    public function test_permanent_midgame_bonus_columns_stack_with_equipment_bonuses(): void
    {
        $character = new GameCharacter([
            'attack' => 8,
            'defense' => 6,
            'speed' => 5,
            'max_health' => 100,
            'max_stamina' => 12,
            'max_sp' => 24,
            'magic_attack' => 3,
            'crit_chance' => 2,
            'evade_chance' => 4,
            'monster_damage' => 5,
            'lumiis_damage' => 6,
            'experience_rate' => 7,
            'yang_rate' => 8,
            'loot_rate' => 9,
            'intelligence' => 2,
            'dexterity' => 6,
        ]);

        $character->setRelation('equipment', new Collection([
            $this->equippedItem([], 0, [
                ['bonus_type' => 'magic_attack', 'bonus_value' => 4],
                ['bonus_type' => 'crit_chance', 'bonus_value' => 3],
                ['bonus_type' => 'evade_chance', 'bonus_value' => 1],
                ['bonus_type' => 'monster_damage', 'bonus_value' => 2],
                ['bonus_type' => 'lumiis_damage', 'bonus_value' => 1],
                ['bonus_type' => 'experience_rate', 'bonus_value' => 2],
                ['bonus_type' => 'yang_rate', 'bonus_value' => 1],
                ['bonus_type' => 'loot_rate', 'bonus_value' => 3],
            ]),
        ]));
        $character->setRelation('learnedSkills', new Collection);

        $this->assertSame(11, $character->effectiveMagicAttack());
        $this->assertSame(7, $character->criticalChancePercent());
        $this->assertSame(8, $character->evadeChancePercent());
        $this->assertSame(7, $character->monsterDamagePercentBonus());
        $this->assertSame(7, $character->lumiisDamagePercentBonus());
        $this->assertSame(9, $character->experienceRatePercentBonus());
        $this->assertSame(9, $character->yangRatePercentBonus());
        $this->assertSame(12, $character->lootRatePercentBonus());
    }

    public function test_late_bonus_columns_fall_back_safely_when_the_model_was_only_partially_selected(): void
    {
        $character = new GameCharacter([
            'attack' => 8,
            'defense' => 6,
            'speed' => 5,
            'max_health' => 100,
            'max_stamina' => 12,
        ]);

        $character->setRelation('equipment', new Collection);
        $character->setRelation('learnedSkills', new Collection);

        $this->assertSame(0, $character->effectiveVitality());
        $this->assertSame(0, $character->effectiveIntelligence());
        $this->assertSame(0, $character->effectiveStrength());
        $this->assertSame(0, $character->effectiveDexterity());
        $this->assertSame(24, $character->effectiveMaxSp());
        $this->assertSame(24, $character->effectiveCurrentSp());
        $this->assertSame(0, $character->effectiveMagicAttack());
        $this->assertSame(0, $character->criticalChancePercent());
        $this->assertSame(0, $character->evadeChancePercent());
        $this->assertSame(0, $character->monsterDamagePercentBonus());
        $this->assertSame(0, $character->lumiisDamagePercentBonus());
        $this->assertSame(0, $character->experienceRatePercentBonus());
        $this->assertSame(0, $character->yangRatePercentBonus());
        $this->assertSame(0, $character->lootRatePercentBonus());
    }

    public function test_new_players_recover_stamina_twice_as_fast_until_level_five(): void
    {
        $character = new GameCharacter([
            'level' => 2,
            'stamina' => 3,
            'max_stamina' => 12,
            'last_active_at' => now()->subMinutes(15),
            'stamina_updated_at' => now()->subMinutes(15),
        ]);
        $character->setRelation('equipment', new Collection);
        $character->setRelation('learnedSkills', new Collection);

        $preview = $character->staminaRecoveryState(now());

        $this->assertTrue($character->newPlayerStaminaBoostActive());
        $this->assertSame(6, $preview['stamina']);
        $this->assertSame(3, $preview['recovered']);
        $this->assertSame(300, $preview['next_tick_seconds']);
    }

    private function equippedItem(array $itemAttributes, int $upgradeLevel = 0, array $bonusAttributes = []): CharacterEquipment
    {
        $item = new Item(array_merge([
            'name' => 'Debug Item',
            'item_type' => 'equipment',
            'equipment_slot' => 'weapon',
            'attack_bonus' => 0,
            'defense_bonus' => 0,
            'health_bonus' => 0,
            'stamina_bonus' => 0,
            'speed_bonus' => 0,
            'travel_speed_percent_bonus' => 0,
        ], $itemAttributes));

        $inventoryItem = new CharacterItem([
            'upgrade_level' => $upgradeLevel,
        ]);
        $inventoryItem->setRelation('item', $item);
        $inventoryItem->setRelation(
            'bonuses',
            new Collection(array_map(
                fn (array $attributes): CharacterItemBonus => new CharacterItemBonus($attributes),
                $bonusAttributes,
            )),
        );

        $equipment = new CharacterEquipment([
            'slot' => $item->equipment_slot,
        ]);
        $equipment->setRelation('characterItem', $inventoryItem);

        return $equipment;
    }

    private function learnedSkill(string $effectType, int $rank, int $effectValue): CharacterSkill
    {
        $skill = new Skill([
            'effect_type' => $effectType,
            'effect_value' => $effectValue,
        ]);

        $characterSkill = new CharacterSkill([
            'rank' => $rank,
        ]);
        $characterSkill->setRelation('skill', $skill);

        return $characterSkill;
    }
}
