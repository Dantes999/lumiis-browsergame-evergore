<?php

namespace App\Services\Game;

use App\Enums\GameCharacterState;
use App\Models\GameCharacter;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use App\Support\Game\MetinClassCatalog;
use App\Support\Game\TutorialFlow;
use App\Support\Game\WeaponTypeCatalog;

class CharacterProvisioningService
{
    public const MAX_CHARACTERS_PER_ACCOUNT = 4;

    public function __construct(
        private readonly CharacterInventoryService $inventoryService,
    ) {}

    public function create(User $user, string $name, string $race): GameCharacter
    {
        $starterLocation = Location::query()->firstOrCreate(
            ['slug' => 'yongan'],
            [
                'name' => 'Yongan',
                'region' => 'Shinsoo',
                'description' => 'Automatically provisioned fallback starter zone.',
                'threat_level' => 1,
                'is_safe_zone' => true,
            ],
        );

        $startingStats = MetinClassCatalog::startingStatsForRace($race);
        $startingVitality = (int) ($startingStats['vitality'] ?? 0);
        $startingIntelligence = (int) ($startingStats['intelligence'] ?? 0);
        $startingHealth = (int) ($startingStats['max_health'] ?? 100) + ($startingVitality * 10);
        $startingSp = (int) ($startingStats['max_sp'] ?? 24) + ($startingIntelligence * 6);
        $tutorialEnabled = TutorialFlow::enabled();

        $character = GameCharacter::query()->create([
            'user_id' => $user->id,
            'location_id' => $starterLocation->id,
            'name' => $name,
            'faction' => 'Shinsoo',
            'race' => $race,
            'skill_group' => null,
            'game_state' => $tutorialEnabled ? GameCharacterState::Tutorial : GameCharacterState::Active,
            'tutorial_step' => $tutorialEnabled ? 1 : null,
            'level' => 1,
            'experience' => 0,
            'yang' => 150,
            'health' => $startingHealth,
            'max_health' => $startingStats['max_health'] ?? 100,
            'stamina' => 15,
            'max_stamina' => 15,
            'attack' => $startingStats['attack'] ?? 8,
            'defense' => $startingStats['defense'] ?? 6,
            'speed' => $startingStats['speed'] ?? 5,
            'magic_attack' => $startingStats['magic_attack'] ?? 0,
            'vitality' => $startingStats['vitality'] ?? 0,
            'intelligence' => $startingStats['intelligence'] ?? 0,
            'strength' => $startingStats['strength'] ?? 0,
            'dexterity' => $startingStats['dexterity'] ?? 0,
            'sp' => $startingSp,
            'max_sp' => $startingStats['max_sp'] ?? 24,
            'unspent_status_points' => 0,
            'last_active_at' => now(),
            'stamina_updated_at' => now(),
            'health_updated_at' => now(),
            'sp_updated_at' => now(),
            'version' => 1,
        ]);

        $starterWeaponSlug = WeaponTypeCatalog::starterWeaponSlugForRace($race);
        $starterItemsAvailable = Item::query()
            ->whereIn('slug', ['small-red-potion', $starterWeaponSlug, 'cotton-armor'])
            ->count() === 3;

        if ($starterItemsAvailable) {
            $this->inventoryService->grant($character, 'small-red-potion', 3);
            $this->inventoryService->grant($character, $starterWeaponSlug);
            $this->inventoryService->grant($character, 'cotton-armor');
        }

        return $character;
    }

    public function maxCharactersPerAccount(): int
    {
        return self::MAX_CHARACTERS_PER_ACCOUNT;
    }
}
