<?php

namespace Tests\Unit;

use App\Models\Item;
use App\Models\ItemBonusDefinition;
use App\Support\Game\ItemBonusCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemBonusCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ItemBonusDefinition::flushRuntimeCache();
    }

    public function test_database_definitions_override_default_bonus_behavior(): void
    {
        ItemBonusDefinition::query()->create([
            'bonus_type' => 'strength',
            'label' => 'Might',
            'minimum_value' => 5,
            'maximum_value' => 6,
            'suffix' => '%',
            'allowed_slots' => ['weapon'],
            'enabled' => true,
            'sort_order' => 10,
        ]);

        ItemBonusDefinition::query()->create([
            'bonus_type' => 'max_health',
            'label' => 'HPX',
            'minimum_value' => 9,
            'maximum_value' => 12,
            'suffix' => '',
            'allowed_slots' => ['weapon'],
            'enabled' => true,
            'sort_order' => 20,
        ]);

        ItemBonusDefinition::query()->create([
            'bonus_type' => 'dexterity',
            'label' => 'DEX',
            'minimum_value' => 1,
            'maximum_value' => 4,
            'suffix' => '',
            'allowed_slots' => ['weapon'],
            'enabled' => false,
            'sort_order' => 30,
        ]);

        ItemBonusDefinition::flushRuntimeCache();

        $weapon = Item::query()->create([
            'slug' => 'test-blade',
            'name' => 'Test Blade',
            'item_type' => 'equipment',
            'equipment_slot' => 'weapon',
            'description' => 'Test item.',
            'price' => 0,
            'sell_price' => 0,
            'required_level' => 1,
            'stackable' => false,
            'max_stack' => 1,
            'attack_bonus' => 0,
            'defense_bonus' => 0,
            'health_bonus' => 0,
            'stamina_bonus' => 0,
            'speed_bonus' => 0,
            'travel_speed_percent_bonus' => 0,
            'heal_amount' => 0,
            'stamina_restore' => 0,
            'sp_restore' => 0,
        ]);

        $rollableTypes = ItemBonusCatalog::rollableTypesForItem($weapon);

        $this->assertContains('strength', $rollableTypes);
        $this->assertContains('max_health', $rollableTypes);
        $this->assertNotContains('dexterity', $rollableTypes);
        $this->assertSame('+5% Might', ItemBonusCatalog::displayLine('strength', 5));

        $value = ItemBonusCatalog::rollValue('max_health', 'monster');
        $this->assertGreaterThanOrEqual(9, $value);
        $this->assertLessThanOrEqual(12, $value);
    }

    public function test_intrinsic_item_bonuses_are_formatted_and_available_on_the_item(): void
    {
        $item = Item::query()->create([
            'slug' => 'jade-necklace',
            'name' => 'Jadehalskette',
            'item_type' => 'equipment',
            'equipment_slot' => 'necklace',
            'description' => 'Test item.',
            'price' => 0,
            'sell_price' => 0,
            'required_level' => 28,
            'stackable' => false,
            'max_stack' => 1,
            'attack_bonus' => 0,
            'defense_bonus' => 0,
            'health_bonus' => 0,
            'stamina_bonus' => 0,
            'speed_bonus' => 0,
            'travel_speed_percent_bonus' => 0,
            'heal_amount' => 0,
            'stamina_restore' => 0,
            'sp_restore' => 0,
            'intrinsic_bonuses' => [
                ['type' => 'dexterity', 'label' => 'DEX', 'value' => 1],
                ['label' => 'FW', 'value' => 5, 'suffix' => '%'],
            ],
        ]);

        $this->assertSame(1, $item->intrinsicBonusValue('dexterity'));
        $this->assertSame(['+1 DEX', '+5% FW'], $item->intrinsicBonusLines());
    }
}
