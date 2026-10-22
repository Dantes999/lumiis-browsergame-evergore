<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Database\Seeders\WorldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SyncProofUsersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_proof_users_command_creates_deterministic_accounts(): void
    {
        $this->seed(WorldSeeder::class);

        $this->artisan('game:sync-proof-users')
            ->expectsOutput('Proof users synced: ui-proof@example.test, audit-admin@example.test')
            ->assertSuccessful();

        $this->artisan('game:sync-proof-users')->assertSuccessful();

        $player = User::query()
            ->with(['character.equipment.characterItem.item', 'character.inventoryItems.item'])
            ->where('email', 'ui-proof@example.test')
            ->firstOrFail();
        $admin = User::query()->where('email', 'audit-admin@example.test')->firstOrFail();

        $this->assertSame('de', $player->locale);
        $this->assertFalse($player->is_admin);
        $this->assertTrue(Hash::check('TestPass123!', $player->password));
        $this->assertNotNull($player->email_verified_at);
        $this->assertNotNull($player->character);
        $this->assertSame('active', $player->character->game_state->value);
        $this->assertNull($player->character->tutorial_step);
        $this->assertSame('yongan', $player->character->location->slug);
        $this->assertSame(6, $player->character->level);

        $equippedSlugs = $player->character->equipment
            ->pluck('characterItem.item.slug')
            ->filter()
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['cotton-armor', 'recruit-blade'], $equippedSlugs);
        $this->assertSame(
            5,
            (int) $player->character->inventoryItems->firstWhere('item.slug', 'small-red-potion')?->quantity,
        );

        $this->assertTrue($admin->is_admin);
        $this->assertSame('de', $admin->locale);
        $this->assertTrue(Hash::check('TestPass123!', $admin->password));
        $this->assertCount(1, User::query()->where('email', 'ui-proof@example.test')->get());
        $this->assertCount(1, User::query()->where('email', 'audit-admin@example.test')->get());
    }
}
