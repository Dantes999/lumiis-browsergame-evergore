<?php

namespace Tests\Feature\Api;

use App\Models\GameCharacter;
use App\Models\LocationArea;
use App\Models\User;
use App\Services\Game\CharacterProvisioningService;
use Database\Seeders\WorldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GameApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldSeeder::class);
    }

    public function test_state_endpoint_returns_the_player_read_model(): void
    {
        [$user, $character] = $this->createCharacter();

        $this->actingAs($user)
            ->getJson('/api/v1/state')
            ->assertOk()
            ->assertJsonPath('player.name', $character->name)
            ->assertJsonPath('player.location.slug', 'yongan')
            ->assertJsonStructure([
                'player',
                'map' => ['regions'],
                'inventory',
                'equipment',
                'quests',
                'skills',
                'messages',
                'characters',
            ]);
    }

    public function test_region_and_city_endpoints_return_spa_payloads(): void
    {
        [$user] = $this->createCharacter();

        $this->actingAs($user)
            ->getJson('/api/v1/regions/riverbank')
            ->assertOk()
            ->assertJsonPath('slug', 'riverbank')
            ->assertJsonStructure([
                'areaId',
                'paths' => [['id', 'name', 'difficulty', 'monsters', 'pressureGain', 'encounter']],
                'boss',
                'lumiis',
            ]);

        $this->actingAs($user)
            ->getJson('/api/v1/cities/yongan')
            ->assertOk()
            ->assertJsonPath('slug', 'yongan')
            ->assertJsonStructure([
                'npcs' => [['id', 'name', 'role', 'action']],
            ]);
    }

    public function test_action_endpoint_returns_state_and_action_outcome(): void
    {
        [$user, $character] = $this->createCharacter();
        $area = LocationArea::query()
            ->whereHas('location', fn ($query) => $query->where('slug', 'yongan'))
            ->where('slug', 'riverbank')
            ->firstOrFail();

        $this->actingAs($user)
            ->postJson('/api/v1/actions/fight-monsters', [
                'idempotency_key' => (string) Str::uuid(),
                'location_area_id' => $area->id,
            ])
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'state' => ['player', 'battle'],
            ]);

        $this->assertSame(1, $character->actionLogs()->count());
    }

    public function test_legacy_dashboard_redirects_to_the_spa_outside_tests(): void
    {
        [$user] = $this->createCharacter();
        $originalEnvironment = $this->app->environment();
        $this->app->detectEnvironment(fn () => 'local');

        try {
            $this->actingAs($user)
                ->get('/dashboard')
                ->assertRedirect(rtrim((string) config('app.frontend_url'), '/').'/map/1');
        } finally {
            $this->app->detectEnvironment(fn () => $originalEnvironment);
        }
    }

    /**
     * @return array{0: User, 1: GameCharacter}
     */
    private function createCharacter(): array
    {
        $user = User::factory()->create();
        $character = app(CharacterProvisioningService::class)->create($user, 'ApiHero'.Str::upper(Str::random(4)), 'warrior');

        return [$user, $character];
    }
}
