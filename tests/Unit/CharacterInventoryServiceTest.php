<?php

namespace Tests\Unit;

use App\Exceptions\GameActionException;
use App\Models\CharacterItem;
use App\Models\CharacterItemBonus;
use App\Models\GameCharacter;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use App\Services\Game\CharacterInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterInventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private CharacterInventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CharacterInventoryService::class);
    }

    public function test_grant_stacks_stackable_items_into_one_inventory_row(): void
    {
        $character = $this->createCharacter();
        $item = $this->createItem([
            'slug' => 'test-potion',
            'name' => 'Test Potion',
            'item_type' => 'consumable',
            'stackable' => true,
            'max_stack' => 200,
        ]);

        $this->service->grant($character, $item, 2);
        $this->service->grant($character, $item, 3);

        $this->assertSame(1, CharacterItem::query()->count());
        $this->assertDatabaseHas('character_items', [
            'game_character_id' => $character->id,
            'item_id' => $item->id,
            'quantity' => 5,
        ]);
    }

    public function test_grant_creates_one_inventory_row_per_non_stackable_copy(): void
    {
        $character = $this->createCharacter();
        $item = $this->createItem([
            'slug' => 'test-sword',
            'name' => 'Test Sword',
            'item_type' => 'equipment',
            'equipment_slot' => 'weapon',
            'stackable' => false,
            'max_stack' => 1,
        ]);

        $this->service->grant($character, $item, 2);

        $this->assertSame(2, CharacterItem::query()->count());
        $this->assertSame(
            [1, 1],
            CharacterItem::query()->pluck('quantity')->all(),
        );
    }

    public function test_grant_places_new_items_into_the_first_free_inventory_slots(): void
    {
        $character = $this->createCharacter();
        $weapon = $this->createItem([
            'slug' => 'test-blade',
            'name' => 'Test Blade',
            'item_type' => 'equipment',
            'equipment_slot' => 'weapon',
            'inventory_height' => 2,
        ]);
        $armor = $this->createItem([
            'slug' => 'test-armor',
            'name' => 'Test Armor',
            'item_type' => 'equipment',
            'equipment_slot' => 'armor',
            'inventory_height' => 2,
        ]);

        $this->service->grant($character, $weapon);
        $this->service->grant($character, $armor);

        $items = CharacterItem::query()->orderBy('id')->get()->values();

        $this->assertSame(0, $items[0]->inventory_column);
        $this->assertSame(0, $items[0]->inventory_row);
        $this->assertSame(1, $items[1]->inventory_column);
        $this->assertSame(0, $items[1]->inventory_row);
    }

    public function test_consume_decrements_stacks_and_deletes_empty_rows(): void
    {
        $character = $this->createCharacter();
        $item = $this->createItem([
            'slug' => 'test-herb',
            'name' => 'Test Herb',
            'item_type' => 'consumable',
            'stackable' => true,
            'max_stack' => 200,
        ]);

        $inventoryItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $item->id,
            'quantity' => 3,
        ]);

        $this->service->consume($inventoryItem, 2);
        $inventoryItem->refresh();

        $this->assertSame(1, $inventoryItem->quantity);

        $this->service->consume($inventoryItem, 1);

        $this->assertDatabaseMissing('character_items', [
            'id' => $inventoryItem->id,
        ]);
    }

    public function test_move_rejects_overlapping_slots(): void
    {
        $character = $this->createCharacter();
        $weapon = $this->createItem([
            'slug' => 'overlap-blade',
            'name' => 'Overlap Blade',
            'item_type' => 'equipment',
            'equipment_slot' => 'weapon',
            'inventory_height' => 2,
        ]);
        $armor = $this->createItem([
            'slug' => 'overlap-armor',
            'name' => 'Overlap Armor',
            'item_type' => 'equipment',
            'equipment_slot' => 'armor',
            'inventory_height' => 2,
        ]);

        $this->service->grant($character, $weapon);
        $this->service->grant($character, $armor);

        $armorRow = CharacterItem::query()->where('item_id', $armor->id)->firstOrFail();

        $this->expectException(GameActionException::class);
        $this->expectExceptionMessage('already occupied');

        $this->service->move($character, $armorRow, 0, 1);
    }

    public function test_move_rejects_positions_outside_the_inventory_grid(): void
    {
        $character = $this->createCharacter();
        $weapon = $this->createItem([
            'slug' => 'tall-blade',
            'name' => 'Tall Blade',
            'item_type' => 'equipment',
            'equipment_slot' => 'weapon',
            'inventory_height' => 2,
        ]);

        $this->service->grant($character, $weapon);

        $weaponRow = CharacterItem::query()->where('item_id', $weapon->id)->firstOrFail();

        $this->expectException(GameActionException::class);
        $this->expectExceptionMessage('outside the bag');

        $this->service->move($character, $weaponRow, 4, 8);
    }

    public function test_ensure_layout_repairs_missing_inventory_coordinates(): void
    {
        $character = $this->createCharacter();
        $item = $this->createItem([
            'slug' => 'repair-blade',
            'name' => 'Repair Blade',
            'item_type' => 'equipment',
            'equipment_slot' => 'weapon',
            'inventory_height' => 2,
        ]);

        $inventoryItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $item->id,
            'quantity' => 1,
        ]);

        $this->service->ensureLayout($character);

        $inventoryItem->refresh();

        $this->assertSame(0, $inventoryItem->inventory_column);
        $this->assertSame(0, $inventoryItem->inventory_row);
    }

    public function test_grant_can_attach_a_fixed_bonus_blueprint_to_one_equipment_copy(): void
    {
        $character = $this->createCharacter();
        $item = $this->createItem([
            'slug' => 'bonus-blade',
            'name' => 'Bonus Blade',
            'item_type' => 'equipment',
            'equipment_slot' => 'weapon',
        ]);

        $this->service->grant($character, $item, 1, [
            'bonus_blueprint' => [
                ['type' => 'strength', 'value' => 3],
                ['type' => 'crit_chance', 'value' => 2],
            ],
        ]);

        $inventoryItem = CharacterItem::query()->with('bonuses')->firstOrFail();

        $this->assertCount(2, $inventoryItem->bonuses);
        $this->assertSame(['+3 STR', '+2% Crit'], $inventoryItem->bonusLines());
        $this->assertDatabaseHas('character_item_bonuses', [
            'character_item_id' => $inventoryItem->id,
            'slot_number' => 1,
            'bonus_type' => 'strength',
            'bonus_value' => 3,
        ]);
        $this->assertDatabaseHas('character_item_bonuses', [
            'character_item_id' => $inventoryItem->id,
            'slot_number' => 2,
            'bonus_type' => 'crit_chance',
            'bonus_value' => 2,
        ]);
    }

    public function test_grant_ignores_bonus_requests_for_non_equippable_items(): void
    {
        $character = $this->createCharacter();
        $item = $this->createItem([
            'slug' => 'plain-potion',
            'name' => 'Plain Potion',
            'item_type' => 'consumable',
            'stackable' => true,
            'max_stack' => 200,
        ]);

        $this->service->grant($character, $item, 1, [
            'bonus_blueprint' => [
                ['type' => 'strength', 'value' => 3],
            ],
            'roll_bonuses' => true,
            'bonus_source' => 'boss',
        ]);

        $this->assertDatabaseCount('character_item_bonuses', 0);
    }

    public function test_grant_caps_bonus_blueprints_and_rejects_bonus_types_that_do_not_fit_the_item_slot(): void
    {
        $character = $this->createCharacter();
        $item = $this->createItem([
            'slug' => 'strict-blade',
            'name' => 'Strict Blade',
            'item_type' => 'equipment',
            'equipment_slot' => 'weapon',
        ]);

        $this->service->grant($character, $item, 1, [
            'bonus_blueprint' => [
                ['type' => 'strength', 'value' => 3],
                ['type' => 'crit_chance', 'value' => 2],
                ['type' => 'monster_damage', 'value' => 5],
                ['type' => 'lumiis_damage', 'value' => 4],
                ['type' => 'max_health', 'value' => 50],
                ['type' => 'yang_rate', 'value' => 9],
            ],
        ]);

        $inventoryItem = CharacterItem::query()->with('bonuses')->firstOrFail();

        $this->assertCount(4, $inventoryItem->bonuses);
        $this->assertSame(['strength', 'crit_chance', 'monster_damage', 'lumiis_damage'], $inventoryItem->bonuses->pluck('bonus_type')->all());
        $this->assertDatabaseMissing('character_item_bonuses', [
            'character_item_id' => $inventoryItem->id,
            'bonus_type' => 'max_health',
        ]);
        $this->assertDatabaseMissing('character_item_bonuses', [
            'character_item_id' => $inventoryItem->id,
            'bonus_type' => 'yang_rate',
        ]);
    }

    public function test_add_random_bonus_fills_the_next_open_slot(): void
    {
        $character = $this->createCharacter();
        $item = $this->createItem([
            'slug' => 'extend-blade',
            'name' => 'Extend Blade',
            'item_type' => 'equipment',
            'equipment_slot' => 'weapon',
        ]);

        $inventoryItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'bonus_slot_limit' => 4,
        ]);
        CharacterItemBonus::query()->create([
            'character_item_id' => $inventoryItem->id,
            'slot_number' => 1,
            'bonus_type' => 'strength',
            'bonus_value' => 2,
        ]);

        $bonus = $this->service->addRandomBonus($inventoryItem, 'lumiis');

        $this->assertSame(2, CharacterItemBonus::query()->where('character_item_id', $inventoryItem->id)->count());
        $this->assertSame(2, $bonus->slot_number);
        $this->assertNotSame('strength', $bonus->bonus_type);
    }

    public function test_reroll_bonuses_preserves_one_locked_line_and_rebuilds_the_rest(): void
    {
        $character = $this->createCharacter();
        $item = $this->createItem([
            'slug' => 'reroll-armor',
            'name' => 'Reroll Armor',
            'item_type' => 'equipment',
            'equipment_slot' => 'armor',
        ]);

        $inventoryItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'bonus_slot_limit' => 5,
        ]);
        $lockedBonus = CharacterItemBonus::query()->create([
            'character_item_id' => $inventoryItem->id,
            'slot_number' => 1,
            'bonus_type' => 'vitality',
            'bonus_value' => 4,
        ]);
        CharacterItemBonus::query()->create([
            'character_item_id' => $inventoryItem->id,
            'slot_number' => 2,
            'bonus_type' => 'intelligence',
            'bonus_value' => 2,
        ]);
        CharacterItemBonus::query()->create([
            'character_item_id' => $inventoryItem->id,
            'slot_number' => 3,
            'bonus_type' => 'max_health',
            'bonus_value' => 24,
        ]);
        CharacterItemBonus::query()->create([
            'character_item_id' => $inventoryItem->id,
            'slot_number' => 4,
            'bonus_type' => 'max_sp',
            'bonus_value' => 8,
        ]);

        $this->service->rerollBonuses($inventoryItem, $lockedBonus->id, 'lumiis');
        $inventoryItem->refresh();
        $inventoryItem->load('bonuses');

        $this->assertSame(4, $inventoryItem->bonuses->count());
        $this->assertDatabaseHas('character_item_bonuses', [
            'character_item_id' => $inventoryItem->id,
            'slot_number' => 1,
            'bonus_type' => 'vitality',
            'bonus_value' => 4,
        ]);
        $this->assertSame(4, $inventoryItem->bonuses->pluck('bonus_type')->unique()->count());
    }

    public function test_granting_a_level_thirty_weapon_rolls_dss_and_fks_bonuses_on_drop(): void
    {
        $character = $this->createCharacter();
        $item = $this->createItem([
            'slug' => 'moon-blade',
            'name' => 'Moon Blade',
            'item_type' => 'equipment',
            'equipment_slot' => 'weapon',
            'required_level' => 30,
        ]);

        $this->service->grant($character, $item, 1, [
            'roll_bonuses' => true,
            'bonus_source' => 'boss',
        ]);

        $inventoryItem = CharacterItem::query()->with('bonuses')->firstOrFail();
        $bonusTypes = $inventoryItem->bonuses->pluck('bonus_type')->all();

        $this->assertContains('average_damage', $bonusTypes);
        $this->assertContains('skill_damage', $bonusTypes);
    }

    private function createCharacter(): GameCharacter
    {
        $user = User::factory()->create();
        $location = Location::query()->create([
            'slug' => 'test-town',
            'name' => 'Test Town',
            'region' => 'Tests',
            'description' => 'Temporary test location.',
            'threat_level' => 1,
            'is_safe_zone' => true,
        ]);

        return GameCharacter::query()->create([
            'user_id' => $user->id,
            'location_id' => $location->id,
            'name' => 'InventoryTester'.fake()->unique()->numerify('###'),
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
            'unspent_status_points' => 0,
            'version' => 1,
            'last_active_at' => now(),
            'stamina_updated_at' => now(),
        ]);
    }

    private function createItem(array $attributes): Item
    {
        return Item::query()->create(array_merge([
            'slug' => 'test-item-'.fake()->unique()->numerify('###'),
            'name' => 'Test Item',
            'item_type' => 'consumable',
            'equipment_slot' => null,
            'description' => 'Temporary test item.',
            'price' => 0,
            'sell_price' => 0,
            'required_level' => 1,
            'stackable' => false,
            'max_stack' => 1,
            'inventory_height' => 1,
            'icon_path' => null,
            'attack_bonus' => 0,
            'defense_bonus' => 0,
            'health_bonus' => 0,
            'stamina_bonus' => 0,
            'speed_bonus' => 0,
            'travel_speed_percent_bonus' => 0,
            'heal_amount' => 0,
            'stamina_restore' => 0,
            'sp_restore' => 0,
        ], $attributes));
    }
}
