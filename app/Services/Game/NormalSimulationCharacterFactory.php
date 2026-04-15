<?php

namespace App\Services\Game;

use App\Enums\GameCharacterState;
use App\Models\CharacterEquipment;
use App\Models\CharacterItem;
use App\Models\GameCharacter;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use App\Support\Game\ExperienceCurve;
use App\Support\Game\MetinClassCatalog;
use App\Support\Game\WeaponTypeCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class NormalSimulationCharacterFactory
{
    private const SIMULATION_EMAIL_DOMAIN = 'simulation.lumiis.test';

    public function __construct(
        private readonly CharacterInventoryService $inventoryService,
    ) {}

    /**
     * @param  array<string, mixed>  $profile
     */
    public function create(array $profile = [], ?Location $locationOverride = null): GameCharacter
    {
        return DB::transaction(function () use ($profile, $locationOverride): GameCharacter {
            $race = $this->normalizeRace((string) ($profile['race'] ?? MetinClassCatalog::defaultRace()));
            $level = max(1, min(40, (int) ($profile['level'] ?? 1)));
            $location = $locationOverride ?? $this->resolveLocation((string) ($profile['location_slug'] ?? 'yongan'));
            $characterName = $this->resolveCharacterName(isset($profile['name']) ? (string) $profile['name'] : null);

            $user = User::query()->create([
                'name' => $characterName,
                'email' => 'simulation+'.Str::lower(Str::random(16)).'@'.self::SIMULATION_EMAIL_DOMAIN,
                'locale' => app()->getLocale(),
                'password' => Hash::make(Str::random(40)),
            ]);

            $startingStats = MetinClassCatalog::startingStatsForRace($race);
            $startingVitality = (int) ($startingStats['vitality'] ?? 0);
            $startingIntelligence = (int) ($startingStats['intelligence'] ?? 0);
            $now = now();

            $character = GameCharacter::query()->create([
                'user_id' => $user->id,
                'location_id' => $location->id,
                'name' => $characterName,
                'faction' => 'Simulation',
                'race' => $race,
                'skill_group' => null,
                'game_state' => GameCharacterState::Active,
                'tutorial_step' => null,
                'level' => 1,
                'experience' => 0,
                'yang' => 150 + max(0, ($level - 1) * 18),
                'health' => (int) ($startingStats['max_health'] ?? 100) + ($startingVitality * 10),
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
                'sp' => (int) ($startingStats['max_sp'] ?? 24) + ($startingIntelligence * 6),
                'max_sp' => $startingStats['max_sp'] ?? 24,
                'unspent_status_points' => 0,
                'last_active_at' => $now,
                'stamina_updated_at' => $now,
                'health_updated_at' => $now,
                'sp_updated_at' => $now,
                'version' => 1,
            ]);

            $this->applyLevelPreset($character, $level);

            if ((bool) ($profile['equip_loadout'] ?? true)) {
                $this->grantBaselineLoadout($character, $level);
            }

            if ((bool) ($profile['include_supplies'] ?? false)) {
                $this->grantBaselineSupplies($character, $level);
            }

            $character->health = $character->effectiveMaxHealth();
            $character->sp = $character->effectiveMaxSp();
            $character->stamina = $character->effectiveMaxStamina();
            $character->save();

            return $this->freshCharacter($character->id);
        });
    }

    public function isSimulationCharacter(GameCharacter $character): bool
    {
        $character->loadMissing('user');

        $email = Str::lower((string) $character->user?->email);

        return $email !== '' && str_ends_with($email, '@'.self::SIMULATION_EMAIL_DOMAIN);
    }

    public function emailDomain(): string
    {
        return self::SIMULATION_EMAIL_DOMAIN;
    }

    private function normalizeRace(string $race): string
    {
        return MetinClassCatalog::hasRace($race) ? $race : MetinClassCatalog::defaultRace();
    }

    private function resolveLocation(string $locationSlug): Location
    {
        $slug = trim($locationSlug);

        if ($slug === '' || $slug === 'yongan') {
            return Location::query()->firstOrCreate(
                ['slug' => 'yongan'],
                [
                    'name' => 'Yongan',
                    'region' => 'Shinsoo',
                    'description' => 'Automatically provisioned fallback starter zone.',
                    'threat_level' => 1,
                    'is_safe_zone' => true,
                ],
            );
        }

        return Location::query()->where('slug', $slug)->firstOrFail();
    }

    private function resolveCharacterName(?string $requestedName = null): string
    {
        $baseName = trim((string) $requestedName);
        $baseName = $baseName !== '' ? $baseName : 'Sim'.Str::upper(Str::random(6));

        if (! GameCharacter::query()->where('name', $baseName)->exists()) {
            return $baseName;
        }

        $suffix = Str::upper(Str::random(4));
        $trimmedBase = Str::limit($baseName, 15, '');

        return $trimmedBase.$suffix;
    }

    private function applyLevelPreset(GameCharacter $character, int $targetLevel): void
    {
        if ($targetLevel <= 1) {
            return;
        }

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

    private function grantBaselineLoadout(GameCharacter $character, int $level): void
    {
        foreach ($this->baselineEquipmentSlugs($character->chosenRace(), $level) as $itemSlug) {
            if (! Item::query()->where('slug', $itemSlug)->exists()) {
                continue;
            }

            $this->inventoryService->grant($character, $itemSlug);

            $inventoryItem = CharacterItem::query()
                ->where('game_character_id', $character->id)
                ->whereHas('item', fn ($query) => $query->where('slug', $itemSlug))
                ->with(['item', 'equipment'])
                ->latest('id')
                ->first();

            if ($inventoryItem === null || $inventoryItem->item === null || ! $inventoryItem->item->isEquippable()) {
                continue;
            }

            $this->inventoryService->detachFromGrid($inventoryItem);

            CharacterEquipment::query()->where('game_character_id', $character->id)
                ->where('slot', $inventoryItem->item->equipment_slot)
                ->delete();

            CharacterEquipment::query()->create([
                'game_character_id' => $character->id,
                'character_item_id' => $inventoryItem->id,
                'slot' => $inventoryItem->item->equipment_slot,
            ]);
        }
    }

    private function grantBaselineSupplies(GameCharacter $character, int $level): void
    {
        foreach ($this->baselineSupplies($level) as $itemSlug => $quantity) {
            if (! Item::query()->where('slug', $itemSlug)->exists()) {
                continue;
            }

            $this->inventoryService->grant($character, $itemSlug, $quantity);
        }
    }

    /**
     * @return list<string>
     */
    private function baselineEquipmentSlugs(string $race, int $level): array
    {
        $weapon = WeaponTypeCatalog::baselineWeaponSlugForRaceAndLevel($race, $level);

        $armor = $level >= 22
            ? 'orc-mail'
            : ($level >= 20 ? 'stoneguard-vest' : ($level >= 8 ? 'guard-jacket' : 'cotton-armor'));

        $boots = $level >= 20
            ? 'scout-boots'
            : ($level >= 12 ? 'march-boots' : 'traveler-boots');

        $offhand = $level >= 30 ? 'orc-guard-shield' : null;

        return array_values(array_filter([$weapon, $armor, $boots, $offhand]));
    }

    /**
     * @return array<string, int>
     */
    private function baselineSupplies(int $level): array
    {
        if ($level >= 20) {
            return [
                'small-red-potion' => 5,
                'medium-red-potion' => 4,
                'small-blue-potion' => 4,
                'medium-blue-potion' => 3,
            ];
        }

        if ($level >= 10) {
            return [
                'small-red-potion' => 4,
                'medium-red-potion' => 2,
                'small-blue-potion' => 3,
            ];
        }

        if ($level >= 5) {
            return [
                'small-red-potion' => 4,
                'small-blue-potion' => 2,
            ];
        }

        return [
            'small-red-potion' => 3,
        ];
    }

    private function freshCharacter(int $characterId): GameCharacter
    {
        return GameCharacter::query()
            ->with([
                'user',
                'location',
                'inventoryItems.item',
                'inventoryItems.equipment',
                'inventoryItems.bonuses',
                'equipment.characterItem.item',
                'learnedSkills.skill',
            ])
            ->findOrFail($characterId);
    }
}
