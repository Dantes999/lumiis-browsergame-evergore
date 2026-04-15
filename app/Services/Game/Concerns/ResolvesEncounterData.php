<?php

namespace App\Services\Game\Concerns;

use App\Exceptions\GameActionException;
use App\Models\CharacterAreaProgress;
use App\Models\CharacterEquipment;
use App\Models\GameCharacter;
use App\Models\Item;
use App\Models\LocationArea;
use App\Models\LocationAreaMonster;
use App\Support\Game\DamageTypeCatalog;
use App\Support\Game\MetinClassCatalog;
use Illuminate\Support\Collection;

/**
 * Read-side encounter helpers.
 *
 * These methods translate DB configuration into concrete combat inputs:
 * monster selection, pressure logic, loot rolls and replay view data.
 */
trait ResolvesEncounterData
{
    /**
     * Centralizes encounter key normalization so all combat paths use the same vocabulary.
     */
    private function normalizeEncounterRequest(string $requestedEncounter): string
    {
        return trim($requestedEncounter);
    }

    /**
     * Encounter keys now match the DB column prefixes, but this helper keeps the contract explicit.
     */
    private function encounterColumnPrefix(string $encounterType): string
    {
        return $encounterType;
    }

    /**
     * Weighted monster selection per hunting area and player level.
     * This keeps area tables declarative in the database and the runtime logic compact.
     */
    private function resolveMonsterAssignment(LocationArea $area, int $characterLevel): LocationAreaMonster
    {
        $eligibleAssignments = $area->monsterAssignments
            ->filter(fn (LocationAreaMonster $assignment): bool => $assignment->isEligibleForLevel($characterLevel))
            ->values();

        if ($eligibleAssignments->isEmpty()) {
            $minimumAssignmentLevel = $area->minimumMonsterAssignmentLevel();

            if ($minimumAssignmentLevel !== null && $characterLevel < $minimumAssignmentLevel) {
                throw new GameActionException(__('You need to reach level :level before hunting in :area.', [
                    'level' => $minimumAssignmentLevel,
                    'area' => $area->name,
                ]));
            }

            $fallback = $area->monsterAssignments
                ->sortBy('minimum_character_level')
                ->first();

            if ($fallback === null) {
                throw new GameActionException(__('No monster packs are configured for this area yet.'));
            }

            return $fallback;
        }

        $weightTotal = max(1, (int) $eligibleAssignments->sum('weight'));
        $roll = random_int(1, $weightTotal);
        $cursor = 0;

        foreach ($eligibleAssignments as $assignment) {
            $cursor += (int) $assignment->weight;

            if ($roll <= $cursor) {
                return $assignment;
            }
        }

        return $eligibleAssignments->last();
    }

    /**
     * Automatic hunting now considers the whole build, not just the raw character level.
     * Manual fights may also explicitly target one monster family and a difficulty tier.
     */
    private function resolveMonsterAssignmentForCharacter(
        LocationArea $area,
        GameCharacter $character,
        ?string $requestedFamily = null,
        ?string $requestedDifficulty = null,
    ): LocationAreaMonster {
        $allAssignments = $area->monsterAssignments->values();

        if ($allAssignments->isEmpty()) {
            throw new GameActionException(__('No monster packs are configured for this area yet.'));
        }

        $requestedFamily = trim((string) $requestedFamily);
        $requestedDifficulty = trim((string) $requestedDifficulty);

        if ($requestedFamily !== '') {
            $familyAssignments = $allAssignments
                ->filter(fn (LocationAreaMonster $assignment): bool => $assignment->monster?->manualFamilyKey() === $requestedFamily)
                ->values();

            if ($familyAssignments->isEmpty()) {
                throw new GameActionException(__('That monster group is not available in this hunting area.'));
            }

            $tierCandidates = $this->manualDifficultyBucket($familyAssignments, $requestedDifficulty);

            return $this->nearestAssignmentToCharacter($tierCandidates, $character);
        }

        $eligibleAssignments = $allAssignments
            ->filter(fn (LocationAreaMonster $assignment): bool => $this->assignmentMatchesCharacter($assignment, $character))
            ->values();

        if ($eligibleAssignments->isEmpty()) {
            $minimumAssignmentLevel = $area->minimumMonsterAssignmentLevel();

            if ($minimumAssignmentLevel !== null && $character->level < $minimumAssignmentLevel) {
                throw new GameActionException(__('You need to reach level :level or grow stronger before hunting in :area.', [
                    'level' => $minimumAssignmentLevel,
                    'area' => $area->name,
                ]));
            }

            return $this->nearestAssignmentToCharacter($allAssignments, $character);
        }

        $combatRating = $this->characterCombatRating($character);
        $weightedAssignments = $eligibleAssignments->map(function (LocationAreaMonster $assignment) use ($combatRating): array {
            $distance = abs($this->assignmentThreatScore($assignment) - $combatRating);
            $ratingWeight = max(8, 120 - (int) floor($distance / 4));

            return [
                'assignment' => $assignment,
                'weight' => max(1, (int) $assignment->weight) + $ratingWeight,
            ];
        });

        $totalWeight = max(1, (int) $weightedAssignments->sum('weight'));
        $roll = random_int(1, $totalWeight);
        $cursor = 0;

        foreach ($weightedAssignments as $entry) {
            $cursor += (int) $entry['weight'];

            if ($roll <= $cursor) {
                return $entry['assignment'];
            }
        }

        return $eligibleAssignments->last();
    }

    private function assignmentMatchesCharacter(LocationAreaMonster $assignment, GameCharacter $character): bool
    {
        if ($assignment->isEligibleForLevel($character->level)) {
            return true;
        }

        $levelGap = max(0, (int) $assignment->minimum_character_level - (int) $character->level);

        if ($levelGap > $this->autoHuntLevelOvershoot($character)) {
            return false;
        }

        $requiredThreatShare = min(1, $this->autoHuntThreatShare($character) + ($levelGap * 0.02));

        return $this->characterCombatRating($character) >= (int) ceil($this->assignmentThreatScore($assignment) * $requiredThreatShare);
    }

    private function autoHuntLevelOvershoot(GameCharacter $character): int
    {
        return match (true) {
            $character->level < 5 => 0,
            $character->level < 12 => 1,
            $character->level < 25 => 2,
            default => 3,
        };
    }

    private function autoHuntThreatShare(GameCharacter $character): float
    {
        return match (true) {
            $character->level < 5 => 0.96,
            $character->level < 12 => 0.92,
            default => 0.88,
        };
    }

    private function characterCombatRating(GameCharacter $character): int
    {
        return max(1,
            ($character->level * 12)
            + ($character->effectiveAttack() * 5)
            + ($character->effectiveDefense() * 4)
            + ($character->effectiveVitality() * 6)
            + ($character->effectiveStrength() * 6)
            + ($character->effectiveDexterity() * 5)
            + ($character->effectiveIntelligence() * 4)
            + (int) floor($character->effectiveMaxHealth() / 8)
            + (int) floor($character->effectiveMaxSp() / 10)
        );
    }

    private function assignmentThreatScore(LocationAreaMonster $assignment): int
    {
        $monster = $assignment->monster;

        if ($monster === null) {
            return max(1, $assignment->minimum_character_level * 12);
        }

        $averageAttack = (int) floor((((int) $monster->minimum_attack) + ((int) $monster->maximum_attack)) / 2);

        return max(1,
            (((int) $monster->level) * 12)
            + ($averageAttack * 5)
            + (((int) $monster->defense) * 4)
            + (int) floor(((int) $monster->maximum_health) / 7)
            + (((int) $monster->rank) * 18)
            + (max(1, (int) $assignment->pack_size) * 14)
        );
    }

    /**
     * @param  Collection<int, LocationAreaMonster>  $assignments
     * @return Collection<int, LocationAreaMonster>
     */
    private function manualDifficultyBucket(Collection $assignments, ?string $requestedDifficulty): Collection
    {
        $difficulty = match ($requestedDifficulty) {
            'easy', 'hard' => $requestedDifficulty,
            default => 'medium',
        };

        $sorted = $assignments
            ->sortBy(fn (LocationAreaMonster $assignment): int => $this->assignmentThreatScore($assignment))
            ->values();
        $bucketSize = max(1, (int) ceil($sorted->count() / 3));
        $buckets = [
            'easy' => $sorted->slice(0, $bucketSize)->values(),
            'medium' => $sorted->slice($bucketSize, $bucketSize)->values(),
            'hard' => $sorted->slice($bucketSize * 2)->values(),
        ];

        if ($buckets[$difficulty]->isNotEmpty()) {
            return $buckets[$difficulty];
        }

        return match ($difficulty) {
            'easy' => $sorted->take(max(1, min(2, $sorted->count())))->values(),
            'hard' => $sorted->slice(max(0, $sorted->count() - max(1, min(2, $sorted->count()))))->values(),
            default => $sorted,
        };
    }

    /**
     * @param  Collection<int, LocationAreaMonster>  $assignments
     */
    private function nearestAssignmentToCharacter(Collection $assignments, GameCharacter $character): LocationAreaMonster
    {
        $combatRating = $this->characterCombatRating($character);

        /** @var LocationAreaMonster $assignment */
        $assignment = $assignments
            ->sortBy(fn (LocationAreaMonster $entry): int => abs($this->assignmentThreatScore($entry) - $combatRating))
            ->first();

        return $assignment;
    }

    /**
     * Ensures actions can only target areas on the player's current map and only on huntable nodes.
     */
    private function resolveHuntingArea(GameCharacter $character, int $locationAreaId): LocationArea
    {
        /** @var LocationArea|null $area */
        $area = $character->location->areas->firstWhere('id', $locationAreaId);

        if ($area === null) {
            throw new GameActionException(__('That hunting area is not part of your current map.'));
        }

        if (! $area->isHunting()) {
            throw new GameActionException(__('That hotspot is a town service, not a hunting ground.'));
        }

        return $area;
    }

    /**
     * Creates area progress lazily so callers do not need to care whether a record already exists.
     */
    private function areaProgress(GameCharacter $character, LocationArea $area): CharacterAreaProgress
    {
        /** @var CharacterAreaProgress|null $progress */
        $progress = $character->areaProgress->firstWhere('location_area_id', $area->id);

        if ($progress !== null) {
            return $progress;
        }

        $progress = CharacterAreaProgress::query()->create([
            'game_character_id' => $character->id,
            'location_area_id' => $area->id,
        ]);

        $character->setRelation(
            'areaProgress',
            $character->areaProgress->push($progress),
        );

        return $progress;
    }

    /**
     * Bosses are no longer random auto-encounters.
     *
     * Players explicitly trigger them from the UI once they have filled the area's pressure meter
     * by clearing monsters and breaking Lumiis stones.
     */
    private function resolveEncounterType(
        LocationArea $area,
        CharacterAreaProgress $progress,
        string $requestedEncounter,
    ): string {
        $requestedEncounter = $this->normalizeEncounterRequest($requestedEncounter);

        if ($requestedEncounter === 'boss') {
            if (! $this->bossIsReady($area, $progress)) {
                throw new GameActionException(
                    __('The boss soul is still hidden. Fight monsters and Lumiis in this area first.'),
                );
            }

            return 'boss';
        }

        return $requestedEncounter;
    }

    /**
     * Pressure is the current "boss readiness" meter for one character in one hunting area.
     */
    private function bossIsReady(LocationArea $area, CharacterAreaProgress $progress): bool
    {
        return $progress->pressure >= (int) $area->boss_pressure_threshold;
    }

    /**
     * Pulls min/max encounter values from the area definition.
     */
    private function rollEncounterValue(LocationArea $area, string $encounterType, string $resource): int
    {
        $columnPrefix = $this->encounterColumnPrefix($encounterType);
        $minimum = (int) data_get($area, $columnPrefix.'_'.$resource.'_min', 0);
        $maximum = (int) data_get($area, $columnPrefix.'_'.$resource.'_max', $minimum);

        if ($maximum <= $minimum) {
            return $minimum;
        }

        return random_int($minimum, $maximum);
    }

    /**
     * Loot tables are DB-driven. This helper turns raw config into concrete item grants.
     *
     * @return list<array{slug: string, label: string, quantity: int}>
     */
    private function rollLoot(array $lootTable, int $bonusPercent = 0, ?GameCharacter $character = null): array
    {
        if ($lootTable === []) {
            return [];
        }

        $slugMap = collect($lootTable)
            ->pluck('slug')
            ->filter()
            ->unique()
            ->values();

        /** @var \Illuminate\Support\Collection<string, Item> $items */
        $items = Item::query()
            ->whereIn('slug', $slugMap)
            ->get()
            ->keyBy('slug');

        $drops = [];

        foreach ($lootTable as $entry) {
            $slug = (string) data_get($entry, 'slug', '');

            if ($slug === '') {
                continue;
            }

            $chance = max(0, min(10000, (int) data_get($entry, 'chance', 0)));
            $adjustedChance = max(0, min(10000, $chance + (int) floor($chance * ($bonusPercent / 100))));

            if ($adjustedChance === 0 || random_int(1, 10000) > $adjustedChance) {
                continue;
            }

            $minimum = max(1, (int) data_get($entry, 'min', 1));
            $maximum = max($minimum, (int) data_get($entry, 'max', $minimum));
            $quantity = $maximum > $minimum ? random_int($minimum, $maximum) : $minimum;
            $item = $items->get($slug);
            $resolvedDrop = $item instanceof Item
                ? ['slug' => $slug, 'label' => $item->name]
                : $this->resolveDynamicLootItem($slug, $character);

            if ($resolvedDrop === null) {
                continue;
            }

            $drops[] = [
                'slug' => $resolvedDrop['slug'],
                'label' => $resolvedDrop['label'],
                'quantity' => $quantity,
            ];
        }

        return $drops;
    }

    /**
     * @return array{slug: string, label: string}|null
     */
    private function resolveDynamicLootItem(string $slug, ?GameCharacter $character): ?array
    {
        if ($slug !== MetinClassCatalog::matchingSkillBookRewardToken() || ! $character instanceof GameCharacter) {
            return null;
        }

        $resolvedSlug = MetinClassCatalog::preferredSkillBookSlugForCharacter($character);

        if ($resolvedSlug === null) {
            return null;
        }

        $item = Item::query()->where('slug', $resolvedSlug)->first();

        if (! $item instanceof Item) {
            return null;
        }

        return [
            'slug' => $item->slug,
            'label' => $item->name,
        ];
    }

    /**
     * Builds the user-facing summary line for non-pack encounters.
     *
     * @param  list<string>  $itemNames
     */
    private function encounterMessage(
        LocationArea $area,
        string $encounterType,
        string $requestedEncounter,
        int $experienceGain,
        int $yangGain,
        int $damageTaken,
        array $itemNames,
    ): string {
        $encounterType = $this->normalizeEncounterRequest($encounterType);
        $requestedEncounter = $this->normalizeEncounterRequest($requestedEncounter);
        $encounterName = $area->encounterName($encounterType) ?? $area->name;

        $message = match ($encounterType) {
            'boss' => __('The corrupted soul of :encounter breaks free in :area.', [
                'encounter' => $encounterName,
                'area' => $area->name,
            ]),
            'lumiis' => __('You shatter :encounter in :area and release trapped souls.', [
                'encounter' => $encounterName,
                'area' => $area->name,
            ]),
            default => __('You cleanse :area and bring down :encounter.', [
                'area' => $area->name,
                'encounter' => $encounterName,
            ]),
        };

        if ($encounterType === 'boss' && $requestedEncounter === 'lumiis') {
            $message .= ' '.__('Enough trapped souls have been released to expose the source of the corruption.');
        } elseif ($encounterType === 'boss') {
            $message .= ' '.__('The pressure in this area has finally exposed the source of the corruption.');
        }

        $message .= ' '.__('+:experience EXP, +:yang Yang', [
            'experience' => $experienceGain,
            'yang' => $yangGain,
        ]);

        if ($damageTaken > 0) {
            $message .= __(', -:damage HP', ['damage' => $damageTaken]);
        }

        if ($itemNames !== []) {
            $message .= ' '.__('Loot: :items.', ['items' => implode(', ', $itemNames)]);
        } else {
            $message .= '.';
        }

        return $message;
    }

    /**
     * Auto Lumiis fights still resolve inside one request, so we generate a replay payload here.
     *
     * @param  list<string>  $itemNames
     * @return array{battle_log: list<string>, battle_summary: array<string, mixed>, battle_replay: array<string, mixed>}
     */
    private function buildEncounterReplayPayload(
        GameCharacter $character,
        LocationArea $area,
        string $encounterType,
        string $encounterName,
        int $playerStartingHealth,
        int $effectiveAttack,
        int $effectiveDefense,
        int $effectiveSpeed,
        int $damageTaken,
        int $experienceGain,
        int $yangGain,
        int $staminaCost,
        int $cooldownSeconds,
        array $itemNames,
        string $message,
    ): array {
        $encounterType = $this->normalizeEncounterRequest($encounterType);
        $areaDifficulty = max(
            1,
            (int) ($area->recommended_level ?: max(1, $character->location->threat_level)),
        );
        $rounds = match ($encounterType) {
            'boss' => max(6, min(10, 5 + intdiv($areaDifficulty, 4))),
            'lumiis' => max(4, min(8, 4 + intdiv($areaDifficulty, 6))),
            default => max(3, min(6, 3 + intdiv($areaDifficulty, 8))),
        };
        $enemyHealthMax = match ($encounterType) {
            'boss' => 96 + ($areaDifficulty * 14),
            'lumiis' => 64 + ($areaDifficulty * 11),
            default => 42 + ($areaDifficulty * 8),
        };
        $playerDamageSlices = $this->distributeReplayPoints($enemyHealthMax, $rounds, 1);
        $enemyAttackCount = max(1, $rounds - 1);
        $enemyDamageSlices = $this->distributeReplayPoints($damageTaken, $enemyAttackCount, $damageTaken > 0 ? 1 : 0);
        $combatSkills = $this->buildCombatSkills($character);
        $enemyHealth = $enemyHealthMax;
        $playerHealth = $playerStartingHealth;
        $enemyDamageType = DamageTypeCatalog::enemyDamageType(null, $encounterType, match ($encounterType) {
            'lumiis' => 'Lumiis Core',
            'boss' => 'Boss',
            default => 'Monster',
        });
        $enemyResistanceBadges = DamageTypeCatalog::resistanceBadges(
            DamageTypeCatalog::enemyResistanceProfile($encounterType, match ($encounterType) {
                'lumiis' => 'Lumiis Core',
                'boss' => 'Boss',
                default => 'Monster',
            }),
        );
        $battleLog = [
            __('Encounter: :encounter in :area.', [
                'encounter' => $encounterName,
                'area' => $area->name,
            ]),
        ];
        $battleSteps = [];

        foreach ($playerDamageSlices as $index => $playerDamage) {
            $round = $index + 1;
            $battleLog[] = __('Round :round:', ['round' => $round]);
            $enemyHealth = max(0, $enemyHealth - $playerDamage);
            $selectedSkill = $combatSkills !== []
                && ($round === 1 || random_int(1, 100) <= 35)
                ? $combatSkills[array_rand($combatSkills)]
                : null;

            $playerAttackMessage = $selectedSkill !== null
                ? __(':player uses :skill on :enemy for :damage damage.', [
                    'player' => $character->name,
                    'skill' => $selectedSkill['name'],
                    'enemy' => $encounterName,
                    'damage' => $playerDamage,
                ])
                : match ($encounterType) {
                    'lumiis' => __(':player cracks :enemy for :damage damage.', [
                        'player' => $character->name,
                        'enemy' => $encounterName,
                        'damage' => $playerDamage,
                    ]),
                    'boss' => __(':player cuts into :enemy for :damage damage.', [
                        'player' => $character->name,
                        'enemy' => $encounterName,
                        'damage' => $playerDamage,
                    ]),
                    default => __(':player hits :enemy for :damage damage.', [
                        'player' => $character->name,
                        'enemy' => $encounterName,
                        'damage' => $playerDamage,
                    ]),
                };

            if ($enemyHealth === 0) {
                $playerAttackMessage .= match ($encounterType) {
                    'lumiis' => ' '.__(':enemy shatters and trapped souls spill free.', ['enemy' => $encounterName]),
                    default => ' '.__(':enemy falls.', ['enemy' => $encounterName]),
                };
            }

            $battleLog[] = $playerAttackMessage;
            $battleSteps[] = [
                'round' => $round,
                'actor' => 'player',
                'actor_label' => $character->name,
                'target_type' => 'enemy',
                'target_id' => 'enemy-1',
                'target_label' => $encounterName,
                'damage' => $playerDamage,
                'remaining_health' => $enemyHealth,
                'message' => $playerAttackMessage,
            ];

            if ($enemyHealth === 0) {
                break;
            }

            if (! array_key_exists($index, $enemyDamageSlices)) {
                continue;
            }

            $enemyDamage = $enemyDamageSlices[$index];
            $playerHealth = max(0, $playerHealth - $enemyDamage);
            $enemyAttackMessage = match ($encounterType) {
                'lumiis' => __(':enemy releases a soul burst for :damage damage.', [
                    'enemy' => $encounterName,
                    'damage' => $enemyDamage,
                ]),
                'boss' => __(':enemy slams :player for :damage damage.', [
                    'enemy' => $encounterName,
                    'player' => $character->name,
                    'damage' => $enemyDamage,
                ]),
                default => __(':enemy claws :player for :damage damage.', [
                    'enemy' => $encounterName,
                    'player' => $character->name,
                    'damage' => $enemyDamage,
                ]),
            };

            $battleLog[] = $enemyAttackMessage;
            $battleSteps[] = [
                'round' => $round,
                'actor' => 'enemy',
                'actor_label' => $encounterName,
                'target_type' => 'player',
                'target_label' => $character->name,
                'damage' => $enemyDamage,
                'remaining_health' => $playerHealth,
                'message' => $enemyAttackMessage,
            ];
        }

        return [
            'battle_log' => $battleLog,
            'battle_summary' => [
                'monster_name' => $encounterName,
                'pack_size' => 1,
                'rounds' => count($playerDamageSlices),
                'victory' => true,
                'encounter_type' => $encounterType,
            ],
            'battle_replay' => [
                'title' => $encounterName,
                'location' => $area->name,
                'encounter_type' => $encounterType,
                'victory' => true,
                'player' => $this->buildReplayPlayerProfile(
                    $character,
                    $playerStartingHealth,
                    $effectiveAttack,
                    $effectiveDefense,
                    $effectiveSpeed,
                ),
                'enemies' => [[
                    'id' => 'enemy-1',
                    'name' => $encounterName,
                    'label' => $encounterName,
                    'level' => $areaDifficulty,
                    'health' => $enemyHealthMax,
                    'max_health' => $enemyHealthMax,
                    'attack' => match ($encounterType) {
                        'boss' => (string) (18 + $areaDifficulty).'-'.(24 + ($areaDifficulty * 2)),
                        'lumiis' => (string) (9 + $areaDifficulty).'-'.(14 + $areaDifficulty),
                        default => (string) (6 + $areaDifficulty).'-'.(10 + $areaDifficulty),
                    },
                    'category' => match ($encounterType) {
                        'lumiis' => 'Lumiis Core',
                        'boss' => 'Boss',
                        default => 'Monster',
                    },
                    'damage_type' => $enemyDamageType,
                    'damage_type_label' => DamageTypeCatalog::label($enemyDamageType),
                    'resistance_badges' => $enemyResistanceBadges,
                ]],
                'steps' => $battleSteps,
                'summary' => [
                    'message' => $message,
                    'rounds' => count($playerDamageSlices),
                    'experience' => $experienceGain,
                    'yang' => $yangGain,
                    'health_lost' => $damageTaken,
                    'stamina_cost' => $staminaCost,
                    'loot' => $itemNames,
                    'cooldown_seconds' => $cooldownSeconds,
                ],
            ],
        ];
    }

    /**
     * Builds the static player card shown inside replays and battle windows.
     *
     * @return array{name: string, level: int, health: int, max_health: int, sp: int, max_sp: int, attack: int, defense: int, speed: int, magic_attack: int, crit_chance: int, evade_chance: int, equipment: list<string>}
     */
    private function buildReplayPlayerProfile(
        GameCharacter $character,
        int $startingHealth,
        int $effectiveAttack,
        int $effectiveDefense,
        int $effectiveSpeed,
    ): array {
        $equipment = $character->equipment
            ->map(function (CharacterEquipment $equipment): ?string {
                $itemName = $equipment->characterItem?->displayName();

                return $itemName === null
                    ? null
                    : ucfirst($equipment->slot).': '.$itemName;
            })
            ->filter()
            ->values()
            ->all();

        return [
            'name' => $character->name,
            'level' => (int) $character->level,
            'health' => $startingHealth,
            'max_health' => (int) $character->effectiveMaxHealth(),
            'sp' => (int) $character->effectiveCurrentSp(),
            'max_sp' => (int) $character->effectiveMaxSp(),
            'attack' => $effectiveAttack,
            'defense' => $effectiveDefense,
            'speed' => $effectiveSpeed,
            'magic_attack' => (int) $character->effectiveMagicAttack(),
            'crit_chance' => (int) $character->criticalChancePercent(),
            'evade_chance' => (int) $character->evadeChancePercent(),
            'primary_damage_type' => DamageTypeCatalog::primaryCharacterDamageType($character),
            'primary_damage_type_label' => DamageTypeCatalog::label(DamageTypeCatalog::primaryCharacterDamageType($character)),
            'resistance_badges' => DamageTypeCatalog::resistanceBadges($character->combatResistanceProfile()),
            'equipment' => $equipment,
        ];
    }

    /**
     * Spreads total damage across replay rounds so the playback feels alive instead of flat.
     *
     * @return list<int>
     */
    private function distributeReplayPoints(int $total, int $parts, int $minimumPerPart = 0): array
    {
        if ($parts <= 0) {
            return [];
        }

        if ($total <= 0) {
            return array_fill(0, $parts, 0);
        }

        $minimumPerPart = min($minimumPerPart, intdiv($total, $parts));
        $values = array_fill(0, $parts, $minimumPerPart);
        $remaining = $total - ($minimumPerPart * $parts);

        while ($remaining > 0) {
            foreach (array_keys($values) as $index) {
                if ($remaining <= 0) {
                    break;
                }

                $values[$index]++;
                $remaining--;
            }
        }

        $shuffleCount = min($parts * 2, max(1, $total));

        for ($index = 0; $index < $shuffleCount; $index++) {
            $from = random_int(0, $parts - 1);
            $to = random_int(0, $parts - 1);

            if ($from === $to || $values[$from] <= $minimumPerPart) {
                continue;
            }

            $values[$from]--;
            $values[$to]++;
        }

        return $values;
    }
}
