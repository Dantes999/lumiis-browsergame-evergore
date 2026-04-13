<?php

namespace Tests\Feature;

use App\Models\GameCharacter;
use App\Models\Item;
use App\Models\ItemBonusDefinition;
use App\Models\LocationArea;
use App\Models\Location;
use App\Models\LocationConnection;
use App\Models\Monster;
use App\Models\Quest;
use App\Models\Shop;
use App\Models\ShopItem;
use App\Models\SmithRefineRate;
use App\Models\User;
use Database\Seeders\WorldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Enums\GameCharacterState;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldSeeder::class);
    }

    public function test_non_admin_users_cannot_access_the_admin_panel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertForbidden();
    }

    public function test_guest_users_are_redirected_from_the_admin_dashboard(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_guest_users_cannot_update_admin_user_records(): void
    {
        $targetUser = User::factory()->create();

        $response = $this->patch(route('admin.users.update', $targetUser), [
            'name' => 'Guest Edit Attempt',
            'email' => 'guest-edit@example.test',
            'is_admin' => false,
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_users_cannot_update_admin_user_records(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        $response = $this->actingAs($user)->patch(route('admin.users.update', $targetUser), [
            'name' => 'Blocked Edit Attempt',
            'email' => 'blocked-edit@example.test',
            'is_admin' => false,
        ]);

        $response->assertForbidden();
    }

    public function test_admin_users_can_view_the_admin_dashboard(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()->assertSee('Control Panel');
    }

    public function test_last_admin_account_cannot_be_demoted(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)
            ->from(route('admin.users.edit', $admin))
            ->patch(route('admin.users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'is_admin' => false,
            ]);

        $response
            ->assertRedirect(route('admin.users.edit', $admin))
            ->assertSessionHasErrors('is_admin');

        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_admin_can_update_a_character_record(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $location = Location::query()->where('slug', 'yongan')->firstOrFail();
        $otherLocation = Location::query()->where('slug', 'joan')->firstOrFail();

        $character = GameCharacter::query()->create([
            'user_id' => $user->id,
            'location_id' => $location->id,
            'name' => 'AdminTarget',
            'faction' => 'Shinsoo',
            'race' => 'warrior',
            'skill_group' => null,
            'game_state' => GameCharacterState::Active,
            'level' => 1,
            'experience' => 0,
            'yang' => 100,
            'health' => 90,
            'max_health' => 100,
            'stamina' => 10,
            'max_stamina' => 12,
            'attack' => 8,
            'defense' => 6,
            'speed' => 5,
            'unspent_status_points' => 0,
            'version' => 1,
            'last_active_at' => now(),
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.characters.update', $character), [
            'name' => 'EditedTarget',
            'faction' => 'Chunjo',
            'race' => 'warrior',
            'skill_group' => 'body',
            'game_state' => GameCharacterState::Tutorial->value,
            'tutorial_step' => 4,
            'location_id' => $otherLocation->id,
            'level' => 7,
            'experience' => 420,
            'yang' => 999,
            'health' => 120,
            'max_health' => 140,
            'stamina' => 9,
            'max_stamina' => 15,
            'attack' => 22,
            'defense' => 18,
            'speed' => 11,
            'action_available_at' => now()->addMinute()->toDateTimeString(),
            'last_active_at' => now()->toDateTimeString(),
        ]);

        $response->assertRedirect(route('admin.characters.edit', $character));
        $this->assertDatabaseHas('game_characters', [
            'id' => $character->id,
            'name' => 'EditedTarget',
            'faction' => 'Chunjo',
            'game_state' => GameCharacterState::Tutorial->value,
            'tutorial_step' => 4,
            'location_id' => $otherLocation->id,
            'level' => 7,
            'yang' => 999,
            'version' => 1,
        ]);
    }

    public function test_admin_character_editor_hides_the_internal_version_field(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $location = Location::query()->where('slug', 'yongan')->firstOrFail();

        $character = GameCharacter::query()->create([
            'user_id' => $user->id,
            'location_id' => $location->id,
            'name' => 'NoVersionField',
            'faction' => 'Shinsoo',
            'race' => 'warrior',
            'skill_group' => null,
            'game_state' => GameCharacterState::Active,
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
            'unspent_status_points' => 0,
            'version' => 4,
            'last_active_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.characters.edit', $character));

        $response->assertOk();
        $response->assertDontSee('name="version"', false);
        $response->assertDontSee('VERSION');
    }

    public function test_admin_can_update_a_monster_record(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $monster = Monster::query()->where('slug', 'wildhund')->firstOrFail();

        $response = $this->actingAs($admin)->patch(route('admin.monsters.update', $monster), [
            'slug' => 'wildhund',
            'name' => 'Wildhund Elite',
            'level' => 2,
            'rank' => 1,
            'race' => 'Tiere',
            'damage_type' => 'Physisch',
            'sprite' => 'Wildhund.png',
            'maximum_health' => 66,
            'minimum_attack' => 7,
            'maximum_attack' => 11,
            'defense' => 2,
            'experience_reward' => 19,
            'yang_min' => 5,
            'yang_max' => 9,
            'sort_order' => 10,
        ]);

        $response->assertRedirect(route('admin.monsters.edit', $monster));
        $this->assertDatabaseHas('monsters', [
            'id' => $monster->id,
            'name' => 'Wildhund Elite',
            'maximum_health' => 66,
            'experience_reward' => 19,
        ]);
    }

    public function test_admin_can_update_route_level_requirements(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $connection = LocationConnection::query()
            ->whereHas('fromLocation', fn ($query) => $query->where('slug', 'yongan'))
            ->whereHas('toLocation', fn ($query) => $query->where('slug', 'seungryong-valley'))
            ->firstOrFail();

        $response = $this->actingAs($admin)->patch(route('admin.connections.update', $connection), [
            'from_location_id' => $connection->from_location_id,
            'to_location_id' => $connection->to_location_id,
            'travel_seconds' => 60,
            'stamina_cost' => 4,
            'required_level' => 22,
        ]);

        $response->assertRedirect(route('admin.connections.edit', $connection));
        $this->assertDatabaseHas('location_connections', [
            'id' => $connection->id,
            'travel_seconds' => 60,
            'stamina_cost' => 4,
            'required_level' => 22,
        ]);
    }

    public function test_admin_can_update_area_balancing_and_spawn_configuration(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $area = LocationArea::query()->where('slug', 'riverbank')->firstOrFail();

        $response = $this->actingAs($admin)->patch(route('admin.areas.update', $area), [
            'slug' => $area->slug,
            'name' => $area->name,
            'description' => $area->description,
            'area_kind' => $area->area_kind,
            'service_type' => $area->service_type,
            'hotspot_x' => $area->hotspot_x,
            'hotspot_y' => $area->hotspot_y,
            'sort_order' => $area->sort_order,
            'recommended_level' => 2,
            'monster_name' => 'Riverbank Packs',
            'lumiis_name' => 'Riverbank Lumiis',
            'boss_name' => 'Riverbank Alpha',
            'monster_cooldown_seconds' => 33,
            'lumiis_cooldown_seconds' => 88,
            'boss_cooldown_seconds' => 140,
            'monster_exp_min' => 20,
            'monster_exp_max' => 28,
            'monster_yang_min' => 11,
            'monster_yang_max' => 29,
            'monster_damage_min' => 5,
            'monster_damage_max' => 9,
            'lumiis_exp_min' => 31,
            'lumiis_exp_max' => 44,
            'lumiis_yang_min' => 18,
            'lumiis_yang_max' => 36,
            'lumiis_damage_min' => 9,
            'lumiis_damage_max' => 15,
            'boss_exp_min' => 60,
            'boss_exp_max' => 80,
            'boss_yang_min' => 44,
            'boss_yang_max' => 75,
            'boss_damage_min' => 11,
            'boss_damage_max' => 18,
            'monster_pressure_gain' => 8,
            'lumiis_pressure_gain' => 18,
            'boss_pressure_threshold' => 120,
            'boss_base_chance' => 250,
            'boss_pressure_chance_per_point' => 50,
            'monster_loot_rows' => [
                ['item_slug' => 'wolf-pelt', 'chance' => 7000, 'quantity_min' => 1, 'quantity_max' => 2],
            ],
            'lumiis_loot_rows' => [
                ['item_slug' => 'lumiis-shard', 'chance' => 8000, 'quantity_min' => 1, 'quantity_max' => 3],
            ],
            'boss_loot_rows' => [
                ['item_slug' => 'scarlet-saber', 'chance' => 1000, 'quantity_min' => 1, 'quantity_max' => 1],
            ],
            'monster_assignment_rows' => [
                [
                    'monster_slug' => 'wildhund',
                    'weight' => 90,
                    'minimum_character_level' => 1,
                    'maximum_character_level' => 4,
                    'pack_size' => 3,
                    'sort_order' => 10,
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.areas.edit', $area));
        $this->assertDatabaseHas('location_areas', [
            'id' => $area->id,
            'recommended_level' => 2,
            'monster_name' => 'Riverbank Packs',
            'lumiis_cooldown_seconds' => 88,
            'boss_pressure_threshold' => 120,
        ]);
        $this->assertDatabaseHas('location_area_monsters', [
            'location_area_id' => $area->id,
            'weight' => 90,
            'pack_size' => 3,
        ]);
        $area->refresh();
        $this->assertSame([[
            'slug' => 'wolf-pelt',
            'chance' => 7000,
            'min' => 1,
            'max' => 2,
        ]], $area->monster_loot_table);
        $this->assertSame([[
            'slug' => 'lumiis-shard',
            'chance' => 8000,
            'min' => 1,
            'max' => 3,
        ]], $area->lumiis_loot_table);
    }

    public function test_admin_area_editor_renders_row_based_loot_inputs(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $area = LocationArea::query()->where('slug', 'riverbank')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.areas.edit', $area));

        $response->assertOk();
        $response->assertSee('Monster Loot Table');
        $response->assertSee('Lumiis Loot Table');
        $response->assertSee('Boss Loot Table');
        $response->assertSee(':name="`monster_loot_rows[${index}][item_slug]`"', false);
        $response->assertSee(':name="`lumiis_loot_rows[${index}][item_slug]`"', false);
        $response->assertSee(':name="`boss_loot_rows[${index}][item_slug]`"', false);
        $response->assertSee('Monster Spawn Pool');
        $response->assertSee(':name="`monster_assignment_rows[${index}][monster_slug]`"', false);
        $response->assertSee('Add Row');
        $response->assertSee('Duplicate');
        $response->assertSee('Drag To Sort');
        $response->assertDontSee('Monster Loot Table JSON');
        $response->assertDontSee('Lumiis Loot Table JSON');
        $response->assertDontSee('Boss Loot Table JSON');
        $response->assertDontSee('Monster Assignment JSON');
    }

    public function test_admin_can_update_shop_items_and_prices(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $shop = Shop::query()->where('slug', 'yongan-general-store')->firstOrFail();

        $response = $this->actingAs($admin)->patch(route('admin.shops.update', $shop), [
            'slug' => $shop->slug,
            'name' => 'Yongan Supply Hall',
            'description' => 'Edited vendor stock.',
            'location_id' => $shop->location_id,
            'location_area_id' => $shop->location_area_id,
            'shop_item_rows' => [
                ['item_slug' => 'small-red-potion', 'price' => 35, 'stock' => 5, 'sort_order' => 10],
                ['item_slug' => 'small-blue-potion', 'price' => 30, 'stock' => null, 'sort_order' => 20],
            ],
        ]);

        $response->assertRedirect(route('admin.shops.edit', $shop));
        $this->assertDatabaseHas('shops', [
            'id' => $shop->id,
            'name' => 'Yongan Supply Hall',
        ]);
        $this->assertDatabaseHas('shop_items', [
            'shop_id' => $shop->id,
            'price' => 35,
            'stock' => 5,
        ]);
    }

    public function test_admin_shop_editor_renders_row_based_item_inputs(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $shop = Shop::query()->where('slug', 'yongan-general-store')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.shops.edit', $shop));

        $response->assertOk();
        $response->assertSee('Shop Inventory');
        $response->assertSee(':name="`shop_item_rows[${index}][item_slug]`"', false);
        $response->assertSee('Add Row');
        $response->assertSee('Duplicate');
        $response->assertSee('Drag To Sort');
        $response->assertDontSee('Shop Items JSON');
    }

    public function test_admin_can_update_a_quest_record(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $quest = Quest::query()->where('slug', 'yongan-first-sweep')->firstOrFail();

        $response = $this->actingAs($admin)->patch(route('admin.quests.update', $quest), [
            'location_id' => $quest->location_id,
            'slug' => $quest->slug,
            'title' => 'First Sweep Revised',
            'story_text' => 'Edited story.',
            'description' => 'Edited description.',
            'feature_unlock_hint' => 'Edited unlock hint.',
            'required_level' => 2,
            'prerequisite_slug' => null,
            'objective_type' => 'monster_fights_in_area',
            'objective_target' => 3,
            'objective_area_slug' => 'riverbank',
            'reward_experience' => 44,
            'reward_yang' => 55,
            'reward_item_slug' => 'small-red-potion',
            'reward_item_quantity' => 2,
            'sort_order' => 15,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.quests.edit', $quest));
        $this->assertDatabaseHas('quests', [
            'id' => $quest->id,
            'title' => 'First Sweep Revised',
            'objective_target' => 3,
            'reward_experience' => 44,
            'reward_yang' => 55,
            'reward_item_slug' => 'small-red-potion',
            'reward_item_quantity' => 2,
        ]);
    }

    public function test_admin_can_update_biologist_quest_fields(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $quest = Quest::query()->where('slug', 'yongan-biologist-research')->firstOrFail();

        $response = $this->actingAs($admin)->patch(route('admin.quests.update', $quest), [
            'location_id' => $quest->location_id,
            'slug' => $quest->slug,
            'title' => 'Biologist Research Revised',
            'story_text' => 'Edited research story.',
            'description' => 'Edited research description.',
            'feature_unlock_hint' => 'Permanent stats start here.',
            'required_level' => 5,
            'prerequisite_slug' => 'yongan-alpha-release',
            'objective_type' => 'deliver_item_to_biologist',
            'objective_target' => 4,
            'objective_area_slug' => null,
            'objective_item_slug' => 'wolf-pelt',
            'objective_item_quantity' => 2,
            'objective_submission_cooldown_minutes' => 15,
            'objective_submission_success_rate' => 75,
            'reward_experience' => 95,
            'reward_yang' => 80,
            'reward_item_slug' => null,
            'reward_item_quantity' => 1,
            'reward_bonus_type' => 'vitality',
            'reward_bonus_value' => 2,
            'sort_order' => 55,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.quests.edit', $quest));
        $this->assertDatabaseHas('quests', [
            'id' => $quest->id,
            'title' => 'Biologist Research Revised',
            'objective_type' => 'deliver_item_to_biologist',
            'objective_item_slug' => 'wolf-pelt',
            'objective_item_quantity' => 2,
            'objective_submission_cooldown_minutes' => 15,
            'objective_submission_success_rate' => 75,
            'reward_bonus_type' => 'vitality',
            'reward_bonus_value' => 2,
        ]);
    }

    public function test_admin_can_update_refine_rates(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $rates = SmithRefineRate::query()->orderBy('upgrade_level')->get();

        $payload = [
            'rates' => $rates->map(fn (SmithRefineRate $rate) => [
                'id' => $rate->id,
                'upgrade_level' => $rate->upgrade_level,
                'success_percent' => $rate->upgrade_level === 1 ? 91 : $rate->success_percent,
                'yang_cost' => $rate->upgrade_level === 1 ? 55 : $rate->yang_cost,
                'lumiis_shard_cost' => $rate->upgrade_level === 1 ? 2 : $rate->lumiis_shard_cost,
                'destroy_on_fail' => $rate->destroy_on_fail,
                'sort_order' => $rate->sort_order,
            ])->all(),
        ];

        $response = $this->actingAs($admin)->patch(route('admin.refine-rates.update'), $payload);

        $response->assertRedirect(route('admin.refine-rates.index'));
        $this->assertDatabaseHas('smith_refine_rates', [
            'upgrade_level' => 1,
            'success_percent' => 91,
            'yang_cost' => 55,
            'lumiis_shard_cost' => 2,
        ]);
    }

    public function test_admin_refine_rates_index_renders_input_values_correctly(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get(route('admin.refine-rates.index'));

        $response->assertOk();

        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());

        $xpath = new \DOMXPath($document);
        $successInput = $xpath->query('//input[@name="rates[0][success_percent]"]');
        $yangInput = $xpath->query('//input[@name="rates[0][yang_cost]"]');
        $shardInput = $xpath->query('//input[@name="rates[0][lumiis_shard_cost]"]');
        $sortInput = $xpath->query('//input[@name="rates[0][sort_order]"]');

        $this->assertSame(1, $successInput?->length);
        $this->assertSame(1, $yangInput?->length);
        $this->assertSame(1, $shardInput?->length);
        $this->assertSame(1, $sortInput?->length);
        $this->assertSame('100', $successInput?->item(0)?->getAttribute('value'));
        $this->assertSame('25', $yangInput?->item(0)?->getAttribute('value'));
        $this->assertSame('0', $shardInput?->item(0)?->getAttribute('value'));
        $this->assertSame('10', $sortInput?->item(0)?->getAttribute('value'));
        $response->assertDontSee('success_percent&quot; /&gt;', false);
        $response->assertDontSee('yang_cost&quot; /&gt;', false);
        $response->assertDontSee('lumiis_shard_cost&quot; /&gt;', false);
        $response->assertDontSee('sort_order&quot; /&gt;', false);
    }

    public function test_admin_can_update_bonus_balance_settings(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $definitions = ItemBonusDefinition::query()->orderBy('sort_order')->orderBy('bonus_type')->get();
        $soulItems = Item::query()
            ->whereIn('slug', ['soul-imprint', 'soul-reweaver', 'soul-anchor', 'soul-prism'])
            ->orderBy('slug')
            ->get();
        $areas = LocationArea::query()
            ->where('area_kind', 'hunting')
            ->orderBy('location_id')
            ->orderBy('sort_order')
            ->get();

        $payload = [
            'bonus_definitions' => $definitions->map(function (ItemBonusDefinition $definition): array {
                return [
                    'id' => $definition->id,
                    'bonus_type' => $definition->bonus_type,
                    'label' => $definition->bonus_type === 'vitality' ? 'Vitality+' : $definition->label,
                    'minimum_value' => $definition->bonus_type === 'vitality' ? 2 : $definition->minimum_value,
                    'maximum_value' => $definition->bonus_type === 'vitality' ? 5 : $definition->maximum_value,
                    'suffix' => $definition->suffix,
                    'allowed_slots' => $definition->bonus_type === 'vitality'
                        ? ['armor', 'offhand', 'boots']
                        : ($definition->allowed_slots ?? []),
                    'enabled' => $definition->bonus_type === 'vitality' ? 0 : $definition->enabled,
                    'sort_order' => $definition->sort_order,
                ];
            })->all(),
            'soul_items' => $soulItems->map(function (Item $item): array {
                return [
                    'id' => $item->id,
                    'name' => $item->slug === 'soul-imprint' ? 'Soul Imprint+' : $item->name,
                    'description' => $item->slug === 'soul-imprint' ? 'Edited imprint text.' : $item->description,
                    'sell_price' => $item->slug === 'soul-imprint' ? 88 : $item->sell_price,
                    'required_level' => $item->required_level,
                ];
            })->all(),
            'area_drops' => $areas->map(function (LocationArea $area): array {
                return [
                    'area_id' => $area->id,
                    'lumiis_soul_imprint_chance' => $area->slug === 'riverbank'
                        ? 444
                        : $this->lootChance($area->lumiis_loot_table ?? [], 'soul-imprint'),
                    'lumiis_soul_reweaver_chance' => $area->slug === 'riverbank'
                        ? 0
                        : $this->lootChance($area->lumiis_loot_table ?? [], 'soul-reweaver'),
                    'boss_soul_imprint_chance' => $this->lootChance($area->boss_loot_table ?? [], 'soul-imprint'),
                    'boss_soul_reweaver_chance' => $this->lootChance($area->boss_loot_table ?? [], 'soul-reweaver'),
                    'boss_soul_anchor_chance' => $this->lootChance($area->boss_loot_table ?? [], 'soul-anchor'),
                    'boss_soul_stone_chance' => $area->slug === 'riverbank'
                        ? 888
                        : $this->lootChance($area->boss_loot_table ?? [], 'soul-stone'),
                    'boss_soul_prism_chance' => $area->slug === 'riverbank'
                        ? 777
                        : $this->lootChance($area->boss_loot_table ?? [], 'soul-prism'),
                ];
            })->all(),
        ];

        $response = $this->actingAs($admin)->patch(route('admin.bonus-balance.update'), $payload);

        $response->assertRedirect(route('admin.bonus-balance.index'));
        $this->assertDatabaseHas('item_bonus_definitions', [
            'bonus_type' => 'vitality',
            'label' => 'Vitality+',
            'minimum_value' => 2,
            'maximum_value' => 5,
            'enabled' => 0,
        ]);
        $this->assertDatabaseHas('items', [
            'slug' => 'soul-imprint',
            'name' => 'Soul Imprint+',
            'sell_price' => 88,
        ]);

        $riverbank = LocationArea::query()->where('slug', 'riverbank')->firstOrFail();
        $this->assertSame(444, $this->lootChance($riverbank->lumiis_loot_table ?? [], 'soul-imprint'));
        $this->assertSame(0, $this->lootChance($riverbank->lumiis_loot_table ?? [], 'soul-reweaver'));
        $this->assertSame(888, $this->lootChance($riverbank->boss_loot_table ?? [], 'soul-stone'));
        $this->assertSame(777, $this->lootChance($riverbank->boss_loot_table ?? [], 'soul-prism'));
    }

    public function test_admin_bonus_balance_index_renders_current_values(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get(route('admin.bonus-balance.index'));

        $response->assertOk();

        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());

        $xpath = new \DOMXPath($document);
        $labelInput = $xpath->query('//input[@name="bonus_definitions[0][label]"]');
        $sellInput = $xpath->query('//input[@name="soul_items[0][sell_price]"]');
        $stoneChanceInput = $xpath->query('//input[@name="area_drops[0][boss_soul_stone_chance]"]');
        $prismChanceInput = $xpath->query('//input[@name="area_drops[0][boss_soul_prism_chance]"]');

        $this->assertSame(1, $labelInput?->length);
        $this->assertSame(1, $sellInput?->length);
        $this->assertSame(1, $stoneChanceInput?->length);
        $this->assertSame(1, $prismChanceInput?->length);
        $this->assertSame('VIT', $labelInput?->item(0)?->getAttribute('value'));
        $this->assertSame('90', $sellInput?->item(0)?->getAttribute('value'));
        $this->assertSame('0', $stoneChanceInput?->item(0)?->getAttribute('value'));
        $this->assertSame('300', $prismChanceInput?->item(0)?->getAttribute('value'));
        $response->assertSee('bonus_definitions[0][allowed_slots][]', false);
        $response->assertDontSee('allowed_slots_csv');
    }

    /**
     * @param  array<int, array<string, mixed>>  $lootTable
     */
    private function lootChance(array $lootTable, string $itemSlug): int
    {
        foreach ($lootTable as $entry) {
            if (($entry['item_slug'] ?? $entry['slug'] ?? null) === $itemSlug) {
                return (int) ($entry['chance'] ?? 0);
            }
        }

        return 0;
    }
}
