<?php

namespace Tests\Feature;

use App\Models\GameActionLog;
use App\Models\GameBattle;
use App\Models\GameCharacter;
use App\Models\User;
use App\Services\Game\CharacterProvisioningService;
use Database\Seeders\WorldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MaintenancePruningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldSeeder::class);
    }

    public function test_old_action_logs_are_pruned_while_recent_logs_remain(): void
    {
        $character = $this->createCharacter();

        $oldLog = $this->createActionLog($character, now()->subDays(31));
        $recentLog = $this->createActionLog($character, now()->subDays(5));

        $this->artisan('model:prune', ['--model' => [GameActionLog::class]])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('game_action_logs', ['id' => $oldLog->id]);
        $this->assertDatabaseHas('game_action_logs', ['id' => $recentLog->id]);
    }

    public function test_only_old_resolved_battles_are_pruned(): void
    {
        $character = $this->createCharacter();

        $oldResolved = $this->createBattle($character, 'victory', now()->subDays(31));
        $recentResolved = $this->createBattle($character, 'victory', now()->subDays(5));
        $oldOngoing = $this->createBattle($character, 'ongoing', null);

        $this->artisan('model:prune', ['--model' => [GameBattle::class]])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('game_battles', ['id' => $oldResolved->id]);
        $this->assertDatabaseHas('game_battles', ['id' => $recentResolved->id]);
        $this->assertDatabaseHas('game_battles', ['id' => $oldOngoing->id]);
    }

    private function createCharacter(): GameCharacter
    {
        $user = User::factory()->create();

        return app(CharacterProvisioningService::class)->create($user, 'PruneBot', 'warrior');
    }

    private function createActionLog(GameCharacter $character, \DateTimeInterface $createdAt): GameActionLog
    {
        $log = GameActionLog::query()->create([
            'game_character_id' => $character->id,
            'idempotency_key' => (string) Str::uuid(),
            'action' => 'hunt',
            'request_payload' => [],
            'outcome' => ['message' => 'Prunable log'],
            'processed_at' => $createdAt,
        ]);

        $log->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();

        return $log;
    }

    private function createBattle(GameCharacter $character, string $status, ?\DateTimeInterface $resolvedAt): GameBattle
    {
        return GameBattle::query()->create([
            'game_character_id' => $character->id,
            'location_area_id' => null,
            'mode' => 'manual',
            'requested_encounter' => 'monster',
            'encounter_type' => 'monster',
            'encounter_name' => 'Prunable Wolf',
            'status' => $status,
            'round' => 1,
            'state' => ['round' => 1],
            'rewards' => [],
            'started_at' => now()->subDays(40),
            'resolved_at' => $resolvedAt,
        ]);
    }
}
