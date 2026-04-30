<?php

namespace Tests\Feature;

use App\Enums\CharacterQuestStatus;
use App\Enums\GameCharacterState;
use App\Models\CharacterEquipment;
use App\Models\CharacterItem;
use App\Models\CharacterItemBonus;
use App\Models\CharacterQuest;
use App\Models\GameBattle;
use App\Models\CharacterSkill;
use App\Models\GameActionLog;
use App\Models\GameCharacter;
use App\Models\Item;
use App\Models\Location;
use App\Models\LocationArea;
use App\Models\Quest;
use App\Models\ShopItem;
use App\Models\Skill;
use App\Models\User;
use Database\Seeders\WorldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GameActionBranchCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldSeeder::class);
    }

    public function test_hunt_is_blocked_inside_safe_towns(): void
    {
        [$user] = $this->createCharacterAt('yongan');

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'hunt']), [
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, GameActionLog::query()->count());
    }

    public function test_hunt_requires_more_than_fifteen_health(): void
    {
        [$user] = $this->createCharacterAt('seungryong-valley', [
            'health' => 15,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'hunt']), [
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, GameActionLog::query()->count());
    }

    public function test_rest_is_blocked_outside_safe_towns(): void
    {
        [$user] = $this->createCharacterAt('seungryong-valley');

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'rest']), [
            'idempotency_key' => (string) Str::uuid(),
            'rest_minutes' => 30,
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, GameActionLog::query()->count());
    }

    public function test_deep_rest_uses_its_own_reward_branch_and_pays_out_at_the_end(): void
    {
        $this->freezeTime();

        [$user, $character] = $this->createCharacterAt('yongan', [
            'health' => 8,
            'stamina' => 1,
            'yang' => 77,
            'experience' => 19,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'rest']), [
            'idempotency_key' => (string) Str::uuid(),
            'rest_minutes' => 120,
        ]);

        $character->refresh();

        $response->assertSessionHas('status');
        $this->assertSame(8, (int) $character->health);
        $this->assertSame(1, (int) $character->stamina);
        $this->assertSame(77, (int) $character->yang);
        $this->assertSame(19, (int) $character->experience);
        $this->assertContains((int) now()->diffInSeconds($character->action_available_at, true), [7199, 7200]);

        $this->travel(121)->minutes();

        $this->actingAs($user)->get(route('dashboard'));
        $character->refresh();

        $this->assertSame(100, (int) $character->health);
        $this->assertSame(12, (int) $character->stamina);
        $this->assertGreaterThan(0, (int) $character->sp);
        $this->assertSame(297, (int) $character->yang);
        $this->assertSame(19, (int) $character->experience);
        $this->assertNull($character->action_available_at);
    }

    public function test_rest_completion_is_resolved_only_once_after_reopening_the_dashboard(): void
    {
        $this->freezeTime();

        [$user, $character] = $this->createCharacterAt('yongan', [
            'health' => 8,
            'stamina' => 1,
            'yang' => 77,
            'experience' => 19,
        ]);

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'rest']), [
            'idempotency_key' => (string) Str::uuid(),
            'rest_minutes' => 120,
        ])->assertSessionHas('status');

        $this->travel(121)->minutes();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $character->refresh();

        $resolvedState = [
            'health' => (int) $character->health,
            'stamina' => (int) $character->stamina,
            'sp' => (int) $character->sp,
            'yang' => (int) $character->yang,
            'experience' => (int) $character->experience,
            'action_available_at' => $character->action_available_at,
        ];

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $character->refresh();

        $this->assertSame($resolvedState['health'], (int) $character->health);
        $this->assertSame($resolvedState['stamina'], (int) $character->stamina);
        $this->assertSame($resolvedState['sp'], (int) $character->sp);
        $this->assertSame($resolvedState['yang'], (int) $character->yang);
        $this->assertSame($resolvedState['experience'], (int) $character->experience);
        $this->assertSame($resolvedState['action_available_at'], $character->action_available_at);
    }

    public function test_start_manual_fight_is_idempotent_and_does_not_double_spend_stamina(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan');

        $area = LocationArea::query()->where('slug', 'riverbank')->firstOrFail();
        $idempotencyKey = (string) Str::uuid();
        $payload = [
            'idempotency_key' => $idempotencyKey,
            'location_area_id' => $area->id,
            'encounter_request' => 'monster',
            'redirect_focus' => $area->slug,
        ];

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), $payload)
            ->assertRedirect();

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), $payload)
            ->assertRedirect();

        $character->refresh();

        $this->assertSame(7, (int) $character->stamina);
        $this->assertSame(1, GameBattle::query()
            ->where('game_character_id', $character->id)
            ->where('status', 'ongoing')
            ->count());
        $this->assertSame(1, GameActionLog::query()
            ->where('game_character_id', $character->id)
            ->where('action', 'start-manual-fight')
            ->where('idempotency_key', $idempotencyKey)
            ->count());
    }

    public function test_travel_is_disabled_even_when_the_character_has_a_route_target(): void
    {
        [$user] = $this->createCharacterAt('yongan', [
            'level' => 4,
            'stamina' => 1,
        ]);

        $targetLocation = Location::query()->where('slug', 'seungryong-valley')->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'travel']), [
            'idempotency_key' => (string) Str::uuid(),
            'target_location_id' => $targetLocation->id,
        ]);

        $response->assertSessionHas('error', __('Fast travel is currently disabled.'));
        $this->assertSame(0, GameActionLog::query()->count());
    }

    public function test_travel_does_not_change_location_when_the_feature_is_disabled(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 20,
            'stamina' => 12,
        ]);

        $targetLocation = Location::query()->where('slug', 'seungryong-valley')->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'travel']), [
            'idempotency_key' => (string) Str::uuid(),
            'target_location_id' => $targetLocation->id,
        ]);

        $character->refresh();

        $response->assertSessionHas('error', __('Fast travel is currently disabled.'));
        $this->assertSame('yongan', $character->location->slug);
        $this->assertSame(0, GameActionLog::query()->count());
    }

    public function test_learning_skill_rejects_other_race_skills(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 5,
            'unspent_skill_points' => 1,
            'race' => 'warrior',
            'skill_group' => 'body',
        ]);

        $foreignSkill = Skill::query()->where('slug', 'flying-talisman')->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'learn-skill']), [
            'idempotency_key' => (string) Str::uuid(),
            'skill_id' => $foreignSkill->id,
        ]);

        $character->refresh();

        $response->assertSessionHas('error');
        $this->assertSame(1, (int) $character->unspent_skill_points);
        $this->assertSame(0, CharacterSkill::query()->count());
    }

    public function test_learning_an_existing_skill_increments_its_rank(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 5,
            'unspent_skill_points' => 1,
            'race' => 'warrior',
            'skill_group' => 'body',
        ]);

        $skill = Skill::query()->where('slug', 'aura-of-the-sword')->firstOrFail();

        CharacterSkill::query()->create([
            'game_character_id' => $character->id,
            'skill_id' => $skill->id,
            'rank' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'learn-skill']), [
            'idempotency_key' => (string) Str::uuid(),
            'skill_id' => $skill->id,
        ]);

        $character->refresh();

        $response->assertSessionHas('status');
        $this->assertSame(0, (int) $character->unspent_skill_points);
        $this->assertSame(2, (int) CharacterSkill::query()->firstOrFail()->rank);
    }

    public function test_learning_skill_with_points_stops_once_book_progression_begins(): void
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
            'rank' => 16,
            'mastery_rank' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'learn-skill']), [
            'idempotency_key' => (string) Str::uuid(),
            'skill_id' => $skill->id,
        ]);

        $character->refresh();

        $response->assertSessionHas('error');
        $this->assertSame(1, (int) $character->unspent_skill_points);
        $this->assertSame(16, (int) CharacterSkill::query()->firstOrFail()->rank);
    }

    public function test_learning_skill_requires_choosing_a_combat_school_first(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 5,
            'unspent_skill_points' => 1,
            'race' => 'warrior',
            'skill_group' => null,
        ]);

        $skill = Skill::query()->where('slug', 'aura-of-the-sword')->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'learn-skill']), [
            'idempotency_key' => (string) Str::uuid(),
            'skill_id' => $skill->id,
        ]);

        $response->assertSessionHas('error');
        $character->refresh();
        $this->assertSame(1, (int) $character->unspent_skill_points);
        $this->assertSame(0, CharacterSkill::query()->count());
    }

    public function test_advancing_skill_mastery_requires_carrying_a_soul_stone(): void
    {
        [$user] = $this->createCharacterAt('yongan', [
            'level' => 14,
            'race' => 'warrior',
            'skill_group' => 'body',
        ]);

        $skill = Skill::query()->where('slug', 'aura-of-the-sword')->firstOrFail();

        CharacterSkill::query()->create([
            'game_character_id' => $user->character->id,
            'skill_id' => $skill->id,
            'rank' => 16,
            'mastery_rank' => 10,
            'grand_mastery_rank' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'advance-skill-mastery']), [
            'idempotency_key' => (string) Str::uuid(),
            'skill_id' => $skill->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(1, (int) CharacterSkill::query()->firstOrFail()->grand_mastery_rank);
    }

    public function test_skill_group_choice_can_only_happen_once(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 5,
            'race' => 'warrior',
            'skill_group' => 'body',
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'choose-skill-group']), [
            'idempotency_key' => (string) Str::uuid(),
            'skill_group' => 'mental',
        ]);

        $response->assertSessionHas('error');
        $character->refresh();
        $this->assertSame('body', $character->skill_group);
    }

    public function test_buy_item_rejects_items_from_other_town_shops(): void
    {
        [$user] = $this->createCharacterAt('yongan', [
            'yang' => 500,
        ]);

        $foreignShopItem = ShopItem::query()
            ->whereHas('shop.location', fn ($query) => $query->where('slug', 'joan'))
            ->firstOrFail();
        $localVendorArea = LocationArea::query()->where('slug', 'general-store')->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'buy-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $localVendorArea->id,
            'shop_item_id' => $foreignShopItem->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, GameActionLog::query()->count());
    }

    public function test_buy_item_respects_required_level_and_stock(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'yang' => 1000,
            'level' => 5,
        ]);

        $levelLockedItem = ShopItem::query()
            ->whereHas('shop.location', fn ($query) => $query->where('slug', 'yongan'))
            ->whereHas('item', fn ($query) => $query->where('slug', 'pine-shield'))
            ->with('item')
            ->firstOrFail();
        $levelLockedItem->item->update([
            'required_level' => 6,
        ]);
        $armorDealerArea = LocationArea::query()->where('slug', 'armor-dealer')->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'buy-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $armorDealerArea->id,
            'shop_item_id' => $levelLockedItem->id,
        ]);

        $response->assertSessionHas('error');
        $character->refresh();
        $this->assertSame(1000, (int) $character->yang);

        $outOfStockItem = ShopItem::query()
            ->whereHas('shop.location', fn ($query) => $query->where('slug', 'yongan'))
            ->whereHas('item', fn ($query) => $query->where('slug', 'small-red-potion'))
            ->firstOrFail();
        $generalStoreArea = LocationArea::query()->where('slug', 'general-store')->firstOrFail();
        $outOfStockItem->update(['stock' => 0]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'buy-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $generalStoreArea->id,
            'shop_item_id' => $outOfStockItem->id,
        ]);

        $response->assertSessionHas('error');
    }

    public function test_buy_item_rejects_equipment_for_the_wrong_race(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'yang' => 1000,
            'level' => 10,
            'race' => 'warrior',
        ]);

        $weaponDealerArea = LocationArea::query()->where('slug', 'weapon-dealer')->firstOrFail();
        $foreignWeaponOffer = ShopItem::query()
            ->whereHas('shop.location', fn ($query) => $query->where('slug', 'yongan'))
            ->whereHas('item', fn ($query) => $query->where('slug', 'cobra-dagger'))
            ->with('item')
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'buy-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $weaponDealerArea->id,
            'shop_item_id' => $foreignWeaponOffer->id,
        ]);

        $response->assertSessionHas('error');
        $character->refresh();
        $this->assertSame(1000, (int) $character->yang);
        $this->assertDatabaseMissing('character_items', [
            'game_character_id' => $character->id,
            'item_id' => $foreignWeaponOffer->item->id,
        ]);
    }

    public function test_shaman_cannot_buy_one_hand_weapon(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'yang' => 1000,
            'level' => 10,
            'race' => 'shaman',
        ]);

        $weaponDealerArea = LocationArea::query()->where('slug', 'weapon-dealer')->firstOrFail();
        $oneHandOffer = ShopItem::query()
            ->whereHas('shop.location', fn ($query) => $query->where('slug', 'yongan'))
            ->whereHas('item', fn ($query) => $query->where('slug', 'recruit-blade'))
            ->with('item')
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'buy-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $weaponDealerArea->id,
            'shop_item_id' => $oneHandOffer->id,
        ]);

        $response->assertSessionHas('error');
        $character->refresh();
        $this->assertSame(1000, (int) $character->yang);
        $this->assertDatabaseMissing('character_items', [
            'game_character_id' => $character->id,
            'item_id' => $oneHandOffer->item->id,
        ]);
    }

    public function test_blue_potions_stay_locked_until_skills_unlock_at_level_five(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'yang' => 1000,
            'level' => 4,
        ]);

        $generalStoreArea = LocationArea::query()->where('slug', 'general-store')->firstOrFail();
        $bluePotionOffer = ShopItem::query()
            ->whereHas('shop.location', fn ($query) => $query->where('slug', 'yongan'))
            ->whereHas('item', fn ($query) => $query->where('slug', 'small-blue-potion'))
            ->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'buy-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $generalStoreArea->id,
            'shop_item_id' => $bluePotionOffer->id,
        ]);

        $response->assertSessionHas('error');
        $character->refresh();
        $this->assertSame(1000, (int) $character->yang);
        $this->assertSame(0, CharacterItem::query()->where('game_character_id', $character->id)->count());
    }

    public function test_smith_upgrade_allows_armor_items(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'yang' => 200,
            'level' => 5,
        ]);

        $armor = Item::query()->where('slug', 'cotton-armor')->firstOrFail();
        $armorItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $armor->id,
            'quantity' => 1,
            'upgrade_level' => 0,
        ]);
        $smithArea = LocationArea::query()->where('slug', 'town-smith')->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'upgrade-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $smithArea->id,
            'character_item_id' => $armorItem->id,
        ]);

        $response->assertSessionHas('status');
        $this->assertDatabaseHas('character_items', [
            'id' => $armorItem->id,
            'upgrade_level' => 1,
        ]);
    }

    public function test_vendor_sell_rejects_equipped_items(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 5,
        ]);

        $weapon = Item::query()->where('slug', 'recruit-blade')->firstOrFail();
        $weaponItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $weapon->id,
            'quantity' => 1,
        ]);
        CharacterEquipment::query()->create([
            'game_character_id' => $character->id,
            'character_item_id' => $weaponItem->id,
            'slot' => 'weapon',
        ]);
        $vendorArea = LocationArea::query()->where('slug', 'weapon-dealer')->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'sell-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $vendorArea->id,
            'character_item_id' => $weaponItem->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('character_items', [
            'id' => $weaponItem->id,
        ]);
    }

    public function test_unlock_item_bonus_requires_four_existing_bonuses(): void
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

        $response->assertSessionHas('error');
        $targetItem->refresh();

        $this->assertSame(4, (int) $targetItem->bonus_slot_limit);
        $this->assertSame(1, CharacterItemBonus::query()->where('character_item_id', $targetItem->id)->count());
        $this->assertDatabaseHas('character_items', [
            'id' => $prismStack->id,
            'quantity' => 1,
        ]);
    }

    public function test_add_item_bonus_rejects_items_without_open_bonus_slots(): void
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
            'bonus_slot_limit' => 2,
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

        $response->assertSessionHas('error');
        $this->assertSame(2, CharacterItemBonus::query()->where('character_item_id', $targetItem->id)->count());
        $this->assertDatabaseHas('character_items', [
            'id' => $imprintStack->id,
        ]);
    }

    public function test_reroll_with_locked_bonus_requires_a_soul_anchor(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 5,
        ]);

        $armor = Item::query()->where('slug', 'cotton-armor')->firstOrFail();
        $rerollItem = Item::query()->where('slug', 'soul-reweaver')->firstOrFail();
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
        $rerollStack = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $rerollItem->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'reroll-item-bonuses']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $rerollStack->id,
            'target_character_item_id' => $targetItem->id,
            'locked_bonus_id' => $lockedBonus->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('character_items', [
            'id' => $rerollStack->id,
            'quantity' => 1,
        ]);
        $this->assertDatabaseHas('character_item_bonuses', [
            'id' => $lockedBonus->id,
            'bonus_type' => 'vitality',
            'bonus_value' => 4,
        ]);
    }

    public function test_equip_item_rejects_consumables_and_low_level_equipment(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 1,
        ]);

        $potion = Item::query()->where('slug', 'small-red-potion')->firstOrFail();
        $consumableItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $potion->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'equip-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $consumableItem->id,
        ]);

        $response->assertSessionHas('error');

        $mount = Item::query()->where('slug', 'brown-horse-reins')->firstOrFail();
        $mountItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $mount->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'equip-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $mountItem->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, CharacterEquipment::query()->count());
    }

    public function test_equipping_a_second_item_in_the_same_slot_replaces_the_old_equipment(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 1,
        ]);

        $weapon = Item::query()->where('slug', 'recruit-blade')->firstOrFail();

        $firstWeapon = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $weapon->id,
            'quantity' => 1,
        ]);

        $secondWeapon = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $weapon->id,
            'quantity' => 1,
        ]);

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'equip-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $firstWeapon->id,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'equip-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $secondWeapon->id,
        ]);

        $response->assertSessionHas('status');
        $this->assertSame(1, CharacterEquipment::query()->count());
        $this->assertDatabaseHas('character_equipment', [
            'game_character_id' => $character->id,
            'character_item_id' => $secondWeapon->id,
            'slot' => 'weapon',
        ]);
        $this->assertDatabaseMissing('character_equipment', [
            'character_item_id' => $firstWeapon->id,
        ]);
    }

    public function test_unequip_requires_the_item_to_be_currently_equipped(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan');

        $weapon = Item::query()->where('slug', 'recruit-blade')->firstOrFail();
        $inventoryItem = CharacterItem::query()->create([
            'game_character_id' => $character->id,
            'item_id' => $weapon->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'unequip-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $inventoryItem->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, CharacterEquipment::query()->count());
    }

    public function test_inventory_actions_reject_items_owned_by_other_characters(): void
    {
        [$user] = $this->createCharacterAt('yongan');
        [, $otherCharacter] = $this->createCharacterAt('joan', [
            'name' => 'OtherOwner',
        ]);

        $weapon = Item::query()->where('slug', 'recruit-blade')->firstOrFail();
        $foreignInventoryItem = CharacterItem::query()->create([
            'game_character_id' => $otherCharacter->id,
            'item_id' => $weapon->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'equip-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $foreignInventoryItem->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, CharacterEquipment::query()->count());
    }

    public function test_allocate_status_point_invests_into_the_selected_attribute(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'strength' => 2,
            'unspent_status_points' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'allocate-status-point']), [
            'idempotency_key' => (string) Str::uuid(),
            'attribute' => 'strength',
        ]);

        $character->refresh();

        $response->assertSessionHas('status');
        $this->assertSame(3, (int) $character->strength);
        $this->assertSame(0, (int) $character->unspent_status_points);
    }

    public function test_claim_quest_marks_ready_rewards_as_completed_and_grants_the_payout(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'experience' => 10,
            'yang' => 5,
        ]);

        $quest = Quest::query()->where('slug', 'yongan-first-sweep')->firstOrFail();
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

        $character->refresh();
        $characterQuest->refresh();

        $response->assertSessionHas('status');
        $this->assertSame(CharacterQuestStatus::Completed, $characterQuest->status);
        $this->assertSame(10 + (int) $quest->reward_experience, (int) $character->experience);
        $this->assertSame(5 + (int) $quest->reward_yang, (int) $character->yang);
    }

    private function createCharacterAt(string $locationSlug, array $overrides = []): array
    {
        $location = Location::query()->where('slug', $locationSlug)->firstOrFail();
        $user = User::factory()->create();

        $character = GameCharacter::query()->create([
            'user_id' => $user->id,
            'location_id' => $location->id,
            'name' => 'BranchHero'.Str::upper(Str::random(6)),
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
