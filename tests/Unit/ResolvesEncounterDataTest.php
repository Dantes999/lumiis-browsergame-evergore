<?php

namespace Tests\Unit;

use App\Exceptions\GameActionException;
use App\Models\CharacterAreaProgress;
use App\Models\CharacterEquipment;
use App\Models\CharacterItem;
use App\Models\GameCharacter;
use App\Models\Item;
use App\Models\Location;
use App\Models\LocationArea;
use App\Models\LocationAreaMonster;
use App\Models\Monster;
use App\Models\User;
use App\Services\Game\Concerns\ResolvesEncounterData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ResolvesEncounterDataTest extends TestCase
{
    use RefreshDatabase;

    private ResolvesEncounterDataHarness $harness;

    protected function setUp(): void
    {
        parent::setUp();

        $this->harness = new ResolvesEncounterDataHarness();
    }

    public function test_resolve_monster_assignment_uses_eligible_rows_then_falls_back_to_the_lowest_level_band_for_overleveled_characters(): void
    {
        $location = $this->createLocation();
        $area = $this->createArea($location, [
            'slug' => 'wolves',
            'name' => 'Wolf Area',
            'area_kind' => 'hunting',
        ]);

        $wildDog = $this->createMonster([
            'slug' => 'wild-dog',
            'name' => 'Wild Dog',
            'level' => 1,
        ]);
        $wolf = $this->createMonster([
            'slug' => 'wolf',
            'name' => 'Wolf',
            'level' => 3,
        ]);

        $assignmentOne = $this->createAssignment($area, $wildDog, [
            'minimum_character_level' => 1,
            'maximum_character_level' => 2,
            'weight' => 10,
        ]);
        $assignmentTwo = $this->createAssignment($area, $wolf, [
            'minimum_character_level' => 3,
            'maximum_character_level' => 5,
            'weight' => 10,
        ]);

        $area->setRelation('monsterAssignments', new Collection([$assignmentOne, $assignmentTwo]));

        $this->assertSame('lumiis', $this->harness->callNormalizeEncounterRequest(' lumiis '));
        $this->assertSame('boss', $this->harness->callEncounterColumnPrefix('boss'));
        $this->assertTrue($this->harness->callResolveMonsterAssignment($area, 1)->is($assignmentOne));
        $this->assertTrue($this->harness->callResolveMonsterAssignment($area, 99)->is($assignmentOne));
    }

    public function test_resolve_monster_assignment_rejects_underleveled_characters_before_fallback(): void
    {
        $location = $this->createLocation();
        $area = $this->createArea($location, [
            'slug' => 'ridge',
            'name' => 'Pine Ridge',
            'area_kind' => 'hunting',
            'recommended_level' => 12,
        ]);

        $wolf = $this->createMonster([
            'slug' => 'grauwolf',
            'name' => 'Grauwolf',
            'level' => 13,
        ]);

        $assignment = $this->createAssignment($area, $wolf, [
            'minimum_character_level' => 12,
            'maximum_character_level' => 15,
            'weight' => 10,
        ]);

        $area->setRelation('monsterAssignments', new Collection([$assignment]));

        $this->expectException(GameActionException::class);
        $this->expectExceptionMessage('You need to reach level 12 before hunting in Pine Ridge.');

        $this->harness->callResolveMonsterAssignment($area, 3);
    }

    public function test_resolve_monster_assignment_throws_when_area_has_no_monster_rows(): void
    {
        $location = $this->createLocation();
        $area = $this->createArea($location, [
            'slug' => 'empty-hunt',
            'area_kind' => 'hunting',
        ]);
        $area->setRelation('monsterAssignments', new Collection());

        $this->expectException(GameActionException::class);
        $this->expectExceptionMessage('No monster packs are configured for this area yet.');

        $this->harness->callResolveMonsterAssignment($area, 1);
    }

    public function test_resolve_hunting_area_rejects_unknown_and_service_hotspots(): void
    {
        $location = $this->createLocation();
        $huntingArea = $this->createArea($location, [
            'slug' => 'riverbank',
            'name' => 'Riverbank',
            'area_kind' => 'hunting',
        ]);
        $serviceArea = $this->createArea($location, [
            'slug' => 'town-square',
            'name' => 'Town Square',
            'area_kind' => 'service',
            'service_type' => 'town',
        ]);

        $location->setRelation('areas', new Collection([$huntingArea, $serviceArea]));
        $character = $this->createCharacter($location);
        $character->setRelation('location', $location);

        $this->assertTrue($this->harness->callResolveHuntingArea($character, $huntingArea->id)->is($huntingArea));

        try {
            $this->harness->callResolveHuntingArea($character, 999999);
            $this->fail('Expected missing area exception was not thrown.');
        } catch (GameActionException $exception) {
            $this->assertSame('That hunting area is not part of your current map.', $exception->getMessage());
        }

        try {
            $this->harness->callResolveHuntingArea($character, $serviceArea->id);
            $this->fail('Expected service area exception was not thrown.');
        } catch (GameActionException $exception) {
            $this->assertSame('That hotspot is a town service, not a hunting ground.', $exception->getMessage());
        }
    }

    public function test_area_progress_and_boss_resolution_helpers_cover_new_and_existing_states(): void
    {
        $location = $this->createLocation();
        $area = $this->createArea($location, [
            'slug' => 'pressure-field',
            'boss_pressure_threshold' => 40,
        ]);
        $character = $this->createCharacter($location);
        $character->setRelation('areaProgress', new Collection());

        $progress = $this->harness->callAreaProgress($character, $area);
        $sameProgress = $this->harness->callAreaProgress($character, $area);

        $this->assertTrue($progress->is($sameProgress));
        $this->assertFalse($this->harness->callBossIsReady($area, $progress));
        $this->assertSame('monster', $this->harness->callResolveEncounterType($area, $progress, 'monster'));

        try {
            $this->harness->callResolveEncounterType($area, $progress, 'boss');
            $this->fail('Expected hidden boss exception was not thrown.');
        } catch (GameActionException $exception) {
            $this->assertSame(
                'The boss soul is still hidden. Fight monsters and Lumiis in this area first.',
                $exception->getMessage(),
            );
        }

        $progress->pressure = 40;

        $this->assertTrue($this->harness->callBossIsReady($area, $progress));
        $this->assertSame('boss', $this->harness->callResolveEncounterType($area, $progress, ' boss '));
    }

    public function test_roll_helpers_messages_and_replay_payload_cover_the_read_trait_branches(): void
    {
        $location = $this->createLocation([
            'slug' => 'mist-valley',
            'threat_level' => 3,
        ]);
        $area = $this->createArea($location, [
            'slug' => 'lumiis-field',
            'name' => 'Lumiis Field',
            'recommended_level' => 2,
            'lumiis_name' => 'Weathered Lumiis',
            'boss_name' => 'Lumiis Beast',
            'lumiis_exp_min' => 22,
            'lumiis_exp_max' => 22,
            'monster_yang_min' => 7,
            'monster_yang_max' => 9,
            'description' => 'Test area',
        ]);

        $shard = $this->createItem([
            'slug' => 'test-lumiis-shard',
            'name' => 'Test Lumiis Shard',
        ]);
        $blade = $this->createItem([
            'slug' => 'test-blade',
            'name' => 'Test Blade',
        ]);

        $character = $this->createCharacter($location, [
            'name' => 'ReplayHero',
            'level' => 3,
            'health' => 100,
            'max_health' => 100,
        ]);
        $character->setRelation('location', $location);
        $character->setRelation('learnedSkills', new Collection());

        $equippedItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $blade->id,
            'quantity' => 1,
        ]);
        $equipment = CharacterEquipment::query()->create([
            'game_character_id' => $character->id,
            'character_item_id' => $equippedItem->id,
            'slot' => 'weapon',
        ]);
        $equipment->load('characterItem.item');
        $character->setRelation('equipment', new Collection([$equipment]));

        $this->assertSame(22, $this->harness->callRollEncounterValue($area, 'lumiis', 'exp'));
        $this->assertContains($this->harness->callRollEncounterValue($area, 'monster', 'yang'), [7, 8, 9]);
        $this->assertSame([], $this->harness->callRollLoot([]));

        $loot = $this->harness->callRollLoot([
            ['slug' => '', 'chance' => 10000, 'min' => 1, 'max' => 1],
            ['slug' => 'missing-item', 'chance' => 10000, 'min' => 1, 'max' => 1],
            ['slug' => $shard->slug, 'chance' => 0, 'min' => 1, 'max' => 1],
            ['slug' => $shard->slug, 'chance' => 10000, 'min' => 2, 'max' => 2],
        ]);

        $this->assertSame([
            [
                'slug' => 'test-lumiis-shard',
                'label' => 'Test Lumiis Shard',
                'quantity' => 2,
            ],
        ], $loot);

        $bossMessage = $this->harness->callEncounterMessage($area, 'boss', 'lumiis', 50, 20, 6, ['Shard']);
        $lumiisMessage = $this->harness->callEncounterMessage($area, 'lumiis', 'lumiis', 30, 10, 4, []);
        $monsterMessage = $this->harness->callEncounterMessage($area, 'monster', 'monster', 12, 5, 0, []);

        $this->assertStringContainsString('Enough trapped souls have been released', $bossMessage);
        $this->assertStringContainsString('You shatter', $lumiisMessage);
        $this->assertStringContainsString('You cleanse', $monsterMessage);

        $profile = $this->harness->callBuildReplayPlayerProfile($character, 100, 14, 8, 5);

        $this->assertSame('ReplayHero', $profile['name']);
        $this->assertSame(['Weapon: Test Blade'], $profile['equipment']);

        $distribution = $this->harness->callDistributeReplayPoints(10, 3, 1);

        $this->assertCount(3, $distribution);
        $this->assertSame(10, array_sum($distribution));
        $this->assertTrue(collect($distribution)->every(fn (int $value): bool => $value >= 1));

        $payload = $this->harness->callBuildEncounterReplayPayload(
            $character,
            $area,
            'lumiis',
            'Weathered Lumiis',
            100,
            14,
            8,
            5,
            6,
            30,
            20,
            6,
            70,
            ['Test Lumiis Shard'],
            'Replay summary',
        );

        $this->assertSame('lumiis', data_get($payload, 'battle_replay.encounter_type'));
        $this->assertSame('Lumiis Core', data_get($payload, 'battle_replay.enemies.0.category'));
        $this->assertStringContainsString(
            'cracks Weathered Lumiis',
            implode(' ', data_get($payload, 'battle_log', [])),
        );
        $this->assertStringContainsString(
            'soul burst',
            implode(' ', data_get($payload, 'battle_log', [])),
        );
        $this->assertSame('Replay summary', data_get($payload, 'battle_replay.summary.message'));
    }

    public function test_roll_loot_applies_the_loot_rate_bonus_to_drop_chance(): void
    {
        $item = $this->createItem([
            'slug' => 'boosted-drop',
            'name' => 'Boosted Drop',
        ]);

        $loot = $this->harness->callRollLoot([
            ['slug' => $item->slug, 'chance' => 5000, 'min' => 1, 'max' => 1],
        ], 100);

        $this->assertSame([
            [
                'slug' => 'boosted-drop',
                'label' => 'Boosted Drop',
                'quantity' => 1,
            ],
        ], $loot);
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
            ...$overrides,
        ]);
    }

    private function createCharacter(Location $location, array $overrides = []): GameCharacter
    {
        $user = User::factory()->create();

        $character = GameCharacter::query()->create([
            'user_id' => $user->id,
            'location_id' => $location->id,
            'name' => 'ResolveHero'.fake()->unique()->numerify('###'),
            'faction' => 'Shinsoo',
            'level' => 1,
            'experience' => 0,
            'yang' => 0,
            'health' => 100,
            'max_health' => 100,
            'stamina' => 12,
            'max_stamina' => 12,
            'attack' => 8,
            'defense' => 6,
            'speed' => 5,
            'unspent_skill_points' => 0,
            'version' => 1,
            'last_active_at' => now(),
            'stamina_updated_at' => now(),
            ...$overrides,
        ]);

        $character->setRelation('areaProgress', new Collection());
        $character->setRelation('equipment', new Collection());
        $character->setRelation('learnedSkills', new Collection());

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

        return $assignment;
    }

    private function createItem(array $overrides = []): Item
    {
        return Item::query()->create([
            'slug' => 'item-'.fake()->unique()->numerify('###'),
            'name' => 'Test Item',
            'item_type' => 'material',
            'equipment_slot' => null,
            'description' => 'Temporary test item.',
            'price' => 0,
            'sell_price' => 0,
            'required_level' => 1,
            'stackable' => true,
            'max_stack' => 200,
            'attack_bonus' => 0,
            'defense_bonus' => 0,
            'health_bonus' => 0,
            'stamina_bonus' => 0,
            'speed_bonus' => 0,
            'travel_speed_percent_bonus' => 0,
            'heal_amount' => 0,
            'stamina_restore' => 0,
            ...$overrides,
        ]);
    }
}

class ResolvesEncounterDataHarness
{
    use ResolvesEncounterData;

    public function callNormalizeEncounterRequest(string $requestedEncounter): string
    {
        return $this->normalizeEncounterRequest($requestedEncounter);
    }

    public function callEncounterColumnPrefix(string $encounterType): string
    {
        return $this->encounterColumnPrefix($encounterType);
    }

    public function callResolveMonsterAssignment(LocationArea $area, int $characterLevel): LocationAreaMonster
    {
        return $this->resolveMonsterAssignment($area, $characterLevel);
    }

    public function callResolveHuntingArea(GameCharacter $character, int $locationAreaId): LocationArea
    {
        return $this->resolveHuntingArea($character, $locationAreaId);
    }

    public function callAreaProgress(GameCharacter $character, LocationArea $area): CharacterAreaProgress
    {
        return $this->areaProgress($character, $area);
    }

    public function callResolveEncounterType(LocationArea $area, CharacterAreaProgress $progress, string $requestedEncounter): string
    {
        return $this->resolveEncounterType($area, $progress, $requestedEncounter);
    }

    public function callBossIsReady(LocationArea $area, CharacterAreaProgress $progress): bool
    {
        return $this->bossIsReady($area, $progress);
    }

    public function callRollEncounterValue(LocationArea $area, string $encounterType, string $resource): int
    {
        return $this->rollEncounterValue($area, $encounterType, $resource);
    }

    public function callRollLoot(array $lootTable, int $bonusPercent = 0): array
    {
        return $this->rollLoot($lootTable, $bonusPercent);
    }

    public function callEncounterMessage(
        LocationArea $area,
        string $encounterType,
        string $requestedEncounter,
        int $experienceGain,
        int $yangGain,
        int $damageTaken,
        array $itemNames,
    ): string {
        return $this->encounterMessage(
            $area,
            $encounterType,
            $requestedEncounter,
            $experienceGain,
            $yangGain,
            $damageTaken,
            $itemNames,
        );
    }

    public function callBuildEncounterReplayPayload(
        GameCharacter $character,
        LocationArea $area,
        string $encounterType,
        string $encounterName,
        int $playerStartingHealth,
        int $effectiveAttack,
        int $effectiveDefense,
        int $effectiveSpeed,
        int $damageTaken,
        int $experienceGain,
        int $yangGain,
        int $staminaCost,
        int $cooldownSeconds,
        array $itemNames,
        string $message,
    ): array {
        return $this->buildEncounterReplayPayload(
            $character,
            $area,
            $encounterType,
            $encounterName,
            $playerStartingHealth,
            $effectiveAttack,
            $effectiveDefense,
            $effectiveSpeed,
            $damageTaken,
            $experienceGain,
            $yangGain,
            $staminaCost,
            $cooldownSeconds,
            $itemNames,
            $message,
        );
    }

    public function callBuildReplayPlayerProfile(
        GameCharacter $character,
        int $startingHealth,
        int $effectiveAttack,
        int $effectiveDefense,
        int $effectiveSpeed,
    ): array {
        return $this->buildReplayPlayerProfile(
            $character,
            $startingHealth,
            $effectiveAttack,
            $effectiveDefense,
            $effectiveSpeed,
        );
    }

    public function callDistributeReplayPoints(int $total, int $parts, int $minimumPerPart = 0): array
    {
        return $this->distributeReplayPoints($total, $parts, $minimumPerPart);
    }

    /**
     * Replays do not need real combat skills in these focused read-side tests.
     *
     * @return list<array<string, mixed>>
     */
    private function buildCombatSkills(GameCharacter $character): array
    {
        return [];
    }
}
