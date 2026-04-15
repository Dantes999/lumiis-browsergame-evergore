<?php

namespace Tests\Feature;

use App\Enums\GameCharacterState;
use App\Models\GameActionLog;
use App\Models\GameBattle;
use App\Models\GameCharacter;
use App\Models\Location;
use App\Models\User;
use App\Services\Game\ActiveCharacterResolver;
use Database\Seeders\WorldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CharacterSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldSeeder::class);
    }

    public function test_character_hall_lists_roster_slots_and_can_switch_the_active_character(): void
    {
        $user = User::factory()->create();
        $firstCharacter = $this->createCharacterFor($user, 'BladeOne', 'yongan');
        $secondCharacter = $this->createCharacterFor($user, 'BladeTwo', 'joan');

        $this->withSession([ActiveCharacterResolver::SESSION_KEY => $firstCharacter->id])
            ->actingAs($user)
            ->get(route('characters.index'))
            ->assertOk()
            ->assertSee('Slot 1')
            ->assertSee('Slot 4')
            ->assertSee('BladeOne')
            ->assertSee('BladeTwo')
            ->assertSee('Empty Slot');

        $response = $this->withSession([ActiveCharacterResolver::SESSION_KEY => $firstCharacter->id])
            ->actingAs($user)
            ->post(route('characters.select', $secondCharacter));

        $response
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(ActiveCharacterResolver::SESSION_KEY, $secondCharacter->id);

        $this->withSession([ActiveCharacterResolver::SESSION_KEY => $secondCharacter->id])
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('BladeTwo');
    }

    public function test_character_hall_can_create_a_new_character_and_selects_it(): void
    {
        $user = User::factory()->create();
        $this->createCharacterFor($user, 'BladeOne', 'yongan');

        $response = $this->actingAs($user)->post(route('characters.store'), [
            'new_character_name' => 'BladeThree',
            'new_race' => 'sura',
        ]);

        $createdCharacter = GameCharacter::query()->where('name', 'BladeThree')->firstOrFail();

        $response
            ->assertRedirect(route('characters.index'))
            ->assertSessionHas(ActiveCharacterResolver::SESSION_KEY, $createdCharacter->id);

        $this->assertDatabaseHas('game_characters', [
            'user_id' => $user->id,
            'name' => 'BladeThree',
            'race' => 'sura',
        ]);
    }

    public function test_character_hall_rejects_a_fifth_character(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 4) as $slot) {
            $this->createCharacterFor($user, 'Roster'.$slot, 'yongan');
        }

        $response = $this->actingAs($user)
            ->from(route('characters.index'))
            ->post(route('characters.store'), [
                'new_character_name' => 'RosterFive',
                'new_race' => 'warrior',
            ]);

        $response
            ->assertRedirect(route('characters.index'))
            ->assertSessionHasErrorsIn('createCharacter', 'new_character_name');

        $this->assertDatabaseMissing('game_characters', [
            'user_id' => $user->id,
            'name' => 'RosterFive',
        ]);
    }

    public function test_creating_a_character_during_an_active_manual_fight_does_not_switch_slots(): void
    {
        $user = User::factory()->create();
        $currentCharacter = $this->createCharacterFor($user, 'CurrentBlade', 'yongan');

        GameBattle::query()->create([
            'game_character_id' => $currentCharacter->id,
            'location_area_id' => null,
            'mode' => 'manual',
            'requested_encounter' => 'monster',
            'encounter_type' => 'monster',
            'encounter_name' => 'Wild Dog',
            'status' => 'ongoing',
            'round' => 1,
            'state' => ['round' => 1],
            'started_at' => now(),
        ]);

        $response = $this->withSession([ActiveCharacterResolver::SESSION_KEY => $currentCharacter->id])
            ->actingAs($user)
            ->post(route('characters.store'), [
                'new_character_name' => 'FreshBlade',
                'new_race' => 'ninja',
            ]);

        $createdCharacter = GameCharacter::query()->where('name', 'FreshBlade')->firstOrFail();

        $response
            ->assertRedirect(route('characters.index'))
            ->assertSessionHas(ActiveCharacterResolver::SESSION_KEY, $currentCharacter->id);

        $this->assertNotSame($currentCharacter->id, $createdCharacter->id);
    }

    public function test_character_hall_can_rename_a_character(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacterFor($user, 'OldBlade', 'yongan');

        $response = $this->actingAs($user)->patch(route('characters.update', $character), [
            'character_name' => 'NewBlade',
            'editing_character_id' => $character->id,
        ]);

        $response->assertRedirect(route('characters.index'));

        $this->assertDatabaseHas('game_characters', [
            'id' => $character->id,
            'name' => 'NewBlade',
        ]);
    }

    public function test_character_hall_rejects_duplicate_names_when_renaming(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacterFor($user, 'OldBlade', 'yongan');
        $this->createCharacterFor($user, 'TakenBlade', 'joan');

        $response = $this->from(route('characters.index'))
            ->actingAs($user)
            ->patch(route('characters.update', $character), [
                'character_name' => 'TakenBlade',
                'editing_character_id' => $character->id,
            ]);

        $response
            ->assertRedirect(route('characters.index'))
            ->assertSessionHasErrorsIn('renameCharacter_'.$character->id, 'character_name');
    }

    public function test_deleting_the_selected_character_switches_to_the_next_available_slot(): void
    {
        $user = User::factory()->create();
        $selectedCharacter = $this->createCharacterFor($user, 'DeleteMe', 'yongan');
        $replacementCharacter = $this->createCharacterFor($user, 'KeepMe', 'joan');

        $response = $this->withSession([ActiveCharacterResolver::SESSION_KEY => $selectedCharacter->id])
            ->actingAs($user)
            ->delete(route('characters.destroy', $selectedCharacter));

        $response
            ->assertRedirect(route('characters.index'))
            ->assertSessionHas(ActiveCharacterResolver::SESSION_KEY, $replacementCharacter->id);

        $this->assertDatabaseMissing('game_characters', [
            'id' => $selectedCharacter->id,
        ]);
    }

    public function test_character_hall_rejects_deleting_a_character_with_a_live_manual_fight(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacterFor($user, 'DeleteLocked', 'yongan');

        GameBattle::query()->create([
            'game_character_id' => $character->id,
            'location_area_id' => null,
            'mode' => 'manual',
            'requested_encounter' => 'monster',
            'encounter_type' => 'monster',
            'encounter_name' => 'Wild Dog',
            'status' => 'ongoing',
            'round' => 1,
            'state' => ['round' => 1],
            'started_at' => now(),
        ]);

        $response = $this->actingAs($user)->delete(route('characters.destroy', $character));

        $response
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('game_characters', [
            'id' => $character->id,
        ]);
    }

    public function test_game_actions_run_against_the_selected_character(): void
    {
        $user = User::factory()->create();
        $firstCharacter = $this->createCharacterFor($user, 'ActionOne', 'yongan', [
            'health' => 55,
            'stamina' => 4,
        ]);
        $secondCharacter = $this->createCharacterFor($user, 'ActionTwo', 'yongan', [
            'health' => 22,
            'stamina' => 1,
        ]);

        $this->withSession([ActiveCharacterResolver::SESSION_KEY => $secondCharacter->id])
            ->actingAs($user)
            ->post(route('game.actions.store', ['action' => 'rest']), [
                'idempotency_key' => (string) Str::uuid(),
                'rest_minutes' => 30,
            ])
            ->assertRedirect(route('dashboard'));

        $latestLog = GameActionLog::query()->latest('id')->firstOrFail();

        $this->assertSame($secondCharacter->id, $latestLog->game_character_id);
        $this->assertNotSame($firstCharacter->id, $latestLog->game_character_id);
    }

    private function createCharacterFor(User $user, string $name, string $locationSlug, array $overrides = []): GameCharacter
    {
        $location = Location::query()->where('slug', $locationSlug)->firstOrFail();

        return GameCharacter::query()->create(array_merge([
            'user_id' => $user->id,
            'location_id' => $location->id,
            'name' => $name,
            'faction' => 'Shinsoo',
            'race' => 'warrior',
            'skill_group' => null,
            'game_state' => GameCharacterState::Active,
            'tutorial_step' => null,
            'level' => 1,
            'experience' => 0,
            'yang' => 150,
            'health' => 100,
            'max_health' => 100,
            'stamina' => 12,
            'max_stamina' => 12,
            'attack' => 8,
            'defense' => 6,
            'speed' => 5,
            'sp' => 24,
            'max_sp' => 24,
            'last_active_at' => now(),
            'stamina_updated_at' => now(),
            'health_updated_at' => now(),
            'sp_updated_at' => now(),
            'version' => 1,
        ], $overrides));
    }
}
