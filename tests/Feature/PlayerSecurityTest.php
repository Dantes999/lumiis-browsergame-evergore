<?php

namespace Tests\Feature;

use App\Enums\GameCharacterState;
use App\Models\GameActionLog;
use App\Models\GameCharacter;
use App\Models\GameMessage;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\WorldSeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlayerSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldSeeder::class);
    }

    public function test_authenticated_dashboard_responses_disable_caching(): void
    {
        [$user] = $this->createCharacterAt('yongan');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertHeader('Pragma', 'no-cache');
        $cacheControl = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
    }

    public function test_web_responses_include_browser_security_headers(): void
    {
        [$user] = $this->createCharacterAt('yongan');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->assertHeader(
            'Content-Security-Policy',
            "base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'",
        );
    }

    public function test_dashboard_only_shows_whispers_for_the_current_character(): void
    {
        [$viewerUser, $viewerCharacter] = $this->createCharacterAt('yongan', [
            'name' => 'ViewerOne',
            'level' => 3,
        ]);
        [, $senderCharacter] = $this->createCharacterAt('joan', [
            'name' => 'SenderTwo',
        ]);
        [, $otherCharacter] = $this->createCharacterAt('joan', [
            'name' => 'OtherThree',
        ]);

        GameMessage::query()->create([
            'sender_character_id' => $senderCharacter->id,
            'recipient_character_id' => $viewerCharacter->id,
            'channel' => 'whisper',
            'body' => 'Visible whisper for viewer.',
        ]);

        GameMessage::query()->create([
            'sender_character_id' => $senderCharacter->id,
            'recipient_character_id' => $otherCharacter->id,
            'channel' => 'whisper',
            'body' => 'Hidden whisper for another player.',
        ]);

        $response = $this->actingAs($viewerUser)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Visible whisper for viewer.');
        $response->assertDontSee('Hidden whisper for another player.');
    }

    public function test_dashboard_escapes_malicious_chat_names_and_bodies(): void
    {
        [$viewerUser] = $this->createCharacterAt('yongan', [
            'name' => 'ViewerSafe',
            'level' => 3,
        ]);
        [, $senderCharacter] = $this->createCharacterAt('joan', [
            'name' => '<img src=x onerror=alert(1)>',
        ]);

        GameMessage::query()->create([
            'sender_character_id' => $senderCharacter->id,
            'recipient_character_id' => null,
            'channel' => 'global',
            'body' => '<script>alert(1)</script> stolen',
        ]);

        $response = $this->actingAs($viewerUser)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('<img src=x onerror=alert(1)>', false);
        $response->assertDontSee('<script>alert(1)</script> stolen', false);
        $response->assertSee(e('<img src=x onerror=alert(1)>'), false);
        $response->assertSee(e('<script>alert(1)</script> stolen'), false);
    }

    public function test_dashboard_ignores_foreign_battle_replay_flash_ids(): void
    {
        [$viewerUser, $viewerCharacter] = $this->createCharacterAt('yongan');
        [, $foreignCharacter] = $this->createCharacterAt('joan', [
            'name' => 'ReplayOwner',
        ]);

        $foreignLog = GameActionLog::query()->create([
            'game_character_id' => $foreignCharacter->id,
            'idempotency_key' => (string) Str::uuid(),
            'action' => 'fight-monsters',
            'source_location_id' => $foreignCharacter->location_id,
            'target_location_id' => $foreignCharacter->location_id,
            'request_payload' => [],
            'outcome' => [
                'message' => 'Foreign replay',
                'battle_replay' => [
                    'title' => 'Foreign Secret Replay',
                    'location' => 'Hidden Field',
                    'encounter_type' => 'monster',
                    'victory' => true,
                    'player' => [
                        'name' => $foreignCharacter->name,
                        'health' => 80,
                        'max_health' => 100,
                        'level' => 3,
                        'attack' => 10,
                        'defense' => 8,
                        'speed' => 5,
                        'equipment' => [],
                    ],
                    'enemies' => [],
                    'steps' => [],
                    'summary' => [
                        'message' => 'Should never leak.',
                        'rounds' => 0,
                        'experience' => 0,
                        'yang' => 0,
                        'health_lost' => 0,
                        'loot' => [],
                    ],
                ],
            ],
            'processed_at' => now(),
        ]);

        $response = $this->actingAs($viewerUser)
            ->withSession(['battle_replay_log_id' => $foreignLog->id])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Foreign Secret Replay');
        $response->assertDontSee('Should never leak.');
        $this->assertNotSame($viewerCharacter->id, $foreignCharacter->id);
    }

    public function test_dashboard_ignores_foreign_manual_battle_result_flash_ids(): void
    {
        [$viewerUser] = $this->createCharacterAt('yongan');
        [, $foreignCharacter] = $this->createCharacterAt('joan', [
            'name' => 'ResultOwner',
        ]);

        $foreignLog = GameActionLog::query()->create([
            'game_character_id' => $foreignCharacter->id,
            'idempotency_key' => (string) Str::uuid(),
            'action' => 'battle-attack',
            'source_location_id' => $foreignCharacter->location_id,
            'target_location_id' => $foreignCharacter->location_id,
            'request_payload' => [],
            'outcome' => [
                'message' => 'Foreign result',
                'battle_replay' => [
                    'title' => 'Foreign Manual Result',
                    'location' => 'Hidden Field',
                    'encounter_type' => 'monster',
                    'victory' => true,
                    'player' => [
                        'name' => $foreignCharacter->name,
                        'health' => 80,
                        'max_health' => 100,
                        'sp' => 24,
                        'max_sp' => 24,
                        'level' => 3,
                        'attack' => 10,
                        'defense' => 8,
                        'speed' => 5,
                        'magic_attack' => 0,
                        'crit_chance' => 0,
                        'evade_chance' => 0,
                        'equipment' => [],
                    ],
                    'enemies' => [],
                    'steps' => [],
                    'summary' => [
                        'message' => 'Should never leak either.',
                        'rounds' => 0,
                        'experience' => 0,
                        'yang' => 0,
                        'health_lost' => 0,
                        'loot' => [],
                    ],
                ],
            ],
            'processed_at' => now(),
        ]);

        $response = $this->actingAs($viewerUser)
            ->withSession(['manual_battle_result_log_id' => $foreignLog->id])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Foreign Manual Result');
        $response->assertDontSee('Should never leak either.');
    }

    public function test_dashboard_route_is_rate_limited(): void
    {
        [$user] = $this->createCharacterAt('yongan');

        RateLimiter::for('dashboard-views', function (Request $request): array {
            $accountKey = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return [
                Limit::perMinute(2)->by('test-dashboard-views:account:'.$accountKey),
                Limit::perMinute(2)->by('test-dashboard-views:ip:'.$request->ip()),
            ];
        });

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('dashboard'))->assertTooManyRequests();
    }

    public function test_game_action_route_is_rate_limited(): void
    {
        [$user] = $this->createCharacterAt('yongan', [
            'level' => 3,
        ]);

        RateLimiter::for('game-actions', function (Request $request): array {
            $accountKey = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return [
                Limit::perMinute(2)->by('test-game-actions:account:'.$accountKey),
                Limit::perMinute(2)->by('test-game-actions:ip:'.$request->ip()),
            ];
        });

        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'global-chat']), [
            'idempotency_key' => (string) Str::uuid(),
            'message' => 'One',
        ])->assertRedirect();
        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'global-chat']), [
            'idempotency_key' => (string) Str::uuid(),
            'message' => 'Two',
        ])->assertRedirect();
        $this->actingAs($user)->post(route('game.actions.store', ['action' => 'global-chat']), [
            'idempotency_key' => (string) Str::uuid(),
            'message' => 'Three',
        ])->assertTooManyRequests();
    }

    public function test_game_action_route_rejects_requests_without_csrf_token_when_middleware_is_enabled(): void
    {
        [$user] = $this->createCharacterAt('yongan', [
            'level' => 3,
        ]);

        $this->runInEnvironment('production', function () use ($user): void {
            $response = $this->withMiddleware(ValidateCsrfToken::class)
                ->actingAs($user)
                ->post(route('game.actions.store', ['action' => 'global-chat']), [
                    'idempotency_key' => (string) Str::uuid(),
                    'message' => 'No token',
                ]);

            $response->assertStatus(419);
        });
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
            'name' => 'Hero'.Str::upper(Str::random(6)),
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
