<?php

namespace Tests\Feature;

use App\Enums\CharacterQuestStatus;
use App\Enums\GameCharacterState;
use App\Models\CharacterAreaProgress;
use App\Models\CharacterEquipment;
use App\Models\CharacterItem;
use App\Models\CharacterItemBonus;
use App\Models\CharacterQuest;
use App\Models\CharacterSkill;
use App\Models\GameActionLog;
use App\Models\GameBattle;
use App\Models\GameCharacter;
use App\Models\GameMessage;
use App\Models\Item;
use App\Models\Location;
use App\Models\LocationArea;
use App\Models\Quest;
use App\Models\ShopItem;
use App\Models\Skill;
use App\Models\SmithRefineRate;
use App\Models\User;
use App\Services\Game\CharacterInventoryService;
use Database\Seeders\WorldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GameActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldSeeder::class);
    }

    public function test_authenticated_user_can_view_the_game_dashboard(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 5,
            'yang' => 987,
            'health' => 140,
            'max_health' => 140,
            'stamina' => 7,
            'attack' => 18,
            'defense' => 12,
            'speed' => 7,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee($character->name)
            ->assertSee($character->location->name)
            ->assertSee(__('Yang'))
            ->assertSee(__('Stamina'))
            ->assertSee(__('HP'))
            ->assertSee('987')
            ->assertSee('7/12')
            ->assertSee('140/140')
            ->assertDontSee(__('Back To Map'))
            ->assertDontSee('Drill');
    }

    public function test_dashboard_renders_even_if_the_authenticated_user_holds_a_partially_loaded_character_relation(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 5,
        ]);

        $user->setRelation(
            'character',
            GameCharacter::query()
                ->select([
                    'id',
                    'user_id',
                    'location_id',
                    'name',
                    'faction',
                    'game_state',
                    'tutorial_step',
                    'level',
                    'experience',
                    'yang',
                    'health',
                    'max_health',
                    'stamina',
                    'max_stamina',
                    'attack',
                    'defense',
                    'speed',
                    'unspent_status_points',
                    'action_available_at',
                    'last_active_at',
                ])
                ->findOrFail($character->id),
        );

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee($character->name)
            ->assertSee($character->location->name);
    }

    public function test_dashboard_shows_current_map_hotspots_and_active_hunting_area(): void
    {
        [$user] = $this->createCharacterAt('yongan', [
            'level' => 2,
        ]);

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $response = $this->actingAs($user)->get(route('dashboard', [
            'focus' => 'riverbank',
        ]));

        $response
            ->assertOk()
            ->assertSee(__('Current Map'))
            ->assertSee('Lantern Inn')
            ->assertSee('General Store')
            ->assertSee('Riverbank')
            ->assertSee(__('Fight Monsters'))
            ->assertSee(__('Search Lumiis'))
            ->assertSee((string) $area->lumiis_cooldown_seconds);
    }

    public function test_dashboard_previews_passive_health_and_sp_recovery(): void
    {
        $this->freezeTime();

        [$user] = $this->createCharacterAt('yongan', [
            'health' => 40,
            'sp' => 3,
            'last_active_at' => now()->subMinutes(30),
            'health_updated_at' => now()->subMinutes(30),
            'sp_updated_at' => now()->subMinutes(30),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('70/100')
            ->assertSee('24/24');
    }

    public function test_dashboard_shows_action_cooldown_guidance_in_hunting_focus(): void
    {
        $this->freezeTime();

        [$user] = $this->createCharacterAt('yongan', [
            'action_available_at' => now()->addSeconds(95),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'focus' => 'riverbank',
        ]));

        $response
            ->assertOk()
            ->assertSee(__('Action cooldown'))
            ->assertSee(__('Hunting is on cooldown.'))
            ->assertSee(__('Time left'))
            ->assertSee(__('Ready at'))
            ->assertSee('formattedActionLockCountdown()', false)
            ->assertSee('actionLockReadyAtLabel()', false);
    }

    public function test_dashboard_can_render_an_active_manual_battle_overlay_without_lazy_loading(): void
    {
        [$user] = $this->createCharacterAt('yongan');

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'monster',
            'redirect_focus' => $area->slug,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'focus' => $area->slug,
        ]));

        $response
            ->assertOk()
            ->assertSee(__('Fight Window'))
            ->assertSee($area->name);
    }

    public function test_active_manual_battle_overlay_shows_pause_and_abandon_guidance(): void
    {
        [$user] = $this->createCharacterAt('yongan');

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'monster',
            'redirect_focus' => $area->slug,
        ]);

        $response = $this
            ->withSession(['status' => __('Manual battle connection stable.')])
            ->actingAs($user)
            ->get(route('dashboard', [
                'focus' => $area->slug,
            ]));

        $response
            ->assertOk()
            ->assertSee(__('Abandon Fight'))
            ->assertSee(__('Manual battle connection stable.'))
            ->assertSee(__('Closing the tab pauses this fight. Use Abandon Fight to end it with no rewards.'));
    }

    public function test_dashboard_reload_does_not_advance_or_duplicate_an_ongoing_manual_battle(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan');

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'monster',
            'redirect_focus' => $area->slug,
        ]);

        $battle = GameBattle::query()
            ->where('game_character_id', $character->id)
            ->where('status', 'ongoing')
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($user)->get(route('dashboard', [
            'focus' => $area->slug,
        ]))->assertOk();

        $this->actingAs($user)->get(route('dashboard', [
            'focus' => $area->slug,
        ]))->assertOk();

        $character->refresh();
        $battle->refresh();

        $this->assertSame('ongoing', $battle->status);
        $this->assertSame(1, (int) $battle->round);
        $this->assertSame(7, (int) $character->stamina);
        $this->assertSame(1, GameBattle::query()
            ->where('game_character_id', $character->id)
            ->where('status', 'ongoing')
            ->count());
    }

    public function test_dashboard_skill_panel_shows_the_soul_stone_action_for_m10_skills(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 14,
            'race' => 'warrior',
            'skill_group' => 'body',
        ]);

        $skill = Skill::query()->where('slug', 'aura-of-the-sword')->firstOrFail();

        CharacterSkill::query()->create([
            'game_character_id' => $character->id,
            'skill_id' => $skill->id,
            'rank' => 5,
            'mastery_rank' => 10,
        ]);

        app(CharacterInventoryService::class)->grant($character, \App\Support\Game\MetinClassCatalog::soulStoneSlug(), 2);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee(__('Use Soul Stone'))
            ->assertSee(__('Soul Stone Success'));
    }

    public function test_active_manual_battle_overlay_shows_recent_damage_popups(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan');

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'monster',
            'redirect_focus' => $area->slug,
        ]);

        $battle = GameBattle::query()
            ->where('game_character_id', $character->id)
            ->latest('id')
            ->firstOrFail();

        $enemyId = data_get($battle->state, 'enemies.0.id');

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'battle-attack']), [
            'idempotency_key' => (string) Str::uuid(),
            'battle_id' => $battle->id,
            'battle_round' => $battle->round,
            'enemy_id' => $enemyId,
            'redirect_focus' => $area->slug,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'focus' => $area->slug,
        ]));

        $response->assertOk();

        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $enemyPopups = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " battle-damage-pop-enemy ")]');
        $playerPopups = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " battle-damage-pop-player ")]');

        $this->assertGreaterThanOrEqual(1, $enemyPopups?->length ?? 0);
        $this->assertGreaterThanOrEqual(1, $playerPopups?->length ?? 0);
    }

    public function test_active_manual_battle_overlay_formats_log_lines_with_semantic_highlights(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'name' => 'ColorHero',
        ]);

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'monster',
            'redirect_focus' => $area->slug,
        ]);

        $battle = GameBattle::query()
            ->where('game_character_id', $character->id)
            ->latest('id')
            ->firstOrFail();

        $enemyId = data_get($battle->state, 'enemies.0.id');

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'battle-attack']), [
            'idempotency_key' => (string) Str::uuid(),
            'battle_id' => $battle->id,
            'battle_round' => $battle->round,
            'enemy_id' => $enemyId,
            'redirect_focus' => $area->slug,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'focus' => $area->slug,
        ]));

        $response
            ->assertOk()
            ->assertSee('battle-log-name-player', false)
            ->assertSee('battle-log-name-enemy', false)
            ->assertSee('battle-log-damage-dealt', false)
            ->assertSee('battle-log-damage-taken', false);
    }

    public function test_dashboard_can_render_a_focused_vendor_hotspot_with_inventory(): void
    {
        [$user] = $this->createCharacterAt('yongan', [
            'yang' => 123,
        ]);

        $shopItem = ShopItem::query()
            ->whereHas('shop.location', fn ($query) => $query->where('slug', 'yongan'))
            ->whereHas('item', fn ($query) => $query->where('slug', 'small-red-potion'))
            ->firstOrFail();

        $response = $this->actingAs($user)->get(route('dashboard', [
            'focus' => 'general-store',
        ]));

        $response
            ->assertOk()
            ->assertSee('Yongan General Store')
            ->assertSee('Small Red Potion')
            ->assertSee(__('Wallet').': 123 '.__('Yang'))
            ->assertSee(__('Buy'))
            ->assertSee('shop-buy-quantity-'. $shopItem->id, false)
                ->assertSee('quantity-stepper-button', false)
                ->assertSee('quantity-stepper-max', false)
            ->assertSee(__('Wallet Max:'), false)
            ->assertSee('+35 HP')
            ->assertSee('+6 SP');
    }

    public function test_dashboard_inventory_groups_items_into_subinventories_and_shows_multi_use_controls(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'health' => 20,
            'max_health' => 100,
        ]);

        $inventory = app(CharacterInventoryService::class);
        $potion = Item::query()->where('slug', 'small-red-potion')->firstOrFail();
        $spPotion = Item::query()->where('slug', 'small-blue-potion')->firstOrFail();
        $skillBook = Item::query()->where('slug', \App\Support\Game\MetinClassCatalog::skillBookSlug('aura-of-the-sword'))->firstOrFail();
        $upgradeItem = Item::query()->where('slug', 'soul-prism')->firstOrFail();
        $smithItem = Item::query()->where('slug', 'lumiis-shard')->firstOrFail();
        $questItem = Item::query()->where('slug', 'research-catalyst')->firstOrFail();
        $weapon = Item::query()->where('slug', 'recruit-blade')->firstOrFail();
        $material = Item::query()->where('slug', 'wolf-pelt')->firstOrFail();

        $inventory->grant($character, $potion, 4);
        $inventory->grant($character, $spPotion, 3);
        $inventory->grant($character, $skillBook, 2);
        $inventory->grant($character, $upgradeItem, 1);
        $inventory->grant($character, $smithItem, 2);
        $inventory->grant($character, $questItem, 2);
        $inventory->grant($character, $weapon, 1);
        $inventory->grant($character, $material, 2);

        $potionStack = CharacterItem::query()
            ->where('game_character_id', $character->id)
            ->where('item_id', $potion->id)
            ->firstOrFail();

        $response = $this->actingAs($user)->get(route('dashboard', [
            'focus' => 'general-store',
        ]));

        $response
            ->assertOk()
            ->assertSee(__('Bag Page'))
            ->assertSeeInOrder([
                __('HP Potions'),
                __('SP Potions'),
                __('Books'),
                __('Soul Upgrade'),
                __('Smith Upgrade'),
                __('Equipment'),
                __('Quest'),
                __('Materials'),
            ])
            ->assertSee('Bag I')
            ->assertSee('inventory-list-use-quantity-'.$potionStack->id, false)
            ->assertSee('quantity-stepper-button', false)
            ->assertSee('quantity-stepper-max', false)
            ->assertSee(__('Fill'))
            ->assertSee('inventory-list-sell-quantity-'.$potionStack->id, false);
    }

    public function test_weapon_and_armor_dealers_show_the_midgame_alpha_progression_items(): void
    {
        [$user] = $this->createCharacterAt('yongan', [
            'level' => 22,
        ]);

        $weaponDealer = $this->actingAs($user)->get(route('dashboard', [
            'focus' => 'weapon-dealer',
        ]));

        $weaponDealer
            ->assertOk()
            ->assertSee('Frontier Blade')
            ->assertSee('Valley Blade');

        $armorDealer = $this->actingAs($user)->get(route('dashboard', [
            'focus' => 'armor-dealer',
        ]));

        $armorDealer
            ->assertOk()
            ->assertSee('Guard Jacket')
            ->assertSee('Iron Buckler')
            ->assertSee('Orc Mail')
            ->assertSee('Scout Boots')
            ->assertSee('Orc Guard Shield');
    }

    public function test_dashboard_can_render_a_focused_biologist_hotspot_with_active_research(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 5,
        ]);
        $questSlugs = [
            'yongan-first-sweep' => 2,
            'yongan-first-lumiis' => 1,
            'yongan-field-rank' => 3,
            'yongan-alpha-release' => 1,
        ];

        foreach ($questSlugs as $questSlug => $progress) {
            $quest = Quest::query()->where('slug', $questSlug)->firstOrFail();

            CharacterQuest::query()->create([
                'game_character_id' => $character->id,
                'quest_id' => $quest->id,
                'status' => CharacterQuestStatus::Completed,
                'progress' => $progress,
                'started_at' => now()->subHour(),
                'completed_at' => now()->subMinutes(10),
            ]);
        }

        app(CharacterInventoryService::class)->grant($character, 'wolf-pelt', 2);

        $response = $this
            ->withSession(['status' => __('Biologist message delivered.')])
            ->actingAs($user)
            ->get(route('dashboard', [
                'focus' => 'biologist-study',
            ]));

        $response
            ->assertOk()
            ->assertSee(__('Biologist message delivered.'))
            ->assertSee(__('Research Board'))
            ->assertSee(trans('quests.yongan-biologist-research.title'))
            ->assertSee(__('Submit :count x :item', [
                'count' => 1,
                'item' => 'Wolf Pelt',
            ]))
            ->assertSee('Wolf Pelt')
            ->assertSee(__('Stage :current / :total', ['current' => 1, 'total' => 5]))
            ->assertSee(__('Ready to submit now.'));

        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $submitButton = $xpath->query('//form[contains(@action, "submit-quest-item")]//button[not(@disabled)]');

        $this->assertSame(1, $submitButton?->length);
    }

    public function test_dashboard_disables_hunting_actions_and_shows_missing_stamina(): void
    {
        [$user] = $this->createCharacterAt('yongan', [
            'level' => 2,
            'stamina' => 2,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'focus' => 'riverbank',
        ]));

        $response->assertOk();
        $response->assertSee('Missing 2 stamina.');
        $response->assertSee('Missing 4 stamina.');

        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $autoFightButton = $xpath->query('//form[contains(@action, "fight-monsters")]//button[@disabled]');
        $manualFightButton = $xpath->query('//form[contains(@action, "start-manual-fight")]//input[@name="encounter_request"][@value="monster"]/following-sibling::button[@disabled]');
        $autoLumiisButton = $xpath->query('//form[contains(@action, "search-lumiis")]//button[@disabled]');

        $this->assertSame(1, $autoFightButton?->length);
        $this->assertGreaterThan(0, $manualFightButton?->length ?? 0);
        $this->assertSame(1, $autoLumiisButton?->length);
    }

    public function test_dashboard_hides_travel_buttons_when_fast_travel_is_disabled(): void
    {
        [$user] = $this->createCharacterAt('yongan', [
            'level' => 20,
            'stamina' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'focus' => 'south-gate',
        ]));

        $response
            ->assertOk()
            ->assertDontSee('Travel Routes')
            ->assertDontSee('Map Connections')
            ->assertDontSee('Missing 2 stamina.');

        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $travelButtons = $xpath->query('//form[contains(@action, "travel")]');

        $this->assertSame(0, $travelButtons?->length ?? 0);
    }

    public function test_dashboard_hides_travel_requirements_when_fast_travel_is_disabled(): void
    {
        [$user] = $this->createCharacterAt('yongan', [
            'level' => 4,
            'stamina' => 12,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'focus' => 'south-gate',
        ]));

        $response
            ->assertOk()
            ->assertDontSee('Req. Level 20')
            ->assertDontSee('Requires level 20.')
            ->assertDontSee('Travel Routes')
            ->assertDontSee('Map Connections');

        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $travelButtons = $xpath->query('//form[contains(@action, "travel")]');

        $this->assertSame(0, $travelButtons?->length ?? 0);
    }

    public function test_dashboard_disables_high_level_hunting_actions_until_the_area_band_is_reached(): void
    {
        [$user] = $this->createCharacterAt('yongan', [
            'level' => 3,
            'stamina' => 12,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'focus' => 'pine-ridge',
        ]));

        $response
            ->assertOk()
            ->assertSee('Recommended from level 12.')
            ->assertSee('Req. Level 12');

        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $disabledFightButtons = $xpath->query('//form[contains(@action, "fight-monsters")]//button[@disabled]');
        $disabledLumiisButtons = $xpath->query('//form[contains(@action, "search-lumiis")]//button[@disabled]');

        $this->assertSame(1, $disabledFightButtons?->length);
        $this->assertSame(1, $disabledLumiisButtons?->length);
    }

    public function test_dashboard_can_render_the_smith_hotspot_with_upgrade_candidates(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'yang' => 100,
            'level' => 5,
        ]);

        $weapon = Item::query()->where('slug', 'recruit-blade')->firstOrFail();
        CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $weapon->id,
            'quantity' => 1,
            'upgrade_level' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'focus' => 'town-smith',
        ]));

        $response
            ->assertOk()
            ->assertSee(__('Weapon Upgrades +0 to +9'))
            ->assertSee('Recruit Blade +0')
            ->assertSee(__('Upgrade'));
    }

    public function test_move_inventory_item_action_updates_bag_coordinates(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan');

        $weapon = Item::query()->where('slug', 'recruit-blade')->firstOrFail();
        app(CharacterInventoryService::class)->grant($character, $weapon);

        $inventoryItem = CharacterItem::query()
            ->where('game_character_id', $character->id)
            ->where('item_id', $weapon->id)
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'move-inventory-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $inventoryItem->id,
            'inventory_column' => 3,
            'inventory_row' => 4,
            'redirect_focus' => 'general-store',
        ]);

        $inventoryItem->refresh();

        $response->assertSessionHas('status');
        $this->assertSame(3, $inventoryItem->inventory_column);
        $this->assertSame(4, $inventoryItem->inventory_row);
    }

    public function test_move_inventory_item_action_rejects_overlapping_destination(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan');

        $inventoryService = app(CharacterInventoryService::class);
        $weapon = Item::query()->where('slug', 'recruit-blade')->firstOrFail();
        $armor = Item::query()->where('slug', 'cotton-armor')->firstOrFail();

        $inventoryService->grant($character, $weapon);
        $inventoryService->grant($character, $armor);

        $armorRow = CharacterItem::query()
            ->where('game_character_id', $character->id)
            ->where('item_id', $armor->id)
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'move-inventory-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $armorRow->id,
            'inventory_column' => 0,
            'inventory_row' => 1,
            'redirect_focus' => 'general-store',
        ]);

        $armorRow->refresh();

        $response->assertSessionHas('error');
        $this->assertSame(1, $armorRow->inventory_column);
        $this->assertSame(0, $armorRow->inventory_row);
    }

    public function test_equipping_item_removes_it_from_the_bag_grid_and_unequip_places_it_back(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 5,
        ]);

        $weapon = Item::query()->where('slug', 'recruit-blade')->firstOrFail();
        app(CharacterInventoryService::class)->grant($character, $weapon);

        $inventoryItem = CharacterItem::query()
            ->where('game_character_id', $character->id)
            ->where('item_id', $weapon->id)
            ->firstOrFail();

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'equip-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $inventoryItem->id,
            'redirect_focus' => 'general-store',
        ]);

        $inventoryItem->refresh();

        $this->assertNull($inventoryItem->inventory_column);
        $this->assertNull($inventoryItem->inventory_row);
        $this->assertDatabaseHas('character_equipment', [
            'game_character_id' => $character->id,
            'character_item_id' => $inventoryItem->id,
            'slot' => 'weapon',
        ]);

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'unequip-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $inventoryItem->id,
            'redirect_focus' => 'general-store',
        ]);

        $inventoryItem->refresh();

        $this->assertNotNull($inventoryItem->inventory_column);
        $this->assertNotNull($inventoryItem->inventory_row);
        $this->assertDatabaseMissing('character_equipment', [
            'character_item_id' => $inventoryItem->id,
        ]);
    }

    public function test_hunt_action_changes_resources_once_per_idempotency_key(): void
    {
        [$user, $character] = $this->createCharacterAt('seungryong-valley');

        $payload = ['idempotency_key' => (string) Str::uuid()];

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'hunt']), $payload);
        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'hunt']), $payload);

        $character->refresh();

        $this->assertSame(8, $character->stamina);
        $this->assertLessThan(100, $character->health);
        $this->assertGreaterThan(0, (int) $character->yang);
        $this->assertGreaterThan(0, (int) $character->experience);
        $this->assertSame(1, GameActionLog::query()->count());
    }

    public function test_cooldown_blocks_a_second_distinct_action_request(): void
    {
        [$user, $character] = $this->createCharacterAt('seungryong-valley');

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'hunt']), [
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'rest']), [
            'idempotency_key' => (string) Str::uuid(),
            'rest_minutes' => 30,
        ]);

        $character->refresh();

        $response->assertSessionHas('error');
        $this->assertSame(8, $character->stamina);
        $this->assertLessThan(100, $character->health);
        $this->assertSame(1, GameActionLog::query()->count());
    }

    public function test_fight_monsters_updates_area_progress_and_character_rewards(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan');

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $area->update([
            'boss_base_chance' => 0,
            'boss_pressure_chance_per_point' => 0,
            'boss_pressure_threshold' => 9999,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'fight-monsters']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'redirect_focus' => $area->slug,
        ]);

        $character->refresh();
        $progress = CharacterAreaProgress::query()
            ->where('game_character_id', $character->id)
            ->where('location_area_id', $area->id)
            ->firstOrFail();
        $actionLog = GameActionLog::query()->latest('id')->firstOrFail();

        $response->assertSessionMissing('status');
        $response->assertSessionHas('battle_replay_log_id', $actionLog->id);
        $this->assertSame(8, (int) $character->stamina);
        $this->assertGreaterThan(0, (int) $character->experience);
        $this->assertGreaterThan(0, (int) $character->yang);
        $this->assertLessThan(100, (int) $character->health);
        $this->assertSame((int) $area->monster_pressure_gain, (int) $progress->pressure);
        $this->assertSame(1, (int) $progress->monster_fights);
        $this->assertSame(0, (int) $progress->lumiis_searches);
        $this->assertSame(0, (int) $progress->boss_fights);
        $this->assertStringContainsString('Wildhund', (string) data_get($actionLog->outcome, 'message'));
        $this->assertNotEmpty(data_get($actionLog->outcome, 'battle_log'));
        $this->assertIsArray(data_get($actionLog->outcome, 'battle_replay'));
    }

    public function test_dashboard_recent_battle_prefers_newest_log_when_timestamps_match(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan');

        $processedAt = now()->startOfSecond();

        GameActionLog::query()->create([
            'game_character_id' => $character->id,
            'idempotency_key' => (string) Str::uuid(),
            'action' => 'fight-monsters',
            'source_location_id' => $character->location_id,
            'target_location_id' => $character->location_id,
            'request_payload' => [],
            'outcome' => [
                'message' => 'Older battle message',
                'battle_log' => ['Older battle line'],
                'battle_summary' => [
                    'monster_name' => 'Old Pack',
                    'pack_size' => 3,
                    'rounds' => 2,
                    'victory' => true,
                    'encounter_type' => 'monster',
                ],
                'battle_replay' => [
                    'title' => 'Old Pack',
                    'location' => 'Riverbank',
                    'encounter_type' => 'monster',
                    'victory' => true,
                    'player' => [
                        'name' => $character->name,
                        'level' => $character->level,
                        'health' => $character->health,
                        'max_health' => $character->effectiveMaxHealth(),
                        'attack' => $character->effectiveAttack(),
                        'defense' => $character->effectiveDefense(),
                        'speed' => $character->effectiveSpeed(),
                        'equipment' => [],
                    ],
                    'enemies' => [],
                    'steps' => [],
                    'summary' => [
                        'message' => 'Older battle message',
                        'rounds' => 2,
                        'experience' => 10,
                        'yang' => 5,
                        'health_lost' => 2,
                        'loot' => [],
                    ],
                ],
            ],
            'processed_at' => $processedAt,
        ]);

        GameActionLog::query()->create([
            'game_character_id' => $character->id,
            'idempotency_key' => (string) Str::uuid(),
            'action' => 'search-lumiis',
            'source_location_id' => $character->location_id,
            'target_location_id' => $character->location_id,
            'request_payload' => [],
            'outcome' => [
                'message' => 'Newest battle message',
                'battle_log' => ['Newest battle line'],
                'battle_summary' => [
                    'monster_name' => 'New Pack',
                    'pack_size' => 2,
                    'rounds' => 5,
                    'victory' => true,
                    'encounter_type' => 'lumiis',
                ],
                'battle_replay' => [
                    'title' => 'New Pack',
                    'location' => 'Riverbank',
                    'encounter_type' => 'lumiis',
                    'victory' => true,
                    'player' => [
                        'name' => $character->name,
                        'level' => $character->level,
                        'health' => $character->health,
                        'max_health' => $character->effectiveMaxHealth(),
                        'attack' => $character->effectiveAttack(),
                        'defense' => $character->effectiveDefense(),
                        'speed' => $character->effectiveSpeed(),
                        'equipment' => [],
                    ],
                    'enemies' => [],
                    'steps' => [],
                    'summary' => [
                        'message' => 'Newest battle message',
                        'rounds' => 5,
                        'experience' => 30,
                        'yang' => 12,
                        'health_lost' => 4,
                        'loot' => [],
                    ],
                ],
            ],
            'processed_at' => $processedAt,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('New Pack')
            ->assertSee('Newest battle message')
            ->assertDontSee('Old Pack')
            ->assertDontSee('Older battle message');
    }

    public function test_level_up_restores_character_to_full_effective_health(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'experience' => 0,
            'health' => 50,
            'stamina' => 4,
            'attack' => 24,
            'defense' => 50,
        ]);

        $quest = Quest::query()->where('slug', 'yongan-first-sweep')->firstOrFail();
        $character->experience = max(0, $character->nextLevelExperience() - (int) $quest->reward_experience);
        $character->save();

        $armor = Item::query()->where('slug', 'cotton-armor')->firstOrFail();
        $inventoryItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $armor->id,
            'quantity' => 1,
        ]);
        CharacterEquipment::query()->create([
            'game_character_id' => $character->id,
            'character_item_id' => $inventoryItem->id,
            'slot' => 'armor',
        ]);

        $characterQuest = CharacterQuest::query()->create([
            'game_character_id' => $character->id,
            'quest_id' => $quest->id,
            'status' => CharacterQuestStatus::ReadyToClaim,
            'progress' => (int) $quest->objective_target,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'claim-quest']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_quest_id' => $characterQuest->id,
        ]);

        $character->refresh()->load('equipment.characterItem.item');

        $response->assertSessionHas('status');
        $this->assertSame(2, (int) $character->level);
        $this->assertSame($character->effectiveMaxHealth(), (int) $character->health);
        $this->assertSame($character->effectiveMaxStamina(), (int) $character->stamina);
        $this->assertStringContainsString('Level 2 reached.', (string) data_get(GameActionLog::query()->latest('id')->firstOrFail()->outcome, 'message'));
    }

    public function test_auto_pack_fight_uses_a_health_potion_when_health_drops_too_low(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'health' => 35,
            'defense' => 50,
        ]);

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();
        $potion = Item::query()->where('slug', 'small-red-potion')->firstOrFail();

        $inventoryItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $potion->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'fight-monsters']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'redirect_focus' => $area->slug,
        ]);

        $character->refresh();
        $actionLog = GameActionLog::query()->latest('id')->firstOrFail();

        $response->assertSessionMissing('status');
        $this->assertDatabaseMissing('character_items', [
            'id' => $inventoryItem->id,
        ]);
        $this->assertStringContainsString(
            'automatically uses Small Red Potion',
            implode(' ', data_get($actionLog->outcome, 'battle_log', [])),
        );
    }

    public function test_manual_fight_creates_an_ongoing_battle_and_spends_stamina(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan');

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'monster',
            'redirect_focus' => $area->slug,
        ]);

        $character->refresh();
        $battle = GameBattle::query()
            ->where('game_character_id', $character->id)
            ->latest('id')
            ->firstOrFail();

        $response->assertSessionHas('status');
        $this->assertSame(7, (int) $character->stamina);
        $this->assertSame('ongoing', $battle->status);
        $this->assertSame('manual', $battle->mode);
        $this->assertSame('monster', $battle->requested_encounter);
        $this->assertSame('monster', $battle->encounter_type);
        $this->assertStringStartsWith('3x ', $battle->encounter_name);
        $this->assertStringEndsWith(' · Medium', $battle->encounter_name);
        $this->assertCount(3, data_get($battle->state, 'enemies', []));
        $this->assertSame(100, (int) data_get($battle->state, 'player.current_health'));
    }

    public function test_manual_fight_can_be_abandoned_without_rewards_or_duplicate_side_effects(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'experience' => 21,
            'yang' => 17,
        ]);

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'monster',
            'redirect_focus' => $area->slug,
        ]);

        $battle = GameBattle::query()
            ->where('game_character_id', $character->id)
            ->where('status', 'ongoing')
            ->latest('id')
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'abandon-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'battle_id' => $battle->id,
            'redirect_focus' => $area->slug,
        ]);

        $character->refresh();
        $battle->refresh();
        $actionLog = GameActionLog::query()->latest('id')->firstOrFail();

        $response->assertSessionMissing('status');
        $response->assertSessionHas('manual_battle_result_log_id');
        $this->assertSame('abandoned', $battle->status);
        $this->assertSame(21, (int) $character->experience);
        $this->assertSame(17, (int) $character->yang);
        $this->assertSame(7, (int) $character->stamina);
        $this->assertSame(0, GameBattle::query()
            ->where('game_character_id', $character->id)
            ->where('status', 'ongoing')
            ->count());
        $this->assertStringContainsString('No rewards were gained', (string) data_get($actionLog->outcome, 'message'));
    }

    public function test_midgame_manual_pack_rewards_do_not_grant_nearly_a_full_level_anymore(): void
    {
        [$user, $character] = $this->createCharacterAt('seungryong-valley', [
            'level' => 20,
            'health' => 220,
            'max_health' => 220,
            'stamina' => 20,
            'max_stamina' => 20,
            'attack' => 24,
            'defense' => 18,
            'speed' => 10,
        ]);

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'seungryong-valley'))
            ->where('slug', 'orc-approach')
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'monster',
            'redirect_focus' => $area->slug,
        ]);

        $battle = GameBattle::query()
            ->where('game_character_id', $character->id)
            ->latest('id')
            ->firstOrFail();

        $expGapToNextLevel = \App\Support\Game\ExperienceCurve::requiredForLevel(21)
            - \App\Support\Game\ExperienceCurve::requiredForLevel(20);

        $response->assertSessionHas('status');
        $this->assertGreaterThan(150, (int) data_get($battle->rewards, 'experience'));
        $this->assertLessThan(intdiv($expGapToNextLevel, 2), (int) data_get($battle->rewards, 'experience'));
        $this->assertLessThan(300, (int) data_get($battle->rewards, 'yang'));
    }

    public function test_dungeon_manual_pack_rewards_stay_below_half_a_level_even_in_the_throne_chamber(): void
    {
        [$user, $character] = $this->createCharacterAt('monkey-dungeon', [
            'level' => 34,
            'health' => 320,
            'max_health' => 320,
            'stamina' => 20,
            'max_stamina' => 20,
            'attack' => 38,
            'defense' => 28,
            'speed' => 14,
        ]);

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'monkey-dungeon'))
            ->where('slug', 'ape-throne')
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'monster',
            'redirect_focus' => $area->slug,
        ]);

        $battle = GameBattle::query()
            ->where('game_character_id', $character->id)
            ->latest('id')
            ->firstOrFail();

        $expGapToNextLevel = \App\Support\Game\ExperienceCurve::requiredForLevel(35)
            - \App\Support\Game\ExperienceCurve::requiredForLevel(34);

        $response->assertSessionHas('status');
        $this->assertGreaterThan(300, (int) data_get($battle->rewards, 'experience'));
        $this->assertLessThan(intdiv($expGapToNextLevel, 2), (int) data_get($battle->rewards, 'experience'));
        $this->assertLessThan(450, (int) data_get($battle->rewards, 'yang'));
    }

    public function test_manual_battle_auto_uses_a_health_potion_between_rounds(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'health' => 35,
            'defense' => 50,
        ]);

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();
        $potion = Item::query()->where('slug', 'small-red-potion')->firstOrFail();

        $inventoryItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $potion->id,
            'quantity' => 1,
        ]);

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'monster',
            'redirect_focus' => $area->slug,
        ]);

        $battle = GameBattle::query()
            ->where('game_character_id', $character->id)
            ->where('status', 'ongoing')
            ->latest('id')
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'battle-attack']), [
            'idempotency_key' => (string) Str::uuid(),
            'battle_id' => $battle->id,
            'battle_round' => $battle->round,
            'enemy_id' => 'enemy-1',
            'redirect_focus' => $area->slug,
        ]);

        $battle->refresh();

        $response->assertSessionHas('status');
        $this->assertDatabaseMissing('character_items', [
            'id' => $inventoryItem->id,
        ]);
        $this->assertStringContainsString(
            'automatically uses Small Red Potion',
            implode(' ', data_get($battle->state, 'battle_log', [])),
        );
        $this->assertGreaterThan(35, (int) data_get($battle->state, 'player.current_health', 0));
    }

    public function test_manual_battle_damage_skill_turn_damages_target_and_other_enemies(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 5,
            'unspent_skill_points' => 1,
            'race' => 'warrior',
            'skill_group' => 'body',
        ]);

        $skill = Skill::query()->where('slug', 'berserk')->firstOrFail();

        CharacterSkill::query()->create([
            'game_character_id' => $character->id,
            'skill_id' => $skill->id,
            'rank' => 1,
        ]);

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'monster',
            'redirect_focus' => $area->slug,
        ]);

        $battle = GameBattle::query()
            ->where('game_character_id', $character->id)
            ->where('status', 'ongoing')
            ->latest('id')
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'battle-use-skill']), [
            'idempotency_key' => (string) Str::uuid(),
            'battle_id' => $battle->id,
            'battle_round' => $battle->round,
            'enemy_id' => 'enemy-1',
            'skill_slug' => 'berserk',
            'redirect_focus' => $area->slug,
        ]);

        $battle->refresh();

        $enemies = collect(data_get($battle->state, 'enemies', []))->keyBy('id');

        $response->assertSessionHas('status');
        $this->assertSame(2, (int) data_get($battle->state, 'round'));
        $this->assertLessThan(
            (int) data_get($enemies->get('enemy-1'), 'max_health'),
            (int) data_get($enemies->get('enemy-1'), 'current_health'),
        );
        $this->assertLessThan(
            (int) data_get($enemies->get('enemy-2'), 'max_health'),
            (int) data_get($enemies->get('enemy-2'), 'current_health'),
        );
        $this->assertStringContainsString(
            'splash damage',
            implode(' ', data_get($battle->state, 'battle_log', [])),
        );
    }

    public function test_aura_of_the_sword_applies_a_temporary_attack_buff_in_manual_battle(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 5,
            'race' => 'warrior',
            'skill_group' => 'body',
        ]);

        $skill = Skill::query()->where('slug', 'aura-of-the-sword')->firstOrFail();

        CharacterSkill::query()->create([
            'game_character_id' => $character->id,
            'skill_id' => $skill->id,
            'rank' => 1,
        ]);

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'monster',
            'redirect_focus' => $area->slug,
        ]);

        $battle = GameBattle::query()
            ->where('game_character_id', $character->id)
            ->where('status', 'ongoing')
            ->latest('id')
            ->firstOrFail();

        $enemyMaxHealth = (int) data_get($battle->state, 'enemies.0.max_health');

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'battle-use-skill']), [
            'idempotency_key' => (string) Str::uuid(),
            'battle_id' => $battle->id,
            'battle_round' => $battle->round,
            'enemy_id' => 'enemy-1',
            'skill_slug' => 'aura-of-the-sword',
            'redirect_focus' => $area->slug,
        ]);

        $battle->refresh();

        $response->assertSessionHas('status');
        $this->assertSame(2, (int) data_get($battle->state, 'round'));
        $this->assertSame($enemyMaxHealth, (int) data_get($battle->state, 'enemies.0.current_health'));
        $this->assertGreaterThan(0, (int) data_get($battle->state, 'player.temporary_attack_bonus'));
        $this->assertGreaterThan(0, (int) data_get($battle->state, 'player.temporary_attack_bonus_rounds'));
        $this->assertStringContainsString(
            'surrounds the weapon with Aura of the Sword',
            implode(' ', data_get($battle->state, 'battle_log', [])),
        );
    }

    public function test_poison_skill_applies_a_damage_over_time_effect_during_manual_battle(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 8,
            'race' => 'ninja',
            'skill_group' => 'dagger',
            'attack' => 9,
            'defense' => 6,
            'speed' => 8,
            'dexterity' => 6,
            'sp' => 40,
            'max_sp' => 40,
        ]);

        $skill = Skill::query()->where('slug', 'poisonous-cloud')->firstOrFail();

        CharacterSkill::query()->create([
            'game_character_id' => $character->id,
            'skill_id' => $skill->id,
            'rank' => 1,
        ]);

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'monster',
            'redirect_focus' => $area->slug,
        ]);

        $battle = GameBattle::query()
            ->where('game_character_id', $character->id)
            ->where('status', 'ongoing')
            ->latest('id')
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'battle-use-skill']), [
            'idempotency_key' => (string) Str::uuid(),
            'battle_id' => $battle->id,
            'battle_round' => $battle->round,
            'enemy_id' => 'enemy-1',
            'skill_slug' => 'poisonous-cloud',
            'redirect_focus' => $area->slug,
        ]);

        $battle->refresh();
        $battleLog = implode(' ', data_get($battle->state, 'battle_log', []));
        $enemies = collect(data_get($battle->state, 'enemies', []));

        $response->assertSessionHas('status');
        $this->assertStringContainsString('poisoned', $battleLog);
        $this->assertStringContainsString('poison damage', $battleLog);
        $this->assertTrue($enemies->contains(fn (array $enemy): bool => ((int) ($enemy['poison_rounds'] ?? 0)) > 0));
    }

    public function test_manual_battle_turn_rejects_a_mismatched_battle_id(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan');
        [$otherUser, $otherCharacter] = $this->createCharacterAt('yongan');

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'monster',
            'redirect_focus' => $area->slug,
        ]);

        $battle = GameBattle::query()
            ->where('game_character_id', $character->id)
            ->where('status', 'ongoing')
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($otherUser)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'monster',
            'redirect_focus' => $area->slug,
        ]);

        $foreignBattle = GameBattle::query()
            ->where('game_character_id', $otherCharacter->id)
            ->where('status', 'ongoing')
            ->latest('id')
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'battle-attack']), [
            'idempotency_key' => (string) Str::uuid(),
            'battle_id' => $foreignBattle->id,
            'battle_round' => $battle->round,
            'enemy_id' => 'enemy-1',
            'redirect_focus' => $area->slug,
        ]);

        $battle->refresh();

        $response->assertSessionHas('error');
        $this->assertSame(1, (int) $battle->round);
        $this->assertSame('ongoing', $battle->status);
    }

    public function test_manual_battle_completion_redirects_to_a_result_overlay_instead_of_auto_replay(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan');

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'monster',
            'redirect_focus' => $area->slug,
        ]);

        $battle = GameBattle::query()
            ->where('game_character_id', $character->id)
            ->where('status', 'ongoing')
            ->latest('id')
            ->firstOrFail();

        $battleState = $battle->state;
        $battleState['enemies'] = collect($battleState['enemies'] ?? [])
            ->map(fn (array $enemy): array => [...$enemy, 'current_health' => 1])
            ->values()
            ->all();
        $battle->update([
            'state' => $battleState,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'battle-attack']), [
            'idempotency_key' => (string) Str::uuid(),
            'battle_id' => $battle->id,
            'battle_round' => $battle->round,
            'enemy_id' => 'enemy-1',
            'redirect_focus' => $area->slug,
        ]);

        $response->assertSessionMissing('status');
        $response->assertSessionHas('manual_battle_result_log_id');
        $response->assertSessionMissing('battle_replay_log_id');
    }

    public function test_manual_lumiis_fight_spawns_trapped_monsters_after_the_core_is_hit(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 2,
        ]);

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'lumiis',
            'redirect_focus' => $area->slug,
        ]);

        $battle = GameBattle::query()
            ->where('game_character_id', $character->id)
            ->where('status', 'ongoing')
            ->latest('id')
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'battle-attack']), [
            'idempotency_key' => (string) Str::uuid(),
            'battle_id' => $battle->id,
            'battle_round' => $battle->round,
            'enemy_id' => 'lumiis-core',
            'redirect_focus' => $area->slug,
        ]);

        $battle->refresh();

        $response->assertSessionHas('status');
        $this->assertGreaterThan(1, count(data_get($battle->state, 'enemies', [])));
        $this->assertStringContainsString(
            'releases trapped souls',
            implode(' ', data_get($battle->state, 'battle_log', [])),
        );
        $this->assertStringNotContainsString(
            $area->lumiis_name.' hits',
            implode(' ', data_get($battle->state, 'battle_log', [])),
        );
    }

    public function test_manual_lumiis_fight_keeps_the_core_as_the_first_enemy_card(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 2,
        ]);

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'lumiis',
            'redirect_focus' => $area->slug,
        ]);

        $battle = GameBattle::query()
            ->where('game_character_id', $character->id)
            ->where('status', 'ongoing')
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'battle-attack']), [
            'idempotency_key' => (string) Str::uuid(),
            'battle_id' => $battle->id,
            'battle_round' => $battle->round,
            'enemy_id' => 'lumiis-core',
            'redirect_focus' => $area->slug,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'focus' => $area->slug,
        ]));

        $response->assertOk();

        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $firstEnemyCard = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " battle-enemy-card ")][1]');

        $this->assertNotNull($firstEnemyCard);
        $this->assertStringContainsString($area->lumiis_name, $firstEnemyCard->item(0)?->textContent ?? '');
    }

    public function test_starting_a_manual_lumiis_fight_does_not_flash_a_replay_overlay(): void
    {
        [$user] = $this->createCharacterAt('yongan', [
            'level' => 2,
        ]);

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'lumiis',
            'redirect_focus' => $area->slug,
        ]);

        $response->assertSessionHas('status');
        $response->assertSessionMissing('battle_replay_log_id');
        $response->assertSessionMissing('manual_battle_result_log_id');

        $dashboard = $this->actingAs($user)->get(route('dashboard', [
            'focus' => $area->slug,
        ]));

        $dashboard
            ->assertOk()
            ->assertSee('Fight Window')
            ->assertSee('Manual Turn')
            ->assertDontSee('Replay');
    }

    public function test_manual_lumiis_turn_keeps_the_live_fight_window_until_resolution(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 2,
        ]);

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'lumiis',
            'redirect_focus' => $area->slug,
        ]);

        $battle = GameBattle::query()
            ->where('game_character_id', $character->id)
            ->where('status', 'ongoing')
            ->latest('id')
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'battle-attack']), [
            'idempotency_key' => (string) Str::uuid(),
            'battle_id' => $battle->id,
            'battle_round' => $battle->round,
            'enemy_id' => 'lumiis-core',
            'redirect_focus' => $area->slug,
        ]);

        $battle->refresh();

        $response->assertSessionHas('status');
        $response->assertSessionMissing('battle_replay_log_id');
        $response->assertSessionMissing('manual_battle_result_log_id');
        $this->assertSame('ongoing', $battle->status);

        $dashboard = $this->actingAs($user)->get(route('dashboard', [
            'focus' => $area->slug,
        ]));

        $dashboard
            ->assertOk()
            ->assertSee('Fight Window')
            ->assertSee('Manual Turn')
            ->assertDontSee('Replay');
    }

    public function test_manual_boss_fight_requires_full_pressure(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 4,
        ]);

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'boss',
            'redirect_focus' => $area->slug,
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, GameBattle::query()->where('game_character_id', $character->id)->count());
    }

    public function test_manual_boss_fight_can_start_once_pressure_is_full(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 4,
        ]);

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        CharacterAreaProgress::query()->create([
            'game_character_id' => $character->id,
            'location_area_id' => $area->id,
            'pressure' => (int) $area->boss_pressure_threshold,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'boss',
            'redirect_focus' => $area->slug,
        ]);

        $battle = GameBattle::query()
            ->where('game_character_id', $character->id)
            ->latest('id')
            ->firstOrFail();

        $response->assertSessionHas('status');
        $this->assertSame('boss', $battle->requested_encounter);
        $this->assertSame('boss', $battle->encounter_type);
    }

    public function test_non_battle_actions_are_blocked_while_a_manual_fight_is_active(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan');

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'monster',
            'redirect_focus' => $area->slug,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'rest']), [
            'idempotency_key' => (string) Str::uuid(),
            'rest_minutes' => 30,
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(1, GameBattle::query()->where('game_character_id', $character->id)->count());
        $this->assertSame(1, GameActionLog::query()->count());
    }

    public function test_lumiis_search_can_grant_loot_and_uses_area_cooldown(): void
    {
        $this->freezeTime();

        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 2,
            'health' => 220,
            'max_health' => 220,
            'attack' => 28,
            'defense' => 24,
            'speed' => 10,
        ]);

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $area->update([
            'boss_base_chance' => 0,
            'boss_pressure_chance_per_point' => 0,
            'boss_pressure_threshold' => 9999,
            'lumiis_loot_table' => [
                ['slug' => 'lumiis-shard', 'chance' => 10000, 'min' => 2, 'max' => 2],
            ],
        ]);

        $fragment = Item::query()->where('slug', 'lumiis-shard')->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'search-lumiis']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'redirect_focus' => $area->slug,
        ]);

        $character->refresh();
        $progress = CharacterAreaProgress::query()
            ->where('game_character_id', $character->id)
            ->where('location_area_id', $area->id)
            ->firstOrFail();
        $actionLog = GameActionLog::query()->latest('id')->firstOrFail();

        $response->assertSessionMissing('status');
        $response->assertSessionHas('battle_replay_log_id', $actionLog->id);
        $this->assertSame(6, (int) $character->stamina);
        $this->assertSame((int) $area->lumiis_pressure_gain, (int) $progress->pressure);
        $this->assertSame(1, (int) $progress->lumiis_searches);
        $this->assertSame('lumiis', data_get($actionLog->outcome, 'battle_replay.encounter_type'));
        $this->assertDatabaseHas('character_items', [
            'game_character_id' => $character->id,
            'item_id' => $fragment->id,
            'quantity' => 2,
        ]);
        $this->assertNotEmpty(data_get($actionLog->outcome, 'battle_log'));
        $this->assertIsArray(data_get($actionLog->outcome, 'battle_replay'));
        $this->assertStringContainsString(
            'releases trapped souls',
            implode(' ', data_get($actionLog->outcome, 'battle_log', [])),
        );
        $this->assertStringNotContainsString(
            'soul burst',
            implode(' ', data_get($actionLog->outcome, 'battle_log', [])),
        );
        $this->assertTrue(
            collect(data_get($actionLog->outcome, 'battle_replay.steps', []))
                ->contains(fn (array $step): bool => data_get($step, 'target_type') === 'spawn'),
        );
        $cooldown = (int) floor(now()->diffInSeconds($character->action_available_at, true));

        $this->assertContains($cooldown, [
            (int) $area->lumiis_cooldown_seconds - 1,
            (int) $area->lumiis_cooldown_seconds,
        ]);
    }

    public function test_auto_lumiis_replay_header_uses_live_status_instead_of_immediate_victory(): void
    {
        [$user] = $this->createCharacterAt('yongan', [
            'level' => 2,
        ]);

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'search-lumiis']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'redirect_focus' => $area->slug,
        ]);

        $actionLog = GameActionLog::query()->latest('id')->firstOrFail();

        $response->assertSessionHas('battle_replay_log_id', $actionLog->id);

        $dashboard = $this->actingAs($user)->withSession([
            'battle_replay_log_id' => $actionLog->id,
        ])->get(route('dashboard', [
            'focus' => $area->slug,
        ]));

        $dashboard
            ->assertOk()
            ->assertSee('x-text="replayStatusLabel()"', false)
            ->assertDontSee("x-text=\"replay.victory ? 'Victory' : 'Retreat'\"", false);
    }

    public function test_auto_replay_hides_summary_and_loot_until_finished(): void
    {
        [$user] = $this->createCharacterAt('yongan', [
            'level' => 2,
        ]);

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'search-lumiis']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'redirect_focus' => $area->slug,
        ]);

        $actionLog = GameActionLog::query()->latest('id')->firstOrFail();

        $response->assertSessionHas('battle_replay_log_id', $actionLog->id);

        $dashboard = $this->actingAs($user)->withSession([
            'battle_replay_log_id' => $actionLog->id,
        ])->get(route('dashboard', [
            'focus' => $area->slug,
        ]));

        $dashboard
            ->assertOk()
            ->assertSee('x-show="isFinished" x-cloak class="paper-note battle-stage-summary"', false)
            ->assertSee('x-show="isFinished" x-cloak class="paper-note mt-4 battle-stage-loot"', false);
    }

    public function test_auto_lumiis_search_never_auto_triggers_a_boss(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 12,
        ]);

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'pine-ridge')
            ->firstOrFail();

        $area->update([
            'boss_pressure_threshold' => 10,
            'lumiis_pressure_gain' => 10,
            'boss_base_chance' => 10000,
            'boss_pressure_chance_per_point' => 10000,
        ]);

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'search-lumiis']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'redirect_focus' => $area->slug,
        ]);

        $progress = CharacterAreaProgress::query()
            ->where('game_character_id', $character->id)
            ->where('location_area_id', $area->id)
            ->firstOrFail();
        $actionLog = GameActionLog::query()->latest('id')->firstOrFail();

        $this->assertSame('lumiis', data_get($actionLog->outcome, 'battle_summary.encounter_type'));
        $this->assertSame(0, (int) $progress->boss_fights);
        $this->assertSame(10, (int) $progress->pressure);
    }

    public function test_underleveled_character_cannot_hunt_in_pine_ridge(): void
    {
        [$user] = $this->createCharacterAt('yongan', [
            'level' => 3,
            'stamina' => 12,
            'health' => 100,
        ]);

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'pine-ridge')
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'fight-monsters']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'redirect_focus' => $area->slug,
        ]);

        $response->assertSessionHas('error', __('You need to reach level :level or grow stronger before hunting in :area.', [
            'level' => 12,
            'area' => 'Pine Ridge',
        ]));
    }

    public function test_stamina_recovers_over_time_before_fight_checks_are_applied(): void
    {
        $this->freezeTime();

        [$user, $character] = $this->createCharacterAt('yongan', [
            'stamina' => 1,
            'stamina_updated_at' => now()->subMinutes(40),
        ]);

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'fight-monsters']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'redirect_focus' => $area->slug,
        ]);

        $character->refresh();

        $response->assertSessionMissing('status');
        $this->assertSame(5, (int) $character->stamina);
        $this->assertGreaterThan(0, (int) $character->experience);
    }

    public function test_health_and_sp_recover_over_time_before_non_combat_actions_are_applied(): void
    {
        $this->freezeTime();

        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 5,
            'unspent_skill_points' => 1,
            'race' => 'warrior',
            'skill_group' => 'body',
            'health' => 40,
            'sp' => 3,
            'last_active_at' => now()->subMinutes(30),
            'health_updated_at' => now()->subMinutes(30),
            'sp_updated_at' => now()->subMinutes(30),
        ]);

        $skill = Skill::query()->where('slug', 'aura-of-the-sword')->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'learn-skill']), [
            'idempotency_key' => (string) Str::uuid(),
            'skill_id' => $skill->id,
        ]);

        $character->refresh();

        $response->assertSessionHas('status');
        $this->assertSame(70, (int) $character->health);
        $this->assertSame(24, (int) $character->sp);
        $this->assertDatabaseHas('character_skills', [
            'game_character_id' => $character->id,
            'skill_id' => $skill->id,
            'rank' => 1,
        ]);
    }

    public function test_rest_action_locks_character_and_resolves_rewards_only_after_the_timer_finishes(): void
    {
        $this->freezeTime();

        [$user, $character] = $this->createCharacterAt('yongan', [
            'health' => 32,
            'sp' => 6,
            'max_sp' => 24,
            'stamina' => 2,
            'yang' => 40,
            'experience' => 12,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'rest']), [
            'idempotency_key' => (string) Str::uuid(),
            'rest_minutes' => 60,
        ]);

        $character->refresh();

        $response->assertSessionHas('status');
        $this->assertSame(2, (int) $character->stamina);
        $this->assertSame(32, (int) $character->health);
        $this->assertSame(6, (int) $character->sp);
        $this->assertSame(40, (int) $character->yang);
        $this->assertSame(12, (int) $character->experience);
        $cooldown = (int) floor(now()->diffInSeconds($character->action_available_at, true));

        $this->assertContains($cooldown, [3599, 3600]);

        $this->travel(61)->minutes();

        $this->actingAs($user)->get(route('dashboard'));
        $character->refresh();

        $this->assertGreaterThan(32, (int) $character->health);
        $this->assertGreaterThan(6, (int) $character->sp);
        $this->assertSame(12, (int) $character->stamina);
        $this->assertSame(150, (int) $character->yang);
        $this->assertNull($character->action_available_at);
    }

    public function test_dashboard_shows_why_the_first_biologist_stage_is_still_locked(): void
    {
        [$user] = $this->createCharacterAt('yongan', [
            'level' => 5,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'focus' => 'biologist-study',
        ]));

        $response
            ->assertOk()
            ->assertSee(__('Next Research'))
            ->assertSee(__('Finish :quest first.', ['quest' => trans('quests.yongan-alpha-release.title')]));
    }

    public function test_travel_rejects_unconfigured_routes(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 4,
        ]);

        $hiddenCamp = Location::query()->create([
            'slug' => 'hidden-camp',
            'name' => 'Hidden Camp',
            'region' => 'Unknown',
            'description' => 'A route that should not be reachable yet.',
            'threat_level' => 5,
            'is_safe_zone' => false,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'travel']), [
            'idempotency_key' => (string) Str::uuid(),
            'target_location_id' => $hiddenCamp->id,
        ]);

        $character->refresh();

        $response->assertSessionHas('error');
        $this->assertSame('yongan', $character->location->slug);
        $this->assertSame(0, GameActionLog::query()->count());
    }

    public function test_travel_rejects_routes_below_the_required_level(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 19,
            'stamina' => 12,
        ]);

        $targetLocation = Location::query()->where('slug', 'seungryong-valley')->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'travel']), [
            'idempotency_key' => (string) Str::uuid(),
            'target_location_id' => $targetLocation->id,
        ]);

        $character->refresh();

        $response->assertSessionHas('error');
        $this->assertSame('yongan', $character->location->slug);
        $this->assertSame(0, GameActionLog::query()->count());
    }

    public function test_travel_rejects_monkey_dungeon_until_level_twenty_five(): void
    {
        [$user, $character] = $this->createCharacterAt('seungryong-valley', [
            'level' => 24,
            'stamina' => 12,
        ]);

        $targetLocation = Location::query()->where('slug', 'monkey-dungeon')->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'travel']), [
            'idempotency_key' => (string) Str::uuid(),
            'target_location_id' => $targetLocation->id,
        ]);

        $character->refresh();

        $response->assertSessionHas('error');
        $this->assertSame('seungryong-valley', $character->location->slug);
        $this->assertSame(0, GameActionLog::query()->count());
    }

    public function test_route_marker_hotspot_stays_hidden_when_fast_travel_is_disabled(): void
    {
        [$user] = $this->createCharacterAt('seungryong-valley', [
            'level' => 30,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'focus' => 'route-marker',
        ]));

        $response
            ->assertOk()
            ->assertDontSee('Travel Routes')
            ->assertDontSee('Map Connections')
            ->assertDontSee('Route Marker');
    }

    public function test_trade_square_hotspot_stays_hidden_in_the_public_alpha_ui(): void
    {
        [$user] = $this->createCharacterAt('yongan', [
            'level' => 30,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertDontSee('Trade Square')
            ->assertDontSee('Drill Yard');
    }

    public function test_store_purchase_adds_inventory_item_and_spends_yang(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'yang' => 150,
        ]);

        $shopItem = ShopItem::query()
            ->whereHas('shop.location', fn ($query) => $query->where('slug', 'yongan'))
            ->whereHas('item', fn ($query) => $query->where('slug', 'small-red-potion'))
            ->with('item')
            ->firstOrFail();
        $vendorArea = LocationArea::query()->where('slug', 'general-store')->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'buy-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $vendorArea->id,
            'shop_item_id' => $shopItem->id,
        ]);

        $character->refresh();

        $response->assertSessionHas('status');
        $this->assertSame(150 - $shopItem->price, (int) $character->yang);
        $this->assertDatabaseHas('character_items', [
            'game_character_id' => $character->id,
            'item_id' => $shopItem->item->id,
            'quantity' => 1,
        ]);
    }

    public function test_store_purchase_can_buy_multiple_copies_in_one_request(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'yang' => 500,
        ]);

        $shopItem = ShopItem::query()
            ->whereHas('shop.location', fn ($query) => $query->where('slug', 'yongan'))
            ->whereHas('item', fn ($query) => $query->where('slug', 'small-red-potion'))
            ->with('item')
            ->firstOrFail();
        $vendorArea = LocationArea::query()->where('slug', 'general-store')->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'buy-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $vendorArea->id,
            'shop_item_id' => $shopItem->id,
            'quantity' => 3,
        ]);

        $character->refresh();

        $response->assertSessionHas('status');
        $this->assertSame(500 - ($shopItem->price * 3), (int) $character->yang);
        $this->assertDatabaseHas('character_items', [
            'game_character_id' => $character->id,
            'item_id' => $shopItem->item->id,
            'quantity' => 3,
        ]);
    }

    public function test_store_purchase_requires_enough_yang(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'yang' => 0,
        ]);

        $shopItem = ShopItem::query()
            ->whereHas('shop.location', fn ($query) => $query->where('slug', 'yongan'))
            ->whereHas('item', fn ($query) => $query->where('slug', 'small-red-potion'))
            ->with('item')
            ->firstOrFail();
        $vendorArea = LocationArea::query()->where('slug', 'general-store')->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'buy-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $vendorArea->id,
            'shop_item_id' => $shopItem->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('character_items', [
            'game_character_id' => $character->id,
            'item_id' => $shopItem->item->id,
        ]);
    }

    public function test_vendor_can_buy_loot_from_inventory(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'yang' => 10,
        ]);

        $pelt = Item::query()->where('slug', 'wolf-pelt')->firstOrFail();
        $inventoryItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $pelt->id,
            'quantity' => 3,
        ]);
        $vendorArea = LocationArea::query()->where('slug', 'general-store')->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'sell-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $vendorArea->id,
            'character_item_id' => $inventoryItem->id,
            'quantity' => 2,
        ]);

        $response->assertSessionHas('status');
        $character->refresh();
        $inventoryItem->refresh();

        $this->assertSame(28, (int) $character->yang);
        $this->assertSame(1, (int) $inventoryItem->quantity);
    }

    public function test_vendor_sell_deletes_single_non_stackable_item(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'yang' => 0,
            'level' => 5,
        ]);

        $weapon = Item::query()->where('slug', 'recruit-blade')->firstOrFail();
        $inventoryItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $weapon->id,
            'quantity' => 1,
        ]);
        $vendorArea = LocationArea::query()->where('slug', 'weapon-dealer')->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'sell-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $vendorArea->id,
            'character_item_id' => $inventoryItem->id,
        ]);

        $response->assertSessionHas('status');
        $character->refresh();

        $this->assertSame((int) $weapon->sell_price, (int) $character->yang);
        $this->assertDatabaseMissing('character_items', [
            'id' => $inventoryItem->id,
        ]);
    }

    public function test_race_restricted_level_thirty_weapon_cannot_be_equipped_by_the_wrong_race(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 30,
            'race' => 'warrior',
        ]);

        $weapon = Item::query()->where('slug', 'black-leaf-dirk')->firstOrFail();
        app(CharacterInventoryService::class)->grant($character, $weapon, 1);

        $inventoryItem = CharacterItem::query()
            ->where('game_character_id', $character->id)
            ->where('item_id', $weapon->id)
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'equip-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $inventoryItem->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('character_equipment', [
            'character_item_id' => $inventoryItem->id,
        ]);
    }

    public function test_smith_upgrade_guaranteed_plus_zero_weapon_succeeds(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'yang' => 80,
            'level' => 5,
        ]);

        $weapon = Item::query()->where('slug', 'recruit-blade')->firstOrFail();
        $inventoryItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $weapon->id,
            'quantity' => 1,
            'upgrade_level' => 0,
        ]);
        $smithArea = LocationArea::query()->where('slug', 'town-smith')->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'upgrade-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $smithArea->id,
            'character_item_id' => $inventoryItem->id,
            'redirect_focus' => $smithArea->slug,
        ]);

        $response->assertSessionHas('status');
        $character->refresh();
        $inventoryItem->refresh();

        $this->assertSame(55, (int) $character->yang);
        $this->assertSame(1, (int) $inventoryItem->upgrade_level);
    }

    public function test_smith_upgrade_requires_lumiis_shards_on_higher_levels(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'yang' => 500,
            'level' => 5,
        ]);

        $weapon = Item::query()->where('slug', 'recruit-blade')->firstOrFail();
        $inventoryItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $weapon->id,
            'quantity' => 1,
            'upgrade_level' => 3,
        ]);
        $smithArea = LocationArea::query()->where('slug', 'town-smith')->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'upgrade-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $smithArea->id,
            'character_item_id' => $inventoryItem->id,
            'redirect_focus' => $smithArea->slug,
        ]);

        $response->assertSessionHas('error');
        $character->refresh();
        $inventoryItem->refresh();

        $this->assertSame(500, (int) $character->yang);
        $this->assertSame(3, (int) $inventoryItem->upgrade_level);
    }

    public function test_smith_upgrade_consumes_lumiis_shards_when_attempting_higher_levels(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'yang' => 500,
            'level' => 5,
        ]);

        $weapon = Item::query()->where('slug', 'recruit-blade')->firstOrFail();
        $shard = Item::query()->where('slug', 'lumiis-shard')->firstOrFail();
        $inventoryItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $weapon->id,
            'quantity' => 1,
            'upgrade_level' => 3,
        ]);
        $shardStack = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $shard->id,
            'quantity' => 2,
        ]);
        SmithRefineRate::query()->where('upgrade_level', 3)->update(['success_percent' => 100]);
        $smithArea = LocationArea::query()->where('slug', 'town-smith')->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'upgrade-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $smithArea->id,
            'character_item_id' => $inventoryItem->id,
            'redirect_focus' => $smithArea->slug,
        ]);

        $response->assertSessionHas('status');
        $character->refresh();
        $inventoryItem->refresh();
        $shardStack->refresh();

        $this->assertSame(420, (int) $character->yang);
        $this->assertSame(4, (int) $inventoryItem->upgrade_level);
        $this->assertSame(1, (int) $shardStack->quantity);
    }

    public function test_soul_prism_unlocks_a_fifth_item_bonus(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 5,
        ]);

        $weapon = Item::query()->where('slug', 'recruit-blade')->firstOrFail();
        $prism = Item::query()->where('slug', 'soul-prism')->firstOrFail();

        $targetItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $weapon->id,
            'quantity' => 1,
            'bonus_slot_limit' => 4,
        ]);
        CharacterItemBonus::query()->create([
            'character_item_id' => $targetItem->id,
            'slot_number' => 1,
            'bonus_type' => 'strength',
            'bonus_value' => 2,
        ]);
        CharacterItemBonus::query()->create([
            'character_item_id' => $targetItem->id,
            'slot_number' => 2,
            'bonus_type' => 'dexterity',
            'bonus_value' => 2,
        ]);
        CharacterItemBonus::query()->create([
            'character_item_id' => $targetItem->id,
            'slot_number' => 3,
            'bonus_type' => 'magic_attack',
            'bonus_value' => 3,
        ]);
        CharacterItemBonus::query()->create([
            'character_item_id' => $targetItem->id,
            'slot_number' => 4,
            'bonus_type' => 'crit_chance',
            'bonus_value' => 2,
        ]);

        $prismStack = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $prism->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'unlock-item-bonus']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $prismStack->id,
            'target_character_item_id' => $targetItem->id,
        ]);

        $response->assertSessionHas('status');
        $targetItem->refresh();

        $this->assertSame(5, (int) $targetItem->bonus_slot_limit);
        $this->assertSame(5, CharacterItemBonus::query()->where('character_item_id', $targetItem->id)->count());
        $this->assertDatabaseMissing('character_items', [
            'id' => $prismStack->id,
        ]);
    }

    public function test_soul_imprint_adds_one_bonus_to_an_item_with_open_slots(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 5,
        ]);

        $weapon = Item::query()->where('slug', 'recruit-blade')->firstOrFail();
        $imprint = Item::query()->where('slug', 'soul-imprint')->firstOrFail();

        $targetItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $weapon->id,
            'quantity' => 1,
            'bonus_slot_limit' => 4,
        ]);
        CharacterItemBonus::query()->create([
            'character_item_id' => $targetItem->id,
            'slot_number' => 1,
            'bonus_type' => 'strength',
            'bonus_value' => 2,
        ]);

        $imprintStack = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $imprint->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'add-item-bonus']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $imprintStack->id,
            'target_character_item_id' => $targetItem->id,
        ]);

        $response->assertSessionHas('status');

        $this->assertSame(2, CharacterItemBonus::query()->where('character_item_id', $targetItem->id)->count());
        $this->assertDatabaseMissing('character_items', [
            'id' => $imprintStack->id,
        ]);
    }

    public function test_soul_reweaver_rerolls_bonus_lines(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 5,
        ]);

        $weapon = Item::query()->where('slug', 'recruit-blade')->firstOrFail();
        $rerollItem = Item::query()->where('slug', 'soul-reweaver')->firstOrFail();

        $targetItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $weapon->id,
            'quantity' => 1,
            'bonus_slot_limit' => 4,
        ]);
        CharacterItemBonus::query()->create([
            'character_item_id' => $targetItem->id,
            'slot_number' => 1,
            'bonus_type' => 'strength',
            'bonus_value' => 2,
        ]);
        CharacterItemBonus::query()->create([
            'character_item_id' => $targetItem->id,
            'slot_number' => 2,
            'bonus_type' => 'crit_chance',
            'bonus_value' => 2,
        ]);

        $rerollStack = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $rerollItem->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'reroll-item-bonuses']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $rerollStack->id,
            'target_character_item_id' => $targetItem->id,
        ]);

        $response->assertSessionHas('status');
        $targetItem->refresh();
        $targetItem->load('bonuses');

        $this->assertSame(2, $targetItem->bonuses->count());
        $this->assertDatabaseMissing('character_items', [
            'id' => $rerollStack->id,
        ]);
    }

    public function test_soul_anchor_can_preserve_one_bonus_during_reroll(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 5,
        ]);

        $armor = Item::query()->where('slug', 'cotton-armor')->firstOrFail();
        $rerollItem = Item::query()->where('slug', 'soul-reweaver')->firstOrFail();
        $anchorItem = Item::query()->where('slug', 'soul-anchor')->firstOrFail();

        $targetItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $armor->id,
            'quantity' => 1,
            'bonus_slot_limit' => 5,
        ]);
        $lockedBonus = CharacterItemBonus::query()->create([
            'character_item_id' => $targetItem->id,
            'slot_number' => 1,
            'bonus_type' => 'vitality',
            'bonus_value' => 4,
        ]);
        CharacterItemBonus::query()->create([
            'character_item_id' => $targetItem->id,
            'slot_number' => 2,
            'bonus_type' => 'intelligence',
            'bonus_value' => 2,
        ]);
        CharacterItemBonus::query()->create([
            'character_item_id' => $targetItem->id,
            'slot_number' => 3,
            'bonus_type' => 'max_health',
            'bonus_value' => 24,
        ]);

        $rerollStack = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $rerollItem->id,
            'quantity' => 1,
        ]);
        $anchorStack = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $anchorItem->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'reroll-item-bonuses']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $rerollStack->id,
            'target_character_item_id' => $targetItem->id,
            'locked_bonus_id' => $lockedBonus->id,
        ]);

        $response->assertSessionHas('status');
        $targetItem->refresh();
        $targetItem->load('bonuses');

        $this->assertDatabaseHas('character_item_bonuses', [
            'character_item_id' => $targetItem->id,
            'slot_number' => 1,
            'bonus_type' => 'vitality',
            'bonus_value' => 4,
        ]);
        $this->assertDatabaseMissing('character_items', [
            'id' => $rerollStack->id,
        ]);
        $this->assertDatabaseMissing('character_items', [
            'id' => $anchorStack->id,
        ]);
    }

    public function test_consumable_restores_health_without_triggering_character_cooldown(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'health' => 50,
            'stamina' => 5,
        ]);

        $potion = Item::query()->where('slug', 'small-red-potion')->firstOrFail();

        $inventoryItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $potion->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'use-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $inventoryItem->id,
        ]);

        $character->refresh();

        $response->assertSessionHas('status');
        $this->assertSame(85, (int) $character->health);
        $this->assertNull($character->action_available_at);
        $this->assertDatabaseMissing('character_items', [
            'id' => $inventoryItem->id,
        ]);
    }

    public function test_consumable_can_be_used_multiple_times_in_one_request(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'health' => 20,
            'stamina' => 5,
        ]);

        $potion = Item::query()->where('slug', 'small-red-potion')->firstOrFail();

        $inventoryItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $potion->id,
            'quantity' => 4,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'use-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $inventoryItem->id,
            'quantity' => 2,
        ]);

        $character->refresh();
        $inventoryItem->refresh();

        $response->assertSessionHas('status');
        $this->assertSame(90, (int) $character->health);
        $this->assertSame(5, (int) $character->stamina);
        $this->assertSame(2, (int) $inventoryItem->quantity);
    }

    public function test_blue_potion_use_until_full_restores_sp_and_not_stamina(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'health' => 100,
            'stamina' => 4,
            'sp' => 5,
            'max_sp' => 24,
        ]);

        $potion = Item::query()->where('slug', 'medium-blue-potion')->firstOrFail();

        $inventoryItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $potion->id,
            'quantity' => 3,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'use-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $inventoryItem->id,
            'quantity' => 1,
            'use_until_full' => true,
        ]);

        $character->refresh();

        $response->assertSessionHas('status');
        $this->assertSame(24, (int) $character->sp);
        $this->assertSame(4, (int) $character->stamina);
        $inventoryItem->refresh();
        $this->assertSame(1, (int) $inventoryItem->quantity);
    }

    public function test_consumable_rejects_using_more_copies_than_are_owned(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'health' => 20,
        ]);

        $potion = Item::query()->where('slug', 'small-red-potion')->firstOrFail();

        $inventoryItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $potion->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'use-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $inventoryItem->id,
            'quantity' => 3,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('character_items', [
            'id' => $inventoryItem->id,
            'quantity' => 1,
        ]);
        $this->assertSame(0, GameActionLog::query()->count());
    }

    public function test_consumable_that_would_have_no_effect_is_rejected_without_being_consumed(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'health' => 100,
            'stamina' => 12,
        ]);

        $potion = Item::query()->where('slug', 'small-red-potion')->firstOrFail();

        $inventoryItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $potion->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'use-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $inventoryItem->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('character_items', [
            'id' => $inventoryItem->id,
            'quantity' => 1,
        ]);
        $this->assertSame(0, GameActionLog::query()->count());
    }

    public function test_skill_book_upgrades_a_matching_skill_after_rank_five(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 10,
            'race' => 'warrior',
            'skill_group' => 'body',
            'unspent_skill_points' => 0,
        ]);

        $skill = Skill::query()->where('slug', 'aura-of-the-sword')->firstOrFail();
        $book = Item::query()->where('slug', 'skill-book-aura-of-the-sword')->firstOrFail();

        CharacterSkill::query()->create([
            'game_character_id' => $character->id,
            'skill_id' => $skill->id,
            'rank' => 5,
            'mastery_rank' => 0,
        ]);

        $inventoryItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $book->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'use-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $inventoryItem->id,
        ]);

        $response->assertSessionHas('status');
        $this->assertDatabaseMissing('character_items', [
            'id' => $inventoryItem->id,
        ]);
        $learnedSkill = CharacterSkill::query()->firstOrFail();
        $this->assertSame(5, (int) $learnedSkill->rank);
        $this->assertSame(1, (int) $learnedSkill->mastery_rank);
        $this->assertNotNull($learnedSkill->book_available_at);
    }

    public function test_skill_book_uses_multiple_successful_reads_for_later_mastery_stages(): void
    {
        $this->freezeTime();

        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 10,
            'race' => 'warrior',
            'skill_group' => 'body',
            'unspent_skill_points' => 0,
        ]);

        $skill = Skill::query()->where('slug', 'aura-of-the-sword')->firstOrFail();
        $book = Item::query()->where('slug', 'skill-book-aura-of-the-sword')->firstOrFail();

        CharacterSkill::query()->create([
            'game_character_id' => $character->id,
            'skill_id' => $skill->id,
            'rank' => 5,
            'mastery_rank' => 1,
            'mastery_progress' => 0,
        ]);

        $inventoryItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $book->id,
            'quantity' => 2,
        ]);

        $firstResponse = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'use-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $inventoryItem->id,
        ]);

        $firstResponse->assertSessionHas('status');
        $firstResponse->assertSessionHas('status', __('You study :skill successfully. Book progress: :current/:target.', [
            'skill' => $skill->name,
            'current' => 1,
            'target' => 2,
        ]));

        $afterFirstRead = CharacterSkill::query()->firstOrFail();
        $this->assertSame(1, (int) $afterFirstRead->mastery_rank);
        $this->assertSame(1, (int) $afterFirstRead->mastery_progress);

        $this->travel(\App\Support\Game\MetinClassCatalog::skillBookCooldownMinutes() + 1)->minutes();

        $secondResponse = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'use-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $inventoryItem->fresh()->id,
        ]);

        $secondResponse->assertSessionHas('status');
        $learnedSkill = CharacterSkill::query()->firstOrFail();
        $this->assertSame(2, (int) $learnedSkill->mastery_rank);
        $this->assertSame(0, (int) $learnedSkill->mastery_progress);
    }

    public function test_skill_book_rejects_when_the_skill_is_not_yet_ready_for_book_progression(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 10,
            'race' => 'warrior',
            'skill_group' => 'body',
            'unspent_skill_points' => 0,
        ]);

        $skill = Skill::query()->where('slug', 'aura-of-the-sword')->firstOrFail();
        $book = Item::query()->where('slug', 'skill-book-aura-of-the-sword')->firstOrFail();

        CharacterSkill::query()->create([
            'game_character_id' => $character->id,
            'skill_id' => $skill->id,
            'rank' => 4,
            'mastery_rank' => 0,
        ]);

        $inventoryItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $book->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'use-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $inventoryItem->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('character_items', [
            'id' => $inventoryItem->id,
            'quantity' => 1,
        ]);
        $this->assertSame(4, (int) CharacterSkill::query()->firstOrFail()->rank);
    }

    public function test_skill_book_rejects_when_it_belongs_to_another_race_or_school(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 10,
            'race' => 'warrior',
            'skill_group' => 'body',
            'unspent_skill_points' => 0,
        ]);

        $skill = Skill::query()->where('slug', 'aura-of-the-sword')->firstOrFail();
        $foreignBook = Item::query()->where('slug', 'skill-book-lightning-throw')->firstOrFail();

        CharacterSkill::query()->create([
            'game_character_id' => $character->id,
            'skill_id' => $skill->id,
            'rank' => 5,
            'mastery_rank' => 0,
        ]);

        $inventoryItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $foreignBook->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'use-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $inventoryItem->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('character_items', [
            'id' => $inventoryItem->id,
            'quantity' => 1,
        ]);
        $this->assertSame(5, (int) CharacterSkill::query()->firstOrFail()->rank);
    }

    public function test_skill_book_sets_a_reading_cooldown_before_the_next_attempt(): void
    {
        $this->freezeTime();

        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 10,
            'race' => 'warrior',
            'skill_group' => 'body',
            'unspent_skill_points' => 0,
        ]);

        $skill = Skill::query()->where('slug', 'aura-of-the-sword')->firstOrFail();
        $book = Item::query()->where('slug', 'skill-book-aura-of-the-sword')->firstOrFail();

        CharacterSkill::query()->create([
            'game_character_id' => $character->id,
            'skill_id' => $skill->id,
            'rank' => 5,
            'mastery_rank' => 0,
        ]);

        $inventoryItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $book->id,
            'quantity' => 2,
        ]);

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'use-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $inventoryItem->id,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'use-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $inventoryItem->fresh()->id,
        ]);

        $response->assertSessionHas('error');
        $response->assertSessionHas('error', __('You need to wait :minutes minutes before studying this skill again.', [
            'minutes' => \App\Support\Game\MetinClassCatalog::skillBookCooldownMinutes(),
        ]));
    }

    public function test_soul_stone_advances_a_skill_from_m10_to_g1(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 14,
            'race' => 'warrior',
            'skill_group' => 'body',
            'unspent_skill_points' => 0,
        ]);

        $skill = Skill::query()->where('slug', 'aura-of-the-sword')->firstOrFail();

        CharacterSkill::query()->create([
            'game_character_id' => $character->id,
            'skill_id' => $skill->id,
            'rank' => 5,
            'mastery_rank' => 10,
            'grand_mastery_rank' => 0,
        ]);

        app(CharacterInventoryService::class)->grant($character, \App\Support\Game\MetinClassCatalog::soulStoneSlug(), 1);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'advance-skill-mastery']), [
            'idempotency_key' => (string) Str::uuid(),
            'skill_id' => $skill->id,
        ]);

        $response->assertSessionHas('status');
        $learnedSkill = CharacterSkill::query()->firstOrFail();
        $this->assertSame(1, (int) $learnedSkill->grand_mastery_rank);
        $this->assertNull($learnedSkill->perfect_mastered_at);
        $this->assertSame('G1', $learnedSkill->stageLabel());
        $this->assertSame(0, app(CharacterInventoryService::class)->quantityForSlug($character->fresh(), \App\Support\Game\MetinClassCatalog::soulStoneSlug()));
    }

    public function test_soul_stone_can_perfect_a_skill_after_g10(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 14,
            'race' => 'warrior',
            'skill_group' => 'body',
            'unspent_skill_points' => 0,
        ]);

        $skill = Skill::query()->where('slug', 'aura-of-the-sword')->firstOrFail();

        CharacterSkill::query()->create([
            'game_character_id' => $character->id,
            'skill_id' => $skill->id,
            'rank' => 5,
            'mastery_rank' => 10,
            'grand_mastery_rank' => 10,
        ]);

        app(CharacterInventoryService::class)->grant($character, \App\Support\Game\MetinClassCatalog::soulStoneSlug(), 1);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'advance-skill-mastery']), [
            'idempotency_key' => (string) Str::uuid(),
            'skill_id' => $skill->id,
        ]);

        $response->assertSessionHas('status');
        $learnedSkill = CharacterSkill::query()->firstOrFail();
        $this->assertNotNull($learnedSkill->perfect_mastered_at);
        $this->assertSame('P', $learnedSkill->stageLabel());
    }

    public function test_soul_stone_rejects_when_book_mastery_is_not_finished(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 14,
            'race' => 'warrior',
            'skill_group' => 'body',
            'unspent_skill_points' => 0,
        ]);

        $skill = Skill::query()->where('slug', 'aura-of-the-sword')->firstOrFail();

        CharacterSkill::query()->create([
            'game_character_id' => $character->id,
            'skill_id' => $skill->id,
            'rank' => 5,
            'mastery_rank' => 8,
            'grand_mastery_rank' => 0,
        ]);

        app(CharacterInventoryService::class)->grant($character, \App\Support\Game\MetinClassCatalog::soulStoneSlug(), 1);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'advance-skill-mastery']), [
            'idempotency_key' => (string) Str::uuid(),
            'skill_id' => $skill->id,
        ]);

        $response->assertSessionHas('error');
        $learnedSkill = CharacterSkill::query()->firstOrFail();
        $this->assertSame(0, (int) $learnedSkill->grand_mastery_rank);
        $this->assertSame(1, app(CharacterInventoryService::class)->quantityForSlug($character->fresh(), \App\Support\Game\MetinClassCatalog::soulStoneSlug()));
    }

    public function test_equipped_mount_reduces_travel_cooldown(): void
    {
        $this->freezeTime();

        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 20,
        ]);

        $mount = Item::query()->where('slug', 'brown-horse-reins')->firstOrFail();

        $inventoryItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $mount->id,
            'quantity' => 1,
        ]);

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'equip-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $inventoryItem->id,
        ]);

        $actionLogCountBeforeTravel = GameActionLog::query()->count();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'travel']), [
            'idempotency_key' => (string) Str::uuid(),
            'target_location_id' => Location::query()->where('slug', 'seungryong-valley')->firstOrFail()->id,
        ]);

        $character->refresh();

        $response->assertSessionHas('error', __('Fast travel is currently disabled.'));
        $this->assertSame('yongan', $character->location->slug);
        $this->assertSame($actionLogCountBeforeTravel, GameActionLog::query()->count());
    }

    public function test_level_five_character_can_choose_a_skill_group_and_learn_a_race_skill(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 5,
            'unspent_skill_points' => 1,
            'race' => 'warrior',
        ]);

        $groupResponse = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'choose-skill-group']), [
            'idempotency_key' => (string) Str::uuid(),
            'skill_group' => 'body',
        ]);

        $groupResponse->assertSessionHas('status');
        $character->refresh();
        $this->assertSame('body', $character->skill_group);

        $skill = Skill::query()->where('slug', 'aura-of-the-sword')->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'learn-skill']), [
            'idempotency_key' => (string) Str::uuid(),
            'skill_id' => $skill->id,
        ]);

        $character->refresh()->load('learnedSkills.skill');

        $response->assertSessionHas('status');
        $this->assertSame(0, (int) $character->unspent_skill_points);
        $this->assertSame(1, $character->learnedSkills->firstWhere('skill_id', $skill->id)?->rank);
    }

    public function test_skill_learning_blocks_when_skill_is_already_at_max_rank(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 20,
            'unspent_skill_points' => 1,
            'race' => 'warrior',
            'skill_group' => 'body',
        ]);

        $skill = Skill::query()->where('slug', 'aura-of-the-sword')->firstOrFail();

        CharacterSkill::query()->create([
            'game_character_id' => $character->id,
            'skill_id' => $skill->id,
            'rank' => $skill->max_rank,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'learn-skill']), [
            'idempotency_key' => (string) Str::uuid(),
            'skill_id' => $skill->id,
        ]);

        $character->refresh();

        $response->assertSessionHas('error');
        $this->assertSame(1, (int) $character->unspent_skill_points);
        $this->assertSame($skill->max_rank, CharacterSkill::query()->firstOrFail()->rank);
    }

    public function test_manual_battle_skill_use_rejects_when_current_sp_is_too_low(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 5,
            'race' => 'warrior',
            'skill_group' => 'body',
        ]);

        $skill = Skill::query()->where('slug', 'aura-of-the-sword')->firstOrFail();

        CharacterSkill::query()->create([
            'game_character_id' => $character->id,
            'skill_id' => $skill->id,
            'rank' => 1,
        ]);

        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'monster',
            'redirect_focus' => $area->slug,
        ]);

        $battle = GameBattle::query()
            ->where('game_character_id', $character->id)
            ->latest('id')
            ->firstOrFail();

        $state = $battle->state;
        data_set($state, 'player.current_sp', 0);
        $battle->state = $state;
        $battle->save();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'battle-use-skill']), [
            'idempotency_key' => (string) Str::uuid(),
            'battle_id' => $battle->id,
            'battle_round' => $battle->round,
            'enemy_id' => data_get($state, 'enemies.0.id'),
            'skill_slug' => $skill->slug,
            'redirect_focus' => $area->slug,
        ]);

        $response->assertSessionHas('error');
        $response->assertSessionHas('error', __('You do not have enough SP to use that skill.'));
    }

    public function test_global_chat_and_whisper_messages_are_persisted(): void
    {
        [$senderUser, $senderCharacter] = $this->createCharacterAt('yongan', [
            'level' => 3,
        ]);
        [$recipientUser, $recipientCharacter] = $this->createCharacterAt('joan', [
            'name' => 'WhisperTarget',
        ]);

        $this->actingAs($senderUser)->post(route('game.actions.store', ['action' => 'global-chat']), [
            'idempotency_key' => (string) Str::uuid(),
            'message' => 'Valley party at sundown.',
        ]);

        $this->actingAs($senderUser)->post(route('game.actions.store', ['action' => 'whisper']), [
            'idempotency_key' => (string) Str::uuid(),
            'recipient_name' => $recipientCharacter->name,
            'message' => 'Meet me at the gate.',
        ]);

        $this->assertDatabaseHas(GameMessage::class, [
            'sender_character_id' => $senderCharacter->id,
            'channel' => 'global',
            'body' => 'Valley party at sundown.',
        ]);

        $this->assertDatabaseHas(GameMessage::class, [
            'sender_character_id' => $senderCharacter->id,
            'recipient_character_id' => $recipientCharacter->id,
            'channel' => 'whisper',
            'body' => 'Meet me at the gate.',
        ]);
    }

    public function test_global_chat_is_idempotent_for_duplicate_submissions(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 2,
        ]);

        $payload = [
            'idempotency_key' => (string) Str::uuid(),
            'message' => 'One message only.',
        ];

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'global-chat']), $payload);
        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'global-chat']), $payload);

        $this->assertDatabaseCount('game_messages', 1);
        $this->assertDatabaseHas('game_messages', [
            'sender_character_id' => $character->id,
            'channel' => 'global',
            'body' => 'One message only.',
        ]);
        $this->assertSame(1, GameActionLog::query()->count());
    }

    public function test_idempotency_key_cannot_be_reused_for_a_different_action(): void
    {
        [$user] = $this->createCharacterAt('yongan', [
            'level' => 2,
        ]);

        $idempotencyKey = (string) Str::uuid();

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'global-chat']), [
            'idempotency_key' => $idempotencyKey,
            'message' => 'First and only.',
        ]);

        $secondResponse = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'rest']), [
            'idempotency_key' => $idempotencyKey,
            'rest_minutes' => 30,
        ]);

        $secondResponse->assertSessionHas('error');
        $this->assertDatabaseCount('game_messages', 1);
        $this->assertSame(1, GameActionLog::query()->count());
    }

    public function test_chat_validation_rejects_whitespace_only_messages(): void
    {
        [$user] = $this->createCharacterAt('yongan', [
            'level' => 3,
        ]);
        [, $recipientCharacter] = $this->createCharacterAt('joan', [
            'name' => 'QuietTarget',
        ]);

        $globalResponse = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'global-chat']), [
            'idempotency_key' => (string) Str::uuid(),
            'message' => '   ',
        ]);

        $whisperResponse = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'whisper']), [
            'idempotency_key' => (string) Str::uuid(),
            'recipient_name' => ' '.$recipientCharacter->name.' ',
            'message' => '   ',
        ]);

        $globalResponse->assertSessionHasErrors('message');
        $whisperResponse->assertSessionHasErrors('message');
        $this->assertDatabaseMissing('game_messages', [
            'channel' => 'global',
        ]);
        $this->assertDatabaseMissing('game_messages', [
            'channel' => 'whisper',
            'recipient_character_id' => $recipientCharacter->id,
        ]);
        $this->assertSame(0, GameActionLog::query()->count());
    }

    /**
     * @return array{0: User, 1: GameCharacter}
     */
    private function createCharacterAt(string $locationSlug, array $overrides = []): array
    {
        $location = Location::query()->where('slug', $locationSlug)->firstOrFail();
        $user = User::factory()->create();

        $character = GameCharacter::query()->create([
            'user_id' => $user->id,
            'location_id' => $location->id,
            'name' => 'Hero'.Str::upper(Str::random(6)),
            'faction' => 'Shinsoo',
            'race' => 'warrior',
            'skill_group' => null,
            'game_state' => GameCharacterState::Active,
            'tutorial_step' => null,
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
            'magic_attack' => 0,
            'sp' => 24,
            'max_sp' => 24,
            'unspent_skill_points' => 0,
            'unspent_status_points' => 0,
            'version' => 1,
            'last_active_at' => now(),
            'stamina_updated_at' => now(),
            'health_updated_at' => now(),
            'sp_updated_at' => now(),
            ...$overrides,
        ]);

        return [$user, $character];
    }
}
