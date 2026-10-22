<?php

namespace Tests\Feature;

use App\Enums\CharacterQuestStatus;
use App\Enums\GameCharacterState;
use App\Models\CharacterAreaProgress;
use App\Models\CharacterItem;
use App\Models\CharacterQuest;
use App\Models\GameActionLog;
use App\Models\GameBattle;
use App\Models\GameCharacter;
use App\Models\Item;
use App\Models\Location;
use App\Models\LocationArea;
use App\Models\Quest;
use App\Models\User;
use App\Services\Game\CharacterInventoryService;
use Database\Seeders\WorldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuestProgressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldSeeder::class);
    }

    public function test_level_one_dashboard_shows_the_starter_questline_and_locked_feature_notes(): void
    {
        [$user, $character] = $this->createActiveCharacterAt('yongan');

        $response = $this->actingAs($user)->get(route('dashboard', [
            'focus' => 'riverbank',
        ]));

        $response
            ->assertOk()
            ->assertSee(__(':location Questline', ['location' => 'Yongan']))
            ->assertSee(trans('quests.yongan-first-sweep.title'))
            ->assertSee(trans('quests.yongan-first-sweep.feature_unlock_hint'))
            ->assertSee(__('Fight Monsters'))
            ->assertDontSee(__('Chat and Whispers'));

        $firstSweep = $this->questFor($character, 'yongan-first-sweep');

        $this->assertSame(CharacterQuestStatus::Active, $firstSweep->status);
        $this->assertSame(0, (int) $firstSweep->progress);
    }

    public function test_map_two_dashboard_shows_the_valley_questline(): void
    {
        [$user, $character] = $this->createActiveCharacterAt('seungryong-valley', [
            'level' => 20,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'focus' => 'orc-approach',
        ]));

        $response
            ->assertOk()
            ->assertSee(__(':location Questline', ['location' => 'Seungryong Valley']))
            ->assertSee(trans('quests.seungryong-frontier-sweep.title'))
            ->assertSee(trans('quests.seungryong-frontier-sweep.feature_unlock_hint'));

        $frontierSweep = $this->questFor($character, 'seungryong-frontier-sweep');

        $this->assertSame(CharacterQuestStatus::Active, $frontierSweep->status);
        $this->assertSame(0, (int) $frontierSweep->progress);
    }

    public function test_monkey_dungeon_dashboard_shows_the_dungeon_questline(): void
    {
        [$user, $character] = $this->createActiveCharacterAt('monkey-dungeon', [
            'level' => 26,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'focus' => 'lower-halls',
        ]));

        $response
            ->assertOk()
            ->assertSee(__(':location Questline', ['location' => 'Monkey Dungeon']))
            ->assertSee(trans('quests.monkey-lower-halls-sweep.title'))
            ->assertSee(trans('quests.monkey-lower-halls-sweep.feature_unlock_hint'));

        $lowerHallsSweep = $this->questFor($character, 'monkey-lower-halls-sweep');

        $this->assertSame(CharacterQuestStatus::Active, $lowerHallsSweep->status);
        $this->assertSame(0, (int) $lowerHallsSweep->progress);
    }

    public function test_map_two_biologist_panel_appears_after_the_valley_boss_loop(): void
    {
        [$user, $character] = $this->createActiveCharacterAt('seungryong-valley', [
            'level' => 34,
        ]);

        $this->markQuestCompleted($character, 'seungryong-frontier-sweep', 3);
        $this->markQuestCompleted($character, 'seungryong-first-lumiis', 1);
        $this->markQuestCompleted($character, 'seungryong-war-chief-release', 1);
        $this->markQuestCompleted($character, 'seungryong-stone-shelf-push', 2);
        $this->markQuestCompleted($character, 'seungryong-black-orc-release', 1);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'focus' => 'field-biologist',
        ]));

        $response
            ->assertOk()
            ->assertSee(trans('quests.seungryong-biologist-research.title'))
            ->assertSee('Field Biologist');

        $biologistQuest = $this->questFor($character, 'seungryong-biologist-research');

        $this->assertSame(CharacterQuestStatus::Active, $biologistQuest->status);
    }

    public function test_monkey_dungeon_biologist_panel_appears_after_the_ape_lord_loop(): void
    {
        [$user, $character] = $this->createActiveCharacterAt('monkey-dungeon', [
            'level' => 38,
        ]);

        $this->markQuestCompleted($character, 'monkey-lower-halls-sweep', 3);
        $this->markQuestCompleted($character, 'monkey-first-lumiis', 1);
        $this->markQuestCompleted($character, 'monkey-captain-release', 1);
        $this->markQuestCompleted($character, 'monkey-throne-climb', 2);
        $this->markQuestCompleted($character, 'monkey-ape-lord-release', 1);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'focus' => 'expedition-biologist',
        ]));

        $response
            ->assertOk()
            ->assertSee(trans('quests.monkey-biologist-research.title'))
            ->assertSee('Expedition Biologist');

        $biologistQuest = $this->questFor($character, 'monkey-biologist-research');

        $this->assertSame(CharacterQuestStatus::Active, $biologistQuest->status);
    }

    public function test_two_monster_fights_complete_the_first_quest_and_unlock_lumiis_and_global_chat(): void
    {
        [$user, $character] = $this->createActiveCharacterAt('yongan', [
            'experience' => 40,
        ]);
        $area = $this->starterArea('riverbank');

        $area->update([
            'boss_base_chance' => 0,
            'boss_pressure_chance_per_point' => 0,
            'boss_pressure_threshold' => 9999,
        ]);

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'fight-monsters']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'redirect_focus' => $area->slug,
        ]);

        $character->refresh();
        $character->update([
            'action_available_at' => null,
            'health' => (int) $character->max_health,
            'stamina' => (int) $character->max_stamina,
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
        $firstSweep = $this->questFor($character, 'yongan-first-sweep');

        $response->assertSessionMissing('status');
        $this->assertSame(2, (int) $progress->monster_fights);
        $this->assertSame(CharacterQuestStatus::ReadyToClaim, $firstSweep->status);
        $this->assertSame(2, (int) $firstSweep->progress);
        $this->assertSame(1, (int) $character->level);
        $this->assertFalse($character->lumiisUnlocked());
        $this->assertFalse($character->globalChatUnlocked());
        $this->assertStringContainsString(
            trans('quests.yongan-first-sweep.title'),
            collect((array) session('quest_updates'))->implode(' || '),
        );

        $this->claimQuestReward($user, $character, 'yongan-first-sweep', $area->slug);
        $character->refresh();
        $firstSweep->refresh();

        $this->assertSame(2, (int) $character->level);
        $this->assertTrue($character->lumiisUnlocked());
        $this->assertTrue($character->globalChatUnlocked());
        $this->assertSame(CharacterQuestStatus::Completed, $firstSweep->status);

        $dashboard = $this->actingAs($user)->get(route('dashboard', [
            'focus' => $area->slug,
        ]));
        $firstLumiis = $this->questFor($character, 'yongan-first-lumiis');

        $dashboard
            ->assertOk()
            ->assertSee(__('Chat and Whispers'))
            ->assertSee(__('Whisper Lv 3'))
            ->assertSee(trans('quests.yongan-first-lumiis.title'));

        $this->assertSame(CharacterQuestStatus::Active, $firstLumiis->status);
    }

    public function test_first_lumiis_quest_becomes_ready_only_after_the_manual_fight_resolves(): void
    {
        [$user, $character] = $this->createActiveCharacterAt('yongan', [
            'level' => 2,
            'health' => 400,
            'max_health' => 400,
            'attack' => 60,
            'defense' => 40,
            'stamina' => 15,
            'max_stamina' => 15,
        ]);
        $area = $this->starterArea('riverbank');

        $area->update([
            'boss_base_chance' => 0,
            'boss_pressure_chance_per_point' => 0,
            'boss_pressure_threshold' => 9999,
        ]);

        CharacterAreaProgress::query()->create([
            'game_character_id' => $character->id,
            'location_area_id' => $area->id,
            'monster_fights' => 2,
            'lumiis_searches' => 1,
        ]);
        $this->markQuestCompleted($character, 'yongan-first-sweep', 2);

        $this->actingAs($user)->get(route('dashboard', [
            'focus' => $area->slug,
        ]))->assertOk();

        $quest = $this->questFor($character, 'yongan-first-lumiis');
        $this->assertSame(CharacterQuestStatus::Active, $quest->status);

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'lumiis',
            'redirect_focus' => $area->slug,
        ])->assertRedirect();

        // The objective counter is already full, but the quest must not flip while
        // the player is still inside the manual battle.
        $this->assertSame(CharacterQuestStatus::Active, $quest->fresh()->status);

        $battle = GameBattle::query()
            ->where('game_character_id', $character->id)
            ->where('status', 'ongoing')
            ->latest('id')
            ->firstOrFail();

        // Lumiis fights spawn soul waves as the core fractures. Weaken every wave
        // and attack until the battle really resolves, asserting that the quest stays
        // Active for all non-final turns.
        for ($attempt = 0; $attempt < 20 && $battle->fresh()->status === 'ongoing'; $attempt++) {
            $battle->refresh();
            $state = $battle->state;
            $aliveEnemies = collect($state['enemies'] ?? [])
                ->filter(fn (array $enemy): bool => (int) ($enemy['current_health'] ?? 0) > 0)
                ->values();

            if ($aliveEnemies->isEmpty()) {
                break;
            }

            $state['enemies'] = collect($state['enemies'] ?? [])
                ->map(fn (array $enemy): array => [...$enemy, 'current_health' => 1])
                ->values()
                ->all();
            $battle->update(['state' => $state]);

            $this->actingAs($user)->post(route('game.actions.store', ['action' => 'battle-attack']), [
                'idempotency_key' => (string) Str::uuid(),
                'battle_id' => $battle->id,
                'battle_round' => $battle->round,
                'enemy_id' => (string) data_get($aliveEnemies->first(), 'id'),
                'redirect_focus' => $area->slug,
            ])->assertRedirect();

            if ($battle->fresh()->status === 'ongoing') {
                $this->assertSame(CharacterQuestStatus::Active, $quest->fresh()->status);
            }
        }

        $battle->refresh();

        $this->assertSame('victory', $battle->status);
        $this->assertSame(CharacterQuestStatus::ReadyToClaim, $quest->fresh()->status);
    }

    public function test_first_lumiis_quest_can_push_the_character_to_level_three_and_unlock_whispers(): void
    {
        [$user, $character] = $this->createActiveCharacterAt('yongan', [
            'level' => 2,
            'experience' => 212,
            'health' => 180,
            'max_health' => 180,
            'attack' => 28,
            'defense' => 18,
        ]);
        $area = $this->starterArea('riverbank');

        $area->update([
            'boss_base_chance' => 0,
            'boss_pressure_chance_per_point' => 0,
            'boss_pressure_threshold' => 9999,
        ]);

        CharacterAreaProgress::query()->create([
            'game_character_id' => $character->id,
            'location_area_id' => $area->id,
            'monster_fights' => 2,
        ]);
        $this->markQuestCompleted($character, 'yongan-first-sweep', 2);

        $this->actingAs($user)->get(route('dashboard', [
            'focus' => $area->slug,
        ]));

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'search-lumiis']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'redirect_focus' => $area->slug,
        ]);

        $character->refresh();
        $response->assertSessionMissing('status');
        $questUpdateText = collect((array) session('quest_updates'))->implode(' || ');
        $this->assertStringContainsString(trans('quests.yongan-first-lumiis.title'), $questUpdateText);
        $smallRedPotion = Item::query()->where('slug', 'small-red-potion')->firstOrFail();
        $smallBluePotion = Item::query()->where('slug', 'small-blue-potion')->firstOrFail();
        $smallRedQuantity = (int) CharacterItem::query()
            ->where('game_character_id', $character->id)
            ->where('item_id', $smallRedPotion->id)
            ->sum('quantity');
        $smallBlueQuantity = (int) CharacterItem::query()
            ->where('game_character_id', $character->id)
            ->where('item_id', $smallBluePotion->id)
            ->sum('quantity');

        $this->assertSame(0, $smallRedQuantity);
        $this->assertSame(0, $smallBlueQuantity);

        $dashboard = $this->actingAs($user)->get(route('dashboard', [
            'focus' => $area->slug,
        ]));
        $firstLumiis = $this->questFor($character, 'yongan-first-lumiis');

        $this->assertSame(CharacterQuestStatus::ReadyToClaim, $firstLumiis->status);

        $this->claimQuestReward($user, $character, 'yongan-first-lumiis', $area->slug);
        $fieldRank = $this->questFor($character, 'yongan-field-rank');
        $this->assertSame(CharacterQuestStatus::ReadyToClaim, $fieldRank->status);
        $this->claimQuestReward($user, $character, 'yongan-field-rank', $area->slug);
        $character->refresh();
        $firstLumiis->refresh();
        $fieldRank->refresh();

        $smallRedQuantity = (int) CharacterItem::query()
            ->where('game_character_id', $character->id)
            ->where('item_id', $smallRedPotion->id)
            ->sum('quantity');
        $smallBlueQuantity = (int) CharacterItem::query()
            ->where('game_character_id', $character->id)
            ->where('item_id', $smallBluePotion->id)
            ->sum('quantity');

        $dashboard
            ->assertOk()
            ->assertSee(__('Chat and Whispers'));

        $this->assertSame(CharacterQuestStatus::Completed, $firstLumiis->status);
        $this->assertSame(CharacterQuestStatus::Completed, $fieldRank->status);
        $this->assertGreaterThanOrEqual(2, $smallRedQuantity);
        $this->assertSame(0, $smallBlueQuantity);
        $this->assertSame(3, (int) $character->level);
        $this->assertTrue($character->whispersUnlocked());
        $this->assertFalse($character->travelRoutesUnlocked());
    }

    public function test_locked_features_reject_direct_post_requests_until_their_required_level(): void
    {
        [$levelOneUser] = $this->createActiveCharacterAt('yongan');
        $starterArea = $this->starterArea('riverbank');

        $levelOneLumiis = $this->actingAs($levelOneUser)->post(route('game.actions.store', ['action' => 'search-lumiis']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $starterArea->id,
            'redirect_focus' => $starterArea->slug,
        ]);
        $levelOneChat = $this->actingAs($levelOneUser)->post(route('game.actions.store', ['action' => 'global-chat']), [
            'idempotency_key' => (string) Str::uuid(),
            'message' => 'Too early.',
        ]);

        [$levelTwoUser, $levelTwoCharacter] = $this->createActiveCharacterAt('yongan', [
            'level' => 2,
        ]);
        [, $recipientCharacter] = $this->createActiveCharacterAt('joan', [
            'name' => 'LaterWhisper',
        ]);

        $levelTwoWhisper = $this->actingAs($levelTwoUser)->post(route('game.actions.store', ['action' => 'whisper']), [
            'idempotency_key' => (string) Str::uuid(),
            'recipient_name' => $recipientCharacter->name,
            'message' => 'Not yet unlocked.',
        ]);

        [$levelThreeUser, $levelThreeCharacter] = $this->createActiveCharacterAt('yongan', [
            'level' => 3,
        ]);
        CharacterAreaProgress::query()->create([
            'game_character_id' => $levelThreeCharacter->id,
            'location_area_id' => $starterArea->id,
            'pressure' => (int) $starterArea->boss_pressure_threshold,
        ]);

        $levelThreeTravel = $this->actingAs($levelThreeUser)->post(route('game.actions.store', ['action' => 'travel']), [
            'idempotency_key' => (string) Str::uuid(),
            'target_location_id' => Location::query()->where('slug', 'seungryong-valley')->firstOrFail()->id,
        ]);
        $levelThreeBoss = $this->actingAs($levelThreeUser)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $starterArea->id,
            'encounter_request' => 'boss',
            'redirect_focus' => $starterArea->slug,
        ]);

        $levelOneLumiis->assertSessionHas('error');
        $levelOneChat->assertSessionHas('error');
        $levelTwoWhisper->assertSessionHas('error');
        $levelThreeTravel->assertSessionHas('error');
        $levelThreeBoss->assertSessionHas('error');
        $this->assertSame(0, GameActionLog::query()->count());
    }

    public function test_boss_quest_completion_can_reach_level_five_and_open_advanced_town_services(): void
    {
        [$user, $character] = $this->createActiveCharacterAt('yongan', [
            'level' => 4,
            'experience' => 420,
        ]);
        $area = $this->starterArea('riverbank');

        CharacterAreaProgress::query()->create([
            'game_character_id' => $character->id,
            'location_area_id' => $area->id,
            'monster_fights' => 2,
            'lumiis_searches' => 1,
            'pressure' => (int) $area->boss_pressure_threshold,
        ]);
        $this->markQuestCompleted($character, 'yongan-first-sweep', 2);
        $this->markQuestCompleted($character, 'yongan-first-lumiis', 1);
        $this->markQuestCompleted($character, 'yongan-field-rank', 3);

        $this->actingAs($user)->get(route('dashboard', [
            'focus' => $area->slug,
        ]));

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'boss',
            'redirect_focus' => $area->slug,
        ]);

        $battle = GameBattle::query()
            ->where('game_character_id', $character->id)
            ->where('status', 'ongoing')
            ->latest('id')
            ->firstOrFail();
        $state = $battle->state;
        $firstEnemyId = (string) data_get($state, 'enemies.0.id');
        $state['enemies'] = collect($state['enemies'] ?? [])
            ->map(fn (array $enemy): array => [...$enemy, 'current_health' => 1])
            ->values()
            ->all();
        $battle->update([
            'state' => $state,
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'battle-attack']), [
            'idempotency_key' => (string) Str::uuid(),
            'battle_id' => $battle->id,
            'battle_round' => $battle->round,
            'enemy_id' => $firstEnemyId,
            'redirect_focus' => $area->slug,
        ]);

        $character->refresh();
        $battle->refresh();
        $alphaRelease = $this->questFor($character, 'yongan-alpha-release');

        $response->assertSessionMissing('status');
        $this->assertSame('victory', $battle->status);
        $this->assertSame(CharacterQuestStatus::ReadyToClaim, $alphaRelease->status);

        $this->claimQuestReward($user, $character, 'yongan-alpha-release', $area->slug);
        $character->refresh();
        $alphaRelease->refresh();

        $this->assertSame(CharacterQuestStatus::Completed, $alphaRelease->status);
        $this->assertGreaterThanOrEqual(5, (int) $character->level);
        $this->assertTrue($character->advancedTownServicesUnlocked());
        $mediumBluePotion = Item::query()->where('slug', 'medium-blue-potion')->firstOrFail();
        $mediumBlueQuantity = (int) CharacterItem::query()
            ->where('game_character_id', $character->id)
            ->where('item_id', $mediumBluePotion->id)
            ->sum('quantity');

        $this->assertGreaterThanOrEqual(1, $mediumBlueQuantity);

        $dashboard = $this->actingAs($user)->get(route('dashboard'));

        $dashboard
            ->assertOk()
            ->assertSee('Weapon Dealer')
            ->assertSee('Armor Dealer')
            ->assertSee('Town Smith');
    }

    public function test_story_arc_completion_percent_reflects_partial_progress(): void
    {
        [$user, $character] = $this->createActiveCharacterAt('yongan');
        $area = $this->starterArea('riverbank');

        CharacterAreaProgress::query()->create([
            'game_character_id' => $character->id,
            'location_area_id' => $area->id,
            'monster_fights' => 3,
            'lumiis_searches' => 1,
            'boss_fights' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'focus' => $area->slug,
        ]));

        $response->assertOk();

        /** @var array<string, mixed> $storyline */
        $storyline = $response->viewData('storyline');
        $steps = collect(data_get($storyline, 'steps', []));
        $expectedPercent = (int) round(
            $steps->sum(fn (array $step): int => min((int) data_get($step, 'current', 0), (int) data_get($step, 'target', 0)))
            / max(1, $steps->sum(fn (array $step): int => (int) data_get($step, 'target', 0)))
            * 100,
        );

        $this->assertIsArray($storyline);
        $this->assertGreaterThan(0, (int) $storyline['completion_percent']);
        $this->assertLessThan(100, (int) $storyline['completion_percent']);
        $this->assertSame($expectedPercent, (int) $storyline['completion_percent']);
        $this->assertSame(3, (int) data_get($storyline, 'steps.0.current'));
        $this->assertSame(1, (int) data_get($storyline, 'steps.1.current'));
        $this->assertSame(0, (int) data_get($storyline, 'steps.2.current'));
    }

    public function test_biologist_research_turn_ins_complete_a_quest_and_grant_a_permanent_bonus(): void
    {
        [$user, $character] = $this->createActiveCharacterAt('yongan', [
            'level' => 5,
            'experience' => 520,
            'vitality' => 0,
        ]);
        $biologistArea = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('service_type', 'biologist')
            ->firstOrFail();

        $this->markQuestCompleted($character, 'yongan-first-sweep', 2);
        $this->markQuestCompleted($character, 'yongan-first-lumiis', 1);
        $this->markQuestCompleted($character, 'yongan-field-rank', 3);
        $this->markQuestCompleted($character, 'yongan-alpha-release', 1);

        app(CharacterInventoryService::class)->grant($character, 'wolf-pelt', 3);

        $dashboard = $this->actingAs($user)->get(route('dashboard', [
            'focus' => $biologistArea->slug,
        ]));

        $dashboard
            ->assertOk()
            ->assertSee(trans('quests.yongan-biologist-research.title'))
            ->assertSee('Wolf Pelt')
            ->assertSee('+1 '.__('Vitality'))
            ->assertSee(__('Stage :current / :total', ['current' => 1, 'total' => 5]));

        $biologistQuest = $this->questFor($character, 'yongan-biologist-research');

        for ($submission = 0; $submission < 3; $submission++) {
            $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'submit-quest-item']), [
                'idempotency_key' => (string) Str::uuid(),
                'location_area_id' => $biologistArea->id,
                'character_quest_id' => $biologistQuest->id,
                'redirect_focus' => $biologistArea->slug,
            ]);

            $response->assertSessionHas('status');
            $biologistQuest->refresh();
        }

        $character->refresh();

        $this->assertSame(CharacterQuestStatus::ReadyToClaim, $biologistQuest->status);
        $this->claimQuestReward($user, $character, 'yongan-biologist-research', $biologistArea->slug);
        $character->refresh();
        $biologistQuest->refresh();
        $this->assertSame(CharacterQuestStatus::Completed, $biologistQuest->status);
        $this->assertSame(3, (int) $biologistQuest->progress);
        $this->assertSame(1, (int) $character->vitality);
        $this->assertSame(1, app(CharacterInventoryService::class)->quantityForSlug($character, 'research-catalyst'));
        $this->assertSame(0, app(CharacterInventoryService::class)->quantityForSlug($character, 'wolf-pelt'));

        $nextDashboard = $this->actingAs($user)->get(route('dashboard', [
            'focus' => $biologistArea->slug,
        ]));

        $nextDashboard
            ->assertOk()
            ->assertSee(trans('quests.yongan-biologist-research-2.title'))
            ->assertSee(__('Stage :current / :total', ['current' => 2, 'total' => 5]))
            ->assertSee(__('4 successful submissions'))
            ->assertSee(__('1 x :item per submission', [
                'count' => 1,
                'item' => 'Lumiis Shard',
            ]))
            ->assertDontSee('4 x 1 Lumiis Shard');
    }

    public function test_third_biologist_stage_grants_monster_damage_and_another_catalyst(): void
    {
        [$user, $character] = $this->createActiveCharacterAt('yongan', [
            'level' => 14,
            'experience' => 2500,
            'monster_damage' => 0,
        ]);
        $biologistArea = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('service_type', 'biologist')
            ->firstOrFail();

        $this->markQuestCompleted($character, 'yongan-first-sweep', 2);
        $this->markQuestCompleted($character, 'yongan-first-lumiis', 1);
        $this->markQuestCompleted($character, 'yongan-field-rank', 3);
        $this->markQuestCompleted($character, 'yongan-alpha-release', 1);
        $this->markQuestCompleted($character, 'yongan-biologist-research', 3);
        $this->markQuestCompleted($character, 'yongan-biologist-research-2', 4);

        app(CharacterInventoryService::class)->grant($character, 'ridge-claw', 5);

        $dashboard = $this->actingAs($user)->get(route('dashboard', [
            'focus' => $biologistArea->slug,
        ]));

        $dashboard
            ->assertOk()
            ->assertSee(trans('quests.yongan-biologist-research-3.title'))
            ->assertSee('+4% '.__('Monster Damage'));

        $biologistQuest = $this->questFor($character, 'yongan-biologist-research-3');
        $biologistQuest->quest->update([
            'objective_submission_success_rate' => 100,
        ]);

        for ($submission = 0; $submission < 5; $submission++) {
            $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'submit-quest-item']), [
                'idempotency_key' => (string) Str::uuid(),
                'location_area_id' => $biologistArea->id,
                'character_quest_id' => $biologistQuest->id,
                'redirect_focus' => $biologistArea->slug,
            ]);

            $response->assertSessionHas('status');
            $biologistQuest->refresh();

            if ($submission < 4) {
                $this->travel(31)->minutes();
            }
        }

        $character->refresh();

        $this->assertSame(CharacterQuestStatus::ReadyToClaim, $biologistQuest->status);
        $this->claimQuestReward($user, $character, 'yongan-biologist-research-3', $biologistArea->slug);
        $character->refresh();
        $biologistQuest->refresh();
        $this->assertSame(CharacterQuestStatus::Completed, $biologistQuest->status);
        $this->assertSame(5, (int) $biologistQuest->progress);
        $this->assertSame(4, (int) $character->monster_damage);
        $this->assertSame(4, $character->monsterDamagePercentBonus());
        $this->assertSame(1, app(CharacterInventoryService::class)->quantityForSlug($character, 'research-catalyst'));
        $this->assertSame(0, app(CharacterInventoryService::class)->quantityForSlug($character, 'ridge-claw'));
    }

    public function test_fourth_biologist_stage_rewards_a_research_report_for_late_chain_progress(): void
    {
        [$user, $character] = $this->createActiveCharacterAt('yongan', [
            'level' => 18,
            'experience' => 4200,
            'crit_chance' => 0,
        ]);
        $biologistArea = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('service_type', 'biologist')
            ->firstOrFail();

        $this->markQuestCompleted($character, 'yongan-first-sweep', 2);
        $this->markQuestCompleted($character, 'yongan-first-lumiis', 1);
        $this->markQuestCompleted($character, 'yongan-field-rank', 3);
        $this->markQuestCompleted($character, 'yongan-alpha-release', 1);
        $this->markQuestCompleted($character, 'yongan-biologist-research', 3);
        $this->markQuestCompleted($character, 'yongan-biologist-research-2', 4);
        $this->markQuestCompleted($character, 'yongan-biologist-research-3', 5);

        app(CharacterInventoryService::class)->grant($character, 'alpha-wild-fang', 4);

        $dashboard = $this->actingAs($user)->get(route('dashboard', [
            'focus' => $biologistArea->slug,
        ]));

        $dashboard
            ->assertOk()
            ->assertSee(trans('quests.yongan-biologist-research-4.title'))
            ->assertSee('+2% '.__('Crit Chance'));

        $biologistQuest = $this->questFor($character, 'yongan-biologist-research-4');
        $biologistQuest->quest->update([
            'objective_submission_success_rate' => 100,
        ]);

        for ($submission = 0; $submission < 4; $submission++) {
            $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'submit-quest-item']), [
                'idempotency_key' => (string) Str::uuid(),
                'location_area_id' => $biologistArea->id,
                'character_quest_id' => $biologistQuest->id,
                'redirect_focus' => $biologistArea->slug,
            ]);

            $response->assertSessionHas('status');
            $biologistQuest->refresh();

            if ($submission < 3) {
                $this->travel(46)->minutes();
            }
        }

        $character->refresh();

        $this->assertSame(CharacterQuestStatus::ReadyToClaim, $biologistQuest->status);
        $this->claimQuestReward($user, $character, 'yongan-biologist-research-4', $biologistArea->slug);
        $character->refresh();
        $biologistQuest->refresh();
        $this->assertSame(CharacterQuestStatus::Completed, $biologistQuest->status);
        $this->assertSame(2, (int) $character->crit_chance);
        $this->assertSame(1, app(CharacterInventoryService::class)->quantityForSlug($character, 'research-report'));
        $this->assertSame(0, app(CharacterInventoryService::class)->quantityForSlug($character, 'alpha-wild-fang'));
    }

    public function test_biologist_chain_second_stage_respects_cooldown_and_grants_a_stronger_bonus(): void
    {
        [$user, $character] = $this->createActiveCharacterAt('yongan', [
            'level' => 10,
            'experience' => 1400,
            'vitality' => 1,
            'max_health' => 100,
        ]);
        $biologistArea = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('service_type', 'biologist')
            ->firstOrFail();

        $this->markQuestCompleted($character, 'yongan-first-sweep', 2);
        $this->markQuestCompleted($character, 'yongan-first-lumiis', 1);
        $this->markQuestCompleted($character, 'yongan-field-rank', 3);
        $this->markQuestCompleted($character, 'yongan-alpha-release', 1);
        $this->markQuestCompleted($character, 'yongan-biologist-research', 3);

        app(CharacterInventoryService::class)->grant($character, 'lumiis-shard', 4);

        $dashboard = $this->actingAs($user)->get(route('dashboard', [
            'focus' => $biologistArea->slug,
        ]));

        $dashboard
            ->assertOk()
            ->assertSee(trans('quests.yongan-biologist-research-2.title'))
            ->assertSee(__('Stage :current / :total', ['current' => 2, 'total' => 5]))
            ->assertSee('+18 '.__('Max Health'));

        $startingLevel = (int) $character->level;
        $startingMaxHealth = (int) $character->max_health;
        $biologistQuest = $this->questFor($character, 'yongan-biologist-research-2');
        $biologistQuest->quest->update([
            'objective_submission_success_rate' => 100,
        ]);

        $firstSubmission = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'submit-quest-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $biologistArea->id,
            'character_quest_id' => $biologistQuest->id,
            'redirect_focus' => $biologistArea->slug,
        ]);

        $firstSubmission->assertSessionHas('status');
        $biologistQuest->refresh();
        $this->assertSame(1, (int) $biologistQuest->progress);
        $this->assertNotNull($biologistQuest->next_submission_at);

        $blockedSubmission = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'submit-quest-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $biologistArea->id,
            'character_quest_id' => $biologistQuest->id,
            'redirect_focus' => $biologistArea->slug,
        ]);

        $blockedSubmission->assertSessionHas('error');
        $biologistQuest->refresh();
        $this->assertSame(1, (int) $biologistQuest->progress);
        $this->assertSame(3, app(CharacterInventoryService::class)->quantityForSlug($character, 'lumiis-shard'));

        for ($submission = 0; $submission < 3; $submission++) {
            $this->travel(21)->minutes();

            $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'submit-quest-item']), [
                'idempotency_key' => (string) Str::uuid(),
                'location_area_id' => $biologistArea->id,
                'character_quest_id' => $biologistQuest->id,
                'redirect_focus' => $biologistArea->slug,
            ]);

            $response->assertSessionHas('status');
            $biologistQuest->refresh();
        }

        $character->refresh();
        $levelsGained = (int) $character->level - $startingLevel;

        $this->assertSame(CharacterQuestStatus::ReadyToClaim, $biologistQuest->status);
        $this->claimQuestReward($user, $character, 'yongan-biologist-research-2', $biologistArea->slug);
        $character->refresh();
        $biologistQuest->refresh();
        $levelsGained = (int) $character->level - $startingLevel;

        $this->assertSame(CharacterQuestStatus::Completed, $biologistQuest->status);
        $this->assertSame(4, (int) $biologistQuest->progress);
        $this->assertSame($startingMaxHealth + 18 + ($levelsGained * 12), (int) $character->max_health);
        $this->assertSame(0, app(CharacterInventoryService::class)->quantityForSlug($character, 'lumiis-shard'));

        $nextDashboard = $this->actingAs($user)->get(route('dashboard', [
            'focus' => $biologistArea->slug,
        ]));

        $nextDashboard
            ->assertOk()
            ->assertSee(trans('quests.yongan-biologist-research-3.title'))
            ->assertSee(__('Stage :current / :total', ['current' => 3, 'total' => 5]))
            ->assertSee('+18 '.__('Max Health'));
    }

    public function test_failed_biologist_submission_consumes_the_sample_and_starts_cooldown(): void
    {
        [$user, $character] = $this->createActiveCharacterAt('yongan', [
            'level' => 10,
            'experience' => 1400,
        ]);
        $biologistArea = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('service_type', 'biologist')
            ->firstOrFail();

        $this->markQuestCompleted($character, 'yongan-first-sweep', 2);
        $this->markQuestCompleted($character, 'yongan-first-lumiis', 1);
        $this->markQuestCompleted($character, 'yongan-field-rank', 3);
        $this->markQuestCompleted($character, 'yongan-alpha-release', 1);
        $this->markQuestCompleted($character, 'yongan-biologist-research', 3);

        $this->actingAs($user)->get(route('dashboard', [
            'focus' => $biologistArea->slug,
        ]));

        $quest = Quest::query()->where('slug', 'yongan-biologist-research-2')->firstOrFail();
        $quest->update([
            'objective_submission_success_rate' => 0,
        ]);

        app(CharacterInventoryService::class)->grant($character, 'lumiis-shard', 1);

        $biologistQuest = $this->questFor($character, 'yongan-biologist-research-2');

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'submit-quest-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $biologistArea->id,
            'character_quest_id' => $biologistQuest->id,
            'redirect_focus' => $biologistArea->slug,
        ]);

        $response->assertSessionHas('status');
        $biologistQuest->refresh();

        $this->assertSame(CharacterQuestStatus::Active, $biologistQuest->status);
        $this->assertSame(0, (int) $biologistQuest->progress);
        $this->assertNotNull($biologistQuest->next_submission_at);
        $this->assertSame(0, app(CharacterInventoryService::class)->quantityForSlug($character, 'lumiis-shard'));
        $this->assertStringContainsString('sample breaks down', (string) session('status'));
    }

    public function test_research_catalyst_boosts_the_next_submission_and_is_consumed_after_use(): void
    {
        [$user, $character] = $this->createActiveCharacterAt('yongan', [
            'level' => 10,
            'experience' => 1400,
        ]);
        $biologistArea = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('service_type', 'biologist')
            ->firstOrFail();

        $this->markQuestCompleted($character, 'yongan-first-sweep', 2);
        $this->markQuestCompleted($character, 'yongan-first-lumiis', 1);
        $this->markQuestCompleted($character, 'yongan-field-rank', 3);
        $this->markQuestCompleted($character, 'yongan-alpha-release', 1);
        $this->markQuestCompleted($character, 'yongan-biologist-research', 3);

        $inventory = app(CharacterInventoryService::class);
        $inventory->grant($character, 'lumiis-shard', 4);
        $inventory->grant($character, 'research-catalyst', 1);

        $this->actingAs($user)->get(route('dashboard', [
            'focus' => $biologistArea->slug,
        ]));

        $biologistQuest = $this->questFor($character, 'yongan-biologist-research-2');
        $catalystStack = $inventory->stackBySlug($character, 'research-catalyst');

        $boostResponse = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'boost-research-submission']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $biologistArea->id,
            'character_quest_id' => $biologistQuest->id,
            'character_item_id' => $catalystStack?->id,
            'redirect_focus' => $biologistArea->slug,
        ]);

        $boostResponse->assertSessionHas('status');
        $biologistQuest->refresh();
        $this->assertSame(20, (int) $biologistQuest->submission_bonus_percent);
        $this->assertSame(0, $inventory->quantityForSlug($character, 'research-catalyst'));

        $submitResponse = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'submit-quest-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $biologistArea->id,
            'character_quest_id' => $biologistQuest->id,
            'redirect_focus' => $biologistArea->slug,
        ]);

        $submitResponse->assertSessionHas('status');
        $biologistQuest->refresh();

        $this->assertSame(1, (int) $biologistQuest->progress);
        $this->assertSame(0, (int) $biologistQuest->submission_bonus_percent);
    }

    public function test_research_report_skips_the_active_biologist_cooldown(): void
    {
        [$user, $character] = $this->createActiveCharacterAt('yongan', [
            'level' => 10,
            'experience' => 1400,
        ]);
        $biologistArea = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('service_type', 'biologist')
            ->firstOrFail();

        $this->markQuestCompleted($character, 'yongan-first-sweep', 2);
        $this->markQuestCompleted($character, 'yongan-first-lumiis', 1);
        $this->markQuestCompleted($character, 'yongan-field-rank', 3);
        $this->markQuestCompleted($character, 'yongan-alpha-release', 1);
        $this->markQuestCompleted($character, 'yongan-biologist-research', 3);

        $inventory = app(CharacterInventoryService::class);
        $inventory->grant($character, 'lumiis-shard', 5);
        $inventory->grant($character, 'research-report', 1);

        $this->actingAs($user)->get(route('dashboard', [
            'focus' => $biologistArea->slug,
        ]));

        $biologistQuest = $this->questFor($character, 'yongan-biologist-research-2');
        $biologistQuest->quest->update([
            'objective_submission_success_rate' => 100,
        ]);

        $firstSubmission = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'submit-quest-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $biologistArea->id,
            'character_quest_id' => $biologistQuest->id,
            'redirect_focus' => $biologistArea->slug,
        ]);

        $firstSubmission->assertSessionHas('status');
        $biologistQuest->refresh();
        $this->assertNotNull($biologistQuest->next_submission_at);

        $reportStack = $inventory->stackBySlug($character, 'research-report');
        $skipResponse = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'skip-research-cooldown']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $biologistArea->id,
            'character_quest_id' => $biologistQuest->id,
            'character_item_id' => $reportStack?->id,
            'redirect_focus' => $biologistArea->slug,
        ]);

        $skipResponse->assertSessionHas('status');
        $biologistQuest->refresh();

        $this->assertNull($biologistQuest->next_submission_at);
        $this->assertSame(0, $inventory->quantityForSlug($character, 'research-report'));
    }

    public function test_biologist_dashboard_shows_the_research_archive_and_completed_bonuses(): void
    {
        [$user, $character] = $this->createActiveCharacterAt('yongan', [
            'level' => 10,
            'experience' => 1400,
        ]);
        $biologistArea = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('service_type', 'biologist')
            ->firstOrFail();

        $this->markQuestCompleted($character, 'yongan-first-sweep', 2);
        $this->markQuestCompleted($character, 'yongan-first-lumiis', 1);
        $this->markQuestCompleted($character, 'yongan-field-rank', 3);
        $this->markQuestCompleted($character, 'yongan-alpha-release', 1);
        $this->markQuestCompleted($character, 'yongan-biologist-research', 3);
        $this->markQuestCompleted($character, 'yongan-biologist-research-2', 4);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'focus' => $biologistArea->slug,
        ]));

        $response
            ->assertOk()
            ->assertSee(__('Research Archive'))
            ->assertSee('+1 '.__('Vitality'))
            ->assertSee('+18 '.__('Max Health'))
            ->assertSee(trans('quests.yongan-biologist-research.title'))
            ->assertSee(trans('quests.yongan-biologist-research-2.title'));
    }

    public function test_biologist_turn_in_rejects_direct_posts_before_the_feature_is_unlocked(): void
    {
        [$user, $character] = $this->createActiveCharacterAt('yongan', [
            'level' => 4,
        ]);
        $biologistArea = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('service_type', 'biologist')
            ->firstOrFail();
        $quest = Quest::query()->where('slug', 'yongan-biologist-research')->firstOrFail();

        $characterQuest = CharacterQuest::query()->create([
            'game_character_id' => $character->id,
            'quest_id' => $quest->id,
            'status' => CharacterQuestStatus::Active,
            'progress' => 0,
            'started_at' => now()->subMinutes(5),
        ]);

        app(CharacterInventoryService::class)->grant($character, 'wolf-pelt', 1);
        app(CharacterInventoryService::class)->grant($character, 'research-catalyst', 1);
        app(CharacterInventoryService::class)->grant($character, 'research-report', 1);
        $characterQuest->update([
            'next_submission_at' => now()->addMinutes(15),
        ]);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'submit-quest-item']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $biologistArea->id,
            'character_quest_id' => $characterQuest->id,
            'redirect_focus' => $biologistArea->slug,
        ]);
        $catalystStack = app(CharacterInventoryService::class)->stackBySlug($character, 'research-catalyst');
        $reportStack = app(CharacterInventoryService::class)->stackBySlug($character, 'research-report');
        $boostResponse = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'boost-research-submission']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $biologistArea->id,
            'character_quest_id' => $characterQuest->id,
            'character_item_id' => $catalystStack?->id,
            'redirect_focus' => $biologistArea->slug,
        ]);
        $skipResponse = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'skip-research-cooldown']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $biologistArea->id,
            'character_quest_id' => $characterQuest->id,
            'character_item_id' => $reportStack?->id,
            'redirect_focus' => $biologistArea->slug,
        ]);

        $response->assertSessionHas('error');
        $boostResponse->assertSessionHas('error');
        $skipResponse->assertSessionHas('error');
        $this->assertSame(0, (int) $character->fresh()->vitality);
        $this->assertSame(1, app(CharacterInventoryService::class)->quantityForSlug($character, 'wolf-pelt'));
        $this->assertSame(1, app(CharacterInventoryService::class)->quantityForSlug($character, 'research-catalyst'));
        $this->assertSame(1, app(CharacterInventoryService::class)->quantityForSlug($character, 'research-report'));
    }

    /**
     * @return array{0: User, 1: GameCharacter}
     */
    private function createActiveCharacterAt(string $locationSlug, array $overrides = []): array
    {
        $location = Location::query()->where('slug', $locationSlug)->firstOrFail();
        $user = User::factory()->create();

        $character = GameCharacter::query()->create([
            'user_id' => $user->id,
            'location_id' => $location->id,
            'name' => 'QuestHero'.Str::upper(Str::random(6)),
            'faction' => 'Shinsoo',
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
            'unspent_skill_points' => 0,
            'unspent_status_points' => 0,
            'version' => 1,
            'last_active_at' => now(),
            'stamina_updated_at' => now(),
            ...$overrides,
        ]);

        return [$user, $character];
    }

    private function starterArea(string $slug): LocationArea
    {
        return LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', $slug)
            ->firstOrFail();
    }

    private function markQuestCompleted(GameCharacter $character, string $questSlug, int $progress): void
    {
        $quest = Quest::query()->where('slug', $questSlug)->firstOrFail();

        CharacterQuest::query()->updateOrCreate(
            [
                'game_character_id' => $character->id,
                'quest_id' => $quest->id,
            ],
            [
                'status' => CharacterQuestStatus::Completed,
                'progress' => $progress,
                'started_at' => now()->subHour(),
                'completed_at' => now()->subMinutes(5),
            ],
        );
    }

    private function questFor(GameCharacter $character, string $questSlug): CharacterQuest
    {
        return CharacterQuest::query()
            ->where('game_character_id', $character->id)
            ->whereHas('quest', fn ($query) => $query->where('slug', $questSlug))
            ->with('quest')
            ->firstOrFail();
    }

    private function claimQuestReward(User $user, GameCharacter $character, string $questSlug, ?string $focus = null): void
    {
        $characterQuest = $this->questFor($character, $questSlug);

        $response = $this->actingAs($user)->post(route('game.actions.store', ['action' => 'claim-quest']), array_filter([
            'idempotency_key' => (string) Str::uuid(),
            'character_quest_id' => $characterQuest->id,
            'redirect_focus' => $focus,
        ], static fn ($value): bool => $value !== null));

        $response->assertSessionHas('status');
    }
}
