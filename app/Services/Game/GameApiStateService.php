<?php

namespace App\Services\Game;

use App\Models\CharacterItem;
use App\Models\CharacterSkill;
use App\Models\GameBattle;
use App\Models\GameCharacter;
use App\Models\GameMessage;
use App\Models\Location;
use App\Models\LocationArea;
use App\Models\Skill;
use App\Support\Game\FeatureUnlocks;
use App\Support\Game\MetinClassCatalog;
use App\Support\Game\TutorialFlow;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GameApiStateService
{
    public function __construct(
        private readonly QuestProgressService $questProgressService,
        private readonly ManualEncounterPreviewService $previewService,
        private readonly RestStateService $restStateService,
    ) {}

    /**
     * Full read model for the SPA. The client never computes game state itself.
     *
     * @return array<string, mixed>
     */
    public function snapshot(GameCharacter $character): array
    {
        $character->loadMissing([
            'location.areas.monsterAssignments.monster',
            'location.areas.shop.shopItems.item',
            'inventoryItems.item',
            'inventoryItems.bonuses',
            'inventoryItems.equipment',
            'equipment.characterItem.item',
            'equipment.characterItem.bonuses',
            'equipment.characterItem.equipment',
            'learnedSkills.skill',
            'characterQuests.quest',
            'areaProgress.locationArea',
        ]);

        $activeBattle = GameBattle::query()
            ->where('game_character_id', $character->id)
            ->where('status', 'ongoing')
            ->latest('id')
            ->first();

        return [
            'player' => $this->playerPayload($character),
            'map' => $this->mapPayload($character),
            'inventory' => $character->inventoryItems->map(fn (CharacterItem $item): array => $this->itemPayload($item))->values()->all(),
            'equipment' => $this->equipmentPayload($character),
            'quests' => $this->questProgressService->dashboardData($character),
            'biologist' => $this->questProgressService->biologistPanelData($character),
            'skills' => $this->skillsPayload($character),
            'skillGroups' => $character->hasChosenSkillGroup()
                ? []
                : collect(MetinClassCatalog::groupsForRace($character->chosenRace()))
                    ->map(fn (array $group, string $key): array => [
                        'key' => $key,
                        'label' => $group['label'],
                        'description' => $group['description'],
                    ])
                    ->values()
                    ->all(),
            'battle' => $activeBattle ? $this->battlePayload($activeBattle) : null,
            'messages' => $this->messagesPayload($character),
            'rest' => $this->restStateService->currentRestState($character),
            'tutorial' => TutorialFlow::viewData($character),
            'features' => FeatureUnlocks::dashboardData($character),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function playerPayload(GameCharacter $character): array
    {
        return [
            'id' => $character->id,
            'name' => $character->name,
            'level' => (int) $character->level,
            'experience' => (int) $character->experience,
            'nextLevelExperience' => $character->nextLevelExperience(),
            'yang' => (int) $character->yang,
            'health' => (int) $character->health,
            'maxHealth' => $character->effectiveMaxHealth(),
            'stamina' => (int) $character->stamina,
            'maxStamina' => $character->effectiveMaxStamina(),
            'sp' => $character->effectiveCurrentSp(),
            'maxSp' => $character->effectiveMaxSp(),
            'attack' => $character->effectiveAttack(),
            'defense' => $character->effectiveDefense(),
            'speed' => $character->effectiveSpeed(),
            'magicAttack' => $character->effectiveMagicAttack(),
            'unspentSkillPoints' => (int) $character->unspent_skill_points,
            'unspentStatusPoints' => (int) $character->unspent_status_points,
            'race' => $character->chosenRace(),
            'skillGroup' => $character->skill_group,
            'gameState' => $character->game_state?->value,
            'tutorialStep' => $character->tutorial_step,
            'location' => [
                'id' => $character->location_id,
                'slug' => $character->location->slug,
                'name' => $character->location->name,
            ],
            'cooldownSeconds' => $character->cooldownSecondsRemaining(),
            'actionAvailableAt' => $character->action_available_at?->toIso8601String(),
            'healthRecovery' => $character->healthRecoveryState(),
            'staminaRecovery' => $character->staminaRecoveryState(),
            'spRecovery' => $character->spRecoveryState(),
            'unlocks' => [
                'lumiis' => $character->lumiisUnlocked(),
                'globalChat' => $character->globalChatUnlocked(),
                'whispers' => $character->whispersUnlocked(),
                'skills' => $character->skillsUnlocked(),
                'advancedTownServices' => $character->advancedTownServicesUnlocked(),
                'bossFights' => $character->bossFightsUnlocked(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapPayload(GameCharacter $character): array
    {
        $location = $character->location;

        return [
            'id' => $location->id,
            'slug' => $location->slug,
            'name' => $location->name,
            'subtitle' => __(':region · Threat :threat', [
                'region' => $location->region,
                'threat' => $location->threat_level,
            ]),
            'image' => '/images/maps/map1_red.png',
            'regions' => $this->regionsPayload($character, $location),
            'services' => $location->areas
                ->filter(fn (LocationArea $area): bool => $area->isService())
                ->map(fn (LocationArea $area): array => [
                    'id' => $area->id,
                    'slug' => $area->slug,
                    'name' => $area->name,
                    'serviceType' => $area->service_type,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function regionsPayload(GameCharacter $character, Location $location): array
    {
        $regions = [];

        if ($location->is_safe_zone) {
            $regions[] = [
                'slug' => $location->slug,
                'name' => $location->name,
                'kind' => 'city',
                'description' => $location->description,
                'levelRange' => __('Safe zone'),
                'x' => 50,
                'y' => 52,
                'accent' => '#d6a85a',
            ];
        }

        foreach ($location->areas as $area) {
            if (! $area->isHunting()) {
                continue;
            }

            $progress = $character->areaProgress->firstWhere('location_area_id', $area->id);

            $regions[] = [
                'slug' => $area->slug,
                'name' => $area->name,
                'kind' => 'hunting',
                'description' => $area->description,
                'levelRange' => __('Lv :level+', ['level' => $area->recommended_level ?? 1]),
                'x' => (float) ($area->hotspot_x ?? 50),
                'y' => (float) ($area->hotspot_y ?? 50),
                'accent' => $this->areaAccent($area),
                'pressure' => (int) ($progress?->pressure ?? 0),
                'pressureTarget' => (int) $area->boss_pressure_threshold,
                'bossReady' => (int) ($progress?->pressure ?? 0) >= (int) $area->boss_pressure_threshold,
            ];
        }

        return $regions;
    }

    /**
     * @return array<string, mixed>
     */
    public function regionPayload(GameCharacter $character, LocationArea $area): array
    {
        $area->loadMissing(['monsterAssignments.monster', 'shop.shopItems.item']);
        $progress = $character->areaProgress->firstWhere('location_area_id', $area->id);
        $families = $area->monsterAssignments
            ->filter(fn ($assignment): bool => $assignment->monster !== null)
            ->groupBy(fn ($assignment): string => $assignment->monster->manualFamilyKey());

        $primaryFamily = $families->sortBy(fn (Collection $assignments): int => (int) $assignments->min('monster.level'))->keys()->first();
        $primaryAssignments = $primaryFamily !== null ? $families->get($primaryFamily) : collect();

        return [
            'areaId' => $area->id,
            'slug' => $area->slug,
            'mapSlug' => $character->location->slug,
            'name' => $area->name,
            'description' => $area->description,
            'levelRange' => __('Lv :level+', ['level' => $area->recommended_level ?? 1]),
            'background' => $character->location->slug === 'yongan' ? 'meadow' : 'forest',
            'pressure' => (int) ($progress?->pressure ?? 0),
            'pressureTarget' => (int) $area->boss_pressure_threshold,
            'paths' => $this->pathsPayload($primaryFamily, $primaryAssignments, $area),
            'families' => $families->map(fn (Collection $assignments, string $key): array => [
                'key' => $key,
                'label' => __(':family', ['family' => $assignments->first()?->monster?->manualFamilyLabel() ?? $key]),
                'monsters' => $assignments->map(fn ($assignment): array => $this->monsterPayload($assignment->monster))->values()->all(),
            ])->values()->all(),
            'lumiis' => [
                'name' => $area->lumiis_name ?? __('Lumiis'),
                'preview' => $this->lumiisPreview($character, $area),
            ],
            'boss' => [
                'slug' => 'boss-'.$area->id,
                'name' => $area->boss_name ?? __('Area Boss'),
                'level' => (int) $area->recommended_level + 3,
                'unlocked' => (int) ($progress?->pressure ?? 0) >= (int) $area->boss_pressure_threshold,
                'pressure' => (int) ($progress?->pressure ?? 0),
                'pressureTarget' => (int) $area->boss_pressure_threshold,
            ],
            'previews' => $this->previewService->buildActionPreviews($character, $area),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cityPayload(GameCharacter $character, Location $location): array
    {
        $location->loadMissing(['areas.shop.shopItems.item']);

        return [
            'slug' => $location->slug,
            'mapSlug' => $location->slug,
            'name' => $location->name,
            'description' => $location->description,
            'npcs' => $location->areas
                ->filter(fn (LocationArea $area): bool => $area->isService())
                ->values()
                ->map(fn (LocationArea $area, int $index): array => $this->npcPayload($area, $index))
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $assignments
     * @return list<array<string, mixed>>
     */
    private function pathsPayload(?string $familyKey, Collection $assignments, LocationArea $area): array
    {
        $tiers = [
            'safe' => ['difficulty' => 'easy', 'label' => __('Sicherer Pfad'), 'chance' => 6, 'loot' => 1.0, 'exp' => 1.0, 'pressure' => 8],
            'medium' => ['difficulty' => 'medium', 'label' => __('Jägerpfad'), 'chance' => 12, 'loot' => 1.25, 'exp' => 1.2, 'pressure' => 14],
            'hard' => ['difficulty' => 'hard', 'label' => __('Rudelpfad'), 'chance' => 22, 'loot' => 1.6, 'exp' => 1.45, 'pressure' => 22],
        ];

        return collect($tiers)->map(function (array $tier, string $key) use ($familyKey, $assignments, $area): array {
            return [
                'id' => $key,
                'name' => $tier['label'],
                'difficulty' => $key,
                'description' => __('A measured route through :area. The further you push, the stronger the opposition.', ['area' => $area->name]),
                'monsters' => $assignments
                    ->take(3)
                    ->map(fn ($assignment): array => $this->monsterPayload($assignment->monster))
                    ->values()
                    ->all(),
                'lumiisChance' => $tier['chance'],
                'lootModifier' => $tier['loot'],
                'experienceModifier' => $tier['exp'],
                'pressureGain' => (int) ($area->monster_pressure_gain ?: $tier['pressure']),
                'encounter' => [
                    'request' => 'monster',
                    'family' => $familyKey,
                    'difficulty' => $tier['difficulty'],
                ],
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function monsterPayload(mixed $monster): array
    {
        return [
            'slug' => $monster->slug,
            'name' => $monster->name,
            'level' => (int) $monster->level,
            'category' => 'monster',
            'accent' => '#9a7650',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lumiisPreview(GameCharacter $character, LocationArea $area): ?array
    {
        try {
            return $this->previewService->buildActionPreviews($character, $area)['lumiis_manual'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function npcPayload(LocationArea $area, int $index): array
    {
        $isShop = $area->shop !== null;
        $action = match ($area->service_type) {
            'biologist' => 'quests',
            'inn' => 'dialog',
            default => $isShop ? 'shop' : 'dialog',
        };

        return [
            'id' => 'area-'.$area->id,
            'areaId' => $area->id,
            'slug' => $area->slug,
            'name' => $area->name,
            'role' => $area->service_type ? Str::headline($area->service_type) : __('Town Service'),
            'greeting' => $area->description,
            'x' => (float) ($area->hotspot_x ?? 15 + ($index * 16)),
            'y' => (float) ($area->hotspot_y ?? 58),
            'accent' => $this->areaAccent($area),
            'action' => $action,
            'shopItems' => $isShop
                ? $area->shop->shopItems->map(fn ($shopItem): array => [
                    'id' => $shopItem->id,
                    'name' => $shopItem->item?->name,
                    'slug' => $shopItem->item?->slug,
                    'price' => (int) $shopItem->price,
                    'stock' => $shopItem->stock,
                    'requiredLevel' => (int) ($shopItem->item?->required_level ?? 1),
                ])->values()->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(CharacterItem $inventoryItem): array
    {
        $item = $inventoryItem->item;

        return [
            'id' => $inventoryItem->id,
            'itemId' => $inventoryItem->item_id,
            'slug' => $item?->slug,
            'name' => $inventoryItem->displayName(),
            'description' => $item?->description,
            'type' => $item?->item_type,
            'slot' => $item?->equipment_slot,
            'requiredLevel' => (int) ($item?->required_level ?? 1),
            'stackable' => (bool) ($item?->stackable ?? false),
            'quantity' => (int) $inventoryItem->quantity,
            'sellPrice' => (int) ($item?->sell_price ?? 0),
            'icon' => $item?->icon_path ? '/'.$item->icon_path : null,
            'equipped' => $inventoryItem->equipment !== null,
            'column' => $inventoryItem->inventory_column,
            'row' => $inventoryItem->inventory_row,
            'upgradeLevel' => (int) $inventoryItem->upgrade_level,
            'bonusSlotLimit' => (int) $inventoryItem->bonus_slot_limit,
            'bonuses' => $inventoryItem->bonuses->map(fn ($bonus): array => [
                'id' => $bonus->id,
                'type' => $bonus->bonus_type,
                'value' => (int) $bonus->bonus_value,
                'label' => $bonus->displayLine(),
            ])->values()->all(),
            'stats' => [
                'attack' => (int) ($item?->attack_bonus ?? 0),
                'defense' => (int) ($item?->defense_bonus ?? 0),
                'health' => (int) ($item?->health_bonus ?? 0),
                'sp' => (int) ($item?->sp_restore ?? 0),
                'heal' => (int) ($item?->heal_amount ?? 0),
                'stamina' => (int) ($item?->stamina_bonus ?? 0),
                'speed' => (int) ($item?->speed_bonus ?? 0),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function equipmentPayload(GameCharacter $character): array
    {
        return $character->equipment
            ->mapWithKeys(fn ($equipment): array => [
                $equipment->slot => $equipment->characterItem
                    ? $this->itemPayload($equipment->characterItem)
                    : null,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function skillsPayload(GameCharacter $character): array
    {
        if (! $character->hasChosenSkillGroup()) {
            return [];
        }

        $learned = $character->learnedSkills->keyBy('skill_id');

        return Skill::query()
            ->where('race', $character->chosenRace())
            ->where('skill_group', $character->skill_group)
            ->orderBy('required_level')
            ->orderBy('name')
            ->get()
            ->map(function (Skill $skill) use ($character, $learned): array {
                /** @var CharacterSkill|null $characterSkill */
                $characterSkill = $learned->get($skill->id);

                return [
                    'id' => $skill->id,
                    'slug' => $skill->slug,
                    'name' => $skill->name,
                    'description' => $skill->description,
                    'requiredLevel' => (int) $skill->required_level,
                    'learned' => $characterSkill !== null,
                    'rank' => (int) ($characterSkill?->rank ?? 0),
                    'stage' => $characterSkill?->stageLabel(),
                    'canLearn' => $characterSkill === null
                        && $character->level >= $skill->required_level
                        && $character->unspent_skill_points > 0,
                    'canAdvance' => $characterSkill !== null
                        && $character->unspent_skill_points > 0
                        && ! $characterSkill->hasReachedPerfectMastery(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function battlePayload(GameBattle $battle): array
    {
        $state = $battle->state ?? [];
        $enemies = collect($state['enemies'] ?? [])->values();

        return [
            'id' => $battle->id,
            'round' => (int) ($state['round'] ?? $battle->round),
            'encounterName' => $battle->encounter_name,
            'encounterType' => $battle->encounter_type,
            'status' => $battle->status,
            'player' => [
                'name' => $battle->character?->name,
                'health' => (int) data_get($state, 'player.current_health', 0),
                'maxHealth' => (int) data_get($state, 'player.max_health', 0),
                'sp' => (int) data_get($state, 'player.current_sp', 0),
                'maxSp' => (int) data_get($state, 'player.max_sp', 0),
            ],
            'enemies' => $enemies->map(fn (array $enemy): array => [
                'id' => (string) ($enemy['id'] ?? ''),
                'name' => (string) ($enemy['name'] ?? 'Enemy'),
                'level' => (int) ($enemy['level'] ?? 1),
                'health' => (int) ($enemy['current_health'] ?? 0),
                'maxHealth' => (int) ($enemy['max_health'] ?? 1),
                'category' => str_contains((string) ($enemy['category'] ?? ''), 'Lumiis') ? 'lumiis' : 'monster',
                'accent' => '#9a7650',
            ])->all(),
            'log' => array_values($state['battle_log'] ?? []),
            'skills' => $state['player']['skills'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function messagesPayload(GameCharacter $character): array
    {
        $global = GameMessage::query()
            ->with('sender')
            ->where('channel', 'global')
            ->latest()
            ->limit(12)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (GameMessage $message): array => [
                'id' => $message->id,
                'sender' => $message->sender?->name,
                'body' => $message->body,
                'createdAt' => $message->created_at?->toIso8601String(),
            ])
            ->all();

        $whispers = GameMessage::query()
            ->with(['sender', 'recipient'])
            ->where('channel', 'whisper')
            ->where(fn ($query) => $query
                ->where('sender_character_id', $character->id)
                ->orWhere('recipient_character_id', $character->id))
            ->latest()
            ->limit(12)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (GameMessage $message): array => [
                'id' => $message->id,
                'sender' => $message->sender?->name,
                'recipient' => $message->recipient?->name,
                'body' => $message->body,
                'createdAt' => $message->created_at?->toIso8601String(),
                'incoming' => $message->recipient_character_id === $character->id,
            ])
            ->all();

        return [
            'global' => $global,
            'whispers' => $whispers,
        ];
    }

    private function areaAccent(LocationArea $area): string
    {
        if ($area->isService()) {
            return '#d6a85a';
        }

        return match ($area->slug) {
            'riverbank' => '#7ea66a',
            'eber-forst' => '#a77c55',
            'wolfsbach' => '#6f8fa6',
            default => '#8d7455',
        };
    }
}
