<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class GameApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_endpoint_is_rate_limited_per_account(): void
    {
        $user = User::factory()->create();

        RateLimiter::for('game-api-state', function (Request $request): array {
            return [
                Limit::perMinute(2)->by('test-game-api-state:'.$request->user()?->id),
            ];
        });

        $this->actingAs($user)->getJson('/api/v1/state')->assertOk();
        $this->actingAs($user)->getJson('/api/v1/state')->assertOk();
        $this->actingAs($user)->getJson('/api/v1/state')->assertTooManyRequests();
    }
}
