<?php

namespace Tests\Feature;

use App\Enums\GameCharacterState;
use App\Models\CharacterEquipment;
use App\Models\CharacterItem;
use App\Models\CharacterSkill;
use App\Models\GameActionLog;
use App\Models\GameBattle;
use App\Models\GameCharacter;
use App\Models\Item;
use App\Models\Location;
use App\Models\LocationArea;
use App\Models\Skill;
use App\Models\User;
use App\Support\Game\TutorialFlow;
use Database\Seeders\WorldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TutorialFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game_tutorial.enabled', true);
        $this->seed(WorldSeeder::class);
    }

    public function test_registration_starts_the_player_in_the_tutorial_state(): void
    {
        $stepOneTitle = trans('game.tutorial.steps.1.title');
        $stepOneBody = trans('game.tutorial.steps.1.body');

        $response = $this->post(route('register'), [
            'character_name' => 'TutorialHero',
            'race' => 'warrior',
            'email' => 'tutorial@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $character = GameCharacter::query()->where('name', 'TutorialHero')->firstOrFail();

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertSame(GameCharacterState::Tutorial, $character->game_state);
        $this->assertSame(1, (int) $character->tutorial_step);

        $dashboardResponse = $this->actingAs($character->user)->get(route('dashboard'));

        $dashboardResponse
            ->assertOk()
            ->assertSee(__('Tutorial Step').' 1/12')
            ->assertSee($stepOneTitle)
            ->assertSee($stepOneBody)
            ->assertSee('data-tutorial-step="1"', false)
            ->assertSee('x-show="!tutorialDriverActive || tutorial?.step === 6"', false)
            ->assertDontSee('tutorialStep === 6', false)
            ->assertSee('tutorial-character-panel')
            ->assertDontSee(__('Chat and Whispers'))
            ->assertDontSee(__('Weapon Dealer'));
    }

    public function test_tutorial_navigation_reveals_hotspots_step_by_step(): void
    {
        [$user, $character] = $this->createTutorialCharacter('yongan', 1);

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'tutorial-next']), [
            'idempotency_key' => (string) Str::uuid(),
            'redirect_focus' => 'inn',
        ]);

        $character->refresh();
        $this->assertSame(2, (int) $character->tutorial_step);

        $stepTwoResponse = $this->actingAs($user)->get(route('dashboard'));

        $stepTwoResponse
            ->assertOk()
            ->assertSee('Lantern Inn')
            ->assertSee('Riverbank');

        $stepTwoDocument = new \DOMDocument;
        @$stepTwoDocument->loadHTML($stepTwoResponse->getContent());
        $stepTwoXpath = new \DOMXPath($stepTwoDocument);
        $generalStoreLinks = $stepTwoXpath->query('//a[contains(@href, "focus=general-store")]');

        $this->assertSame(0, $generalStoreLinks?->length);

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'tutorial-next']), [
            'idempotency_key' => (string) Str::uuid(),
            'redirect_focus' => 'general-store',
        ]);

        $character->refresh();
        $this->assertSame(3, (int) $character->tutorial_step);

        $stepThreeResponse = $this->actingAs($user)->get(route('dashboard'));

        $stepThreeResponse->assertOk()->assertSee('General Store');
    }

    public function test_tutorial_blocks_regular_gameplay_actions_before_the_fight_step(): void
    {
        [$user] = $this->createTutorialCharacter('yongan', 1);
        $riverbank = LocationArea::query()->where('slug', 'riverbank')->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'fight-monsters']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $riverbank->id,
            'redirect_focus' => $riverbank->slug,
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, GameActionLog::query()->count());
    }

    public function test_tutorial_start_fight_step_only_allows_the_configured_manual_monster_battle(): void
    {
        [$user, $character] = $this->createTutorialCharacter('yongan', 7);
        $riverbank = LocationArea::query()->where('slug', 'riverbank')->firstOrFail();

        $invalidResponse = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $riverbank->id,
            'encounter_request' => 'lumiis',
            'redirect_focus' => $riverbank->slug,
        ]);

        $invalidResponse->assertSessionHas('error');
        $this->assertDatabaseCount('game_battles', 0);

        $validResponse = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $riverbank->id,
            'encounter_request' => 'monster',
            'redirect_focus' => $riverbank->slug,
        ]);

        $character->refresh();
        $battle = GameBattle::query()->where('game_character_id', $character->id)->latest('id')->firstOrFail();

        $validResponse->assertSessionHas('status');
        $this->assertSame(8, (int) $character->tutorial_step);
        $this->assertSame(12, (int) $character->stamina);
        $this->assertSame('3x Wildhund', $battle->encounter_name);
        $this->assertSame('ongoing', $battle->status);
    }

    public function test_tutorial_inventory_step_requires_weapon_and_armor_equipped_before_continue(): void
    {
        [$user, $character] = $this->createTutorialCharacter('yongan', 6);

        $blockedResponse = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'tutorial-next']), [
            'idempotency_key' => (string) Str::uuid(),
            'redirect_focus' => 'riverbank',
        ]);

        $blockedResponse->assertSessionHas('error');

        $weapon = $character->inventoryItems->first(fn (CharacterItem $item): bool => $item->item?->slug === 'recruit-blade');
        $armor = $character->inventoryItems->first(fn (CharacterItem $item): bool => $item->item?->slug === 'cotton-armor');

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'equip-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $weapon->id,
            'redirect_focus' => 'riverbank',
        ]);

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'equip-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'character_item_id' => $armor->id,
            'redirect_focus' => 'riverbank',
        ]);

        $advanceResponse = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'tutorial-next']), [
            'idempotency_key' => (string) Str::uuid(),
            'redirect_focus' => 'riverbank',
        ]);

        $character->refresh();

        $advanceResponse->assertSessionHas('status');
        $this->assertSame(7, (int) $character->tutorial_step);
    }

    public function test_tutorial_requires_the_battle_ui_step_before_the_first_attack(): void
    {
        [$user, $character] = $this->createTutorialCharacter('yongan', 7);
        $riverbank = LocationArea::query()->where('slug', 'riverbank')->firstOrFail();

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $riverbank->id,
            'encounter_request' => 'monster',
            'redirect_focus' => $riverbank->slug,
        ]);

        $battle = GameBattle::query()->where('game_character_id', $character->id)->where('status', 'ongoing')->latest('id')->firstOrFail();

        $blockedAttack = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'battle-attack']), [
            'idempotency_key' => (string) Str::uuid(),
            'battle_id' => $battle->id,
            'battle_round' => $battle->round,
            'enemy_id' => 'enemy-1',
            'redirect_focus' => $riverbank->slug,
        ]);

        $blockedAttack->assertSessionHas('error');

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'tutorial-next']), [
            'idempotency_key' => (string) Str::uuid(),
            'redirect_focus' => $riverbank->slug,
        ]);

        $character->refresh();
        $this->assertSame(9, (int) $character->tutorial_step);
    }

    public function test_tutorial_active_battle_rejects_direct_skill_requests_even_if_the_character_knows_a_skill(): void
    {
        [$user, $character] = $this->createTutorialCharacter('yongan', 7, [
            'level' => 5,
            'race' => 'warrior',
            'skill_group' => 'body',
        ]);
        $riverbank = LocationArea::query()->where('slug', 'riverbank')->firstOrFail();
        $skill = Skill::query()->where('slug', 'aura-of-the-sword')->firstOrFail();

        CharacterSkill::query()->create([
            'game_character_id' => $character->id,
            'skill_id' => $skill->id,
            'rank' => 1,
        ]);

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $riverbank->id,
            'encounter_request' => 'monster',
            'redirect_focus' => $riverbank->slug,
        ]);

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'tutorial-next']), [
            'idempotency_key' => (string) Str::uuid(),
            'redirect_focus' => $riverbank->slug,
        ]);

        $battle = GameBattle::query()->where('game_character_id', $character->id)->where('status', 'ongoing')->latest('id')->firstOrFail();

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'battle-use-skill']), [
            'idempotency_key' => (string) Str::uuid(),
            'battle_id' => $battle->id,
            'battle_round' => $battle->round,
            'enemy_id' => 'enemy-1',
            'skill_slug' => $skill->slug,
            'redirect_focus' => $riverbank->slug,
        ]);

        $response->assertSessionHas('error');
        $response->assertSessionHas('error', __('Finish the tutorial battle before using other features.'));
    }

    public function test_tutorial_battle_advances_progress_and_heals_after_victory(): void
    {
        [$user, $character] = $this->createTutorialCharacter('yongan', 7, [
            'health' => 42,
            'stamina' => 3,
        ]);
        $riverbank = LocationArea::query()->where('slug', 'riverbank')->firstOrFail();

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $riverbank->id,
            'encounter_request' => 'monster',
            'redirect_focus' => $riverbank->slug,
        ]);

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'tutorial-next']), [
            'idempotency_key' => (string) Str::uuid(),
            'redirect_focus' => $riverbank->slug,
        ]);

        $battle = GameBattle::query()->where('game_character_id', $character->id)->where('status', 'ongoing')->latest('id')->firstOrFail();
        $state = $battle->state;
        $state['enemies'] = collect($state['enemies'] ?? [])
            ->map(fn (array $enemy): array => [...$enemy, 'current_health' => 1])
            ->values()
            ->all();
        $battle->update(['state' => $state]);

        $attackResponse = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'battle-attack']), [
            'idempotency_key' => (string) Str::uuid(),
            'battle_id' => $battle->id,
            'battle_round' => $battle->round,
            'enemy_id' => 'enemy-1',
            'redirect_focus' => $riverbank->slug,
        ]);

        $character->refresh();
        $character->load('equipment.characterItem.item');
        $battle->refresh();

        $attackResponse->assertSessionHas('manual_battle_result_log_id');
        $this->assertSame(11, (int) $character->tutorial_step);
        $this->assertSame(GameCharacterState::Tutorial, $character->game_state);
        $this->assertSame($character->effectiveMaxHealth(), (int) $character->health);
        $this->assertGreaterThan(0, (int) $character->experience);
        $this->assertGreaterThan(0, (int) $character->yang);
        $this->assertSame('victory', $battle->status);
    }

    public function test_tutorial_completion_switches_the_character_into_the_normal_game_state(): void
    {
        [$user, $character] = $this->createTutorialCharacter('yongan', 11);

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'tutorial-next']), [
            'idempotency_key' => (string) Str::uuid(),
            'redirect_focus' => 'riverbank',
        ]);

        $character->refresh();
        $this->assertSame(12, (int) $character->tutorial_step);

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'tutorial-next']), [
            'idempotency_key' => (string) Str::uuid(),
            'redirect_focus' => 'riverbank',
        ]);

        $character->refresh();

        $this->assertSame(GameCharacterState::Active, $character->game_state);
        $this->assertNull($character->tutorial_step);

        $dashboardResponse = $this->actingAs($user)->get(route('dashboard'));

        $dashboardResponse
            ->assertOk()
            ->assertDontSee(__('Locked onboarding'))
            ->assertSee(__('Map Questline'))
            ->assertSee(trans('quests.yongan-first-sweep.title'))
            ->assertDontSee(__('Chat and Whispers'));
    }

    public function test_weapon_dealer_armor_dealer_and_smith_unlock_at_level_five(): void
    {
        [$user, $character] = $this->createTutorialCharacter('yongan', 12, [
            'game_state' => GameCharacterState::Active,
            'tutorial_step' => null,
            'level' => 4,
        ]);

        $lockedResponse = $this->actingAs($user)->get(route('dashboard'));

        $lockedResponse
            ->assertOk()
            ->assertDontSee('Weapon Dealer')
            ->assertDontSee('Armor Dealer')
            ->assertDontSee(__('Town Smith'));

        $character->update([
            'level' => 5,
        ]);

        $unlockedResponse = $this->actingAs($user)->get(route('dashboard'));

        $unlockedResponse
            ->assertOk()
            ->assertSee('Weapon Dealer')
            ->assertSee('Armor Dealer')
            ->assertSee(__('Town Smith'));
    }

    /**
     * @return array{0: User, 1: GameCharacter}
     */
    private function createTutorialCharacter(string $locationSlug, int $tutorialStep, array $overrides = []): array
    {
        $location = Location::query()->where('slug', $locationSlug)->firstOrFail();
        $user = User::factory()->create();

        $character = GameCharacter::query()->create([
            'user_id' => $user->id,
            'location_id' => $location->id,
            'name' => 'Tutor'.Str::upper(Str::random(6)),
            'faction' => 'Shinsoo',
            'race' => 'warrior',
            'skill_group' => null,
            'game_state' => GameCharacterState::Tutorial,
            'tutorial_step' => $tutorialStep,
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

        $weapon = Item::query()->where('slug', 'recruit-blade')->first();
        $armor = Item::query()->where('slug', 'cotton-armor')->first();

        if ($weapon !== null) {
            $weaponItem = CharacterItem::query()->create([
                'game_character_id' => $character->id,
                'item_id' => $weapon->id,
                'quantity' => 1,
            ]);

            if (($character->tutorial_step ?? 0) >= TutorialFlow::STEP_START_FIGHT) {
                CharacterEquipment::query()->create([
                    'game_character_id' => $character->id,
                    'character_item_id' => $weaponItem->id,
                    'slot' => 'weapon',
                ]);
            }
        }

        if ($armor !== null) {
            $armorItem = CharacterItem::query()->create([
                'game_character_id' => $character->id,
                'item_id' => $armor->id,
                'quantity' => 1,
            ]);

            if (($character->tutorial_step ?? 0) >= TutorialFlow::STEP_START_FIGHT) {
                CharacterEquipment::query()->create([
                    'game_character_id' => $character->id,
                    'character_item_id' => $armorItem->id,
                    'slot' => 'armor',
                ]);
            }
        }

        $character->load('inventoryItems.item', 'inventoryItems.equipment', 'equipment.characterItem.item');

        return [$user, $character];
    }
}
