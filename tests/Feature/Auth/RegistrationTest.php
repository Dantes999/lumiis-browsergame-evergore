<?php

namespace Tests\Feature\Auth;

use App\Models\CharacterItem;
use App\Models\GameCharacter;
use App\Models\Item;
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

    public function test_registration_grants_a_practical_starter_loadout_for_the_tutorial(): void
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
