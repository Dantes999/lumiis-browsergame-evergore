<?php

namespace App\Console\Commands;

use App\Enums\GameCharacterState;
use App\Models\CharacterAreaProgress;
use App\Models\CharacterEquipment;
use App\Models\CharacterItem;
use App\Models\CharacterQuest;
use App\Models\CharacterSkill;
use App\Models\GameActionLog;
use App\Models\GameBattle;
use App\Models\GameCharacter;
use App\Models\GameMessage;
use App\Models\Location;
use App\Models\User;
use App\Services\Game\CharacterInventoryService;
use App\Support\Game\ExperienceCurve;
use App\Support\Game\MetinClassCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Keeps the UI proof users deterministic so local proof runs and CI screenshots
 * always hit the same stable dashboard and admin surfaces.
 */
class SyncProofUsersCommand extends Command
{
    private const PLAYER_EMAIL = 'ui-proof@example.test';

    private const ADMIN_EMAIL = 'audit-admin@example.test';

    protected $signature = 'game:sync-proof-users {--password=TestPass123! : Shared password for the proof accounts}';

    protected $description = 'Create or refresh the deterministic UI proof accounts used by the release gate';

    public function handle(CharacterInventoryService $inventoryService): int
    {
        $location = Location::query()->where('slug', 'yongan')->first();

        if ($location === null) {
            $this->error('Starter town yongan is missing. Run the world seeder before syncing proof users.');

            return self::FAILURE;
        }

        $password = (string) $this->option('password');

        DB::transaction(function () use ($inventoryService, $location, $password): void {
            $this->syncPlayerProofUser($inventoryService, $location, $password);
            $this->syncAdminProofUser($password);
        });

        $this->info('Proof users synced: '.self::PLAYER_EMAIL.', '.self::ADMIN_EMAIL);

        return self::SUCCESS;
    }

    private function syncPlayerProofUser(
        CharacterInventoryService $inventoryService,
        Location $location,
        string $password,
    ): void {
        $preferredName = 'UiProofHero';
        $user = User::query()->firstOrNew(['email' => self::PLAYER_EMAIL]);
        $user->forceFill([
            'name' => $preferredName,
            'email' => self::PLAYER_EMAIL,
            'locale' => 'de',
            'password' => Hash::make($password),
            'is_admin' => false,
            'email_verified_at' => now(),
        ])->save();

        $character = $user->character()->first();
        $characterName = $this->resolveCharacterName($preferredName, $character?->id);
        $startingStats = MetinClassCatalog::startingStatsForRace('warrior');

        if ($character === null) {
            $character = GameCharacter::query()->create([
                'user_id' => $user->id,
                'location_id' => $location->id,
                'name' => $characterName,
                'faction' => 'Shinsoo',
                'race' => 'warrior',
                'skill_group' => 'body',
                'game_state' => GameCharacterState::Active,
                'tutorial_step' => null,
                'level' => 1,
                'experience' => 0,
                'yang' => 150,
                'health' => (int) ($startingStats['health'] ?? 104),
                'max_health' => (int) ($startingStats['max_health'] ?? 104),
                'stamina' => 15,
                'max_stamina' => 15,
                'attack' => (int) ($startingStats['attack'] ?? 9),
                'defense' => (int) ($startingStats['defense'] ?? 7),
                'speed' => (int) ($startingStats['speed'] ?? 5),
                'magic_attack' => (int) ($startingStats['magic_attack'] ?? 0),
                'vitality' => (int) ($startingStats['vitality'] ?? 5),
                'intelligence' => (int) ($startingStats['intelligence'] ?? 2),
                'strength' => (int) ($startingStats['strength'] ?? 5),
                'dexterity' => (int) ($startingStats['dexterity'] ?? 3),
                'sp' => (int) ($startingStats['sp'] ?? 22),
                'max_sp' => (int) ($startingStats['max_sp'] ?? 22),
                'last_active_at' => now(),
                'stamina_updated_at' => now(),
                'health_updated_at' => now(),
                'sp_updated_at' => now(),
                'version' => 1,
            ]);
        }

        $this->resetProofCharacterState($character, $location, $characterName, $startingStats);
        $this->grantProofLoadout($inventoryService, $character);

        $user->forceFill([
            'name' => $character->name,
        ])->save();
    }

    private function syncAdminProofUser(string $password): void
    {
        $user = User::query()->firstOrNew(['email' => self::ADMIN_EMAIL]);
        $user->forceFill([
            'name' => 'Audit Admin',
            'email' => self::ADMIN_EMAIL,
            'locale' => 'de',
            'password' => Hash::make($password),
            'is_admin' => true,
            'email_verified_at' => now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $startingStats
     */
    private function resetProofCharacterState(GameCharacter $character, Location $location, string $characterName, array $startingStats): void
    {
        CharacterEquipment::query()->where('game_character_id', $character->id)->delete();
        CharacterItem::query()->where('game_character_id', $character->id)->delete();
        CharacterSkill::query()->where('game_character_id', $character->id)->delete();
        CharacterAreaProgress::query()->where('game_character_id', $character->id)->delete();
        CharacterQuest::query()->where('game_character_id', $character->id)->delete();
        GameBattle::query()->where('game_character_id', $character->id)->delete();
        GameActionLog::query()->where('game_character_id', $character->id)->delete();
        GameMessage::query()
            ->where('sender_character_id', $character->id)
            ->orWhere('recipient_character_id', $character->id)
            ->delete();

        $character->forceFill([
            'location_id' => $location->id,
            'name' => $characterName,
            'faction' => 'Shinsoo',
            'race' => 'warrior',
            'skill_group' => 'body',
            'game_state' => GameCharacterState::Active,
            'tutorial_step' => null,
            'level' => 1,
            'experience' => 0,
            'yang' => 150,
            'health' => (int) ($startingStats['health'] ?? 104),
            'max_health' => (int) ($startingStats['max_health'] ?? 104),
            'stamina' => 15,
            'max_stamina' => 15,
            'attack' => (int) ($startingStats['attack'] ?? 9),
            'defense' => (int) ($startingStats['defense'] ?? 7),
            'speed' => (int) ($startingStats['speed'] ?? 5),
            'magic_attack' => (int) ($startingStats['magic_attack'] ?? 0),
            'vitality' => (int) ($startingStats['vitality'] ?? 5),
            'intelligence' => (int) ($startingStats['intelligence'] ?? 2),
            'strength' => (int) ($startingStats['strength'] ?? 5),
            'dexterity' => (int) ($startingStats['dexterity'] ?? 3),
            'sp' => (int) ($startingStats['sp'] ?? 22),
            'max_sp' => (int) ($startingStats['max_sp'] ?? 22),
            'unspent_skill_points' => 0,
            'unspent_status_points' => 0,
            'action_available_at' => null,
            'last_active_at' => now(),
            'stamina_updated_at' => now(),
            'health_updated_at' => now(),
            'sp_updated_at' => now(),
            'version' => 1,
        ])->save();

        $this->applyLevelPreset($character, 6);
    }

    private function grantProofLoadout(CharacterInventoryService $inventoryService, GameCharacter $character): void
    {
        $inventoryService->grant($character, 'small-red-potion', 5);
        $inventoryService->grant($character, 'recruit-blade');
        $inventoryService->grant($character, 'cotton-armor');

        $this->equipItemBySlug($inventoryService, $character, 'recruit-blade');
        $this->equipItemBySlug($inventoryService, $character, 'cotton-armor');

        $character->refresh();
        $character->forceFill([
            'health' => $character->effectiveMaxHealth(),
            'sp' => $character->effectiveMaxSp(),
            'stamina' => $character->effectiveMaxStamina(),
        ])->save();
    }

    private function equipItemBySlug(CharacterInventoryService $inventoryService, GameCharacter $character, string $itemSlug): void
    {
        $inventoryItem = CharacterItem::query()
            ->where('game_character_id', $character->id)
            ->whereHas('item', fn ($query) => $query->where('slug', $itemSlug))
            ->with('item')
            ->latest('id')
            ->first();

        if ($inventoryItem === null || $inventoryItem->item === null || ! $inventoryItem->item->isEquippable()) {
            return;
        }

        $inventoryService->detachFromGrid($inventoryItem);

        CharacterEquipment::query()->updateOrCreate(
            [
                'game_character_id' => $character->id,
                'slot' => $inventoryItem->item->equipment_slot,
            ],
            [
                'character_item_id' => $inventoryItem->id,
            ],
        );
    }

    private function applyLevelPreset(GameCharacter $character, int $targetLevel): void
    {
        while ($character->level < $targetLevel) {
            $character->level++;
            $character->max_health += 12;
            $character->max_sp += 4;
            $character->max_stamina += 1;
            $character->attack += 1;
            $character->defense += 1;

            if ($character->level % 2 === 0) {
                $character->speed += 1;
            }

            $character->unspent_status_points += 1;

            if (MetinClassCatalog::earnsSkillPointAtLevel($character->level)) {
                $character->unspent_skill_points += 1;
            }
        }

        $character->experience = ExperienceCurve::requiredForLevel($targetLevel);
        $character->yang += max(0, ($targetLevel - 1) * 10);
        $character->save();
    }

    private function resolveCharacterName(string $preferredName, ?int $currentCharacterId = null): string
    {
        $existingOwner = GameCharacter::query()
            ->where('name', $preferredName)
            ->when($currentCharacterId !== null, fn ($query) => $query->whereKeyNot($currentCharacterId))
            ->exists();

        if (! $existingOwner) {
            return $preferredName;
        }

        return 'UiProofHero01';
    }
}
