<?php

namespace Tests\Feature\Auth;

use App\Models\CharacterItem;
use App\Enums\GameCharacterState;
use App\Models\GameCharacter;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'character_name' => 'BladeMaster',
            'race' => 'warrior',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas(GameCharacter::class, [
            'name' => 'BladeMaster',
            'faction' => 'Shinsoo',
            'race' => 'warrior',
            'location_id' => \App\Models\Location::query()->where('slug', 'yongan')->value('id'),
            'game_state' => GameCharacterState::Active->value,
            'tutorial_step' => null,
            'stamina' => 15,
            'max_stamina' => 15,
        ]);
        $this->assertDatabaseHas(User::class, [
            'email' => 'test@example.com',
            'name' => 'BladeMaster',
        ]);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_regenerates_the_session_id(): void
    {
        $this->startSession();

        $beforeRegistrationSessionId = $this->app['session.store']->getId();

        $this->withSession(['pre_auth_probe' => 'present'])->post('/register', [
            'character_name' => 'SessionGuard',
            'race' => 'warrior',
            'email' => 'session-guard@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertNotSame($beforeRegistrationSessionId, $this->app['session.store']->getId());
    }

    public function test_registration_grants_a_practical_starter_loadout_for_the_opening_game_loop(): void
    {
        $this->seed(\Database\Seeders\WorldSeeder::class);

        $this->post('/register', [
            'character_name' => 'StarterPack',
            'race' => 'warrior',
            'email' => 'starter@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $character = GameCharacter::query()
            ->where('name', 'StarterPack')
            ->firstOrFail();

        $smallRedPotion = Item::query()->where('slug', 'small-red-potion')->firstOrFail();
        $smallBluePotion = Item::query()->where('slug', 'small-blue-potion')->firstOrFail();

        $this->assertDatabaseHas(CharacterItem::class, [
            'game_character_id' => $character->id,
            'item_id' => $smallRedPotion->id,
            'quantity' => 3,
        ]);
        $this->assertDatabaseMissing(CharacterItem::class, [
            'game_character_id' => $character->id,
            'item_id' => $smallBluePotion->id,
        ]);
    }

    public function test_registration_screen_no_longer_shows_a_realm_selector(): void
    {
        $response = $this->get('/register');

        $response
            ->assertOk()
            ->assertDontSee('name="faction"', false)
            ->assertSee('Betritt Lumiis')
            ->assertSee('Wähle deine Kampfrasse');
    }

    public function test_registration_rejects_duplicate_character_names(): void
    {
        $starterTown = Location::query()->firstOrCreate(
            ['slug' => 'yongan'],
            [
                'name' => 'Yongan',
                'region' => 'Shinsoo',
                'description' => 'Starter town for validation tests.',
                'threat_level' => 1,
                'is_safe_zone' => true,
            ],
        );

        $owner = User::factory()->create();

        GameCharacter::query()->create([
            'user_id' => $owner->id,
            'location_id' => $starterTown->id,
            'name' => 'BladeMaster',
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
            'stamina' => 15,
            'max_stamina' => 15,
            'attack' => 8,
            'defense' => 6,
            'speed' => 5,
            'sp' => 24,
            'max_sp' => 24,
            'unspent_status_points' => 0,
            'last_active_at' => now(),
            'stamina_updated_at' => now(),
            'health_updated_at' => now(),
            'sp_updated_at' => now(),
            'version' => 1,
        ]);

        $response = $this->from('/register')->post('/register', [
            'character_name' => 'BladeMaster',
            'race' => 'warrior',
            'email' => 'new-player@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertRedirect('/register')
            ->assertSessionHasErrors('character_name');

        $this->assertGuest();
        $this->assertDatabaseMissing(User::class, [
            'email' => 'new-player@example.test',
        ]);
    }

    public function test_registration_rejects_invalid_character_name_format(): void
    {
        $response = $this->from('/register')->post('/register', [
            'character_name' => '<BladeMaster>',
            'race' => 'warrior',
            'email' => 'bad-name@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertRedirect('/register')
            ->assertSessionHasErrors('character_name');

        $this->assertGuest();
        $this->assertDatabaseMissing(User::class, [
            'email' => 'bad-name@example.test',
        ]);
    }

    public function test_registration_requests_are_rate_limited(): void
    {
        RateLimiter::for('registration-attempts', function (Request $request): array {
            return [
                Limit::perMinute(2)->by('test-registration-attempts:ip:'.$request->ip()),
            ];
        });

        $this->from('/register')->post('/register', [
            'character_name' => '',
            'race' => '',
            'email' => '',
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect('/register');

        $this->from('/register')->post('/register', [
            'character_name' => '',
            'race' => '',
            'email' => '',
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect('/register');

        $this->from('/register')->post('/register', [
            'character_name' => '',
            'race' => '',
            'email' => '',
            'password' => '',
            'password_confirmation' => '',
        ])->assertTooManyRequests();
    }
}
