<?php

namespace App\Services\Game\Concerns;

use App\Exceptions\GameActionException;
use App\Models\CharacterItem;
use App\Models\CharacterSkill;
use App\Models\GameBattle;
use App\Models\GameCharacter;
use App\Models\LocationArea;
use App\Support\Game\DamageTypeCatalog;
use App\Support\Game\MetinClassCatalog;
use App\Support\Game\TutorialFlow;
use App\Support\Game\WeaponCombatProfileCatalog;
use Illuminate\Support\Carbon;

/**
 * Combat-specific action handlers.
 *
 * This trait contains everything that mutates battle state:
 * auto encounters, manual battle turns, reward payout and replay payload creation.
 */
trait HandlesCombatActions
{
    /**
     * Normal map monster action. Areas with configured monster tables use the full pack battle flow.
     *
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function fightMonsters(GameCharacter $character, array $payload, Carbon $processedAt): array
    {
        $area = $this->resolveHuntingArea($character, (int) $payload['location_area_id']);

        if ($area->monsterAssignments->isNotEmpty()) {
            return $this->fightMonsterPack($character, $area, $processedAt);
        }

        return $this->resolveAreaEncounter($character, $payload, $processedAt, 'monster');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function searchLumiis(GameCharacter $character, array $payload, Carbon $processedAt): array
    {
        return $this->resolveAreaEncounter($character, $payload, $processedAt, 'lumiis');
    }

    /**
     * Starts a persistent manual battle that the player continues with follow-up requests.
     *
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function startManualFight(GameCharacter $character, array $payload, Carbon $processedAt): array
    {
        $requestedEncounter = $this->normalizeEncounterRequest((string) $payload['encounter_request']);
        $requestedFamily = $requestedEncounter === 'monster' ? trim((string) ($payload['monster_family'] ?? '')) : null;
        $requestedDifficulty = $requestedEncounter === 'monster' ? trim((string) ($payload['monster_difficulty'] ?? 'medium')) : null;
        $area = $this->resolveHuntingArea($character, (int) $payload['location_area_id']);
        $useTutorialBattle = $this->shouldUseTutorialManualBattle($character, $area, $requestedEncounter);

        if ($useTutorialBattle) {
            // The onboarding fight should always start from a stable state so new players cannot
            // accidentally soft-lock themselves by entering it wounded or exhausted.
            $character->health = $character->effectiveMaxHealth();
            $character->stamina = $character->effectiveMaxStamina();
            $character->sp = $character->effectiveMaxSp();
        }

        $battleBlueprint = $useTutorialBattle
            ? $this->initializeTutorialMonsterBattle($character, $area)
            : ($requestedEncounter === 'monster' && $area->monsterAssignments->isNotEmpty()
                ? $this->initializeManualPackBattle($character, $area, $requestedFamily, $requestedDifficulty)
                : $this->initializeManualEncounterBattle($character, $area, $requestedEncounter));

        $character->stamina -= (int) $battleBlueprint['rewards']['stamina_cost'];
        $this->resetPassiveRecoveryClocks($character, $processedAt);

        GameBattle::query()->create([
            'game_character_id' => $character->id,
            'location_area_id' => $area->id,
            'mode' => 'manual',
            'requested_encounter' => $requestedEncounter,
            'encounter_type' => $battleBlueprint['encounter_type'],
            'encounter_name' => $battleBlueprint['encounter_name'],
            'status' => 'ongoing',
            'round' => 1,
            'state' => $battleBlueprint['state'],
            'rewards' => $battleBlueprint['rewards'],
            'started_at' => $processedAt,
        ]);

        if ($useTutorialBattle && TutorialFlow::currentStep($character) <= TutorialFlow::STEP_START_FIGHT) {
            $character->tutorial_step = TutorialFlow::STEP_BATTLE_UI;
        }

        return [
            'message' => __('Manual fight started against :name. Choose attacks or skills in the fight window.', [
                'name' => $battleBlueprint['encounter_name'],
            ]),
            'cooldown_seconds' => 0,
            'rewards' => [
                'stamina' => -((int) $battleBlueprint['rewards']['stamina_cost']),
            ],
            'target_location_id' => $character->location_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function battleAttack(
        GameCharacter $character,
        ?GameBattle $battle,
        array $payload,
        Carbon $processedAt,
    ): array {
        return $this->continueManualBattle($character, $battle, $payload, $processedAt, 'attack');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function battleUseSkill(
        GameCharacter $character,
        ?GameBattle $battle,
        array $payload,
        Carbon $processedAt,
    ): array {
        return $this->continueManualBattle($character, $battle, $payload, $processedAt, 'skill');
    }

    /**
     * Closing the browser should pause a manual fight, not mutate it further.
     * This explicit action exists for players who intentionally want to drop the fight
     * with no rewards and no hidden extra penalty beyond already spent stamina/damage.
     *
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int, battle_log: list<string>, battle_summary: array<string, mixed>, battle_replay: array<string, mixed>}
     */
    private function abandonManualFight(
        GameCharacter $character,
        ?GameBattle $battle,
        array $payload,
        Carbon $processedAt,
    ): array {
        $requestedBattleId = (int) ($payload['battle_id'] ?? 0);

        if (
            $battle === null
            || $battle->id !== $requestedBattleId
            || $battle->game_character_id !== $character->id
            || $battle->status !== 'ongoing'
        ) {
            throw new GameActionException(__('That manual battle is no longer active.'));
        }

        /** @var array<string, mixed> $state */
        $state = $battle->state ?? [];
        /** @var array<string, mixed> $rewards */
        $rewards = $battle->rewards ?? [];

        if ((bool) ($rewards['tutorial_battle'] ?? false)) {
            throw new GameActionException(__('Finish the tutorial fight before leaving it.'));
        }

        $area = LocationArea::query()->findOrFail($battle->location_area_id);
        $roundsFought = max(1, (int) ($state['round'] ?? $battle->round ?? 1));
        $currentHealth = max(1, (int) data_get($state, 'player.current_health', $character->health));
        $currentSp = max(0, min((int) data_get($state, 'player.current_sp', $character->effectiveCurrentSp()), $character->effectiveMaxSp()));
        $startingHealth = (int) data_get($state, 'player.starting_health', $character->health);
        $healthLost = max(0, $startingHealth - $currentHealth);
        $resolvedRewards = array_merge($rewards, [
            'experience' => 0,
            'yang' => 0,
            'loot_drops' => [],
            'loot_names' => [],
            'pressure_gain' => 0,
        ]);

        $state['battle_log'][] = __('You abandon the fight and retreat from the field.');
        $character->health = $currentHealth;
        $character->sp = $currentSp;

        $message = __('You abandon the fight against :enemy. No rewards were gained.', [
            'enemy' => (string) ($resolvedRewards['enemy_name'] ?? $battle->encounter_name),
        ]);

        $battle->status = 'abandoned';
        $battle->resolved_at = $processedAt;
        $battle->round = $roundsFought;
        $battle->state = $state;
        $battle->rewards = $resolvedRewards;
        $battle->save();

        return [
            'message' => $message,
            'cooldown_seconds' => 0,
            'rewards' => array_filter([
                'health' => -$healthLost,
            ], static fn (mixed $value): bool => $value !== 0),
            'target_location_id' => $character->location_id,
            'battle_log' => collect($state['battle_log'] ?? [])->values()->all(),
            'battle_summary' => [
                'monster_name' => (string) ($resolvedRewards['enemy_name'] ?? $battle->encounter_name),
                'pack_size' => (int) ($resolvedRewards['pack_size'] ?? 1),
                'rounds' => $roundsFought,
                'victory' => false,
                'encounter_type' => $this->normalizeEncounterRequest((string) ($resolvedRewards['encounter_type'] ?? $battle->encounter_type)),
            ],
            'battle_replay' => $this->buildResolvedBattleReplay(
                $character,
                $state,
                $resolvedRewards,
                $area,
                $message,
                0,
                $healthLost,
                $roundsFought,
                false,
            ),
        ];
    }

    /**
     * Resolves exactly one manual round per request.
     * That makes the battle state easy to persist, replay and debug.
     *
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function continueManualBattle(
        GameCharacter $character,
        ?GameBattle $battle,
        array $payload,
        Carbon $processedAt,
        string $playerActionType,
    ): array {
        $requestedBattleId = (int) ($payload['battle_id'] ?? 0);

        if (
            $battle === null
            || $battle->id !== $requestedBattleId
            || $battle->game_character_id !== $character->id
            || $battle->status !== 'ongoing'
        ) {
            throw new GameActionException(__('That manual battle is no longer active.'));
        }

        /** @var array<string, mixed> $state */
        $state = $battle->state ?? [];
        /** @var array<string, mixed> $rewards */
        $rewards = $battle->rewards ?? [];
        $currentRound = max(1, (int) ($state['round'] ?? $battle->round ?? 1));
        $requestedRound = (int) ($payload['battle_round'] ?? 0);

        // Parallel tabs or double-clicked requests may arrive after another turn already advanced
        // the battle. We reject stale round numbers so one manual turn can never be spent twice.
        if ($requestedRound !== $currentRound) {
            throw new GameActionException(
                __('This battle turn is no longer current. Refresh the battle window before acting again.'),
            );
        }

        $area = LocationArea::query()
            ->with('monsterAssignments.monster')
            ->findOrFail($battle->location_area_id);
        $enemyId = (string) $payload['enemy_id'];
        $skill = null;

        if ($playerActionType === 'skill') {
            $skill = collect(data_get($state, 'player.skills', []))
                ->firstWhere('slug', (string) $payload['skill_slug']);

            if ($skill === null) {
                throw new GameActionException(__('That battle skill is not available right now.'));
            }
        }

        $aliveEnemies = collect($state['enemies'] ?? [])
            ->filter(fn (array $enemy): bool => ($enemy['current_health'] ?? 0) > 0);

        if (! $aliveEnemies->contains(fn (array $enemy): bool => $enemy['id'] === $enemyId)) {
            throw new GameActionException(__('That target is no longer available in the battle.'));
        }

        $round = $currentRound;
        $state['battle_log'][] = __('Round :round:', ['round' => $round]);
        $this->applyManualPlayerAction($state, $enemyId, $playerActionType, $skill, $area, $character);

        if ($character->isInTutorial() && TutorialFlow::currentStep($character) === TutorialFlow::STEP_FIRST_ATTACK) {
            $character->tutorial_step = TutorialFlow::STEP_FINISH_FIGHT;
        }

        if (! collect($state['enemies'])->contains(fn (array $enemy): bool => ($enemy['current_health'] ?? 0) > 0)) {
            return $this->finishManualBattle($character, $battle, $state, $rewards, $processedAt, true);
        }

        $this->applyManualEnemyTurn($state);

        if (((int) data_get($state, 'player.current_health', 0)) <= 0) {
            return $this->finishManualBattle($character, $battle, $state, $rewards, $processedAt, false);
        }

        $this->tryAutoUseHealthPotionForBattleState($character, $state);
        $state['player']['skills'] = $this->normalizeCombatSkillPool(data_get($state, 'player.skills', []));
        $this->tickCombatSkillCooldowns($state['player']['skills']);
        $state['round'] = $round + 1;
        $battle->state = $state;
        $battle->round = (int) $state['round'];
        $battle->save();
        $character->health = max(1, (int) data_get($state, 'player.current_health', 1));

        return [
            'message' => (string) collect($state['battle_log'])->last(),
            'cooldown_seconds' => 0,
            'rewards' => [],
            'target_location_id' => $character->location_id,
        ];
    }

    /**
     * Handles one-request auto encounters such as monster sweeps and Lumiis breaks.
     *
     * Bosses are intentionally excluded here. They are manual-only so the player can experience
     * them as the centerpiece encounter of an area's cleanse cycle.
     *
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function resolveAreaEncounter(
        GameCharacter $character,
        array $payload,
        Carbon $processedAt,
        string $requestedEncounter,
    ): array {
        $area = $this->resolveHuntingArea($character, (int) $payload['location_area_id']);
        $progress = $this->areaProgress($character, $area);
        $requestedEncounter = $this->normalizeEncounterRequest($requestedEncounter);

        $staminaCost = $requestedEncounter === 'lumiis' ? 6 : 4;
        $minimumHealth = $requestedEncounter === 'lumiis' ? 24 : 18;

        if ($character->stamina < $staminaCost) {
            throw new GameActionException(
                __('You need at least :stamina stamina to enter :area.', [
                    'stamina' => $staminaCost,
                    'area' => $area->name,
                ]),
            );
        }

        if ($character->health <= $minimumHealth) {
            throw new GameActionException(__('You are too wounded for :area. Rest before returning.', [
                'area' => $area->name,
            ]));
        }

        $encounterType = $this->resolveEncounterType($area, $progress, $requestedEncounter);

        if ($encounterType === 'lumiis') {
            // Lumiis fights are special enough to deserve their own server-side simulation.
            return $this->fightAutoLumiisBattle($character, $area, $processedAt);
        }

        $cooldownSeconds = (int) data_get(
            $area,
            $this->encounterColumnPrefix($encounterType).'_cooldown_seconds',
            45,
        );
        $areaDifficulty = max(
            1,
            (int) ($area->recommended_level ?: max(1, $character->location->threat_level)),
        );

        $effectiveAttack = $character->effectiveAttack();
        $effectiveDefense = $character->effectiveDefense();

        $experienceGain = $this->applyRewardRateBonus(
            $character,
            $this->rollEncounterValue($area, $encounterType, 'exp')
            + max(0, intdiv($effectiveAttack, $encounterType === 'boss' ? 3 : 4))
            + max(0, $areaDifficulty - $character->level),
            'experience',
        );

        $yangGain = $this->applyRewardRateBonus(
            $character,
            $this->rollEncounterValue($area, $encounterType, 'yang')
            + max(0, intdiv($effectiveAttack, $encounterType === 'boss' ? 4 : 5)),
            'yang',
        );

        $rawDamage = $this->rollEncounterValue($area, $encounterType, 'damage')
            + max(0, $areaDifficulty - max(1, $character->level));
        $minimumDamage = match ($encounterType) {
            'boss' => 10,
            'lumiis' => 6,
            default => 3,
        };
        $damageReduction = intdiv($effectiveDefense, $encounterType === 'boss' ? 4 : 3);
        $encounterCategory = match ($encounterType) {
            'lumiis' => 'Lumiis Core',
            'boss' => 'Boss',
            default => 'Monster',
        };
        $encounterDamageType = DamageTypeCatalog::enemyDamageType(null, $encounterType, $encounterCategory);
        $encounterResistanceProfile = DamageTypeCatalog::enemyResistanceProfile($encounterType, $encounterCategory);
        $matchupTempoPercent = DamageTypeCatalog::resistancePercent(
            $encounterResistanceProfile,
            DamageTypeCatalog::primaryCharacterDamageType($character),
        );
        $damageTaken = max($minimumDamage, $rawDamage - $damageReduction);
        $damageTaken = max(1, (int) floor($damageTaken * (1 + ($matchupTempoPercent / 100))));
        $damageTaken = DamageTypeCatalog::damageAfterResistance(
            $damageTaken,
            $encounterDamageType,
            $character->combatResistanceProfile(),
        );
        $damageTaken = min(max(0, $character->health - 1), $damageTaken);

        $character->stamina -= $staminaCost;
        $character->yang += $yangGain;
        $character->experience += $experienceGain;
        $character->health = max(1, $character->health - $damageTaken);
        $this->resetPassiveRecoveryClocks($character, $processedAt);
        $character->action_available_at = $processedAt->copy()->addSeconds($cooldownSeconds);

        $pressureDelta = 0;

        if ($encounterType === 'boss') {
            $progress->pressure = 0;
            $progress->boss_fights += 1;
            $progress->last_boss_at = $processedAt;
        } elseif ($encounterType === 'lumiis') {
            $pressureDelta = (int) $area->lumiis_pressure_gain;
            $progress->pressure += $pressureDelta;
            $progress->lumiis_searches += 1;
        } else {
            $pressureDelta = (int) $area->monster_pressure_gain;
            $progress->pressure += $pressureDelta;
            $progress->monster_fights += 1;
        }

        $progress->save();

        $lootDrops = $this->rollLoot($area->lootTable($encounterType), $character->lootRatePercentBonus(), $character);
        $itemNames = [];

        foreach ($lootDrops as $drop) {
            $this->inventoryService->grant(
                $character,
                $drop['slug'],
                $drop['quantity'],
                $this->dropGrantOptions($encounterType),
            );
            $itemNames[] = $drop['label'].($drop['quantity'] > 1 ? ' x'.$drop['quantity'] : '');
        }

        $message = $this->encounterMessage(
            $area,
            $encounterType,
            $requestedEncounter,
            $experienceGain,
            $yangGain,
            $damageTaken,
            $itemNames,
        );

        $encounterName = $area->encounterName($encounterType) ?? $area->name;
        $replayPayload = $this->buildEncounterReplayPayload(
            $character,
            $area,
            $encounterType,
            $encounterName,
            $character->health + $damageTaken,
            $effectiveAttack,
            $effectiveDefense,
            $character->effectiveSpeed(),
            $damageTaken,
            $experienceGain,
            $yangGain,
            $staminaCost,
            $cooldownSeconds,
            $itemNames,
            $message,
        );

        return [
            'message' => $message,
            'cooldown_seconds' => $cooldownSeconds,
            'rewards' => [
                'stamina' => -$staminaCost,
                'yang' => $yangGain,
                'experience' => $experienceGain,
                'health' => -$damageTaken,
                'pressure' => $encounterType === 'boss' ? 0 : $pressureDelta,
                'items_found' => count($lootDrops),
            ],
            'target_location_id' => $character->location_id,
            'battle_log' => $replayPayload['battle_log'],
            'battle_summary' => $replayPayload['battle_summary'],
            'battle_replay' => $replayPayload['battle_replay'],
        ];
    }

    /**
     * Auto-pack combat is still fully server-authoritative.
     * The client only receives the replay log and final outcome.
     *
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int, battle_log: list<string>, battle_summary: array<string, mixed>}
     */
    private function fightMonsterPack(GameCharacter $character, LocationArea $area, Carbon $processedAt): array
    {
        $assignment = $this->resolveMonsterAssignmentForCharacter($area, $character);
        $monster = $assignment->monster;
        $packSize = max(1, (int) $assignment->pack_size);
        $progress = $this->areaProgress($character, $area);
        $staminaCost = 4 + intdiv(max(0, $monster->level - 1), 12);

        if ($character->stamina < $staminaCost) {
            throw new GameActionException(__('You need at least :stamina stamina to challenge this hunting pack.', [
                'stamina' => $staminaCost,
            ]));
        }

        if ($character->health <= 12) {
            throw new GameActionException(__('You are too wounded to survive a pack fight. Rest and heal first.'));
        }

        $playerHealth = $character->health;
        $playerStartingHealth = $character->health;
        $effectiveAttack = $character->effectiveAttack();
        $effectiveDefense = $character->effectiveDefense();
        $effectiveSpeed = $character->effectiveSpeed();
        $effectiveMagicAttack = $character->effectiveMagicAttack();
        $criticalChance = $character->criticalChancePercent();
        $evadeChance = $character->evadeChancePercent();
        $weaponType = $character->equippedWeaponType();
        $playerResistanceProfile = $character->combatResistanceProfile();
        $skillGroupCombatProfile = MetinClassCatalog::skillGroupCombatProfile(
            $character->chosenRace(),
            $character->hasChosenSkillGroup() ? (string) $character->skill_group : null,
        );
        $playerSp = $character->effectiveCurrentSp();
        $combatSkills = $this->normalizeCombatSkillPool($this->buildCombatSkills($character));
        $temporaryGuard = 0;
        $temporaryGuardRounds = 0;
        $temporaryCritBonus = 0;
        $temporaryCritBonusRounds = 0;
        $temporaryEvadeBonus = 0;
        $temporaryEvadeBonusRounds = 0;
        $temporaryAttackBonus = 0;
        $temporaryAttackBonusRounds = 0;
        $temporaryLifestealPercent = 0;
        $temporaryLifestealPercentRounds = 0;
        $temporaryReflectPercent = 0;
        $temporaryReflectPercentRounds = 0;
        $temporaryDamageToSpPercent = 0;
        $temporaryDamageToSpPercentRounds = 0;
        $temporaryIncomingDamagePercent = 0;
        $temporaryIncomingDamagePercentRounds = 0;
        $enemies = [];

        for ($index = 1; $index <= $packSize; $index++) {
            $enemies[] = [
                'id' => 'enemy-'.$index,
                'label' => $monster->name.' #'.$index,
                'health' => (int) $monster->maximum_health,
                'damage_type' => DamageTypeCatalog::enemyDamageType((string) $monster->damage_type, 'monster', (string) $monster->race, $monster->manualFamilyKey()),
                'resistances' => DamageTypeCatalog::enemyResistanceProfile('monster', (string) $monster->race, $monster->manualFamilyKey(), (string) $monster->damage_type),
                'family' => $monster->manualFamilyKey(),
                'poison_rounds' => 0,
                'poison_damage' => 0,
                'burn_rounds' => 0,
                'burn_damage' => 0,
                'attack_down_percent' => 0,
                'attack_down_rounds' => 0,
                'hit_penalty_percent' => 0,
                'hit_penalty_rounds' => 0,
                'stagger_rounds' => 0,
            ];
        }

        $battleLog = [
            __('Encounter: :pack (Lv :level) in :area.', [
                'pack' => $packSize.'x '.$monster->name,
                'level' => $monster->level,
                'area' => $area->name,
            ]),
        ];
        $battleSteps = [];
        $round = 1;

        while ($playerHealth > 0 && collect($enemies)->contains(fn (array $enemy): bool => $enemy['health'] > 0) && $round <= 60) {
            $battleLog[] = __('Round :round:', ['round' => $round]);

            foreach ($enemies as $enemyIndex => $enemy) {
                if ($enemy['health'] > 0) {
                    $activeSelfBuffRounds = max(
                        $temporaryAttackBonusRounds,
                        $temporaryGuardRounds,
                        $temporaryCritBonusRounds,
                        $temporaryEvadeBonusRounds,
                        $temporaryLifestealPercentRounds,
                        $temporaryReflectPercentRounds,
                        $temporaryDamageToSpPercentRounds,
                        $temporaryIncomingDamagePercentRounds,
                    );
                    $selectedSkill = $this->chooseAutoCombatSkill($combatSkills, $playerSp, $round, $activeSelfBuffRounds);
                    $skillSpCost = (int) ($selectedSkill['sp_cost'] ?? 0);
                    $playerSp = max(0, $playerSp - $skillSpCost);

                    if ($selectedSkill !== null && (bool) ($selectedSkill['self_buff_only'] ?? false)) {
                        $buffRounds = max(
                            1,
                            (int) (($selectedSkill['buff_rounds'] ?? 0) ?: ($selectedSkill['attack_buff_rounds'] ?? 0)),
                        );
                        $effectDuration = $buffRounds + 1;
                        $this->applyTemporaryEffect(
                            $temporaryAttackBonus,
                            $temporaryAttackBonusRounds,
                            (int) ($selectedSkill['attack_buff'] ?? 0),
                            $effectDuration,
                        );
                        $this->applyTemporaryEffect(
                            $temporaryGuard,
                            $temporaryGuardRounds,
                            (int) ($selectedSkill['guard'] ?? 0),
                            $effectDuration,
                        );
                        $this->applyTemporaryEffect(
                            $temporaryCritBonus,
                            $temporaryCritBonusRounds,
                            (int) ($selectedSkill['crit_bonus'] ?? 0),
                            $effectDuration,
                        );
                        $this->applyTemporaryEffect(
                            $temporaryEvadeBonus,
                            $temporaryEvadeBonusRounds,
                            (int) ($selectedSkill['evade_bonus'] ?? 0),
                            $effectDuration,
                        );
                        $this->applyTemporaryEffect(
                            $temporaryLifestealPercent,
                            $temporaryLifestealPercentRounds,
                            (int) ($selectedSkill['lifesteal_percent'] ?? 0),
                            $effectDuration,
                        );
                        $this->applyTemporaryEffect(
                            $temporaryReflectPercent,
                            $temporaryReflectPercentRounds,
                            (int) ($selectedSkill['reflect_percent'] ?? 0),
                            $effectDuration,
                        );
                        $this->applyTemporaryEffect(
                            $temporaryDamageToSpPercent,
                            $temporaryDamageToSpPercentRounds,
                            (int) ($selectedSkill['damage_to_sp_percent'] ?? 0),
                            $effectDuration,
                        );
                        $this->applyTemporaryEffect(
                            $temporaryIncomingDamagePercent,
                            $temporaryIncomingDamagePercentRounds,
                            max(0, -((int) ($selectedSkill['incoming_damage_percent'] ?? 0))),
                            $effectDuration,
                        );

                        $playerAttackMessage = __(':player invokes :skill. :effects for the next :rounds rounds.', [
                            'player' => $character->name,
                            'skill' => $selectedSkill['name'],
                            'effects' => $this->describeSelfBuffEffects($selectedSkill),
                            'rounds' => $buffRounds,
                        ]);
                        $battleLog[] = $playerAttackMessage;
                        $battleSteps[] = [
                            'round' => $round,
                            'actor' => 'player',
                            'actor_label' => $character->name,
                            'target_type' => 'player',
                            'target_id' => 'player',
                            'target_label' => $character->name,
                            'damage' => 0,
                            'remaining_health' => $playerHealth,
                            'message' => $playerAttackMessage,
                        ];
                        $this->markCombatSkillUsed($combatSkills, $selectedSkill['slug'] ?? null);

                        break;
                    }

                    $attackBaseStat = $selectedSkill !== null && (bool) ($selectedSkill['uses_magic_attack'] ?? false)
                        ? $effectiveMagicAttack
                        : $effectiveAttack;
                    $attackBaseStat += $this->activeTemporaryEffect($temporaryAttackBonus, $temporaryAttackBonusRounds);
                    $attackBounds = WeaponCombatProfileCatalog::attackRollBounds($weaponType, $attackBaseStat, $effectiveSpeed);
                    $attackBounds = $this->applySkillGroupAttackBounds($attackBounds, $skillGroupCombatProfile);
                    $playerAttack = random_int($attackBounds['minimum'], $attackBounds['maximum']);
                    $playerAttack = (int) floor(
                        ($playerAttack + (int) ($selectedSkill['damage_bonus'] ?? 0))
                        * (float) ($selectedSkill['damage_multiplier'] ?? 1.0),
                    );
                    $playerAttack = $this->applyWeaponDamageBonus(
                        $character,
                        $playerAttack,
                        $selectedSkill !== null,
                        WeaponCombatProfileCatalog::damagePercentBonus($weaponType, $selectedSkill !== null, $round)
                            + $this->skillGroupDamagePercentBonus($skillGroupCombatProfile, $selectedSkill !== null, $round),
                    );
                    $playerDamage = $this->applyCategoryDamageBonus(
                        $character,
                        max(1, $playerAttack - (int) $monster->defense),
                        (string) $monster->race,
                    );
                    $criticalHitChance = min(
                        100,
                        $criticalChance
                        + WeaponCombatProfileCatalog::critChanceBonus($weaponType)
                        + (int) ($skillGroupCombatProfile['crit_chance_bonus'] ?? 0)
                        + $this->skillGroupOpeningCritBonus($skillGroupCombatProfile, $round)
                        + (int) ($selectedSkill['crit_bonus'] ?? 0)
                        + $this->activeTemporaryEffect($temporaryCritBonus, $temporaryCritBonusRounds),
                    );
                    $criticalHit = $criticalHitChance > 0 && random_int(1, 100) <= $criticalHitChance;

                    if ($criticalHit) {
                        $playerDamage = max($playerDamage + 1, (int) floor($playerDamage * 1.5));
                    }

                    $damageType = $this->resolvePlayerActionDamageType([
                        'race' => $character->chosenRace(),
                        'skill_group' => $character->hasChosenSkillGroup() ? (string) $character->skill_group : null,
                        'weapon_type' => $weaponType,
                    ], $selectedSkill);
                    $enemyResistancePercent = DamageTypeCatalog::resistancePercent(
                        $this->resolveEnemyResistanceProfile($enemies[$enemyIndex]),
                        $damageType,
                    );
                    $playerDamage = DamageTypeCatalog::damageAfterResistance(
                        $playerDamage,
                        $damageType,
                        $this->resolveEnemyResistanceProfile($enemies[$enemyIndex]),
                    );

                    $enemies[$enemyIndex]['health'] = max(0, $enemy['health'] - $playerDamage);
                    $this->applyTemporaryEffect(
                        $temporaryGuard,
                        $temporaryGuardRounds,
                        (int) ($selectedSkill['guard'] ?? 0)
                            + WeaponCombatProfileCatalog::guardBonus($weaponType)
                            + (int) ($skillGroupCombatProfile['guard_bonus'] ?? 0),
                        1,
                    );
                    $this->applyTemporaryEffect($temporaryCritBonus, $temporaryCritBonusRounds, (int) ($selectedSkill['crit_bonus'] ?? 0), 1);
                    $this->applyTemporaryEffect($temporaryEvadeBonus, $temporaryEvadeBonusRounds, (int) ($selectedSkill['evade_bonus'] ?? 0), 1);
                    $playerAttackMessage = $selectedSkill !== null
                        ? __(':player uses :skill on :enemy for :damage damage.', [
                            'player' => $character->name,
                            'skill' => $selectedSkill['name'],
                            'enemy' => $enemy['label'],
                            'damage' => $playerDamage,
                        ])
                        : __(':player attacks :enemy for :damage damage.', [
                            'player' => $character->name,
                            'enemy' => $enemy['label'],
                            'damage' => $playerDamage,
                        ]);

                    if ($criticalHit) {
                        $playerAttackMessage .= ' '.__('Critical hit!');
                    }

                    $playerAttackMessage .= $this->damageTypeImpactSuffix($enemyResistancePercent, $damageType);

                    if ($enemies[$enemyIndex]['health'] === 0) {
                        $playerAttackMessage .= ' '.__(':enemy falls.', ['enemy' => $enemy['label']]);
                    }

                    $battleLog[] = $playerAttackMessage;
                    $battleSteps[] = [
                        'round' => $round,
                        'actor' => 'player',
                        'actor_label' => $character->name,
                        'target_type' => 'enemy',
                        'target_id' => $enemy['id'],
                        'target_label' => $enemy['label'],
                        'damage' => $playerDamage,
                        'remaining_health' => $enemies[$enemyIndex]['health'],
                        'message' => $playerAttackMessage,
                    ];

                    $healAmount = max(
                        0,
                        (int) ($selectedSkill['heal_flat'] ?? 0)
                        + (int) floor($character->effectiveMaxHealth() * (((int) ($selectedSkill['heal_percent'] ?? 0)) / 100)),
                    );

                    if ($healAmount > 0) {
                        $healedTo = min($character->effectiveMaxHealth(), $playerHealth + $healAmount);
                        $actualHeal = max(0, $healedTo - $playerHealth);
                        $playerHealth = $healedTo;

                        if ($actualHeal > 0) {
                            $healMessage = __(':player restores :amount HP through :skill.', [
                                'player' => $character->name,
                                'amount' => $actualHeal,
                                'skill' => $selectedSkill['name'],
                            ]);
                            $battleLog[] = $healMessage;
                            $battleSteps[] = [
                                'round' => $round,
                                'actor' => 'player',
                                'actor_label' => $character->name,
                                'target_type' => 'player',
                                'target_label' => $character->name,
                                'damage' => -$actualHeal,
                                'remaining_health' => $playerHealth,
                                'message' => $healMessage,
                            ];
                        }
                    }

                    $lifestealPercent = max(
                        0,
                        (int) ($skillGroupCombatProfile['lifesteal_percent'] ?? 0)
                            + $this->activeTemporaryEffect($temporaryLifestealPercent, $temporaryLifestealPercentRounds),
                    );

                    if ($lifestealPercent > 0 && $playerDamage > 0 && $playerHealth > 0) {
                        $lifestealHeal = $this->lifestealHealAmount($playerDamage, $lifestealPercent);
                        $healedTo = min($character->effectiveMaxHealth(), $playerHealth + $lifestealHeal);
                        $actualHeal = max(0, $healedTo - $playerHealth);
                        $playerHealth = $healedTo;

                        if ($actualHeal > 0) {
                            $lifestealMessage = __(':player steals :amount HP through :skill.', [
                                'player' => $character->name,
                                'amount' => $actualHeal,
                                'skill' => $selectedSkill['name'] ?? __('the attack'),
                            ]);
                            $battleLog[] = $lifestealMessage;
                            $battleSteps[] = [
                                'round' => $round,
                                'actor' => 'player',
                                'actor_label' => $character->name,
                                'target_type' => 'player',
                                'target_label' => $character->name,
                                'damage' => -$actualHeal,
                                'remaining_health' => $playerHealth,
                                'message' => $lifestealMessage,
                            ];
                        }
                    }

                    $this->tryApplySkillStatusesToEnemy(
                        $enemies[$enemyIndex],
                        $selectedSkill,
                        $battleLog,
                        $battleSteps,
                        $round,
                    );

                    $this->markCombatSkillUsed($combatSkills, $selectedSkill['slug'] ?? null);

                    foreach ($enemies as $splashIndex => $splashEnemy) {
                        if ($splashIndex === $enemyIndex || $splashEnemy['health'] <= 0) {
                            continue;
                        }

                        $splashPercent = $selectedSkill !== null
                            ? (int) ($selectedSkill['splash_percent'] ?? 70)
                            : $this->resolvePackBasicSplashPercent((int) $monster->level, (string) $monster->race);

                        $splashDamage = max(
                            1,
                            (int) floor($playerDamage * ($splashPercent / 100)) - max(0, intdiv((int) $monster->defense, 2)),
                        );
                        $splashDamage = DamageTypeCatalog::damageAfterResistance(
                            $splashDamage,
                            $damageType,
                            $this->resolveEnemyResistanceProfile($enemies[$splashIndex]),
                        );
                        $enemies[$splashIndex]['health'] = max(0, $splashEnemy['health'] - $splashDamage);
                        $splashMessage = __(':enemy suffers :damage splash damage.', [
                            'enemy' => $splashEnemy['label'],
                            'damage' => $splashDamage,
                        ]);

                        if ($enemies[$splashIndex]['health'] === 0) {
                            $splashMessage .= ' '.__(':enemy falls.', ['enemy' => $splashEnemy['label']]);
                        }

                        $battleLog[] = $splashMessage;
                        $battleSteps[] = [
                            'round' => $round,
                            'actor' => 'player',
                            'actor_label' => $character->name,
                            'target_type' => 'enemy',
                            'target_id' => $splashEnemy['id'],
                            'target_label' => $splashEnemy['label'],
                            'damage' => $splashDamage,
                            'remaining_health' => $enemies[$splashIndex]['health'],
                            'message' => $splashMessage,
                        ];

                        $this->tryApplySkillStatusesToEnemy(
                            $enemies[$splashIndex],
                            $selectedSkill,
                            $battleLog,
                            $battleSteps,
                            $round,
                        );
                    }

                    break;
                }
            }

            if (! collect($enemies)->contains(fn (array $enemy): bool => $enemy['health'] > 0)) {
                break;
            }

            foreach ($enemies as $enemyIndex => $enemy) {
                $this->applyEnemyStatusTicks(
                    $enemies[$enemyIndex],
                    $battleLog,
                    $battleSteps,
                    $round,
                );
            }

            if (! collect($enemies)->contains(fn (array $enemy): bool => $enemy['health'] > 0)) {
                break;
            }

            $aliveEnemyCount = max(1, collect($enemies)->where('health', '>', 0)->count());

            foreach ($enemies as $enemyIndex => $enemy) {
                if ($enemy['health'] <= 0) {
                    continue;
                }

                if ((int) ($enemy['stagger_rounds'] ?? 0) > 0) {
                    $enemies[$enemyIndex]['stagger_rounds'] = max(0, (int) $enemy['stagger_rounds'] - 1);
                    $staggerMessage = __(':enemy is staggered and loses the turn.', [
                        'enemy' => $enemy['label'],
                    ]);
                    $battleLog[] = $staggerMessage;
                    $battleSteps[] = [
                        'round' => $round,
                        'actor' => 'system',
                        'actor_label' => __('Status'),
                        'target_type' => 'enemy',
                        'target_id' => $enemy['id'],
                        'target_label' => $enemy['label'],
                        'damage' => 0,
                        'remaining_health' => $enemies[$enemyIndex]['health'],
                        'message' => $staggerMessage,
                    ];
                    $this->decayEnemyEffect($enemies[$enemyIndex], 'attack_down_percent', 'attack_down_rounds');
                    $this->decayEnemyEffect($enemies[$enemyIndex], 'hit_penalty_percent', 'hit_penalty_rounds');

                    continue;
                }

                $enemyAttack = random_int((int) $monster->minimum_attack, (int) $monster->maximum_attack);
                $enemyAttack = $this->applyOutgoingEnemyDamageReduction(
                    $enemyAttack,
                    $this->activeEnemyEffect($enemy, 'attack_down_percent', 'attack_down_rounds'),
                );
                $activeGuard = $this->activeTemporaryEffect($temporaryGuard, $temporaryGuardRounds);
                $guardReduction = $activeGuard > 0
                    ? max(1, intdiv($activeGuard, $aliveEnemyCount))
                    : 0;
                $packAggressionBonus = $this->resolvePackAggressionBonus(
                    $aliveEnemyCount,
                    (int) $monster->level,
                    (string) $monster->race,
                );
                $effectiveEvadeChance = min(
                    95,
                    $evadeChance
                        + $this->activeTemporaryEffect($temporaryEvadeBonus, $temporaryEvadeBonusRounds)
                        + $this->activeEnemyEffect($enemy, 'hit_penalty_percent', 'hit_penalty_rounds'),
                );
                $dodged = $effectiveEvadeChance > 0
                    && random_int(1, 100) <= $effectiveEvadeChance;
                $enemyDamage = $dodged
                    ? 0
                    : max(
                        $this->minimumEnemyDamage((int) $monster->level, (string) $monster->race, $aliveEnemyCount),
                        $enemyAttack
                        + $packAggressionBonus
                        + $this->enemyPressureBonus((int) $monster->level, (int) $monster->rank)
                        - $this->enemyDamageReduction($effectiveDefense)
                        - $guardReduction,
                    );
                $enemyDamage = $dodged
                    ? 0
                    : $this->applyIncomingDamagePercentModifier(
                        $enemyDamage,
                        (int) ($skillGroupCombatProfile['incoming_damage_percent'] ?? 0)
                            - $this->activeTemporaryEffect(
                                $temporaryIncomingDamagePercent,
                                $temporaryIncomingDamagePercentRounds,
                            ),
                    );
                $enemyDamageType = $this->resolveEnemyDamageType($enemy);
                $playerResistancePercent = DamageTypeCatalog::resistancePercent($playerResistanceProfile, $enemyDamageType);
                $enemyDamage = $dodged
                    ? 0
                    : DamageTypeCatalog::damageAfterResistance($enemyDamage, $enemyDamageType, $playerResistanceProfile);
                $spAbsorbed = 0;

                if (! $dodged) {
                    $enemyDamage = $this->absorbDamageIntoSp(
                        $enemyDamage,
                        $playerSp,
                        (int) ($skillGroupCombatProfile['damage_to_sp_percent'] ?? 0)
                            + $this->activeTemporaryEffect(
                                $temporaryDamageToSpPercent,
                                $temporaryDamageToSpPercentRounds,
                            ),
                        $spAbsorbed,
                    );
                }

                $playerHealth -= $enemyDamage;
                $enemyAttackMessage = $dodged
                    ? __(':player dodges :enemy.', [
                        'player' => $character->name,
                        'enemy' => $enemy['label'],
                    ])
                    : __(':enemy hits :player for :damage damage.', [
                        'enemy' => $enemy['label'],
                        'player' => $character->name,
                        'damage' => $enemyDamage,
                    ]);

                if ($spAbsorbed > 0) {
                    $enemyAttackMessage .= ' '.__(':amount damage is absorbed by SP.', ['amount' => $spAbsorbed]);
                }

                if (! $dodged) {
                    $enemyAttackMessage .= $this->damageTypeImpactSuffix($playerResistancePercent, $enemyDamageType);
                }

                if ($playerHealth <= 0) {
                    $enemyAttackMessage .= ' '.__(':player collapses.', ['player' => $character->name]);
                }

                $battleLog[] = $enemyAttackMessage;
                $battleSteps[] = [
                    'round' => $round,
                    'actor' => 'enemy',
                    'actor_label' => $enemy['label'],
                    'target_type' => 'player',
                    'target_label' => $character->name,
                    'damage' => $enemyDamage,
                    'remaining_health' => max(0, $playerHealth),
                    'message' => $enemyAttackMessage,
                ];

                $reflectPercent = max(
                    0,
                    (int) ($skillGroupCombatProfile['reflect_percent'] ?? 0)
                        + $this->activeTemporaryEffect($temporaryReflectPercent, $temporaryReflectPercentRounds),
                );

                if (! $dodged && $enemyDamage > 0 && $reflectPercent > 0 && $enemies[$enemyIndex]['health'] > 0) {
                    $reflectedDamage = min(
                        $enemies[$enemyIndex]['health'],
                        $this->reflectedDamageAmount($enemyDamage, $reflectPercent),
                    );
                    $enemies[$enemyIndex]['health'] = max(0, $enemies[$enemyIndex]['health'] - $reflectedDamage);

                    if ($reflectedDamage > 0) {
                        $reflectMessage = __(':enemy is hit by reflected force for :damage damage.', [
                            'enemy' => $enemy['label'],
                            'damage' => $reflectedDamage,
                        ]);

                        if ($enemies[$enemyIndex]['health'] === 0) {
                            $reflectMessage .= ' '.__(':enemy falls.', ['enemy' => $enemy['label']]);
                        }

                        $battleLog[] = $reflectMessage;
                        $battleSteps[] = [
                            'round' => $round,
                            'actor' => 'system',
                            'actor_label' => $character->name,
                            'target_type' => 'enemy',
                            'target_id' => $enemy['id'],
                            'target_label' => $enemy['label'],
                            'damage' => $reflectedDamage,
                            'remaining_health' => $enemies[$enemyIndex]['health'],
                            'message' => $reflectMessage,
                        ];
                    }
                }

                $this->decayEnemyEffect($enemies[$enemyIndex], 'attack_down_percent', 'attack_down_rounds');
                $this->decayEnemyEffect($enemies[$enemyIndex], 'hit_penalty_percent', 'hit_penalty_rounds');

                if ($playerHealth <= 0) {
                    $battleLog[] = __(':player is overwhelmed and dragged back to town.', [
                        'player' => $character->name,
                    ]);

                    break;
                }
            }

            $this->tryAutoUseHealthPotion(
                $character,
                $playerHealth,
                $character->effectiveMaxHealth(),
                $battleLog,
                $battleSteps,
                $round,
            );

            $retainedGuard = 0;

            if ($temporaryGuardRounds <= 1 && $temporaryGuard > 0) {
                $retainedGuard = (int) floor($temporaryGuard * (((int) ($skillGroupCombatProfile['guard_retention_percent'] ?? 0)) / 100));
            }

            $this->decayTemporaryEffect($temporaryGuard, $temporaryGuardRounds);
            $this->decayTemporaryEffect($temporaryCritBonus, $temporaryCritBonusRounds);
            $this->decayTemporaryEffect($temporaryEvadeBonus, $temporaryEvadeBonusRounds);
            $this->decayTemporaryEffect($temporaryAttackBonus, $temporaryAttackBonusRounds);
            $this->decayTemporaryEffect($temporaryLifestealPercent, $temporaryLifestealPercentRounds);
            $this->decayTemporaryEffect($temporaryReflectPercent, $temporaryReflectPercentRounds);
            $this->decayTemporaryEffect($temporaryDamageToSpPercent, $temporaryDamageToSpPercentRounds);
            $this->decayTemporaryEffect($temporaryIncomingDamagePercent, $temporaryIncomingDamagePercentRounds);

            if ($retainedGuard > 0) {
                $this->applyTemporaryEffect($temporaryGuard, $temporaryGuardRounds, $retainedGuard, 1);
            }

            $this->tickCombatSkillCooldowns($combatSkills);

            $round++;
        }

        $victory = $playerHealth > 0 && ! collect($enemies)->contains(fn (array $enemy): bool => $enemy['health'] > 0);
        $roundsFought = max(1, $round);
        $cooldownSeconds = max(
            (int) $area->monster_cooldown_seconds,
            12 + ($roundsFought * 4) + intdiv((int) $monster->level, 2) + ($victory ? 0 : 18),
        );

        $character->stamina -= $staminaCost;
        $character->sp = max(0, min($playerSp, $character->effectiveMaxSp()));
        $character->action_available_at = $processedAt->copy()->addSeconds($cooldownSeconds);
        $this->resetPassiveRecoveryClocks($character, $processedAt);

        $progress->pressure += (int) $area->monster_pressure_gain;
        $progress->monster_fights += 1;
        $progress->save();
        $replayPlayerProfile = $this->buildReplayPlayerProfile(
            $character,
            $playerStartingHealth,
            $effectiveAttack,
            $effectiveDefense,
            $effectiveSpeed,
        );
        $replayEnemies = array_map(
            fn (array $enemy): array => [
                'id' => $enemy['id'],
                'name' => $monster->name,
                'label' => $enemy['label'],
                'level' => (int) $monster->level,
                'health' => (int) $monster->maximum_health,
                'max_health' => (int) $monster->maximum_health,
                'attack' => (int) $monster->minimum_attack.'-'.(int) $monster->maximum_attack,
                'category' => (string) $monster->race,
                'damage_type' => (string) ($enemy['damage_type'] ?? 'physical'),
                'damage_type_label' => DamageTypeCatalog::label((string) ($enemy['damage_type'] ?? 'physical')),
                'resistance_badges' => DamageTypeCatalog::resistanceBadges((array) ($enemy['resistances'] ?? [])),
            ],
            $enemies,
        );

        if (! $victory) {
            $character->health = 1;
            $healthLost = max(0, $playerStartingHealth - 1);
            $message = __('The :monster pack drives you back after :rounds rounds. You escape with 1 HP.', [
                'monster' => $monster->name,
                'rounds' => $roundsFought,
            ]);

            return [
                'message' => $message,
                'cooldown_seconds' => $cooldownSeconds,
                'rewards' => [
                    'stamina' => -$staminaCost,
                    'health' => -$healthLost,
                    'pressure' => (int) $area->monster_pressure_gain,
                ],
                'target_location_id' => $character->location_id,
                'battle_log' => $battleLog,
                'battle_summary' => [
                    'monster_name' => $monster->name,
                    'pack_size' => $packSize,
                    'rounds' => $roundsFought,
                    'victory' => false,
                ],
                'battle_replay' => [
                    'title' => $packSize.'x '.$monster->name,
                    'location' => $area->name,
                    'encounter_type' => 'monster',
                    'victory' => false,
                    'player' => $replayPlayerProfile,
                    'enemies' => $replayEnemies,
                    'steps' => $battleSteps,
                    'summary' => [
                        'message' => $message,
                        'rounds' => $roundsFought,
                        'experience' => 0,
                        'yang' => 0,
                        'health_lost' => $healthLost,
                        'stamina_cost' => $staminaCost,
                        'loot' => [],
                        'cooldown_seconds' => $cooldownSeconds,
                    ],
                ],
            ];
        }

        $character->health = max(1, $playerHealth);
        $experienceGain = $this->resolveManualPackExperienceReward($character, $area, $monster, $packSize);
        $yangGain = $this->resolveManualPackYangReward($character, $area, $monster, $packSize);
        $character->experience += $experienceGain;
        $character->yang += $yangGain;

        $lootDrops = $this->rollLoot($area->lootTable('monster'), $character->lootRatePercentBonus(), $character);
        $itemNames = [];

        foreach ($lootDrops as $drop) {
            $this->inventoryService->grant($character, $drop['slug'], $drop['quantity'], $this->dropGrantOptions('monster'));
            $itemNames[] = $drop['label'].($drop['quantity'] > 1 ? ' x'.$drop['quantity'] : '');
        }

        $message = __('You defeat :pack after :rounds rounds. +:experience EXP, +:yang Yang, -:health HP', [
            'pack' => $packSize.'x '.$monster->name,
            'rounds' => $roundsFought,
            'experience' => $experienceGain,
            'yang' => $yangGain,
            'health' => $playerStartingHealth - $playerHealth,
        ]);

        if ($itemNames !== []) {
            $message .= ' '.__('Loot: :items.', ['items' => implode(', ', $itemNames)]);
        } else {
            $message .= '.';
        }
        $healthLost = max(0, $playerStartingHealth - $playerHealth);

        return [
            'message' => $message,
            'cooldown_seconds' => $cooldownSeconds,
            'rewards' => [
                'stamina' => -$staminaCost,
                'yang' => $yangGain,
                'experience' => $experienceGain,
                'health' => -$healthLost,
                'pressure' => (int) $area->monster_pressure_gain,
                'items_found' => count($lootDrops),
            ],
            'target_location_id' => $character->location_id,
            'battle_log' => $battleLog,
            'battle_summary' => [
                'monster_name' => $monster->name,
                'pack_size' => $packSize,
                'rounds' => $roundsFought,
                'victory' => true,
            ],
            'battle_replay' => [
                'title' => $packSize.'x '.$monster->name,
                'location' => $area->name,
                'encounter_type' => 'monster',
                'victory' => true,
                'player' => $replayPlayerProfile,
                'enemies' => $replayEnemies,
                'steps' => $battleSteps,
                'summary' => [
                    'message' => $message,
                    'rounds' => $roundsFought,
                    'experience' => $experienceGain,
                    'yang' => $yangGain,
                    'health_lost' => $healthLost,
                    'stamina_cost' => $staminaCost,
                    'loot' => $itemNames,
                    'cooldown_seconds' => $cooldownSeconds,
                ],
            ],
        ];
    }

    /**
     * Auto Lumiis still uses the same real battle rules as manual fights.
     *
     * The difference is only presentation: the server resolves the whole battle in one request and
     * the UI replays the stored steps afterwards.
     *
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int, battle_log: list<string>, battle_summary: array<string, mixed>, battle_replay: array<string, mixed>}
     */
    private function fightAutoLumiisBattle(GameCharacter $character, LocationArea $area, Carbon $processedAt): array
    {
        $battleBlueprint = $this->initializeManualEncounterBattle($character, $area, 'lumiis');
        /** @var array<string, mixed> $state */
        $state = $battleBlueprint['state'];
        /** @var array<string, mixed> $rewards */
        $rewards = $battleBlueprint['rewards'];
        $state['player']['skills'] = $this->normalizeCombatSkillPool(
            collect(data_get($state, 'player.skills', []))->values()->all(),
        );

        while (
            (int) data_get($state, 'player.current_health', 0) > 0
            && collect($state['enemies'] ?? [])->contains(fn (array $enemy): bool => ((int) ($enemy['current_health'] ?? 0)) > 0)
            && (int) ($state['round'] ?? 1) <= 80
        ) {
            $round = (int) ($state['round'] ?? 1);
            $state['battle_log'][] = 'Round '.$round.':';
            $targetEnemyId = $this->resolveAutoBattleTargetId($state);

            if ($targetEnemyId === null) {
                break;
            }

            $selectedSkill = $this->chooseAutoCombatSkill(
                $state['player']['skills'],
                (int) data_get($state, 'player.current_sp', 0),
                $round,
            );

            $this->applyManualPlayerAction(
                $state,
                $targetEnemyId,
                $selectedSkill === null ? 'attack' : 'skill',
                $selectedSkill,
                $area,
                $character,
            );

            if (! collect($state['enemies'])->contains(fn (array $enemy): bool => ((int) ($enemy['current_health'] ?? 0)) > 0)) {
                break;
            }

            $this->applyManualEnemyTurn($state);

            if ((int) data_get($state, 'player.current_health', 0) <= 0) {
                break;
            }

            $this->tryAutoUseHealthPotionForBattleState($character, $state);
            $this->tickCombatSkillCooldowns($state['player']['skills']);
            $state['round'] = $round + 1;
        }

        return $this->resolveAutoBattleBlueprint(
            $character,
            $area,
            $state,
            $rewards,
            $processedAt,
        );
    }

    /**
     * Auto Lumiis fights should feel readable instead of suicidal.
     *
     * The resolver now clears freed souls first and only returns to the core once the current wave
     * is under control. That matches the intended loop: crack the stone, survive the release,
     * clean up the souls, then break the next layer.
     *
     * @param  array<string, mixed>  $state
     */
    private function resolveAutoBattleTargetId(array $state): ?string
    {
        $aliveEnemies = collect($state['enemies'] ?? [])
            ->filter(fn (array $enemy): bool => ((int) ($enemy['current_health'] ?? 0)) > 0)
            ->values();

        if ($aliveEnemies->isEmpty()) {
            return null;
        }

        $aliveSoul = $aliveEnemies->first(fn (array $enemy): bool => ($enemy['category'] ?? null) !== 'Lumiis Core');
        $aliveCore = $aliveEnemies->first(fn (array $enemy): bool => ($enemy['category'] ?? null) === 'Lumiis Core');

        return (string) data_get($aliveSoul ?? $aliveCore ?? $aliveEnemies->first(), 'id');
    }

    /**
     * Applies the resolved auto-battle result to the character without creating a persistent manual battle row.
     *
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $rewards
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int, battle_log: list<string>, battle_summary: array<string, mixed>, battle_replay: array<string, mixed>}
     */
    private function resolveAutoBattleBlueprint(
        GameCharacter $character,
        LocationArea $area,
        array $state,
        array $rewards,
        Carbon $processedAt,
    ): array {
        $progress = $this->areaProgress($character, $area);
        $victory = (int) data_get($state, 'player.current_health', 0) > 0
            && ! collect($state['enemies'] ?? [])->contains(fn (array $enemy): bool => ((int) ($enemy['current_health'] ?? 0)) > 0);
        $roundsFought = max(1, (int) data_get($state, 'round', 1));
        $startingHealth = (int) data_get($state, 'player.starting_health', $character->health);
        $currentHealth = max(0, (int) data_get($state, 'player.current_health', 0));
        // Auto-resolved Lumiis runs should respect the area's configured cooldown instead of the
        // longer manual battle formula. The player already pays the "slow fight" cost in the
        // replay length and stamina spend, so the post-action lock should stay predictable.
        $cooldownSeconds = (int) ($rewards['cooldown_base'] ?? 45);
        $lootNames = collect($rewards['loot_names'] ?? [])->values()->all();
        $encounterType = (string) ($rewards['encounter_type'] ?? 'monster');
        $healthLost = max(0, $startingHealth - ($victory ? $currentHealth : 1));

        $character->stamina -= (int) ($rewards['stamina_cost'] ?? 0);
        $character->sp = max(0, min((int) data_get($state, 'player.current_sp', $character->effectiveCurrentSp()), $character->effectiveMaxSp()));
        $character->action_available_at = $processedAt->copy()->addSeconds($cooldownSeconds);
        $this->resetPassiveRecoveryClocks($character, $processedAt);

        if ($encounterType === 'boss') {
            $progress->pressure = 0;

            if ($victory) {
                $progress->boss_fights += 1;
                $progress->last_boss_at = $processedAt;
            }
        } elseif ($encounterType === 'lumiis') {
            $progress->pressure += (int) ($rewards['pressure_gain'] ?? 0);

            if ($victory) {
                $progress->lumiis_searches += 1;
            }
        } else {
            $progress->pressure += (int) ($rewards['pressure_gain'] ?? 0);

            if ($victory) {
                $progress->monster_fights += 1;
            }
        }

        $progress->save();

        if ($victory) {
            $character->health = max(1, $currentHealth);
            $character->experience += (int) ($rewards['experience'] ?? 0);
            $character->yang += (int) ($rewards['yang'] ?? 0);

            foreach ($rewards['loot_drops'] ?? [] as $drop) {
                $this->inventoryService->grant(
                    $character,
                    $drop['slug'],
                    $drop['quantity'],
                    $this->dropGrantOptions($encounterType),
                );
            }
        } else {
            $character->health = 1;
        }

        $message = $this->manualBattleMessage(
            $area,
            $rewards,
            $roundsFought,
            $healthLost,
            $victory,
            $lootNames,
        );

        return [
            'message' => $message,
            'cooldown_seconds' => $cooldownSeconds,
            'rewards' => array_filter([
                'stamina' => -((int) ($rewards['stamina_cost'] ?? 0)),
                'yang' => $victory ? (int) ($rewards['yang'] ?? 0) : 0,
                'experience' => $victory ? (int) ($rewards['experience'] ?? 0) : 0,
                'health' => -$healthLost,
                'pressure' => (int) ($rewards['pressure_gain'] ?? 0),
                'items_found' => $victory ? count($rewards['loot_drops'] ?? []) : 0,
            ], static fn (mixed $value): bool => $value !== 0),
            'target_location_id' => $character->location_id,
            'battle_log' => collect($state['battle_log'] ?? [])->values()->all(),
            'battle_summary' => [
                'monster_name' => (string) ($rewards['enemy_name'] ?? $area->name),
                'pack_size' => (int) ($rewards['pack_size'] ?? 1),
                'rounds' => $roundsFought,
                'victory' => $victory,
                'encounter_type' => $encounterType,
            ],
            'battle_replay' => $this->buildResolvedBattleReplay(
                $character,
                $state,
                $rewards,
                $area,
                $message,
                $cooldownSeconds,
                $healthLost,
                $roundsFought,
                $victory,
            ),
        ];
    }

    /**
     * Manual pack battles are created from current area and player state.
     *
     * @return array{encounter_type: string, encounter_name: string, state: array<string, mixed>, rewards: array<string, mixed>}
     */
    private function initializeManualPackBattle(
        GameCharacter $character,
        LocationArea $area,
        ?string $requestedFamily = null,
        ?string $requestedDifficulty = null,
    ): array
    {
        $assignment = $this->resolveMonsterAssignmentForCharacter($area, $character, $requestedFamily, $requestedDifficulty);
        $monster = $assignment->monster;
        $packSize = max(1, (int) $assignment->pack_size);
        $difficultyProfile = $this->manualPackDifficultyProfile($requestedDifficulty);
        $staminaCost = 4 + intdiv(max(0, $monster->level - 1), 12) + (int) $difficultyProfile['stamina_bonus'];

        if ($character->stamina < $staminaCost) {
            throw new GameActionException(__('You need at least :stamina stamina to challenge this hunting pack.', [
                'stamina' => $staminaCost,
            ]));
        }

        if ($character->health <= 12) {
            throw new GameActionException(__('You are too wounded to survive a pack fight. Rest and heal first.'));
        }

        $effectiveAttack = $character->effectiveAttack();
        $effectiveDefense = $character->effectiveDefense();
        $effectiveSpeed = $character->effectiveSpeed();
        $enemyHealth = max(1, (int) round((int) $monster->maximum_health * (float) $difficultyProfile['enemy_stat_multiplier']));
        $enemyMinAttack = max(1, (int) round((int) $monster->minimum_attack * (float) $difficultyProfile['enemy_stat_multiplier']));
        $enemyMaxAttack = max($enemyMinAttack + 1, (int) round((int) $monster->maximum_attack * (float) $difficultyProfile['enemy_stat_multiplier']));
        $enemyDefense = max(0, (int) round((int) $monster->defense * (float) $difficultyProfile['enemy_stat_multiplier']));
        $lootDrops = $this->rollLoot(
            $area->lootTable('monster'),
            $character->lootRatePercentBonus() + (int) $difficultyProfile['loot_rate_bonus_percent'],
            $character,
        );
        $lootNames = [];

        foreach ($lootDrops as $drop) {
            $lootNames[] = $drop['label'].($drop['quantity'] > 1 ? ' x'.$drop['quantity'] : '');
        }

        $yangGain = $this->resolveManualPackYangReward(
            $character,
            $area,
            $monster,
            $packSize,
            (float) $difficultyProfile['reward_multiplier'],
        );

        return [
            'encounter_type' => 'monster',
            'encounter_name' => $packSize.'x '.$monster->name.' · '.$difficultyProfile['label'],
            'state' => [
                'player' => $this->buildManualBattlePlayerState(
                    $character,
                    $effectiveAttack,
                    $effectiveDefense,
                    $effectiveSpeed,
                ),
                'enemies' => collect(range(1, $packSize))
                    ->map(fn (int $index): array => [
                        'id' => 'enemy-'.$index,
                        'name' => $monster->name,
                        'label' => $monster->name.' #'.$index,
                        'level' => (int) $monster->level,
                        'current_health' => $enemyHealth,
                        'max_health' => $enemyHealth,
                        'minimum_attack' => $enemyMinAttack,
                        'maximum_attack' => $enemyMaxAttack,
                        'defense' => $enemyDefense,
                        'category' => (string) $monster->race,
                        'damage_type' => DamageTypeCatalog::enemyDamageType((string) $monster->damage_type, 'monster', (string) $monster->race, $monster->manualFamilyKey()),
                        'resistances' => DamageTypeCatalog::enemyResistanceProfile('monster', (string) $monster->race, $monster->manualFamilyKey(), (string) $monster->damage_type),
                        'family' => $monster->manualFamilyKey(),
                        'encounter_type' => 'monster',
                        'attack_down_percent' => 0,
                        'attack_down_rounds' => 0,
                        'hit_penalty_percent' => 0,
                        'hit_penalty_rounds' => 0,
                        'stagger_rounds' => 0,
                    ])
                    ->all(),
                'battle_log' => [__('Encounter: :pack (Lv :level) in :area.', [
                    'pack' => $packSize.'x '.$monster->name.' · '.$difficultyProfile['label'],
                    'level' => $monster->level,
                    'area' => $area->name,
                ])],
                'battle_steps' => [],
                'round' => 1,
            ],
            'rewards' => [
                'encounter_type' => 'monster',
                'requested_encounter' => 'monster',
                'enemy_name' => $monster->name,
                'enemy_level' => (int) $monster->level,
                'pack_size' => $packSize,
                'experience' => $this->resolveManualPackExperienceReward(
                    $character,
                    $area,
                    $monster,
                    $packSize,
                    (float) $difficultyProfile['reward_multiplier'],
                ),
                'yang' => $yangGain,
                'loot_drops' => $lootDrops,
                'loot_names' => $lootNames,
                'stamina_cost' => $staminaCost,
                'pressure_gain' => (int) $area->monster_pressure_gain,
                'cooldown_base' => (int) $area->monster_cooldown_seconds,
                'difficulty_label' => $difficultyProfile['label'],
                'monster_family' => $monster->manualFamilyKey(),
            ],
        ];
    }

    /**
     * Manual pack fights used to pay out directly from the raw monster template EXP values.
     * That made midgame and dungeon packs explode into near-instant level-ups because those
     * template values had also been stretched to drive monster identity and threat. We now tie
     * the reward back to the area's intended pacing and only layer in small monster-level/rank
     * bonuses on top.
     */
    private function resolveManualPackExperienceReward(
        GameCharacter $character,
        LocationArea $area,
        \App\Models\Monster $monster,
        int $packSize,
        float $rewardMultiplier = 1.0,
    ): int {
        $baseAreaExperience = (int) round(
            (((int) $area->monster_exp_min) + ((int) $area->monster_exp_max)) / 2,
        );
        $packMultiplier = 1 + (max(1, $packSize) - 1) * 0.55;
        $monsterLevelBonus = max(0, intdiv((int) $monster->level, 2));
        $monsterRankBonus = max(0, ((int) $monster->rank - 1) * 6);
        $rawReward = max(
            1,
            (int) round((($baseAreaExperience + $monsterLevelBonus + $monsterRankBonus) * $packMultiplier) * $rewardMultiplier),
        );

        return $this->applyRewardRateBonus($character, $rawReward, 'experience');
    }

    /**
     * Yang follows the same rule as EXP here: area pacing first, monster identity second.
     * This keeps manual fights worth doing without letting raw template Yang values flood the
     * economy once the player reaches Map 2 or the first dungeon.
     */
    private function resolveManualPackYangReward(
        GameCharacter $character,
        LocationArea $area,
        \App\Models\Monster $monster,
        int $packSize,
        float $rewardMultiplier = 1.0,
    ): int {
        $baseAreaYang = (int) round(
            (((int) $area->monster_yang_min) + ((int) $area->monster_yang_max)) / 2,
        );
        $packMultiplier = 1 + (max(1, $packSize) - 1) * 0.4;
        $monsterLevelBonus = max(0, intdiv((int) $monster->level, 6));
        $monsterRankBonus = max(0, ((int) $monster->rank - 1) * 4);
        $rawReward = max(
            1,
            (int) round((($baseAreaYang + $monsterLevelBonus + $monsterRankBonus) * $packMultiplier) * $rewardMultiplier),
        );

        return $this->applyRewardRateBonus($character, $rawReward, 'yang');
    }

    /**
     * The first tutorial battle is intentionally hand-authored instead of using
     * the live monster table.
     *
     * That lets us guarantee a readable, winnable first fight without weakening
     * the real map progression for every other player state.
     *
     * @return array{encounter_type: string, encounter_name: string, state: array<string, mixed>, rewards: array<string, mixed>}
     */
    private function initializeTutorialMonsterBattle(GameCharacter $character, LocationArea $area): array
    {
        $effectiveAttack = max($character->effectiveAttack(), 10);
        $effectiveDefense = max($character->effectiveDefense(), 6);
        $effectiveSpeed = max($character->effectiveSpeed(), 5);

        return [
            'encounter_type' => 'monster',
            'encounter_name' => '3x Wildhund',
            'state' => [
                'player' => [
                    ...$this->buildManualBattlePlayerState(
                        $character,
                        $effectiveAttack,
                        $effectiveDefense,
                        $effectiveSpeed,
                    ),
                    'current_health' => $character->effectiveMaxHealth(),
                    'starting_health' => $character->effectiveMaxHealth(),
                ],
                'enemies' => collect(range(1, 3))
                    ->map(fn (int $index): array => [
                        'id' => 'enemy-'.$index,
                        'name' => 'Wildhund',
                        'label' => 'Wildhund #'.$index,
                        'level' => 1,
                        'current_health' => 10,
                        'max_health' => 10,
                        'minimum_attack' => 1,
                        'maximum_attack' => 2,
                        'defense' => 0,
                        'category' => 'Tutorial Pack',
                        'attack_down_percent' => 0,
                        'attack_down_rounds' => 0,
                        'hit_penalty_percent' => 0,
                        'hit_penalty_rounds' => 0,
                        'stagger_rounds' => 0,
                    ])
                    ->all(),
                'battle_log' => [__('Tutorial Encounter: :pack in :area.', [
                    'pack' => '3x Wildhund',
                    'area' => $area->name,
                ])],
                'battle_steps' => [],
                'round' => 1,
            ],
            'rewards' => [
                'encounter_type' => 'monster',
                'requested_encounter' => 'monster',
                'enemy_name' => 'Wildhund',
                'enemy_level' => 1,
                'pack_size' => 3,
                'stamina_cost' => 0,
                'pressure_gain' => 4,
                'cooldown_base' => 0,
                'experience' => 24,
                'yang' => 18,
                'loot_drops' => [],
                'loot_names' => [],
                'tutorial_battle' => true,
            ],
        ];
    }

    /**
     * @return array{encounter_type: string, encounter_name: string, state: array<string, mixed>, rewards: array<string, mixed>}
     */
    private function initializeManualEncounterBattle(
        GameCharacter $character,
        LocationArea $area,
        string $requestedEncounter,
    ): array {
        $progress = $this->areaProgress($character, $area);
        $requestedEncounter = $this->normalizeEncounterRequest($requestedEncounter);
        $staminaCost = match ($requestedEncounter) {
            'boss' => 8,
            'lumiis' => 6,
            default => 4,
        };
        $minimumHealth = match ($requestedEncounter) {
            'boss' => 30,
            'lumiis' => 24,
            default => 18,
        };

        if ($character->stamina < $staminaCost) {
            throw new GameActionException(
                __('You need at least :stamina stamina to enter :area.', [
                    'stamina' => $staminaCost,
                    'area' => $area->name,
                ]),
            );
        }

        if ($character->health <= $minimumHealth) {
            throw new GameActionException(__('You are too wounded for :area. Rest before returning.', [
                'area' => $area->name,
            ]));
        }

        $encounterType = $this->resolveEncounterType($area, $progress, $requestedEncounter);
        $areaDifficulty = max(
            1,
            (int) ($area->recommended_level ?: max(1, $character->location->threat_level)),
        );
        $effectiveAttack = $character->effectiveAttack();
        $effectiveDefense = $character->effectiveDefense();
        $effectiveSpeed = $character->effectiveSpeed();
        $encounterName = $area->encounterName($encounterType) ?? $area->name;
        $lootDrops = $this->rollLoot($area->lootTable($encounterType), $character->lootRatePercentBonus(), $character);
        $encounterColumnPrefix = $this->encounterColumnPrefix($encounterType);
        $lootNames = [];

        foreach ($lootDrops as $drop) {
            $lootNames[] = $drop['label'].($drop['quantity'] > 1 ? ' x'.$drop['quantity'] : '');
        }

        $experienceGain = $this->applyRewardRateBonus(
            $character,
            $this->rollEncounterValue($area, $encounterType, 'exp')
            + max(0, intdiv($effectiveAttack, $encounterType === 'boss' ? 3 : 4))
            + max(0, $areaDifficulty - $character->level),
            'experience',
        );

        $yangGain = $this->applyRewardRateBonus(
            $character,
            $this->rollEncounterValue($area, $encounterType, 'yang')
            + max(0, intdiv($effectiveAttack, $encounterType === 'boss' ? 4 : 5)),
            'yang',
        );

        $minimumAttack = max(1, (int) data_get($area, $encounterColumnPrefix.'_damage_min', 4));
        $maximumAttack = max(
            $minimumAttack,
            (int) data_get($area, $encounterColumnPrefix.'_damage_max', $minimumAttack + 3),
        );
        $maximumHealth = match ($encounterType) {
            'boss' => 96 + ($areaDifficulty * 14),
            // Lumiis should survive several fracture phases, but early-map stones still need to be
            // beatable for fresh characters in riverbank.
            'lumiis' => 88 + ($areaDifficulty * 12),
            default => 52 + ($areaDifficulty * 8),
        };
        $enemyDefense = match ($encounterType) {
            'boss' => max(2, intdiv($areaDifficulty, 2) + 2),
            'lumiis' => max(1, intdiv($areaDifficulty, 3) + 1),
            default => max(0, intdiv($areaDifficulty, 4)),
        };
        $pressureGain = match ($encounterType) {
            'monster' => (int) $area->monster_pressure_gain,
            'lumiis' => (int) $area->lumiis_pressure_gain,
            default => 0,
        };
        $primaryEnemyId = $encounterType === 'lumiis' ? 'lumiis-core' : 'enemy-1';

        return [
            'encounter_type' => $encounterType,
            'encounter_name' => $encounterName,
            'state' => [
                // The state payload is the full source of truth for each future manual turn.
                'player' => $this->buildManualBattlePlayerState(
                    $character,
                    $effectiveAttack,
                    $effectiveDefense,
                    $effectiveSpeed,
                ),
                'enemies' => [[
                    'id' => $primaryEnemyId,
                    'name' => $encounterName,
                    'label' => $encounterName,
                    'level' => $areaDifficulty,
                    'current_health' => $maximumHealth,
                    'max_health' => $maximumHealth,
                    'minimum_attack' => $encounterType === 'lumiis' ? 0 : $minimumAttack,
                    'maximum_attack' => $encounterType === 'lumiis' ? 0 : $maximumAttack,
                    'defense' => $enemyDefense,
                    'category' => match ($encounterType) {
                        'lumiis' => 'Lumiis Core',
                        'boss' => 'Boss',
                        default => 'Monster',
                    },
                    'damage_type' => DamageTypeCatalog::enemyDamageType(null, $encounterType, match ($encounterType) {
                        'lumiis' => 'Lumiis Core',
                        'boss' => 'Boss',
                        default => 'Monster',
                    }),
                    'resistances' => DamageTypeCatalog::enemyResistanceProfile($encounterType, match ($encounterType) {
                        'lumiis' => 'Lumiis Core',
                        'boss' => 'Boss',
                        default => 'Monster',
                    }),
                    'encounter_type' => $encounterType,
                    'attack_down_percent' => 0,
                    'attack_down_rounds' => 0,
                    'hit_penalty_percent' => 0,
                    'hit_penalty_rounds' => 0,
                    'stagger_rounds' => 0,
                    'spawned_round' => 0,
                ]],
                'battle_log' => [
                    $encounterType === 'lumiis'
                        ? __('Encounter: :encounter pulses with trapped souls in :area.', [
                            'encounter' => $encounterName,
                            'area' => $area->name,
                        ])
                        : __('Encounter: :encounter in :area.', [
                            'encounter' => $encounterName,
                            'area' => $area->name,
                        ]),
                ],
                'battle_steps' => [],
                'encounter_type' => $encounterType,
                'next_enemy_sequence' => 1,
                'lumiis' => $encounterType === 'lumiis'
                    ? [
                        'core_enemy_id' => $primaryEnemyId,
                        'first_wave_triggered' => false,
                        'triggered_thresholds' => [],
                        'wave_thresholds' => $this->buildLumiisWaveThresholds(),
                    ]
                    : null,
                'round' => 1,
            ],
            'rewards' => [
                'encounter_type' => $encounterType,
                'requested_encounter' => $requestedEncounter,
                'enemy_name' => $encounterName,
                'enemy_level' => $areaDifficulty,
                'pack_size' => 1,
                'experience' => $experienceGain,
                'yang' => $yangGain,
                'loot_drops' => $lootDrops,
                'loot_names' => $lootNames,
                'stamina_cost' => $staminaCost,
                'pressure_gain' => $pressureGain,
                'cooldown_base' => (int) data_get($area, $encounterColumnPrefix.'_cooldown_seconds', 45),
            ],
        ];
    }

    /**
     * Lumiis waves are tied to visible fracture phases.
     *
     * We keep them fixed and easy to reason about so balancing stays straightforward:
     * first hit always spawns a wave, then larger cracks happen around 20/40/60 percent damage.
     *
     * @return list<int>
     */
    private function buildLumiisWaveThresholds(): array
    {
        return [20, 40, 60];
    }

    /**
     * Manual Lumiis fights can spawn new enemies mid-battle as the stone fractures.
     *
     * @param  array<string, mixed>  $state
     */
    private function triggerLumiisSoulWaves(array &$state, LocationArea $area, GameCharacter $character): void
    {
        /** @var array<string, mixed>|null $lumiisState */
        $lumiisState = data_get($state, 'lumiis');

        if (! is_array($lumiisState)) {
            return;
        }

        $coreEnemyId = (string) data_get($lumiisState, 'core_enemy_id', '');
        $coreEnemy = collect($state['enemies'] ?? [])->firstWhere('id', $coreEnemyId);

        if (! is_array($coreEnemy)) {
            return;
        }

        $coreMaxHealth = max(1, (int) data_get($coreEnemy, 'max_health', 1));
        $coreCurrentHealth = max(0, (int) data_get($coreEnemy, 'current_health', 0));

        if ($coreCurrentHealth >= $coreMaxHealth) {
            return;
        }

        if (! (bool) data_get($lumiisState, 'first_wave_triggered', false)) {
            $this->spawnLumiisSoulWave($state, $area, $character, null);
            data_set($state, 'lumiis.first_wave_triggered', true);
        }

        $lostHealthPercent = (int) floor((($coreMaxHealth - $coreCurrentHealth) / $coreMaxHealth) * 100);
        $triggeredThresholds = collect(data_get($lumiisState, 'triggered_thresholds', []))
            ->map(fn (mixed $threshold): int => (int) $threshold)
            ->all();

        $nextThreshold = collect(data_get($lumiisState, 'wave_thresholds', []))
            ->map(fn (mixed $threshold): int => (int) $threshold)
            ->filter(fn (int $threshold): bool => $lostHealthPercent >= $threshold && ! in_array($threshold, $triggeredThresholds, true))
            ->sort()
            ->first();

        if (is_int($nextThreshold)) {
            // One direct crack should release one new wave. Without this guard a large hit after
            // previous splash damage can skip multiple thresholds and flood the player at once.
            $this->spawnLumiisSoulWave($state, $area, $character, $nextThreshold);
            $triggeredThresholds[] = $nextThreshold;
        }

        data_set($state, 'lumiis.triggered_thresholds', array_values(array_unique($triggeredThresholds)));
    }

    /**
     * The Lumiis core itself is not enough. Each fracture frees a small monster wave that must also be cleared.
     *
     * @param  array<string, mixed>  $state
     */
    private function spawnLumiisSoulWave(
        array &$state,
        LocationArea $area,
        GameCharacter $character,
        ?int $threshold,
    ): void {
        $spawnCount = 2;
        $spawnedLabels = [];

        for ($waveIndex = 0; $waveIndex < $spawnCount; $waveIndex++) {
            $assignment = $this->resolveMonsterAssignmentForCharacter($area, $character);
            $monster = $assignment->monster;
            $enemySequence = (int) data_get($state, 'next_enemy_sequence', 1);
            $state['next_enemy_sequence'] = $enemySequence + 1;
            $soulHealth = max(8, (int) floor(((int) $monster->maximum_health) * 0.75));
            $soulMinimumAttack = max(1, (int) floor(((int) $monster->minimum_attack) * 0.8));
            $soulMaximumAttack = max($soulMinimumAttack + 1, (int) floor(((int) $monster->maximum_attack) * 0.8));
            $soulDefense = max(0, (int) floor(((int) $monster->defense) * 0.75));

            $state['enemies'][] = [
                'id' => 'enemy-'.$enemySequence,
                'name' => $monster->name,
                'label' => $monster->name.' Soul #'.$enemySequence,
                'level' => (int) $monster->level,
                // Freed souls should still feel like the original monsters, but weaker and less
                // stable than a full wild pack. That keeps Lumiis fights readable for low-level
                // characters while still preserving the monster identity of each area.
                'current_health' => $soulHealth,
                'max_health' => $soulHealth,
                'minimum_attack' => $soulMinimumAttack,
                'maximum_attack' => $soulMaximumAttack,
                'defense' => $soulDefense,
                'category' => 'Freed Soul',
                'damage_type' => DamageTypeCatalog::enemyDamageType((string) $monster->damage_type, 'lumiis', 'Freed Soul', $monster->manualFamilyKey()),
                'resistances' => DamageTypeCatalog::enemyResistanceProfile('lumiis', 'Freed Soul', $monster->manualFamilyKey(), (string) $monster->damage_type),
                'family' => $monster->manualFamilyKey(),
                'encounter_type' => 'lumiis',
                'attack_down_percent' => 0,
                'attack_down_rounds' => 0,
                'hit_penalty_percent' => 0,
                'hit_penalty_rounds' => 0,
                'stagger_rounds' => 0,
                'spawned_round' => (int) data_get($state, 'round', 1),
            ];
            $spawnedLabels[] = $monster->name;

            $state['battle_steps'][] = [
                'round' => (int) data_get($state, 'round', 1),
                'actor' => 'system',
                'actor_label' => __('Lumiis'),
                'target_type' => 'spawn',
                'target_id' => 'enemy-'.$enemySequence,
                'target_label' => $monster->name.' Soul #'.$enemySequence,
                'remaining_health' => $soulHealth,
                'message' => __(':enemy breaks free from the Lumiis.', [
                    'enemy' => $monster->name.' Soul #'.$enemySequence,
                ]),
            ];
        }

        $lumiisCore = collect($state['enemies'] ?? [])
            ->firstWhere('id', data_get($state, 'lumiis.core_enemy_id'));
        $lumiisName = (string) data_get($lumiisCore, 'label', __('Lumiis'));

        $state['battle_log'][] = $threshold === null
            ? __('The :lumiis cracks and releases trapped souls: :souls.', [
                'lumiis' => $lumiisName,
                'souls' => implode(', ', $spawnedLabels),
            ])
            : __('The :lumiis fractures past :threshold% and releases more souls: :souls.', [
                'lumiis' => $lumiisName,
                'threshold' => $threshold,
                'souls' => implode(', ', $spawnedLabels),
            ]);
    }

    /**
     * Manual player actions always hit the selected target.
     *
     * Skills may also splash nearby enemies, but basic attacks stay single-target so
     * packs remain threatening once the player steps into real hunting zones.
     *
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>|null  $skill
     */
    private function applyManualPlayerAction(
        array &$state,
        string $enemyId,
        string $playerActionType,
        ?array $skill,
        LocationArea $area,
        GameCharacter $character,
    ): void
    {
        $player = &$state['player'];
        $enemies = &$state['enemies'];
        $targetIndex = collect($enemies)
            ->search(fn (array $enemy): bool => $enemy['id'] === $enemyId && ((int) $enemy['current_health']) > 0);

        if ($targetIndex === false) {
            throw new GameActionException(__('That target is no longer alive.'));
        }

        $targetEnemy = $enemies[$targetIndex];
        $skillSpCost = (int) ($skill['sp_cost'] ?? 0);
        $skillCooldownRemaining = (int) ($skill['cooldown_remaining'] ?? 0);

        if ($playerActionType === 'skill' && (int) ($player['current_sp'] ?? 0) < $skillSpCost) {
            throw new GameActionException(__('You do not have enough SP to use that skill.'));
        }

        if ($playerActionType === 'skill' && $skillCooldownRemaining > 0) {
            throw new GameActionException(__('That skill is still on cooldown for :round rounds.', [
                'round' => $skillCooldownRemaining,
            ]));
        }

        $attackBaseStat = $playerActionType === 'skill' && (bool) ($skill['uses_magic_attack'] ?? false)
            ? (int) ($player['magic_attack'] ?? 0)
            : (int) ($player['attack'] ?? 0);
        $attackBaseStat += $this->activeBattleStateEffect(
            $player,
            'temporary_attack_bonus',
            'temporary_attack_bonus_rounds',
        );
        $weaponType = data_get($player, 'weapon_type');
        $skillGroupCombatProfile = (array) data_get($player, 'skill_group_combat_profile', []);
        $attackBounds = WeaponCombatProfileCatalog::attackRollBounds($weaponType, $attackBaseStat, (int) ($player['speed'] ?? 0));
        $attackBounds = $this->applySkillGroupAttackBounds($attackBounds, $skillGroupCombatProfile);
        $attackRoll = random_int($attackBounds['minimum'], $attackBounds['maximum']);
        $bonusDamage = (int) ($skill['damage_bonus'] ?? ($skill['bonus_damage'] ?? 0));
        $guard = (int) ($skill['guard'] ?? 0)
            + WeaponCombatProfileCatalog::guardBonus($weaponType)
            + (int) ($skillGroupCombatProfile['guard_bonus'] ?? 0);
        $evadeBonus = (int) ($skill['evade_bonus'] ?? 0);
        $critBonus = (int) ($skill['crit_bonus'] ?? 0);
        $attackBuff = (int) ($skill['attack_buff'] ?? 0);
        $buffRounds = max(1, (int) (($skill['buff_rounds'] ?? 0) ?: ($skill['attack_buff_rounds'] ?? 0)));
        $player['current_sp'] = max(0, (int) ($player['current_sp'] ?? 0) - $skillSpCost);

        if ($playerActionType === 'skill' && (bool) ($skill['self_buff_only'] ?? false)) {
            $effectDuration = $buffRounds + 1;
            $this->applyBattleStateEffect(
                $player,
                'temporary_attack_bonus',
                'temporary_attack_bonus_rounds',
                $attackBuff,
                $effectDuration,
            );
            $this->applyBattleStateEffect($player, 'temporary_guard', 'temporary_guard_rounds', (int) ($skill['guard'] ?? 0), $effectDuration);
            $this->applyBattleStateEffect($player, 'temporary_crit_bonus', 'temporary_crit_bonus_rounds', $critBonus, $effectDuration);
            $this->applyBattleStateEffect($player, 'temporary_evade_bonus', 'temporary_evade_bonus_rounds', $evadeBonus, $effectDuration);
            $this->applyBattleStateEffect(
                $player,
                'temporary_lifesteal_percent',
                'temporary_lifesteal_percent_rounds',
                (int) ($skill['lifesteal_percent'] ?? 0),
                $effectDuration,
            );
            $this->applyBattleStateEffect(
                $player,
                'temporary_reflect_percent',
                'temporary_reflect_percent_rounds',
                (int) ($skill['reflect_percent'] ?? 0),
                $effectDuration,
            );
            $this->applyBattleStateEffect(
                $player,
                'temporary_damage_to_sp_percent',
                'temporary_damage_to_sp_percent_rounds',
                (int) ($skill['damage_to_sp_percent'] ?? 0),
                $effectDuration,
            );
            $this->applyBattleStateEffect(
                $player,
                'temporary_incoming_damage_percent',
                'temporary_incoming_damage_percent_rounds',
                max(0, -((int) ($skill['incoming_damage_percent'] ?? 0))),
                $effectDuration,
            );

            $buffMessage = __(':player invokes :skill. :effects for the next :rounds rounds.', [
                'player' => $player['name'],
                'skill' => $skill['name'],
                'effects' => $this->describeSelfBuffEffects($skill),
                'rounds' => $buffRounds,
            ]);
            $state['battle_log'][] = $buffMessage;
            $state['battle_steps'][] = [
                'round' => (int) $state['round'],
                'actor' => 'player',
                'actor_label' => $player['name'],
                'target_type' => 'player',
                'target_id' => 'player',
                'target_label' => $player['name'],
                'damage' => 0,
                'remaining_health' => (int) ($player['current_health'] ?? 0),
                'message' => $buffMessage,
            ];

            if (isset($player['skills']) && is_array($player['skills'])) {
                $this->markCombatSkillUsed($player['skills'], (string) ($skill['slug'] ?? ''));
            }

            return;
        }

        $mainDamage = max(
            1,
            (int) floor(($attackRoll + $bonusDamage) * (float) ($skill['damage_multiplier'] ?? 1.0)) - (int) $targetEnemy['defense'],
        );
        $mainDamage = $this->applyWeaponDamageBonus(
            $character,
            $mainDamage,
            $playerActionType === 'skill',
            WeaponCombatProfileCatalog::damagePercentBonus(
                $weaponType,
                $playerActionType === 'skill',
                (int) ($state['round'] ?? 1),
            ) + $this->skillGroupDamagePercentBonus(
                $skillGroupCombatProfile,
                $playerActionType === 'skill',
                (int) ($state['round'] ?? 1),
            ),
        );
        $mainDamage = $this->applyCategoryDamageBonus($character, $mainDamage, (string) ($targetEnemy['category'] ?? 'Monster'));
        $criticalHitChance = min(
            100,
            (int) ($player['crit_chance'] ?? 0)
            + WeaponCombatProfileCatalog::critChanceBonus($weaponType)
            + (int) ($skillGroupCombatProfile['crit_chance_bonus'] ?? 0)
            + $this->skillGroupOpeningCritBonus($skillGroupCombatProfile, (int) ($state['round'] ?? 1))
            + $critBonus
            + $this->activeBattleStateEffect($player, 'temporary_crit_bonus', 'temporary_crit_bonus_rounds'),
        );
        $criticalHit = $criticalHitChance > 0 && random_int(1, 100) <= $criticalHitChance;

        if ($criticalHit) {
            $mainDamage = max($mainDamage + 1, (int) floor($mainDamage * 1.5));
        }

        $damageType = $this->resolvePlayerActionDamageType($player, $skill);
        $enemyResistancePercent = DamageTypeCatalog::resistancePercent(
            $this->resolveEnemyResistanceProfile($enemies[$targetIndex]),
            $damageType,
        );
        $mainDamage = DamageTypeCatalog::damageAfterResistance(
            $mainDamage,
            $damageType,
            $this->resolveEnemyResistanceProfile($enemies[$targetIndex]),
        );

        $enemies[$targetIndex]['current_health'] = max(0, (int) $targetEnemy['current_health'] - $mainDamage);
        $targetIsLumiisCore = ($targetEnemy['category'] ?? null) === 'Lumiis Core';

        $mainMessage = $playerActionType === 'skill'
            ? __(':player uses :skill on :enemy for :damage damage.', [
                'player' => $player['name'],
                'skill' => $skill['name'],
                'enemy' => $targetEnemy['label'],
                'damage' => $mainDamage,
            ])
            : __(':player attacks :enemy for :damage damage.', [
                'player' => $player['name'],
                'enemy' => $targetEnemy['label'],
                'damage' => $mainDamage,
            ]);

        if ($criticalHit) {
            $mainMessage .= ' '.__('Critical hit!');
        }

        $mainMessage .= $this->damageTypeImpactSuffix($enemyResistancePercent, $damageType);

        if ($enemies[$targetIndex]['current_health'] === 0) {
            $mainMessage .= ' '.__(':enemy falls.', ['enemy' => $targetEnemy['label']]);
        }

        $state['battle_log'][] = $mainMessage;
        $state['battle_steps'][] = [
            'round' => (int) $state['round'],
            'actor' => 'player',
            'actor_label' => $player['name'],
            'target_type' => 'enemy',
            'target_id' => $targetEnemy['id'],
            'target_label' => $targetEnemy['label'],
            'damage' => $mainDamage,
            'remaining_health' => $enemies[$targetIndex]['current_health'],
            'message' => $mainMessage,
        ];

        $this->tryApplySkillStatusesToEnemy(
            $enemies[$targetIndex],
            $skill,
            $state['battle_log'],
            $state['battle_steps'],
            (int) $state['round'],
        );

        if ($guard > 0) {
            $this->applyBattleStateEffect($player, 'temporary_guard', 'temporary_guard_rounds', $guard, 1);
        }

        if ($evadeBonus > 0) {
            $this->applyBattleStateEffect($player, 'temporary_evade_bonus', 'temporary_evade_bonus_rounds', $evadeBonus, 1);
        }

        if ($critBonus > 0) {
            $this->applyBattleStateEffect($player, 'temporary_crit_bonus', 'temporary_crit_bonus_rounds', $critBonus, 1);
        }

        $healAmount = max(
            0,
            (int) ($skill['heal_flat'] ?? 0)
            + (int) floor(((int) ($player['max_health'] ?? 0)) * (((int) ($skill['heal_percent'] ?? 0)) / 100)),
        );

        if ($healAmount > 0) {
            $healedTo = min((int) ($player['max_health'] ?? 0), (int) ($player['current_health'] ?? 0) + $healAmount);
            $actualHeal = max(0, $healedTo - (int) ($player['current_health'] ?? 0));
            $player['current_health'] = $healedTo;

            if ($actualHeal > 0) {
                $healMessage = __(':player restores :amount HP through :skill.', [
                    'player' => $player['name'],
                    'amount' => $actualHeal,
                    'skill' => $skill['name'],
                ]);
                $state['battle_log'][] = $healMessage;
                $state['battle_steps'][] = [
                    'round' => (int) $state['round'],
                    'actor' => 'player',
                    'actor_label' => $player['name'],
                    'target_type' => 'player',
                    'target_label' => $player['name'],
                    'damage' => -$actualHeal,
                    'remaining_health' => $player['current_health'],
                    'message' => $healMessage,
                ];
            }
        }

        $lifestealPercent = max(
            0,
            (int) ($skillGroupCombatProfile['lifesteal_percent'] ?? 0)
                + $this->activeBattleStateEffect(
                    $player,
                    'temporary_lifesteal_percent',
                    'temporary_lifesteal_percent_rounds',
                ),
        );

        if ($lifestealPercent > 0 && $mainDamage > 0) {
            $lifestealHeal = $this->lifestealHealAmount($mainDamage, $lifestealPercent);
            $healedTo = min((int) ($player['max_health'] ?? 0), (int) ($player['current_health'] ?? 0) + $lifestealHeal);
            $actualHeal = max(0, $healedTo - (int) ($player['current_health'] ?? 0));
            $player['current_health'] = $healedTo;

            if ($actualHeal > 0) {
                $lifestealMessage = __(':player steals :amount HP through :skill.', [
                    'player' => $player['name'],
                    'amount' => $actualHeal,
                    'skill' => $playerActionType === 'skill' ? ($skill['name'] ?? __('the skill')) : __('the attack'),
                ]);
                $state['battle_log'][] = $lifestealMessage;
                $state['battle_steps'][] = [
                    'round' => (int) $state['round'],
                    'actor' => 'player',
                    'actor_label' => $player['name'],
                    'target_type' => 'player',
                    'target_label' => $player['name'],
                    'damage' => -$actualHeal,
                    'remaining_health' => $player['current_health'],
                    'message' => $lifestealMessage,
                ];
            }
        }

        if ($playerActionType === 'skill' && isset($player['skills']) && is_array($player['skills'])) {
            $this->markCombatSkillUsed($player['skills'], (string) ($skill['slug'] ?? ''));
        }

        foreach ($enemies as $enemyIndex => $enemy) {
            if ($enemyIndex === $targetIndex || (int) $enemy['current_health'] <= 0) {
                continue;
            }

            if (! $targetIsLumiisCore && ($enemy['category'] ?? null) === 'Lumiis Core') {
                // Soul clean-up should not silently fracture the Lumiis further.
                // The player must actively hit the core to trigger the next release phase.
                continue;
            }

            $splashPercent = $playerActionType === 'skill'
                ? (int) ($skill['splash_percent'] ?? 70)
                : $this->resolvePackBasicSplashPercent(
                    (int) ($targetEnemy['level'] ?? 1),
                    (string) ($targetEnemy['category'] ?? 'Monster'),
                );

            $splashDamage = max(
                1,
                (int) floor($mainDamage * ($splashPercent / 100)) - max(0, intdiv((int) $enemy['defense'], 2)),
            );
            $splashDamage = DamageTypeCatalog::damageAfterResistance(
                $splashDamage,
                $damageType,
                $this->resolveEnemyResistanceProfile($enemies[$enemyIndex]),
            );
            $enemies[$enemyIndex]['current_health'] = max(0, (int) $enemy['current_health'] - $splashDamage);
            $splashMessage = __(':enemy suffers :damage splash damage.', [
                'enemy' => $enemy['label'],
                'damage' => $splashDamage,
            ]);

            if ($enemies[$enemyIndex]['current_health'] === 0) {
                $splashMessage .= ' '.__(':enemy falls.', ['enemy' => $enemy['label']]);
            }

            $state['battle_log'][] = $splashMessage;
            $state['battle_steps'][] = [
                'round' => (int) $state['round'],
                'actor' => 'player',
                'actor_label' => $player['name'],
                'target_type' => 'enemy',
                'target_id' => $enemy['id'],
                'target_label' => $enemy['label'],
                'damage' => $splashDamage,
                'remaining_health' => $enemies[$enemyIndex]['current_health'],
                'message' => $splashMessage,
            ];

            $this->tryApplySkillStatusesToEnemy(
                $enemies[$enemyIndex],
                $skill,
                $state['battle_log'],
                $state['battle_steps'],
                (int) $state['round'],
            );
        }

        // Only hits on the Lumiis core should release new monster waves.
        if (($targetEnemy['category'] ?? null) === 'Lumiis Core') {
            $this->triggerLumiisSoulWaves($state, $area, $character);
        }
    }

    /**
     * Enemy turns are intentionally deterministic to read: each alive enemy attacks once.
     *
     * @param  array<string, mixed>  $state
     */
    private function applyManualEnemyTurn(array &$state): void
    {
        $aliveEnemies = collect($state['enemies'] ?? [])
            ->filter(fn (array $enemy): bool => ((int) $enemy['current_health']) > 0)
            ->values();

        if ($aliveEnemies->isEmpty()) {
            return;
        }

        foreach ($state['enemies'] as $enemyIndex => $enemy) {
            $this->applyEnemyStatusTicks(
                $state['enemies'][$enemyIndex],
                $state['battle_log'],
                $state['battle_steps'],
                (int) $state['round'],
            );
        }

        $aliveEnemies = collect($state['enemies'] ?? [])
            ->filter(fn (array $enemy): bool => ((int) $enemy['current_health']) > 0)
            ->values();

        if ($aliveEnemies->isEmpty()) {
            return;
        }

        $skillGroupCombatProfile = (array) data_get($state, 'player.skill_group_combat_profile', []);
        $playerResistanceProfile = is_array(data_get($state, 'player.resistances'))
            ? (array) data_get($state, 'player.resistances')
            : [];
        $guard = $this->activeBattleStateEffect($state['player'], 'temporary_guard', 'temporary_guard_rounds');
        $evadeBonus = $this->activeBattleStateEffect($state['player'], 'temporary_evade_bonus', 'temporary_evade_bonus_rounds');
        $guardReduction = $guard > 0 ? max(1, intdiv($guard, max(1, $aliveEnemies->count()))) : 0;

        foreach ($state['enemies'] as $enemyIndex => $enemy) {
            if ((int) ($enemy['current_health'] ?? 0) <= 0) {
                continue;
            }

            if (($enemy['category'] ?? null) === 'Lumiis Core') {
                continue;
            }

            if ((int) ($enemy['stagger_rounds'] ?? 0) > 0) {
                $state['enemies'][$enemyIndex]['stagger_rounds'] = max(0, (int) $enemy['stagger_rounds'] - 1);
                $staggerMessage = __(':enemy is staggered and loses the turn.', [
                    'enemy' => $enemy['label'],
                ]);
                $state['battle_log'][] = $staggerMessage;
                $state['battle_steps'][] = [
                    'round' => (int) $state['round'],
                    'actor' => 'system',
                    'actor_label' => __('Status'),
                    'target_type' => 'enemy',
                    'target_id' => $enemy['id'],
                    'target_label' => $enemy['label'],
                    'damage' => 0,
                    'remaining_health' => $state['enemies'][$enemyIndex]['current_health'],
                    'message' => $staggerMessage,
                ];
                $this->decayEnemyEffect($state['enemies'][$enemyIndex], 'attack_down_percent', 'attack_down_rounds');
                $this->decayEnemyEffect($state['enemies'][$enemyIndex], 'hit_penalty_percent', 'hit_penalty_rounds');

                continue;
            }

            $enemyAttack = random_int((int) $enemy['minimum_attack'], (int) $enemy['maximum_attack']);
            $enemyAttack = $this->applyOutgoingEnemyDamageReduction(
                $enemyAttack,
                $this->activeEnemyEffect($enemy, 'attack_down_percent', 'attack_down_rounds'),
            );
            $effectiveEvadeChance = min(
                95,
                (int) data_get($state, 'player.evade_chance', 0)
                    + $evadeBonus
                    + $this->activeEnemyEffect($enemy, 'hit_penalty_percent', 'hit_penalty_rounds'),
            );
            $dodged = $effectiveEvadeChance > 0
                && random_int(1, 100) <= $effectiveEvadeChance;
            $enemyDamage = $dodged
                ? 0
                : max(
                    $this->minimumEnemyDamage(
                        (int) ($enemy['level'] ?? 1),
                        (string) ($enemy['category'] ?? 'Monster'),
                        $aliveEnemies->count(),
                    ),
                    $enemyAttack
                    + $this->resolvePackAggressionBonus(
                        $aliveEnemies->count(),
                        (int) ($enemy['level'] ?? 1),
                        (string) ($enemy['category'] ?? 'Monster'),
                    )
                    + $this->enemyPressureBonus((int) ($enemy['level'] ?? 1), 1)
                    - $this->enemyDamageReduction((int) data_get($state, 'player.defense', 0))
                    - $guardReduction,
                );
            $enemyDamage = $dodged
                ? 0
                : $this->applyIncomingDamagePercentModifier(
                    $enemyDamage,
                    (int) ($skillGroupCombatProfile['incoming_damage_percent'] ?? 0)
                        - $this->activeBattleStateEffect(
                            $state['player'],
                            'temporary_incoming_damage_percent',
                            'temporary_incoming_damage_percent_rounds',
                        ),
                );
            $enemyDamageType = $this->resolveEnemyDamageType($enemy);
            $playerResistancePercent = DamageTypeCatalog::resistancePercent($playerResistanceProfile, $enemyDamageType);
            $enemyDamage = $dodged
                ? 0
                : DamageTypeCatalog::damageAfterResistance($enemyDamage, $enemyDamageType, $playerResistanceProfile);
            $spAbsorbed = 0;

            if (! $dodged) {
                $enemyDamage = $this->absorbDamageIntoSp(
                    $enemyDamage,
                    $state['player']['current_sp'],
                    (int) ($skillGroupCombatProfile['damage_to_sp_percent'] ?? 0)
                        + $this->activeBattleStateEffect(
                            $state['player'],
                            'temporary_damage_to_sp_percent',
                            'temporary_damage_to_sp_percent_rounds',
                        ),
                    $spAbsorbed,
                );
            }

            $state['player']['current_health'] = max(0, (int) $state['player']['current_health'] - $enemyDamage);
            $enemyMessage = $dodged
                ? __(':player dodges :enemy.', [
                    'player' => $state['player']['name'],
                    'enemy' => $enemy['label'],
                ])
                : __(':enemy hits :player for :damage damage.', [
                    'enemy' => $enemy['label'],
                    'player' => $state['player']['name'],
                    'damage' => $enemyDamage,
                ]);

            if ($spAbsorbed > 0) {
                $enemyMessage .= ' '.__(':amount damage is absorbed by SP.', ['amount' => $spAbsorbed]);
            }

            if (! $dodged) {
                $enemyMessage .= $this->damageTypeImpactSuffix($playerResistancePercent, $enemyDamageType);
            }

            if ((int) $state['player']['current_health'] === 0) {
                $enemyMessage .= ' '.__(':player collapses.', ['player' => $state['player']['name']]);
            }

            $state['battle_log'][] = $enemyMessage;
            $state['battle_steps'][] = [
                'round' => (int) $state['round'],
                'actor' => 'enemy',
                'actor_label' => $enemy['label'],
                'target_type' => 'player',
                'target_label' => $state['player']['name'],
                'damage' => $enemyDamage,
                'remaining_health' => $state['player']['current_health'],
                'message' => $enemyMessage,
            ];

            $reflectPercent = max(
                0,
                (int) ($skillGroupCombatProfile['reflect_percent'] ?? 0)
                    + $this->activeBattleStateEffect(
                        $state['player'],
                        'temporary_reflect_percent',
                        'temporary_reflect_percent_rounds',
                    ),
            );

            if (! $dodged && $enemyDamage > 0 && $reflectPercent > 0 && (int) ($state['enemies'][$enemyIndex]['current_health'] ?? 0) > 0) {
                $reflectedDamage = min(
                    (int) $state['enemies'][$enemyIndex]['current_health'],
                    $this->reflectedDamageAmount($enemyDamage, $reflectPercent),
                );
                $state['enemies'][$enemyIndex]['current_health'] = max(
                    0,
                    (int) $state['enemies'][$enemyIndex]['current_health'] - $reflectedDamage,
                );

                if ($reflectedDamage > 0) {
                    $reflectMessage = __(':enemy is hit by reflected force for :damage damage.', [
                        'enemy' => $enemy['label'],
                        'damage' => $reflectedDamage,
                    ]);

                    if ((int) $state['enemies'][$enemyIndex]['current_health'] === 0) {
                        $reflectMessage .= ' '.__(':enemy falls.', ['enemy' => $enemy['label']]);
                    }

                    $state['battle_log'][] = $reflectMessage;
                    $state['battle_steps'][] = [
                        'round' => (int) $state['round'],
                        'actor' => 'system',
                        'actor_label' => $state['player']['name'],
                        'target_type' => 'enemy',
                        'target_id' => $enemy['id'],
                        'target_label' => $enemy['label'],
                        'damage' => $reflectedDamage,
                        'remaining_health' => $state['enemies'][$enemyIndex]['current_health'],
                        'message' => $reflectMessage,
                    ];
                }
            }

            $this->decayEnemyEffect($state['enemies'][$enemyIndex], 'attack_down_percent', 'attack_down_rounds');
            $this->decayEnemyEffect($state['enemies'][$enemyIndex], 'hit_penalty_percent', 'hit_penalty_rounds');

            if ((int) $state['player']['current_health'] === 0) {
                break;
            }
        }

        $retainedGuard = 0;

        if ((int) data_get($state, 'player.temporary_guard_rounds', 0) <= 1) {
            $retainedGuard = (int) floor(
                ((int) data_get($state, 'player.temporary_guard', 0))
                * (((int) ($skillGroupCombatProfile['guard_retention_percent'] ?? 0)) / 100),
            );
        }

        $this->decayBattleStateEffect($state['player'], 'temporary_guard', 'temporary_guard_rounds');
        $this->decayBattleStateEffect($state['player'], 'temporary_crit_bonus', 'temporary_crit_bonus_rounds');
        $this->decayBattleStateEffect($state['player'], 'temporary_evade_bonus', 'temporary_evade_bonus_rounds');
        $this->decayBattleStateEffect($state['player'], 'temporary_attack_bonus', 'temporary_attack_bonus_rounds');
        $this->decayBattleStateEffect($state['player'], 'temporary_lifesteal_percent', 'temporary_lifesteal_percent_rounds');
        $this->decayBattleStateEffect($state['player'], 'temporary_reflect_percent', 'temporary_reflect_percent_rounds');
        $this->decayBattleStateEffect($state['player'], 'temporary_damage_to_sp_percent', 'temporary_damage_to_sp_percent_rounds');
        $this->decayBattleStateEffect($state['player'], 'temporary_incoming_damage_percent', 'temporary_incoming_damage_percent_rounds');

        if ($retainedGuard > 0) {
            $this->applyBattleStateEffect($state['player'], 'temporary_guard', 'temporary_guard_rounds', $retainedGuard, 1);
        }
    }

    /**
     * @return array{label: string, enemy_stat_multiplier: float, reward_multiplier: float, loot_rate_bonus_percent: int, stamina_bonus: int}
     */
    private function manualPackDifficultyProfile(?string $difficulty): array
    {
        return match ($difficulty) {
            'easy' => [
                'label' => __('Easy'),
                'enemy_stat_multiplier' => 0.88,
                'reward_multiplier' => 0.78,
                'loot_rate_bonus_percent' => -30,
                'stamina_bonus' => 0,
            ],
            'hard' => [
                'label' => __('Hard'),
                'enemy_stat_multiplier' => 1.24,
                'reward_multiplier' => 1.38,
                'loot_rate_bonus_percent' => 40,
                'stamina_bonus' => 2,
            ],
            default => [
                'label' => __('Medium'),
                'enemy_stat_multiplier' => 1.0,
                'reward_multiplier' => 1.0,
                'loot_rate_bonus_percent' => 0,
                'stamina_bonus' => 1,
            ],
        };
    }

    private function enemyDamageReduction(int $effectiveDefense): int
    {
        return max(0, intdiv($effectiveDefense, 4));
    }

    private function enemyPressureBonus(int $enemyLevel, int $enemyRank): int
    {
        return max(1, intdiv($enemyLevel, 6) + max(0, $enemyRank - 1));
    }

    private function minimumEnemyDamage(int $enemyLevel, string $enemyCategory, int $aliveEnemyCount): int
    {
        $base = 1 + intdiv(max(1, $enemyLevel), 10);

        if ($aliveEnemyCount > 1) {
            $base++;
        }

        if (str_contains(mb_strtolower($enemyCategory), 'boss')) {
            $base += 2;
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $player
     * @param  array<string, mixed>|null  $skill
     */
    private function resolvePlayerActionDamageType(array $player, ?array $skill = null): string
    {
        $resolvedSkill = is_array($skill) ? $skill : [];
        $explicitType = (string) ($resolvedSkill['damage_type'] ?? '');

        if ($explicitType !== '') {
            return DamageTypeCatalog::normalize($explicitType);
        }

        return DamageTypeCatalog::skillDamageType(
            data_get($player, 'race'),
            data_get($player, 'skill_group'),
            data_get($player, 'weapon_type'),
            (bool) ($resolvedSkill['uses_magic_attack'] ?? false),
        );
    }

    /**
     * @param  array<string, mixed>  $enemy
     */
    private function resolveEnemyDamageType(array $enemy): string
    {
        return DamageTypeCatalog::enemyDamageType(
            is_string($enemy['damage_type'] ?? null) ? (string) $enemy['damage_type'] : null,
            is_string($enemy['encounter_type'] ?? null) ? (string) $enemy['encounter_type'] : null,
            (string) ($enemy['category'] ?? 'Monster'),
            is_string($enemy['family'] ?? null) ? (string) $enemy['family'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $enemy
     * @return array<string, int>
     */
    private function resolveEnemyResistanceProfile(array $enemy): array
    {
        $resistances = $enemy['resistances'] ?? null;

        if (is_array($resistances)) {
            return $resistances;
        }

        return DamageTypeCatalog::enemyResistanceProfile(
            is_string($enemy['encounter_type'] ?? null) ? (string) $enemy['encounter_type'] : null,
            (string) ($enemy['category'] ?? 'Monster'),
            is_string($enemy['family'] ?? null) ? (string) $enemy['family'] : null,
            is_string($enemy['damage_type'] ?? null) ? (string) $enemy['damage_type'] : null,
        );
    }

    private function damageTypeImpactSuffix(int $resistancePercent, string $damageType): string
    {
        if ($resistancePercent === 0) {
            return '';
        }

        return $resistancePercent > 0
            ? ' '.__(':type resistance dulls the impact.', ['type' => DamageTypeCatalog::label($damageType)])
            : ' '.__(':type weakness is exposed.', ['type' => DamageTypeCatalog::label($damageType)]);
    }

    /**
     * Status effects live on the enemy row itself so the replay payload can tell the exact story
     * of when poison or burn was applied and when it later ticked.
     *
     * @param  array<string, mixed>|null  $skill
     * @param  list<string>  $battleLog
     * @param  list<array<string, mixed>>  $battleSteps
     */
    private function tryApplySkillStatusesToEnemy(
        array &$enemy,
        ?array $skill,
        array &$battleLog,
        array &$battleSteps,
        int $round,
    ): void {
        if ($skill === null || ! $this->enemyCanReceiveDamageStatus($enemy)) {
            return;
        }

        foreach ([
            [
                'chance_key' => 'poison_chance',
                'damage_key' => 'poison_damage',
                'rounds_key' => 'poison_rounds',
                'label' => __('poisoned'),
            ],
            [
                'chance_key' => 'burn_chance',
                'damage_key' => 'burn_damage',
                'rounds_key' => 'burn_rounds',
                'label' => __('burning'),
            ],
        ] as $status) {
            $chance = max(0, min(100, (int) ($skill[$status['chance_key']] ?? 0)));
            $damage = max(0, (int) ($skill[$status['damage_key']] ?? 0));
            $rounds = max(0, (int) ($skill[$status['rounds_key']] ?? 0));
            $healthKey = $this->enemyHealthKey($enemy);

            if ($chance < 1 || $damage < 1 || $rounds < 1 || (int) ($enemy[$healthKey] ?? 0) <= 0) {
                continue;
            }

            if (random_int(1, 100) > $chance) {
                continue;
            }

            $enemy[$status['damage_key']] = max((int) ($enemy[$status['damage_key']] ?? 0), $damage);
            $enemy[$status['rounds_key']] = max((int) ($enemy[$status['rounds_key']] ?? 0), $rounds);

            $message = __(':enemy is now :status.', [
                'enemy' => $enemy['label'] ?? $enemy['name'] ?? __('Enemy'),
                'status' => $status['label'],
            ]);
            $battleLog[] = $message;
            $battleSteps[] = [
                'round' => $round,
                'actor' => 'system',
                'actor_label' => __('Status'),
                'target_type' => 'enemy',
                'target_id' => $enemy['id'] ?? null,
                'target_label' => $enemy['label'] ?? $enemy['name'] ?? __('Enemy'),
                'damage' => 0,
                'remaining_health' => (int) ($enemy[$healthKey] ?? 0),
                'message' => $message,
            ];
        }

        foreach ([
            [
                'value_key' => 'attack_down_percent',
                'rounds_key' => 'attack_down_rounds',
                'label' => __('weakened'),
                'message' => __(':enemy is weakened. Attack drops by :amount% for :rounds rounds.', [
                    'enemy' => $enemy['label'] ?? $enemy['name'] ?? __('Enemy'),
                    'amount' => max(0, (int) ($skill['attack_down_percent'] ?? 0)),
                    'rounds' => max(0, (int) ($skill['attack_down_rounds'] ?? 0)),
                ]),
            ],
            [
                'value_key' => 'hit_penalty_percent',
                'rounds_key' => 'hit_penalty_rounds',
                'label' => __('off-balance'),
                'message' => __(':enemy loses aim. Hit chance drops by :amount% for :rounds rounds.', [
                    'enemy' => $enemy['label'] ?? $enemy['name'] ?? __('Enemy'),
                    'amount' => max(0, (int) ($skill['hit_penalty_percent'] ?? 0)),
                    'rounds' => max(0, (int) ($skill['hit_penalty_rounds'] ?? 0)),
                ]),
            ],
        ] as $status) {
            $value = max(0, (int) ($skill[$status['value_key']] ?? 0));
            $rounds = max(0, (int) ($skill[$status['rounds_key']] ?? 0));
            $healthKey = $this->enemyHealthKey($enemy);

            if ($value < 1 || $rounds < 1 || (int) ($enemy[$healthKey] ?? 0) <= 0) {
                continue;
            }

            $enemy[$status['value_key']] = max((int) ($enemy[$status['value_key']] ?? 0), $value);
            $enemy[$status['rounds_key']] = max((int) ($enemy[$status['rounds_key']] ?? 0), $rounds);

            $battleLog[] = $status['message'];
            $battleSteps[] = [
                'round' => $round,
                'actor' => 'system',
                'actor_label' => __('Status'),
                'target_type' => 'enemy',
                'target_id' => $enemy['id'] ?? null,
                'target_label' => $enemy['label'] ?? $enemy['name'] ?? __('Enemy'),
                'damage' => 0,
                'remaining_health' => (int) ($enemy[$healthKey] ?? 0),
                'message' => $status['message'],
            ];
        }

        $staggerChance = max(0, min(100, (int) ($skill['stagger_chance'] ?? 0)));
        $staggerRounds = max(0, (int) ($skill['stagger_rounds'] ?? 0));
        $healthKey = $this->enemyHealthKey($enemy);

        if ($staggerChance > 0
            && $staggerRounds > 0
            && (int) ($enemy[$healthKey] ?? 0) > 0
            && random_int(1, 100) <= $staggerChance) {
            $enemy['stagger_rounds'] = max((int) ($enemy['stagger_rounds'] ?? 0), $staggerRounds);

            $message = __(':enemy is staggered and may lose the next turn.', [
                'enemy' => $enemy['label'] ?? $enemy['name'] ?? __('Enemy'),
            ]);
            $battleLog[] = $message;
            $battleSteps[] = [
                'round' => $round,
                'actor' => 'system',
                'actor_label' => __('Status'),
                'target_type' => 'enemy',
                'target_id' => $enemy['id'] ?? null,
                'target_label' => $enemy['label'] ?? $enemy['name'] ?? __('Enemy'),
                'damage' => 0,
                'remaining_health' => (int) ($enemy[$healthKey] ?? 0),
                'message' => $message,
            ];
        }
    }

    /**
     * Status ticks resolve before the enemy acts.
     *
     * If poison or burn finishes the target, it simply loses its turn that round.
     *
     * @param  list<string>  $battleLog
     * @param  list<array<string, mixed>>  $battleSteps
     */
    private function applyEnemyStatusTicks(
        array &$enemy,
        array &$battleLog,
        array &$battleSteps,
        int $round,
    ): void {
        $healthKey = $this->enemyHealthKey($enemy);

        if ((int) ($enemy[$healthKey] ?? 0) <= 0) {
            return;
        }

        foreach ([
            [
                'damage_key' => 'poison_damage',
                'rounds_key' => 'poison_rounds',
                'label' => __('poison damage'),
            ],
            [
                'damage_key' => 'burn_damage',
                'rounds_key' => 'burn_rounds',
                'label' => __('burn damage'),
            ],
        ] as $status) {
            $remainingRounds = max(0, (int) ($enemy[$status['rounds_key']] ?? 0));
            $damage = max(0, (int) ($enemy[$status['damage_key']] ?? 0));

            if ($remainingRounds < 1 || $damage < 1 || (int) ($enemy[$healthKey] ?? 0) <= 0) {
                continue;
            }

            $enemy[$healthKey] = max(0, (int) ($enemy[$healthKey] ?? 0) - $damage);
            $enemy[$status['rounds_key']] = max(0, $remainingRounds - 1);

            $message = __(':enemy suffers :damage :status.', [
                'enemy' => $enemy['label'] ?? $enemy['name'] ?? __('Enemy'),
                'damage' => $damage,
                'status' => $status['label'],
            ]);

            if ((int) ($enemy[$healthKey] ?? 0) === 0) {
                $message .= ' '.__(':enemy falls.', [
                    'enemy' => $enemy['label'] ?? $enemy['name'] ?? __('Enemy'),
                ]);
            }

            $battleLog[] = $message;
            $battleSteps[] = [
                'round' => $round,
                'actor' => 'system',
                'actor_label' => __('Status'),
                'target_type' => 'enemy',
                'target_id' => $enemy['id'] ?? null,
                'target_label' => $enemy['label'] ?? $enemy['name'] ?? __('Enemy'),
                'damage' => $damage,
                'remaining_health' => (int) ($enemy[$healthKey] ?? 0),
                'message' => $message,
            ];
        }
    }

    /**
     * Stones should not receive organic damage-over-time effects.
     *
     * @param  array<string, mixed>  $enemy
     */
    private function enemyCanReceiveDamageStatus(array $enemy): bool
    {
        return ($enemy['category'] ?? null) !== 'Lumiis Core';
    }

    /**
     * Auto pack battles and manual battles use slightly different enemy health keys.
     *
     * @param  array<string, mixed>  $enemy
     */
    private function enemyHealthKey(array $enemy): string
    {
        return array_key_exists('current_health', $enemy) ? 'current_health' : 'health';
    }

    /**
     * Auto-healing keeps battles readable without forcing another input on every low-health turn.
     *
     * The rule stays intentionally simple: once the character drops into the danger zone,
     * consume the weakest available healing potion and write that into the battle log.
     *
     * @param  list<string>  $battleLog
     * @param  list<array<string, mixed>>  $battleSteps
     */
    private function tryAutoUseHealthPotion(
        GameCharacter $character,
        int &$currentHealth,
        int $maxHealth,
        array &$battleLog,
        array &$battleSteps,
        int $round,
    ): void {
        if ($currentHealth <= 0 || $currentHealth >= $maxHealth) {
            return;
        }

        if ($currentHealth > $this->autoPotionHealthThreshold($maxHealth)) {
            return;
        }

        $potionStack = $this->resolveAutoHealthPotion($character);

        if ($potionStack === null || $potionStack->item === null) {
            return;
        }

        $healedAmount = min(
            (int) $potionStack->item->heal_amount,
            max(0, $maxHealth - $currentHealth),
        );

        if ($healedAmount <= 0) {
            return;
        }

        $this->inventoryService->consume($potionStack);
        $currentHealth += $healedAmount;
        $message = __(':player automatically uses :item and recovers :health HP.', [
            'player' => $character->name,
            'item' => $potionStack->item->name,
            'health' => $healedAmount,
        ]);

        $battleLog[] = $message;
        $battleSteps[] = [
            'round' => $round,
            'actor' => 'system',
            'actor_label' => $character->name,
            'target_type' => 'player',
            'target_id' => 'player',
            'target_label' => $character->name,
            'heal' => $healedAmount,
            'remaining_health' => $currentHealth,
            'message' => $message,
        ];
    }

    /**
     * Persistent manual battles store the player's HP inside the serialized battle state.
     *
     * This small adapter reuses the shared potion rule and writes the updated health/log data
     * back into that state payload.
     *
     * @param  array<string, mixed>  $state
     */
    private function tryAutoUseHealthPotionForBattleState(GameCharacter $character, array &$state): void
    {
        $currentHealth = (int) data_get($state, 'player.current_health', 0);
        $maxHealth = max(1, (int) data_get($state, 'player.max_health', $currentHealth));
        $battleLog = collect($state['battle_log'] ?? [])->values()->all();
        $battleSteps = collect($state['battle_steps'] ?? [])->values()->all();

        $this->tryAutoUseHealthPotion(
            $character,
            $currentHealth,
            $maxHealth,
            $battleLog,
            $battleSteps,
            (int) data_get($state, 'round', 1),
        );

        $state['player']['current_health'] = $currentHealth;
        $state['battle_log'] = $battleLog;
        $state['battle_steps'] = $battleSteps;
    }

    /**
     * The threshold is intentionally generous so potion automation feels reliable to the player.
     */
    private function autoPotionHealthThreshold(int $maxHealth): int
    {
        return max(16, (int) ceil($maxHealth * 0.4));
    }

    /**
     * Auto-combat should spend the smallest healing potion first and keep stronger ones in reserve.
     */
    private function resolveAutoHealthPotion(GameCharacter $character): ?CharacterItem
    {
        /** @var CharacterItem|null $potion */
        $potion = $character->inventoryItems()
            ->with('item')
            ->get()
            ->filter(
                fn (CharacterItem $inventoryItem): bool => $inventoryItem->item?->isConsumable()
                    && (int) $inventoryItem->item->heal_amount > 0,
            )
            ->sortBy(fn (CharacterItem $inventoryItem): int => (int) $inventoryItem->item->heal_amount)
            ->first();

        return $potion;
    }

    /**
     * Finalizes manual battle state, writes rewards and converts the state into replay payloads.
     *
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $rewards
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int, battle_log: list<string>, battle_summary: array<string, mixed>, battle_replay: array<string, mixed>}
     */
    private function finishManualBattle(
        GameCharacter $character,
        GameBattle $battle,
        array $state,
        array $rewards,
        Carbon $processedAt,
        bool $victory,
    ): array {
        $area = LocationArea::query()->findOrFail($battle->location_area_id);
        $progress = $this->areaProgress($character, $area);
        $roundsFought = max(1, (int) ($state['round'] ?? $battle->round));
        $startingHealth = (int) data_get($state, 'player.starting_health', $character->health);
        $currentHealth = max(0, (int) data_get($state, 'player.current_health', 0));
        $cooldownSeconds = $this->manualBattleCooldown($rewards, $roundsFought, $victory);
        $lootNames = collect($rewards['loot_names'] ?? [])->values()->all();
        $encounterType = $this->normalizeEncounterRequest(
            (string) ($rewards['encounter_type'] ?? $battle->encounter_type),
        );
        $requestedEncounter = $this->normalizeEncounterRequest(
            (string) ($rewards['requested_encounter'] ?? $battle->requested_encounter),
        );
        $tutorialBattle = (bool) ($rewards['tutorial_battle'] ?? false);
        $healthLost = max(0, $startingHealth - ($victory ? $currentHealth : 1));

        if ($encounterType === 'boss') {
            $progress->pressure = 0;

            if ($victory) {
                $progress->boss_fights += 1;
                $progress->last_boss_at = $processedAt;
            }
        } elseif ($encounterType === 'lumiis') {
            $progress->pressure += (int) ($rewards['pressure_gain'] ?? 0);

            if ($victory) {
                $progress->lumiis_searches += 1;
            }
        } else {
            $progress->pressure += (int) ($rewards['pressure_gain'] ?? 0);

            if ($victory) {
                $progress->monster_fights += 1;
            }
        }

        $progress->save();
        $character->action_available_at = $processedAt->copy()->addSeconds($cooldownSeconds);
        $character->sp = max(0, min((int) data_get($state, 'player.current_sp', $character->effectiveCurrentSp()), $character->effectiveMaxSp()));

        if ($victory) {
            $character->health = max(1, $currentHealth);
            $character->experience += (int) ($rewards['experience'] ?? 0);
            $character->yang += (int) ($rewards['yang'] ?? 0);

            foreach ($rewards['loot_drops'] ?? [] as $drop) {
                $this->inventoryService->grant(
                    $character,
                    $drop['slug'],
                    $drop['quantity'],
                    $this->dropGrantOptions($encounterType),
                );
            }

            if ($tutorialBattle) {
                $character->health = $character->effectiveMaxHealth();
                $character->tutorial_step = TutorialFlow::STEP_REWARD_EXPLAINED;
                $character->action_available_at = null;
            }
        } else {
            $character->health = 1;

            if ($tutorialBattle) {
                // Even if something unexpected happens, the player should never get stuck in a
                // failed tutorial battle. We reset them to the safe pre-fight step.
                $character->health = $character->effectiveMaxHealth();
                $character->tutorial_step = TutorialFlow::STEP_START_FIGHT;
                $character->action_available_at = null;
            }
        }

        $message = $this->manualBattleMessage(
            $area,
            $rewards,
            $roundsFought,
            $healthLost,
            $victory,
            $lootNames,
        );

        $battle->status = $victory ? 'victory' : 'defeat';
        $battle->resolved_at = $processedAt;
        $battle->round = $roundsFought;
        $battle->state = $state;
        $battle->rewards = $rewards;
        $battle->save();

        return [
            'message' => $message,
            'cooldown_seconds' => $cooldownSeconds,
            'rewards' => array_filter([
                'stamina' => 0,
                'yang' => $victory ? (int) ($rewards['yang'] ?? 0) : 0,
                'experience' => $victory ? (int) ($rewards['experience'] ?? 0) : 0,
                'health' => -$healthLost,
                'pressure' => (int) ($rewards['pressure_gain'] ?? 0),
                'items_found' => $victory ? count($rewards['loot_drops'] ?? []) : 0,
            ], static fn (mixed $value): bool => $value !== 0),
            'target_location_id' => $character->location_id,
            'battle_log' => collect($state['battle_log'] ?? [])->values()->all(),
            'battle_summary' => [
                'monster_name' => (string) ($rewards['enemy_name'] ?? $battle->encounter_name),
                'pack_size' => (int) ($rewards['pack_size'] ?? 1),
                'rounds' => $roundsFought,
                'victory' => $victory,
                'encounter_type' => $encounterType,
            ],
            'battle_replay' => $this->buildResolvedBattleReplay(
                $character,
                $state,
                $rewards,
                $area,
                $message,
                $cooldownSeconds,
                $healthLost,
                $roundsFought,
                $victory,
            ),
        ];
    }

    /**
     * Converts final battle state into the replay payload shared by manual results and auto replays.
     *
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $rewards
     * @return array<string, mixed>
     */
    private function buildResolvedBattleReplay(
        GameCharacter $character,
        array $state,
        array $rewards,
        LocationArea $area,
        string $message,
        int $cooldownSeconds,
        int $healthLost,
        int $roundsFought,
        bool $victory,
    ): array {
        $startingHealth = (int) data_get($state, 'player.starting_health', $character->health);
        $encounterType = (string) ($rewards['encounter_type'] ?? 'monster');

        return [
            'title' => (int) ($rewards['pack_size'] ?? 1) > 1
                ? ((int) $rewards['pack_size']).'x '.(string) ($rewards['enemy_name'] ?? $area->name)
                : (string) ($rewards['enemy_name'] ?? $area->name),
            'location' => $area->name,
            'encounter_type' => $encounterType,
            'victory' => $victory,
            'player' => [
                'name' => data_get($state, 'player.name'),
                'level' => $character->level,
                'health' => $startingHealth,
                'max_health' => (int) data_get($state, 'player.max_health', $startingHealth),
                'sp' => (int) data_get($state, 'player.starting_sp', $character->effectiveCurrentSp()),
                'max_sp' => (int) data_get($state, 'player.max_sp', $character->effectiveMaxSp()),
                'attack' => (int) data_get($state, 'player.attack', 0),
                'defense' => (int) data_get($state, 'player.defense', 0),
                'speed' => (int) data_get($state, 'player.speed', 0),
                'magic_attack' => (int) data_get($state, 'player.magic_attack', 0),
                'crit_chance' => (int) data_get($state, 'player.crit_chance', 0),
                'evade_chance' => (int) data_get($state, 'player.evade_chance', 0),
                'primary_damage_type' => (string) data_get($state, 'player.primary_damage_type', 'physical'),
                'primary_damage_type_label' => (string) data_get(
                    $state,
                    'player.primary_damage_type_label',
                    DamageTypeCatalog::label((string) data_get($state, 'player.primary_damage_type', 'physical')),
                ),
                'resistance_badges' => collect(data_get($state, 'player.resistance_badges', []))->values()->all(),
                'equipment' => data_get($state, 'player.equipment', []),
            ],
            'enemies' => collect($state['enemies'] ?? [])
                ->map(fn (array $enemy): array => [
                    'id' => $enemy['id'],
                    'name' => $enemy['name'],
                    'label' => $enemy['label'],
                    'level' => (int) $enemy['level'],
                    'health' => (int) $enemy['max_health'],
                    'max_health' => (int) $enemy['max_health'],
                    'attack' => (int) $enemy['minimum_attack'].'-'.(int) $enemy['maximum_attack'],
                    'category' => $enemy['category'],
                    'damage_type' => (string) ($enemy['damage_type'] ?? 'physical'),
                    'damage_type_label' => DamageTypeCatalog::label((string) ($enemy['damage_type'] ?? 'physical')),
                    'resistance_badges' => DamageTypeCatalog::resistanceBadges((array) ($enemy['resistances'] ?? [])),
                    'spawned' => (int) data_get($enemy, 'spawned_round', 0) === 0,
                ])
                ->values()
                ->all(),
            'steps' => collect($state['battle_steps'] ?? [])->values()->all(),
            'summary' => [
                'message' => $message,
                'rounds' => $roundsFought,
                'experience' => $victory ? (int) ($rewards['experience'] ?? 0) : 0,
                'yang' => $victory ? (int) ($rewards['yang'] ?? 0) : 0,
                'health_lost' => $healthLost,
                'stamina_cost' => (int) ($rewards['stamina_cost'] ?? 0),
                'loot' => $victory ? collect($rewards['loot_names'] ?? [])->values()->all() : [],
                'cooldown_seconds' => $cooldownSeconds,
                'soul_release' => $victory && $encounterType === 'boss',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildManualBattlePlayerState(
        GameCharacter $character,
        int $effectiveAttack,
        int $effectiveDefense,
        int $effectiveSpeed,
    ): array {
        return [
            'name' => $character->name,
            'race' => $character->chosenRace(),
            'skill_group' => $character->hasChosenSkillGroup() ? (string) $character->skill_group : null,
            'current_health' => $character->health,
            'starting_health' => $character->health,
            'max_health' => $character->effectiveMaxHealth(),
            'current_sp' => $character->effectiveCurrentSp(),
            'starting_sp' => $character->effectiveCurrentSp(),
            'max_sp' => $character->effectiveMaxSp(),
            'attack' => $effectiveAttack,
            'defense' => $effectiveDefense,
            'speed' => $effectiveSpeed,
            'weapon_type' => $character->equippedWeaponType(),
            'primary_damage_type' => DamageTypeCatalog::primaryCharacterDamageType($character),
            'primary_damage_type_label' => DamageTypeCatalog::label(DamageTypeCatalog::primaryCharacterDamageType($character)),
            'resistances' => $character->combatResistanceProfile(),
            'resistance_badges' => DamageTypeCatalog::resistanceBadges($character->combatResistanceProfile()),
            'skill_group_combat_profile' => MetinClassCatalog::skillGroupCombatProfile(
                $character->chosenRace(),
                $character->hasChosenSkillGroup() ? (string) $character->skill_group : null,
            ),
            'magic_attack' => $character->effectiveMagicAttack(),
            'crit_chance' => $character->criticalChancePercent(),
            'evade_chance' => $character->evadeChancePercent(),
            'average_damage_bonus' => $character->averageDamagePercentBonus(),
            'skill_damage_bonus' => $character->skillDamagePercentBonus(),
            'temporary_guard' => 0,
            'temporary_guard_rounds' => 0,
            'temporary_crit_bonus' => 0,
            'temporary_crit_bonus_rounds' => 0,
            'temporary_evade_bonus' => 0,
            'temporary_evade_bonus_rounds' => 0,
            'temporary_attack_bonus' => 0,
            'temporary_attack_bonus_rounds' => 0,
            'temporary_lifesteal_percent' => 0,
            'temporary_lifesteal_percent_rounds' => 0,
            'temporary_reflect_percent' => 0,
            'temporary_reflect_percent_rounds' => 0,
            'temporary_damage_to_sp_percent' => 0,
            'temporary_damage_to_sp_percent_rounds' => 0,
            'temporary_incoming_damage_percent' => 0,
            'temporary_incoming_damage_percent_rounds' => 0,
            'equipment' => $this->buildReplayPlayerProfile(
                $character,
                $character->health,
                $effectiveAttack,
                $effectiveDefense,
                $effectiveSpeed,
            )['equipment'],
            'skills' => $this->buildCombatSkills($character),
        ];
    }

    /**
     * Converts learned progression skills into concrete battle buttons/effects.
     *
     * @return list<array<string, mixed>>
     */
    private function buildCombatSkills(GameCharacter $character): array
    {
        $skills = $character->relationLoaded('learnedSkills')
            ? $character->learnedSkills
            : $character->learnedSkills()->with('skill')->get();

        return $skills
            ->map(function (CharacterSkill $characterSkill) use ($character): ?array {
                return MetinClassCatalog::combatProfileForLearnedSkill($characterSkill, $character);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Manual and auto fights both persist per-skill cooldowns inside the battle state.
     *
     * @param  list<array<string, mixed>>  $skills
     * @return list<array<string, mixed>>
     */
    private function normalizeCombatSkillPool(array $skills): array
    {
        return array_map(function (array $skill): array {
            $skill['cooldown_remaining'] = max(0, (int) ($skill['cooldown_remaining'] ?? 0));

            return $skill;
        }, $skills);
    }

    /**
     * @param  list<array<string, mixed>>  $skills
     */
    private function markCombatSkillUsed(array &$skills, ?string $skillSlug): void
    {
        if ($skillSlug === null || $skillSlug === '') {
            return;
        }

        foreach ($skills as &$knownSkill) {
            if (($knownSkill['slug'] ?? null) !== $skillSlug) {
                continue;
            }

            $knownSkill['cooldown_remaining'] = max(0, (int) ($knownSkill['cooldown_rounds'] ?? 0));

            break;
        }
        unset($knownSkill);
    }

    /**
     * @param  list<array<string, mixed>>  $skills
     */
    private function tickCombatSkillCooldowns(array &$skills): void
    {
        foreach ($skills as &$skill) {
            $skill['cooldown_remaining'] = max(0, (int) ($skill['cooldown_remaining'] ?? 0) - 1);
        }
        unset($skill);
    }

    /**
     * @param  list<array<string, mixed>>  $skills
     * @return array<string, mixed>|null
     */
    private function chooseAutoCombatSkill(array &$skills, int $playerSp, int $round, int $activeAttackBuffRounds = 0): ?array
    {
        $skills = $this->normalizeCombatSkillPool($skills);
        $availableSkills = array_values(array_filter(
            $skills,
            fn (array $skill): bool => $playerSp >= (int) ($skill['sp_cost'] ?? 0)
                && (int) ($skill['cooldown_remaining'] ?? 0) <= 0,
        ));

        if ($activeAttackBuffRounds > 0) {
            $availableSkills = array_values(array_filter(
                $availableSkills,
                fn (array $skill): bool => ! (bool) ($skill['self_buff_only'] ?? false),
            ));
        }

        if ($availableSkills === []) {
            return null;
        }

        if ($round !== 1 && random_int(1, 100) > 35) {
            return null;
        }

        return $availableSkills[array_rand($availableSkills)];
    }

    private function resolvePackBasicSplashPercent(int $enemyLevel, string $category): int
    {
        if ($category === 'Tutorial Pack') {
            return 70;
        }

        return match (true) {
            $enemyLevel <= 10 => random_int(35, 50),
            default => 0,
        };
    }

    private function resolvePackAggressionBonus(int $aliveEnemyCount, int $enemyLevel, string $category): int
    {
        if ($category === 'Tutorial Pack' || $aliveEnemyCount <= 1) {
            return 0;
        }

        return max(0, $aliveEnemyCount - 1) * max(1, intdiv(max(1, $enemyLevel), 10));
    }

    /**
     * The first bonus slice already lets equipment affect economy and farming quality.
     * Keeping the mapping here avoids scattering percentage math across the trait.
     */
    private function applyRewardRateBonus(GameCharacter $character, int $baseAmount, string $resource): int
    {
        $percent = match ($resource) {
            'experience' => $character->experienceRatePercentBonus(),
            'yang' => $character->yangRatePercentBonus(),
            default => 0,
        };

        if ($percent <= 0 || $baseAmount <= 0) {
            return $baseAmount;
        }

        return $baseAmount + (int) floor($baseAmount * ($percent / 100));
    }

    /**
     * Looted equipment should feel meaningfully different from shop gear and starter items.
     *
     * @return array{roll_bonuses: bool, bonus_source: string}
     */
    private function dropGrantOptions(string $encounterType): array
    {
        return [
            'roll_bonuses' => true,
            'bonus_source' => $this->normalizeEncounterRequest($encounterType),
        ];
    }

    private function applyCategoryDamageBonus(
        GameCharacter $character,
        int $baseDamage,
        ?string $enemyCategory,
    ): int {
        return max(1, (int) floor($baseDamage * $character->damageMultiplierForEnemyCategory($enemyCategory)));
    }

    private function applyWeaponDamageBonus(GameCharacter $character, int $baseDamage, bool $skillHit, int $extraPercent = 0): int
    {
        $percent = $skillHit
            ? $character->skillDamagePercentBonus()
            : $character->averageDamagePercentBonus();
        $percent += $extraPercent;

        if ($percent <= 0) {
            return max(1, $baseDamage);
        }

        return max(1, (int) floor($baseDamage * (1 + ($percent / 100))));
    }

    /**
     * @param  array{minimum: int, maximum: int}  $bounds
     * @param  array<string, int>  $profile
     * @return array{minimum: int, maximum: int}
     */
    private function applySkillGroupAttackBounds(array $bounds, array $profile): array
    {
        $minimum = max(1, (int) ($bounds['minimum'] ?? 1) + (int) ($profile['attack_floor_offset'] ?? 0));
        $maximum = max(
            $minimum,
            (int) ($bounds['maximum'] ?? $minimum) + (int) ($profile['attack_ceiling_offset'] ?? 0),
        );

        return [
            'minimum' => $minimum,
            'maximum' => $maximum,
        ];
    }

    /**
     * @param  array<string, int>  $profile
     */
    private function skillGroupDamagePercentBonus(array $profile, bool $skillHit, int $round): int
    {
        return (int) ($profile['basic_damage_percent'] ?? 0)
            + ($skillHit ? (int) ($profile['skill_damage_percent'] ?? 0) : 0)
            + ($round === 1 ? (int) ($profile['opening_damage_percent'] ?? 0) : 0);
    }

    /**
     * @param  array<string, int>  $profile
     */
    private function skillGroupOpeningCritBonus(array $profile, int $round): int
    {
        return $round === 1 ? (int) ($profile['opening_crit_bonus'] ?? 0) : 0;
    }

    private function applyIncomingDamagePercentModifier(int $baseDamage, int $percent): int
    {
        if ($percent === 0 || $baseDamage <= 0) {
            return max(0, $baseDamage);
        }

        return max(1, (int) floor($baseDamage * (1 + ($percent / 100))));
    }

    private function applyOutgoingEnemyDamageReduction(int $baseDamage, int $percent): int
    {
        if ($percent <= 0 || $baseDamage <= 0) {
            return max(0, $baseDamage);
        }

        return max(1, (int) floor($baseDamage * (1 - min(90, $percent) / 100)));
    }

    private function lifestealHealAmount(int $damageDealt, int $percent): int
    {
        if ($damageDealt <= 0 || $percent <= 0) {
            return 0;
        }

        return max(1, (int) floor($damageDealt * ($percent / 100)));
    }

    private function reflectedDamageAmount(int $damageTaken, int $percent): int
    {
        if ($damageTaken <= 0 || $percent <= 0) {
            return 0;
        }

        return max(1, (int) floor($damageTaken * ($percent / 100)));
    }

    private function absorbDamageIntoSp(int $incomingDamage, int &$currentSp, int $percent, int &$spAbsorbed = 0): int
    {
        $spAbsorbed = 0;

        if ($incomingDamage <= 0 || $currentSp <= 0 || $percent <= 0) {
            return max(0, $incomingDamage);
        }

        $spAbsorbed = min($currentSp, max(1, (int) floor($incomingDamage * ($percent / 100))));
        $currentSp = max(0, $currentSp - $spAbsorbed);

        return max(0, $incomingDamage - $spAbsorbed);
    }

    private function applyTemporaryEffect(int &$currentValue, int &$currentRounds, int $newValue, int $duration): void
    {
        if ($newValue <= 0 || $duration <= 0) {
            return;
        }

        $currentValue = max($currentValue, $newValue);
        $currentRounds = max($currentRounds, $duration);
    }

    private function activeTemporaryEffect(int $value, int $rounds): int
    {
        return $rounds > 0 ? $value : 0;
    }

    private function decayTemporaryEffect(int &$currentValue, int &$currentRounds): void
    {
        $currentRounds = max(0, $currentRounds - 1);

        if ($currentRounds === 0) {
            $currentValue = 0;
        }
    }

    /**
     * @param  array<string, mixed>  $player
     */
    private function applyBattleStateEffect(
        array &$player,
        string $valueKey,
        string $roundsKey,
        int $newValue,
        int $duration,
    ): void {
        if ($newValue <= 0 || $duration <= 0) {
            return;
        }

        $player[$valueKey] = max((int) ($player[$valueKey] ?? 0), $newValue);
        $player[$roundsKey] = max((int) ($player[$roundsKey] ?? 0), $duration);
    }

    /**
     * @param  array<string, mixed>  $player
     */
    private function activeBattleStateEffect(array $player, string $valueKey, string $roundsKey): int
    {
        return (int) ($player[$roundsKey] ?? 0) > 0
            ? (int) ($player[$valueKey] ?? 0)
            : 0;
    }

    /**
     * @param  array<string, mixed>  $player
     */
    private function decayBattleStateEffect(array &$player, string $valueKey, string $roundsKey): void
    {
        $player[$roundsKey] = max(0, (int) ($player[$roundsKey] ?? 0) - 1);

        if ((int) $player[$roundsKey] === 0) {
            $player[$valueKey] = 0;
        }
    }

    /**
     * @param  array<string, mixed>  $enemy
     */
    private function activeEnemyEffect(array $enemy, string $valueKey, string $roundsKey): int
    {
        return (int) ($enemy[$roundsKey] ?? 0) > 0
            ? (int) ($enemy[$valueKey] ?? 0)
            : 0;
    }

    /**
     * @param  array<string, mixed>  $enemy
     */
    private function decayEnemyEffect(array &$enemy, string $valueKey, string $roundsKey): void
    {
        $enemy[$roundsKey] = max(0, (int) ($enemy[$roundsKey] ?? 0) - 1);

        if ((int) $enemy[$roundsKey] === 0) {
            $enemy[$valueKey] = 0;
        }
    }

    /**
     * @param  array<string, mixed>  $skill
     */
    private function describeSelfBuffEffects(array $skill): string
    {
        $effects = [];

        if ((int) ($skill['attack_buff'] ?? 0) > 0) {
            $effects[] = __('Attack +:amount', ['amount' => (int) $skill['attack_buff']]);
        }

        if ((int) ($skill['guard'] ?? 0) > 0) {
            $effects[] = __('Guard +:amount', ['amount' => (int) $skill['guard']]);
        }

        if ((int) ($skill['crit_bonus'] ?? 0) > 0) {
            $effects[] = __('Crit +:amount%', ['amount' => (int) $skill['crit_bonus']]);
        }

        if ((int) ($skill['evade_bonus'] ?? 0) > 0) {
            $effects[] = __('Evade +:amount%', ['amount' => (int) $skill['evade_bonus']]);
        }

        if ((int) ($skill['lifesteal_percent'] ?? 0) > 0) {
            $effects[] = __('Lifesteal +:amount%', ['amount' => (int) $skill['lifesteal_percent']]);
        }

        if ((int) ($skill['reflect_percent'] ?? 0) > 0) {
            $effects[] = __('Reflect +:amount%', ['amount' => (int) $skill['reflect_percent']]);
        }

        if ((int) ($skill['damage_to_sp_percent'] ?? 0) > 0) {
            $effects[] = __('SP Shield :amount%', ['amount' => (int) $skill['damage_to_sp_percent']]);
        }

        if ((int) ($skill['incoming_damage_percent'] ?? 0) < 0) {
            $effects[] = __('Incoming damage :amount%', ['amount' => (int) ($skill['incoming_damage_percent'] ?? 0)]);
        }

        return $effects === []
            ? __('Battle effects')
            : implode(', ', $effects);
    }

    /**
     * @param  array<string, mixed>  $rewards
     */
    private function manualBattleCooldown(array $rewards, int $roundsFought, bool $victory): int
    {
        if ((bool) ($rewards['tutorial_battle'] ?? false)) {
            return 0;
        }

        $encounterType = $this->normalizeEncounterRequest((string) ($rewards['encounter_type'] ?? 'monster'));
        $baseCooldown = (int) ($rewards['cooldown_base'] ?? 45);
        $enemyLevel = (int) ($rewards['enemy_level'] ?? 1);

        return match ($encounterType) {
            'boss' => max($baseCooldown, 24 + ($roundsFought * 8) + $enemyLevel + ($victory ? 0 : 18)),
            'lumiis' => max($baseCooldown, 18 + ($roundsFought * 6) + intdiv($enemyLevel, 2) + ($victory ? 0 : 12)),
            default => max($baseCooldown, 12 + ($roundsFought * 4) + intdiv($enemyLevel, 2) + ($victory ? 0 : 18)),
        };
    }

    /**
     * @param  array<string, mixed>  $rewards
     * @param  list<string>  $lootNames
     */
    private function manualBattleMessage(
        LocationArea $area,
        array $rewards,
        int $roundsFought,
        int $healthLost,
        bool $victory,
        array $lootNames,
    ): string {
        $packSize = (int) ($rewards['pack_size'] ?? 1);
        $enemyName = (string) ($rewards['enemy_name'] ?? $area->name);
        $encounterType = $this->normalizeEncounterRequest((string) ($rewards['encounter_type'] ?? 'monster'));

        if (! $victory) {
            if ($encounterType === 'boss') {
                return __(':enemy overwhelms you after :rounds rounds. You retreat with 1 HP.', [
                    'enemy' => $enemyName,
                    'rounds' => $roundsFought,
                ]);
            }

            return $packSize > 1
                ? __('The :enemy pack drives you back after :rounds rounds. You escape with 1 HP.', [
                    'enemy' => $enemyName,
                    'rounds' => $roundsFought,
                ])
                : __(':enemy drives you back after :rounds rounds. You escape with 1 HP.', [
                    'enemy' => $enemyName,
                    'rounds' => $roundsFought,
                ]);
        }

        if ($packSize > 1) {
            $message = __('You defeat :pack after :rounds rounds. +:experience EXP, +:yang Yang, -:health HP', [
                'pack' => $packSize.'x '.$enemyName,
                'rounds' => $roundsFought,
                'experience' => (int) ($rewards['experience'] ?? 0),
                'yang' => (int) ($rewards['yang'] ?? 0),
                'health' => $healthLost,
            ]);
        } else {
            $message = $this->encounterMessage(
                $area,
                $encounterType,
                (string) ($rewards['requested_encounter'] ?? $encounterType),
                (int) ($rewards['experience'] ?? 0),
                (int) ($rewards['yang'] ?? 0),
                $healthLost,
                $lootNames,
            );

            return $message.' '.__('After :rounds rounds.', ['rounds' => $roundsFought]);
        }

        if ($lootNames !== []) {
            $message .= ' '.__('Loot: :items.', ['items' => implode(', ', $lootNames)]);
        } else {
            $message .= '.';
        }

        return $message;
    }
}
