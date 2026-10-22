<?php

namespace Tests\Feature;

use App\Enums\GameActionType;
use App\Enums\GameCharacterState;
use App\Models\CharacterItem;
use App\Models\GameActionLog;
use App\Models\GameBattle;
use App\Models\GameCharacter;
use App\Models\Location;
use App\Models\LocationArea;
use App\Models\ShopItem;
use App\Models\User;
use Database\Seeders\WorldSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentGameActionTest extends TestCase
{
    private string $originalSqliteDatabase;

    private string $sharedDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalSqliteDatabase = (string) config('database.connections.sqlite.database');
        $this->sharedDatabasePath = database_path('testing-concurrency-'.Str::uuid().'.sqlite');

        touch($this->sharedDatabasePath);

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $this->sharedDatabasePath);

        DB::disconnect('sqlite');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::statement('PRAGMA journal_mode = WAL');
        DB::statement('PRAGMA busy_timeout = 5000');

        Artisan::call('migrate:fresh', [
            '--database' => 'sqlite',
            '--seed' => true,
            '--seeder' => WorldSeeder::class,
            '--force' => true,
        ]);

        DB::statement('PRAGMA journal_mode = WAL');
        DB::statement('PRAGMA busy_timeout = 5000');
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        DB::purge('sqlite');

        config()->set('database.connections.sqlite.database', $this->originalSqliteDatabase);

        DB::reconnect('sqlite');

        if (is_file($this->sharedDatabasePath)) {
            unlink($this->sharedDatabasePath);
        }

        parent::tearDown();
    }

    public function test_parallel_shop_buy_only_allows_one_character_to_take_the_last_stock_copy(): void
    {
        [$userOne, $characterOne] = $this->createCharacterAt('yongan', [
            'level' => 2,
            'yang' => 100,
        ]);
        [$userTwo, $characterTwo] = $this->createCharacterAt('yongan', [
            'level' => 2,
            'yang' => 100,
        ]);

        $vendorArea = LocationArea::query()->where('slug', 'general-store')->firstOrFail();
        $shopItem = ShopItem::query()
            ->whereHas('shop', fn ($query) => $query->where('slug', 'yongan-general-store'))
            ->whereHas('item', fn ($query) => $query->where('slug', 'small-red-potion'))
            ->firstOrFail();

        $shopItem->update([
            'stock' => 1,
        ]);

        $results = $this->runConcurrentGameActions([
            [
                'user_id' => $userOne->id,
                'action' => GameActionType::BuyItem->value,
                'payload' => [
                    'idempotency_key' => (string) Str::uuid(),
                    'location_area_id' => $vendorArea->id,
                    'shop_item_id' => $shopItem->id,
                ],
            ],
            [
                'user_id' => $userTwo->id,
                'action' => GameActionType::BuyItem->value,
                'payload' => [
                    'idempotency_key' => (string) Str::uuid(),
                    'location_area_id' => $vendorArea->id,
                    'shop_item_id' => $shopItem->id,
                ],
            ],
        ]);

        $successfulResults = collect($results)->where('ok', true)->values();
        $failedResults = collect($results)->where('ok', false)->values();

        $this->assertCount(1, $successfulResults);
        $this->assertCount(1, $failedResults);
        $this->assertStringContainsString('out of stock', strtolower((string) $failedResults->first()['message']));

        $shopItem->refresh();

        $this->assertSame(0, (int) $shopItem->stock);
        $this->assertSame(1, (int) CharacterItem::query()
            ->whereIn('game_character_id', [$characterOne->id, $characterTwo->id])
            ->whereHas('item', fn ($query) => $query->where('slug', 'small-red-potion'))
            ->sum('quantity'));
        $this->assertSame(1, GameActionLog::query()->where('action', GameActionType::BuyItem->value)->count());
    }

    public function test_parallel_manual_battle_submits_only_resolve_one_turn_for_the_same_round(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 3,
            'yang' => 50,
            'attack' => 10,
            'defense' => 8,
        ]);

        $area = LocationArea::query()->where('slug', 'riverbank')->firstOrFail();

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'start-manual-fight']), [
            'idempotency_key' => (string) Str::uuid(),
            'location_area_id' => $area->id,
            'encounter_request' => 'monster',
            'redirect_focus' => $area->slug,
        ])->assertRedirect();

        $battle = GameBattle::query()
            ->where('game_character_id', $character->id)
            ->where('status', 'ongoing')
            ->latest('id')
            ->firstOrFail();

        $enemyId = (string) data_get($battle->state, 'enemies.0.id');

        $results = $this->runConcurrentGameActions([
            [
                'user_id' => $user->id,
                'action' => GameActionType::BattleAttack->value,
                'payload' => [
                    'idempotency_key' => (string) Str::uuid(),
                    'battle_id' => $battle->id,
                    'battle_round' => 1,
                    'enemy_id' => $enemyId,
                ],
            ],
            [
                'user_id' => $user->id,
                'action' => GameActionType::BattleAttack->value,
                'payload' => [
                    'idempotency_key' => (string) Str::uuid(),
                    'battle_id' => $battle->id,
                    'battle_round' => 1,
                    'enemy_id' => $enemyId,
                ],
            ],
        ]);

        $successfulResults = collect($results)->where('ok', true)->values();
        $failedResults = collect($results)->where('ok', false)->values();

        $this->assertCount(1, $successfulResults);
        $this->assertCount(1, $failedResults);
        $this->assertStringContainsString('no longer current', strtolower((string) $failedResults->first()['message']));

        $battle->refresh();

        $this->assertSame('ongoing', $battle->status);
        $this->assertSame(2, (int) $battle->round);
        $this->assertSame(1, GameActionLog::query()
            ->where('game_character_id', $character->id)
            ->where('action', GameActionType::BattleAttack->value)
            ->count());
    }

    public function test_parallel_action_spam_only_allows_one_monster_fight_reward_cycle(): void
    {
        [$user, $character] = $this->createCharacterAt('yongan', [
            'level' => 2,
            'yang' => 0,
            'experience' => 0,
            'health' => 100,
            'stamina' => 12,
        ]);

        $area = LocationArea::query()->where('slug', 'riverbank')->firstOrFail();

        $results = $this->runConcurrentGameActions([
            [
                'user_id' => $user->id,
                'action' => GameActionType::FightMonsters->value,
                'payload' => [
                    'idempotency_key' => (string) Str::uuid(),
                    'location_area_id' => $area->id,
                ],
            ],
            [
                'user_id' => $user->id,
                'action' => GameActionType::FightMonsters->value,
                'payload' => [
                    'idempotency_key' => (string) Str::uuid(),
                    'location_area_id' => $area->id,
                ],
            ],
        ]);

        $successfulResults = collect($results)->where('ok', true)->values();
        $failedResults = collect($results)->where('ok', false)->values();

        $this->assertCount(1, $successfulResults);
        $this->assertCount(1, $failedResults);
        $this->assertStringContainsString('recovering', strtolower((string) $failedResults->first()['message']));

        $character->refresh();

        $this->assertSame(1, GameActionLog::query()
            ->where('game_character_id', $character->id)
            ->where('action', GameActionType::FightMonsters->value)
            ->count());
        $this->assertNotNull($character->action_available_at);
        $this->assertSame(2, (int) $character->version);
    }

    /**
     * Concurrency tests need real sibling PHP processes, so we hand each worker the same sqlite file.
     *
     * @param  array<int, array{user_id: int, action: string, payload: array<string, mixed>}>  $actions
     * @return list<array{ok: bool, message: string, exception_class?: string}>
     */
    private function runConcurrentGameActions(array $actions): array
    {
        $startAt = microtime(true) + 0.75;
        $scriptPath = base_path('tests/Fixtures/concurrency/process_game_action_worker.php');
        $phpBinary = (new PhpExecutableFinder)->find(false) ?: PHP_BINARY;
        $processes = [];
        $inputFiles = [];

        foreach ($actions as $action) {
            $inputPath = tempnam(sys_get_temp_dir(), 'lumiis-concurrency-');

            if ($inputPath === false) {
                $this->fail('Unable to create a temporary worker input file.');
            }

            file_put_contents($inputPath, json_encode([
                ...$action,
                'database_path' => $this->sharedDatabasePath,
                'start_at' => $startAt,
            ], JSON_THROW_ON_ERROR));

            $process = new Process([$phpBinary, $scriptPath, $inputPath], base_path());
            $process->start();

            $processes[] = $process;
            $inputFiles[] = $inputPath;
        }

        $results = [];

        foreach ($processes as $index => $process) {
            $process->wait();

            $this->assertSame(
                0,
                $process->getExitCode(),
                trim($process->getErrorOutput()."\n".$process->getOutput()),
            );

            /** @var array{ok: bool, message: string, exception_class?: string} $decoded */
            $decoded = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);
            $results[$index] = $decoded;
        }

        foreach ($inputFiles as $inputFile) {
            if (is_file($inputFile)) {
                unlink($inputFile);
            }
        }

        return array_values($results);
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
            'name' => 'Race'.Str::upper(Str::random(6)),
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
