<?php

namespace Tests\Unit;

use App\Models\CharacterEquipment;
use App\Models\CharacterItem;
use App\Models\GameCharacter;
use App\Models\Item;
use App\Models\Location;
use App\Models\LocationArea;
use App\Models\LocationAreaMonster;
use App\Models\Monster;
use App\Models\User;
use App\Services\Game\ManualEncounterPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ManualEncounterPreviewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_for_area_returns_family_previews_with_success_chances(): void
    {
        $service = app(ManualEncounterPreviewService::class);
        $location = $this->createLocation();
        $area = $this->createArea($location, [
            'slug' => 'riverbank',
            'name' => 'Riverbank',
        ]);

        $wolf = $this->createMonster([
            'slug' => 'wolf',
            'name' => 'Wolf',
            'level' => 3,
            'maximum_health' => 34,
            'minimum_attack' => 6,
            'maximum_attack' => 8,
            'defense' => 0,
        ]);
        $alphaWolf = $this->createMonster([
            'slug' => 'alpha-wolf',
            'name' => 'Alpha-Wolf',
            'level' => 4,
            'maximum_health' => 42,
            'minimum_attack' => 7,
            'maximum_attack' => 10,
            'defense' => 1,
        ]);
        $alphaBluewolf = $this->createMonster([
            'slug' => 'alpha-blauwolf',
            'name' => 'Alpha-Blauwolf',
            'level' => 9,
            'maximum_health' => 72,
            'minimum_attack' => 13,
            'maximum_attack' => 17,
            'defense' => 2,
        ]);

        $this->createAssignment($area, $wolf, [
            'minimum_character_level' => 1,
            'maximum_character_level' => 4,
            'sort_order' => 10,
        ]);
        $this->createAssignment($area, $alphaWolf, [
            'minimum_character_level' => 4,
            'maximum_character_level' => 6,
            'sort_order' => 20,
        ]);
        $this->createAssignment($area, $alphaBluewolf, [
            'minimum_character_level' => 8,
            'maximum_character_level' => 10,
            'sort_order' => 30,
        ]);

        $character = $this->createStarterNinja($location, 126);

        $families = $service->buildForArea($character, $area);

        $this->assertCount(1, $families);
        $wolves = $families->firstWhere('key', 'wolves');

        $this->assertSame('wolves', $wolves['key']);
        $this->assertSame('Wolf', $wolves['options']['easy']['encounter_name']);
        $this->assertSame('Alpha-Blauwolf', $wolves['options']['hard']['encounter_name']);
        $this->assertGreaterThan(
            $wolves['options']['hard']['success_rate_percent'],
            $wolves['options']['easy']['success_rate_percent'],
        );
        $this->assertGreaterThanOrEqual(5, $wolves['options']['easy']['success_rate_percent']);
        $this->assertLessThanOrEqual(98, $wolves['options']['easy']['success_rate_percent']);
    }

    public function test_build_for_area_lowers_success_rate_when_current_health_is_low(): void
    {
        $service = app(ManualEncounterPreviewService::class);
        $location = $this->createLocation();
        $area = $this->createArea($location, [
            'slug' => 'riverbank',
            'name' => 'Riverbank',
        ]);
        $wolf = $this->createMonster([
            'slug' => 'wolf',
            'name' => 'Wolf',
            'level' => 3,
            'maximum_health' => 34,
            'minimum_attack' => 6,
            'maximum_attack' => 8,
            'defense' => 0,
        ]);
        $alphaBluewolf = $this->createMonster([
            'slug' => 'alpha-blauwolf',
            'name' => 'Alpha-Blauwolf',
            'level' => 9,
            'maximum_health' => 72,
            'minimum_attack' => 13,
            'maximum_attack' => 17,
            'defense' => 2,
        ]);

        $this->createAssignment($area, $wolf, [
            'minimum_character_level' => 1,
            'maximum_character_level' => 4,
            'sort_order' => 10,
        ]);
        $this->createAssignment($area, $alphaBluewolf, [
            'minimum_character_level' => 8,
            'maximum_character_level' => 10,
            'sort_order' => 20,
        ]);

        $healthyCharacter = $this->createStarterNinja($location, 126);
        $woundedCharacter = $this->createStarterNinja($location, 22);

        $healthyRate = data_get($service->buildForArea($healthyCharacter, $area)->first(), 'options.hard.success_rate_percent');
        $woundedRate = data_get($service->buildForArea($woundedCharacter, $area)->first(), 'options.hard.success_rate_percent');

        $this->assertGreaterThan($woundedRate, $healthyRate);
    }

    public function test_build_action_previews_returns_monster_and_lumiis_entries_with_risk_labels(): void
    {
        $service = app(ManualEncounterPreviewService::class);
        $location = $this->createLocation();
        $area = $this->createArea($location, [
            'slug' => 'riverbank',
            'name' => 'Riverbank',
            'recommended_level' => 4,
        ]);
        $wolf = $this->createMonster([
            'slug' => 'wolf',
            'name' => 'Wolf',
            'level' => 3,
            'maximum_health' => 34,
            'minimum_attack' => 6,
            'maximum_attack' => 8,
            'defense' => 0,
        ]);
        $alphaBluewolf = $this->createMonster([
            'slug' => 'alpha-blauwolf',
            'name' => 'Alpha-Blauwolf',
            'level' => 9,
            'maximum_health' => 72,
            'minimum_attack' => 13,
            'maximum_attack' => 17,
            'defense' => 2,
        ]);

        $this->createAssignment($area, $wolf, [
            'minimum_character_level' => 1,
            'maximum_character_level' => 4,
            'sort_order' => 10,
        ]);
        $this->createAssignment($area, $alphaBluewolf, [
            'minimum_character_level' => 8,
            'maximum_character_level' => 10,
            'sort_order' => 20,
        ]);

        $character = $this->createStarterNinja($location, 126);

        $previews = $service->buildActionPreviews($character, $area);

        $this->assertSame(['monster_auto', 'lumiis_auto', 'lumiis_manual'], array_keys($previews));
        $this->assertSame('Wolf', $previews['monster_auto']['encounter_name']);
        $this->assertNotSame('', $previews['monster_auto']['risk_label']);
        $this->assertSame(__('Lv 4 · core + soul waves'), $previews['lumiis_auto']['encounter_summary']);
        $this->assertGreaterThan(
            $previews['lumiis_auto']['success_rate_percent'],
            $previews['lumiis_manual']['success_rate_percent'],
        );
        $this->assertSame(
            $service->riskLabel($previews['monster_auto']['success_rate_percent']),
            $previews['monster_auto']['risk_label'],
        );
    }

    public function test_risk_label_uses_expected_thresholds(): void
    {
        $service = app(ManualEncounterPreviewService::class);

        $this->assertSame(__('Dangerous'), $service->riskLabel(44));
        $this->assertSame(__('Risky'), $service->riskLabel(45));
        $this->assertSame(__('Fair'), $service->riskLabel(65));
        $this->assertSame(__('Safe'), $service->riskLabel(85));
    }

    private function createLocation(array $overrides = []): Location
    {
        return Location::query()->create([
            'slug' => 'test-location-'.fake()->unique()->numerify('###'),
            'name' => 'Test Location',
            'region' => 'Tests',
            'description' => 'Temporary test location.',
            'threat_level' => 1,
            'is_safe_zone' => false,
            ...$overrides,
        ]);
    }

    private function createArea(Location $location, array $overrides = []): LocationArea
    {
        return LocationArea::query()->create([
            'location_id' => $location->id,
            'slug' => 'test-area-'.fake()->unique()->numerify('###'),
            'name' => 'Test Area',
            'description' => 'Temporary area.',
            'area_kind' => 'hunting',
            'service_type' => null,
            'hotspot_x' => 50,
            'hotspot_y' => 50,
            'sort_order' => 1,
            'recommended_level' => 1,
            'monster_name' => 'Test Monster',
            'lumiis_name' => 'Test Lumiis',
            'boss_name' => 'Test Boss',
            'monster_exp_min' => 12,
            'monster_exp_max' => 18,
            'monster_yang_min' => 5,
            'monster_yang_max' => 8,
            ...$overrides,
        ]);
    }

    private function createStarterNinja(Location $location, int $health): GameCharacter
    {
        $user = User::factory()->create();
        $character = GameCharacter::query()->create([
            'user_id' => $user->id,
            'location_id' => $location->id,
            'name' => 'PreviewHero'.fake()->unique()->numerify('###'),
            'faction' => 'Shinsoo',
            'race' => 'ninja',
            'level' => 1,
            'experience' => 0,
            'yang' => 150,
            'health' => $health,
            'max_health' => 96,
            'stamina' => 15,
            'max_stamina' => 15,
            'attack' => 8,
            'defense' => 5,
            'speed' => 7,
            'magic_attack' => 0,
            'vitality' => 3,
            'intelligence' => 3,
            'strength' => 4,
            'dexterity' => 6,
            'sp' => 42,
            'max_sp' => 24,
            'unspent_skill_points' => 0,
            'unspent_status_points' => 0,
            'version' => 1,
            'last_active_at' => now(),
            'stamina_updated_at' => now(),
            'health_updated_at' => now(),
            'sp_updated_at' => now(),
        ]);

        $dagger = new Item([
            'name' => 'Dagger',
            'item_type' => 'equipment',
            'equipment_slot' => 'weapon',
            'attack_bonus' => 4,
            'defense_bonus' => 0,
            'health_bonus' => 0,
            'stamina_bonus' => 0,
            'speed_bonus' => 1,
            'travel_speed_percent_bonus' => 0,
        ]);
        $cottonArmor = new Item([
            'name' => 'Cotton Armor',
            'item_type' => 'equipment',
            'equipment_slot' => 'armor',
            'attack_bonus' => 0,
            'defense_bonus' => 2,
            'health_bonus' => 10,
            'stamina_bonus' => 0,
            'speed_bonus' => 0,
            'travel_speed_percent_bonus' => 0,
        ]);

        $weaponItem = new CharacterItem;
        $weaponItem->setRelation('item', $dagger);
        $weaponEquipment = new CharacterEquipment(['slot' => 'weapon']);
        $weaponEquipment->setRelation('characterItem', $weaponItem);

        $armorItem = new CharacterItem;
        $armorItem->setRelation('item', $cottonArmor);
        $armorEquipment = new CharacterEquipment(['slot' => 'armor']);
        $armorEquipment->setRelation('characterItem', $armorItem);

        $character->setRelation('equipment', new Collection([$weaponEquipment, $armorEquipment]));
        $character->setRelation('learnedSkills', new Collection);

        return $character;
    }

    private function createMonster(array $overrides = []): Monster
    {
        return Monster::query()->create([
            'slug' => 'monster-'.fake()->unique()->numerify('###'),
            'name' => 'Test Monster',
            'level' => 1,
            'rank' => 1,
            'race' => 'Tiere',
            'damage_type' => 'Physisch',
            'sprite' => 'test.png',
            'maximum_health' => 20,
            'minimum_attack' => 3,
            'maximum_attack' => 6,
            'defense' => 1,
            'experience_reward' => 10,
            'yang_min' => 2,
            'yang_max' => 4,
            'sort_order' => 1,
            ...$overrides,
        ]);
    }

    private function createAssignment(LocationArea $area, Monster $monster, array $overrides = []): LocationAreaMonster
    {
        $assignment = LocationAreaMonster::query()->create([
            'location_area_id' => $area->id,
            'monster_id' => $monster->id,
            'weight' => 10,
            'minimum_character_level' => 1,
            'maximum_character_level' => 10,
            'pack_size' => 3,
            'sort_order' => 1,
            ...$overrides,
        ]);
        $assignment->setRelation('monster', $monster);
        $area->load('monsterAssignments.monster');

        return $assignment;
    }
}
