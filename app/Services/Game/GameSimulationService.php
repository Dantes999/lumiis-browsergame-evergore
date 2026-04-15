<?php

namespace App\Services\Game;

use App\Enums\GameActionType;
use App\Exceptions\GameActionException;
use App\Models\GameActionLog;
use App\Models\GameBattle;
use App\Models\GameCharacter;
use App\Models\LocationArea;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GameSimulationService
{
    public function __construct(
        private readonly ProcessGameAction $processGameAction,
        private readonly NormalSimulationCharacterFactory $characterFactory,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function simulate(array $payload): array
    {
        $area = LocationArea::query()
            ->with('location')
            ->where('slug', (string) $payload['area_slug'])
            ->firstOrFail();

        $encounter = (string) ($payload['encounter'] ?? 'monster');
        $mode = (string) ($payload['mode'] ?? ($encounter === 'boss' ? 'manual' : 'auto'));
        $strategy = (string) ($payload['strategy'] ?? 'attack-only');
        $iterations = max(1, (int) ($payload['iterations'] ?? 1));
        $includeRuns = array_key_exists('include_runs', $payload)
            ? (bool) $payload['include_runs']
            : $iterations === 1;

        $runs = [];

        if (isset($payload['character_id'])) {
            $runs[] = $this->simulateWithExistingCharacter(
                (int) $payload['character_id'],
                $area,
                $mode,
                $encounter,
                $strategy,
            );
        } else {
            /** @var array<string, mixed> $profile */
            $profile = is_array($payload['profile'] ?? null) ? $payload['profile'] : [];

            for ($index = 1; $index <= $iterations; $index++) {
                $runs[] = $this->simulateEphemeralRun(
                    $profile,
                    $area,
                    $mode,
                    $encounter,
                    $strategy,
                    $index,
                );
            }
        }

        $response = [
            'source' => isset($payload['character_id']) ? 'saved-simulation-character' : 'generated-normal-character',
            'area' => $this->serializeArea($area),
            'mode' => $mode,
            'encounter' => $encounter,
            'strategy' => $strategy,
            'iterations' => $iterations,
            'aggregate' => $this->aggregateRuns($runs),
        ];

        if ($includeRuns) {
            $response['runs'] = $runs;
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function simulateEphemeralRun(
        array $profile,
        LocationArea $area,
        string $mode,
        string $encounter,
        string $strategy,
        int $index,
    ): array {
        DB::beginTransaction();

        try {
            $character = $this->characterFactory->create($profile, $area->location);
            $result = $this->runFightSimulation($character, $area, $mode, $encounter, $strategy);
            $result['index'] = $index;

            DB::rollBack();

            return $result;
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateWithExistingCharacter(
        int $characterId,
        LocationArea $area,
        string $mode,
        string $encounter,
        string $strategy,
    ): array {
        $character = $this->loadSimulationCharacter($characterId);

        if ((int) $character->location_id !== (int) $area->location_id) {
            $character->forceFill([
                'location_id' => $area->location_id,
                'action_available_at' => null,
            ])->save();
        }

        return $this->runFightSimulation($this->freshCharacter($character->id), $area, $mode, $encounter, $strategy);
    }

    /**
     * @return array<string, mixed>
     */
    private function runFightSimulation(
        GameCharacter $character,
        LocationArea $area,
        string $mode,
        string $encounter,
        string $strategy,
    ): array {
        $before = $this->serializeCharacter($character);

        if ($mode === 'manual') {
            $openingLog = $this->performAction($character, GameActionType::StartManualFight, [
                'location_area_id' => $area->id,
                'encounter_request' => $encounter,
            ]);

            $turns = [];
            $finalLog = $openingLog;
            $safetyCounter = 0;

            while (($battle = $this->loadActiveBattle($character->id)) !== null && $battle->status === 'ongoing') {
                $safetyCounter++;

                if ($safetyCounter > 80) {
                    throw new GameActionException(__('Simulation exceeded the manual battle turn safety limit.'));
                }

                $decision = $this->resolveManualDecision($battle, $strategy);
                $round = max(1, (int) data_get($battle->state, 'round', $battle->round));
                $action = ($decision['action'] ?? 'attack') === 'skill'
                    ? GameActionType::BattleUseSkill
                    : GameActionType::BattleAttack;

                $payload = [
                    'battle_id' => $battle->id,
                    'battle_round' => $round,
                    'enemy_id' => $decision['enemy_id'],
                ];

                if ($action === GameActionType::BattleUseSkill) {
                    $payload['skill_slug'] = $decision['skill_slug'];
                }

                $finalLog = $this->performAction($character, $action, $payload);

                $turns[] = [
                    'round' => $round,
                    'action' => $action->value,
                    'enemy_id' => $decision['enemy_id'],
                    'skill_slug' => $decision['skill_slug'] ?? null,
                    'message' => (string) data_get($finalLog->outcome, 'message', ''),
                ];
            }

            $after = $this->serializeCharacter($this->freshCharacter($character->id));

            return $this->buildRunResult(
                before: $before,
                after: $after,
                finalLog: $finalLog,
                openingMessage: (string) data_get($openingLog->outcome, 'message', ''),
                turns: $turns,
            );
        }

        $action = $encounter === 'lumiis'
            ? GameActionType::SearchLumiis
            : GameActionType::FightMonsters;

        $finalLog = $this->performAction($character, $action, [
            'location_area_id' => $area->id,
        ]);

        $after = $this->serializeCharacter($this->freshCharacter($character->id));

        return $this->buildRunResult(
            before: $before,
            after: $after,
            finalLog: $finalLog,
            openingMessage: null,
            turns: [],
        );
    }

    private function loadSimulationCharacter(int $characterId): GameCharacter
    {
        $character = $this->freshCharacter($characterId);

        if (! $this->characterFactory->isSimulationCharacter($character)) {
            throw new GameActionException(__('This API only accepts characters created by the simulation character endpoint.'));
        }

        return $character;
    }

    private function performAction(GameCharacter $character, GameActionType $action, array $payload): GameActionLog
    {
        $idempotencyKey = (string) Str::uuid();

        $this->processGameAction->handle(
            $character->user,
            $action,
            array_merge($payload, ['idempotency_key' => $idempotencyKey]),
            $character,
        );

        return GameActionLog::query()
            ->where('game_character_id', $character->id)
            ->where('idempotency_key', $idempotencyKey)
            ->latest('id')
            ->firstOrFail();
    }

    private function loadActiveBattle(int $characterId): ?GameBattle
    {
        return GameBattle::query()
            ->where('game_character_id', $characterId)
            ->where('status', 'ongoing')
            ->latest('id')
            ->first();
    }

    /**
     * @return array{action: string, enemy_id: string, skill_slug?: string}
     */
    private function resolveManualDecision(GameBattle $battle, string $strategy): array
    {
        /** @var array<string, mixed> $state */
        $state = $battle->state ?? [];
        $enemyId = $this->resolveTargetEnemyId($state);

        if ($enemyId === null) {
            throw new GameActionException(__('Simulation could not find a living battle target.'));
        }

        $playerSp = (int) data_get($state, 'player.current_sp', 0);
        $availableSkills = collect(data_get($state, 'player.skills', []))
            ->filter(fn (array $skill): bool => $playerSp >= (int) ($skill['sp_cost'] ?? 0))
            ->filter(fn (array $skill): bool => (int) ($skill['cooldown_remaining'] ?? 0) <= 0)
            ->values();

        if ($strategy === 'auto' && $availableSkills->isNotEmpty()) {
            $skill = $availableSkills->first();
            $skillSlug = (string) ($skill['slug'] ?? '');

            if ($skillSlug !== '') {
                return [
                    'action' => 'skill',
                    'enemy_id' => $enemyId,
                    'skill_slug' => $skillSlug,
                ];
            }
        }

        return [
            'action' => 'attack',
            'enemy_id' => $enemyId,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function resolveTargetEnemyId(array $state): ?string
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
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  list<array<string, mixed>>  $turns
     * @return array<string, mixed>
     */
    private function buildRunResult(
        array $before,
        array $after,
        GameActionLog $finalLog,
        ?string $openingMessage,
        array $turns,
    ): array {
        /** @var array<string, mixed> $outcome */
        $outcome = $finalLog->outcome ?? [];
        /** @var array<string, mixed> $battleSummary */
        $battleSummary = is_array($outcome['battle_summary'] ?? null) ? $outcome['battle_summary'] : [];
        /** @var array<string, mixed> $replay */
        $replay = is_array($outcome['battle_replay'] ?? null) ? $outcome['battle_replay'] : [];
        /** @var array<string, mixed> $replaySummary */
        $replaySummary = is_array($replay['summary'] ?? null) ? $replay['summary'] : [];

        $victory = (bool) ($battleSummary['victory'] ?? $replay['victory'] ?? false);
        $rounds = (int) ($battleSummary['rounds'] ?? $replaySummary['rounds'] ?? 0);
        $experience = (int) ($replaySummary['experience'] ?? data_get($outcome, 'rewards.experience', 0));
        $yang = (int) ($replaySummary['yang'] ?? data_get($outcome, 'rewards.yang', 0));
        $healthLost = (int) ($replaySummary['health_lost'] ?? abs((int) data_get($outcome, 'rewards.health', 0)));
        $staminaCost = (int) ($replaySummary['stamina_cost'] ?? abs((int) data_get($outcome, 'rewards.stamina', 0)));

        return [
            'action_log_id' => $finalLog->id,
            'action' => $finalLog->action,
            'opening_message' => $openingMessage,
            'message' => (string) data_get($outcome, 'message', ''),
            'victory' => $victory,
            'rounds' => $rounds,
            'experience' => $experience,
            'yang' => $yang,
            'health_lost' => $healthLost,
            'stamina_cost' => $staminaCost,
            'battle_summary' => $battleSummary,
            'battle_log' => collect($outcome['battle_log'] ?? [])->values()->all(),
            'battle_replay' => $replay,
            'rewards' => $outcome['rewards'] ?? [],
            'turns' => $turns,
            'character_before' => $before,
            'character_after' => $after,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $runs
     * @return array<string, mixed>
     */
    private function aggregateRuns(array $runs): array
    {
        $totalRuns = count($runs);
        $victories = collect($runs)->where('victory', true)->count();

        $average = static function (string $key) use ($runs, $totalRuns): float {
            if ($totalRuns === 0) {
                return 0.0;
            }

            $sum = array_sum(array_map(
                static fn (array $run): int|float => (int) ($run[$key] ?? 0),
                $runs,
            ));

            return round($sum / $totalRuns, 2);
        };

        return [
            'total_runs' => $totalRuns,
            'victories' => $victories,
            'defeats' => $totalRuns - $victories,
            'victory_rate_percent' => $totalRuns === 0 ? 0.0 : round(($victories / $totalRuns) * 100, 2),
            'average_rounds' => $average('rounds'),
            'average_experience' => $average('experience'),
            'average_yang' => $average('yang'),
            'average_health_lost' => $average('health_lost'),
            'average_stamina_cost' => $average('stamina_cost'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeArea(LocationArea $area): array
    {
        $area->loadMissing('location');

        return [
            'id' => $area->id,
            'slug' => $area->slug,
            'name' => $area->name,
            'location_slug' => $area->location?->slug,
            'location_name' => $area->location?->name,
            'recommended_level' => $area->recommended_level,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCharacter(GameCharacter $character): array
    {
        $character = $this->freshCharacter($character->id);

        return [
            'id' => $character->id,
            'name' => $character->name,
            'race' => $character->chosenRace(),
            'level' => (int) $character->level,
            'experience' => (int) $character->experience,
            'next_level_experience' => $character->nextLevelExperience(),
            'game_state' => $character->game_state?->value,
            'tutorial_step' => $character->tutorial_step,
            'location' => [
                'id' => $character->location?->id,
                'slug' => $character->location?->slug,
                'name' => $character->location?->name,
            ],
            'resources' => [
                'health' => (int) $character->health,
                'max_health' => $character->effectiveMaxHealth(),
                'stamina' => (int) $character->stamina,
                'max_stamina' => $character->effectiveMaxStamina(),
                'sp' => $character->effectiveCurrentSp(),
                'max_sp' => $character->effectiveMaxSp(),
            ],
            'stats' => [
                'attack' => $character->effectiveAttack(),
                'defense' => $character->effectiveDefense(),
                'speed' => $character->effectiveSpeed(),
                'magic_attack' => $character->effectiveMagicAttack(),
                'crit_chance' => $character->criticalChancePercent(),
                'evade_chance' => $character->evadeChancePercent(),
                'monster_damage_bonus' => $character->monsterDamagePercentBonus(),
                'lumiis_damage_bonus' => $character->lumiisDamagePercentBonus(),
            ],
            'equipment' => $character->equipment
                ->map(fn ($equipment): array => [
                    'slot' => $equipment->slot,
                    'slug' => $equipment->characterItem?->item?->slug,
                    'name' => $equipment->characterItem?->item?->name,
                ])
                ->values()
                ->all(),
            'inventory' => $character->inventoryItems
                ->map(fn ($inventoryItem): array => [
                    'id' => $inventoryItem->id,
                    'slug' => $inventoryItem->item?->slug,
                    'name' => $inventoryItem->item?->name,
                    'quantity' => (int) $inventoryItem->quantity,
                    'equipped' => $inventoryItem->equipment !== null,
                ])
                ->values()
                ->all(),
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
                'equipment.characterItem.item',
                'learnedSkills.skill',
            ])
            ->findOrFail($characterId);
    }
}
