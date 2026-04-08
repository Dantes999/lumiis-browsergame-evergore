<?php

namespace Tests\Feature;

use App\Models\GameBattle;
use App\Models\GameCharacter;
use Database\Seeders\WorldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldSeeder::class);
    }

    public function test_simulation_api_can_create_a_normal_character(): void
    {
        $response = $this->postJson('/api/simulations/characters/normal', [
            'name' => 'BalanceBot',
            'race' => 'warrior',
            'level' => 10,
        ]);

        $response->assertCreated()
            ->assertJsonPath('character.name', 'BalanceBot')
            ->assertJsonPath('character.race', 'warrior')
            ->assertJsonPath('character.level', 10)
            ->assertJsonPath('character.game_state', 'active')
            ->assertJsonPath('character.location_slug', 'yongan');

        $this->assertDatabaseHas('game_characters', [
            'name' => 'BalanceBot',
            'level' => 10,
        ]);
    }

    public function test_simulation_api_requires_the_configured_access_key(): void
    {
        config()->set('game_simulator.key', 'alpha-proof-key');

        $this->postJson('/api/simulations/characters/normal', [
            'name' => 'NoKeyBot',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'Missing or invalid simulation API key.');

        $this->withHeaders([
            'X-Simulation-Key' => 'wrong-key',
        ])->postJson('/api/simulations/characters/normal', [
            'name' => 'WrongKeyBot',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'Missing or invalid simulation API key.');

        $this->withHeaders([
            'X-Simulation-Key' => 'alpha-proof-key',
        ])->postJson('/api/simulations/characters/normal', [
            'name' => 'CorrectKeyBot',
        ])->assertCreated()
            ->assertJsonPath('character.name', 'CorrectKeyBot');
    }

    public function test_simulation_api_is_hidden_when_disabled_outside_trusted_environments(): void
    {
        $this->runInEnvironment('production', function (): void {
            config()->set('game_simulator.enabled', false);
            config()->set('game_simulator.key', null);

            $this->postJson('/api/simulations/characters/normal', [
                'name' => 'ProdHiddenBot',
            ])->assertNotFound()
                ->assertJsonPath('message', 'Simulation API is not enabled.');
        });
    }

    public function test_simulation_api_fails_closed_without_a_key_in_production(): void
    {
        $this->runInEnvironment('production', function (): void {
            config()->set('game_simulator.enabled', true);
            config()->set('game_simulator.key', null);

            $this->postJson('/api/simulations/characters/normal', [
                'name' => 'ProdOpenBot',
            ])->assertStatus(503)
                ->assertJsonPath('message', 'Simulation API key is not configured.');
        });
    }

    public function test_simulation_fight_api_can_run_ephemeral_auto_balance_checks(): void
    {
        $response = $this->postJson('/api/simulations/fights', [
            'area_slug' => 'riverbank',
            'mode' => 'auto',
            'encounter' => 'monster',
            'iterations' => 3,
            'include_runs' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('source', 'generated-normal-character')
            ->assertJsonPath('area.slug', 'riverbank')
            ->assertJsonPath('aggregate.total_runs', 3)
            ->assertJsonStructure([
                'aggregate' => [
                    'victories',
                    'defeats',
                    'victory_rate_percent',
                    'average_rounds',
                    'average_experience',
                    'average_yang',
                    'average_health_lost',
                    'average_stamina_cost',
                ],
                'runs' => [
                    '*' => [
                        'message',
                        'victory',
                        'battle_summary',
                        'battle_replay',
                        'character_before',
                        'character_after',
                    ],
                ],
            ]);

        $this->assertDatabaseCount('game_characters', 0);
    }

    public function test_simulation_fight_api_can_run_manual_battle_for_saved_simulation_character(): void
    {
        $createResponse = $this->postJson('/api/simulations/characters/normal', [
            'name' => 'ManualBot',
            'race' => 'warrior',
            'level' => 1,
        ]);

        $characterId = $createResponse->json('character.id');

        $response = $this->postJson('/api/simulations/fights', [
            'character_id' => $characterId,
            'area_slug' => 'riverbank',
            'mode' => 'manual',
            'encounter' => 'monster',
            'strategy' => 'attack-only',
            'include_runs' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('source', 'saved-simulation-character')
            ->assertJsonPath('runs.0.action', 'battle-attack')
            ->assertJsonStructure([
                'runs' => [
                    '*' => [
                        'opening_message',
                        'turns',
                        'battle_replay',
                        'character_before',
                        'character_after',
                    ],
                ],
            ]);

        $this->assertDatabaseCount('game_characters', 1);
        $this->assertDatabaseCount('game_battles', 1);
        $this->assertNotNull(GameBattle::query()->first()?->resolved_at);
        $this->assertInstanceOf(GameCharacter::class, GameCharacter::query()->findOrFail($characterId));
    }

    public function test_simulation_fight_api_rejects_multiple_iterations_for_saved_character(): void
    {
        $createResponse = $this->postJson('/api/simulations/characters/normal', [
            'name' => 'SingleRunBot',
        ]);

        $characterId = $createResponse->json('character.id');

        $response = $this->postJson('/api/simulations/fights', [
            'character_id' => $characterId,
            'area_slug' => 'riverbank',
            'iterations' => 2,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['iterations']);
    }

    private function runInEnvironment(string $environment, callable $callback): void
    {
        $originalEnvironment = $this->app->environment();

        $this->app->detectEnvironment(fn () => $environment);

        try {
            $callback();
        } finally {
            $this->app->detectEnvironment(fn () => $originalEnvironment);
        }
    }
}
