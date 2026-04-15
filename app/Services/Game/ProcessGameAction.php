<?php

namespace App\Services\Game;

use App\Enums\GameActionType;
use App\Exceptions\GameActionException;
use App\Models\GameActionLog;
use App\Models\GameBattle;
use App\Models\GameCharacter;
use App\Models\LocationArea;
use App\Models\User;
use App\Services\Game\Concerns\HandlesCharacterActions;
use App\Services\Game\Concerns\HandlesCombatActions;
use App\Services\Game\Concerns\HandlesInventoryActions;
use App\Services\Game\Concerns\HandlesMessagingActions;
use App\Services\Game\Concerns\HandlesTutorialActions;
use App\Services\Game\Concerns\ManagesCharacterState;
use App\Services\Game\Concerns\ResolvesEncounterData;
use App\Support\Game\TutorialFlow;
use Illuminate\Support\Arr;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Thin orchestration layer for all player actions.
 *
 * The actual domain logic is split into focused traits so maintenance stays human-sized:
 * - combat and encounters
 * - inventory and equipment
 * - character progression and recovery
 * - chat / whisper delivery
 *
 * This class now owns only the cross-cutting flow:
 * load state, apply guards, dispatch, finalize and log.
 */
class ProcessGameAction
{
    use HandlesCharacterActions;
    use HandlesCombatActions;
    use HandlesInventoryActions;
    use HandlesMessagingActions;
    use HandlesTutorialActions;
    use ManagesCharacterState;
    use ResolvesEncounterData;

    public function __construct(
        private readonly CharacterInventoryService $inventoryService,
        private readonly QuestProgressService $questProgressService,
        private readonly RestStateService $restStateService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(User $user, GameActionType $action, array $payload, ?GameCharacter $selectedCharacter = null): string
    {
        return $this->runWithActionLocks($user, $action, $payload, function () use ($user, $action, $payload, $selectedCharacter): string {
            return DB::transaction(function () use ($user, $action, $payload, $selectedCharacter): string {
                $processedAt = now();

                // Always operate on the latest locked character row so cooldown, stamina and rewards
                // cannot race against a second request from another browser tab.
                $character = $this->loadCharacterForAction($user, $selectedCharacter);
                $existingLog = $this->findExistingLog($character, (string) $payload['idempotency_key']);

                if ($existingLog !== null) {
                    $this->guardIdempotencyReplay($existingLog, $action, $payload);

                    // Idempotency lets us safely return the first result when the same request
                    // is submitted twice because of retries, double-clicks or bad connections.
                    return (string) data_get($existingLog->outcome, 'message', __('Action already processed.'));
                }

                // Long rests resolve at the end of their timer. Before we process any new action
                // we settle a finished rest exactly once so the character state is never one
                // request behind the countdown the player just saw.
                $this->restStateService->resolveCompletedRest($character, $processedAt);

                // Passive HP/SP/Stamina recovery is time-based, so we refresh it before any action
                // checks run. This keeps the guard phase and the action phase in sync.
                $this->syncPassiveRecovery($character, $processedAt);

                // Manual battles are persistent state machines. We need to know whether the player
                // is currently locked into one before allowing any other action.
                $activeBattle = $this->loadActiveBattle($character);
                $this->guardActionAvailability($user, $action, $character, $activeBattle, $payload);

                $sourceLocationId = $character->location_id;
                $startingLevel = $character->level;

                // Action-specific logic lives in the smaller concern traits.
                $result = $this->dispatchAction($action, $character, $activeBattle, $payload, $processedAt);

                // This normalizes shared post-processing such as level-ups, caps and activity tracking.
                $this->finalizeCharacterAfterAction($character, $result, $startingLevel, $processedAt);

                // Every processed action is logged for replay UI, debugging and anti-cheat visibility.
                $this->recordActionLog($character, $action, $payload, $sourceLocationId, $result, $processedAt);

                return $result['message'];
            });
        });
    }

    /**
     * Database row locks still need a process-wide companion lock for hotspots such as repeated
     * double-clicks or two buyers racing for the last shop copy. We acquire deterministic lock
     * keys before opening the DB transaction so follow-up requests start from fresh state.
     *
     * @param  callable(): string  $callback
     */
    private function runWithActionLocks(User $user, GameActionType $action, array $payload, callable $callback): string
    {
        $lockKeys = $this->actionLockKeys($user, $action, $payload);

        $runner = $callback;

        foreach (array_reverse($lockKeys) as $lockKey) {
            $nextRunner = $runner;
            $runner = fn (): string => $this->acquireActionLock($lockKey, $nextRunner);
        }

        return $runner();
    }

    /**
     * @return list<string>
     */
    private function actionLockKeys(User $user, GameActionType $action, array $payload): array
    {
        $lockKeys = ['game-action:user:'.$user->id];

        if ($action === GameActionType::BuyItem && isset($payload['shop_item_id'])) {
            $lockKeys[] = 'game-shop-item:'.(int) $payload['shop_item_id'];
        }

        if ($action === GameActionType::UpgradeItem && isset($payload['character_item_id'])) {
            $lockKeys[] = 'game-smith-item:'.(int) $payload['character_item_id'];
        }

        if (in_array($action, [
            GameActionType::SubmitQuestItem,
            GameActionType::BoostResearchSubmission,
            GameActionType::SkipResearchCooldown,
            GameActionType::ClaimQuest,
        ], true) && isset($payload['character_quest_id'])) {
            $lockKeys[] = 'game-biologist-quest:'.(int) $payload['character_quest_id'];
        }

        if (in_array($action, [
            GameActionType::AddItemBonus,
            GameActionType::RerollItemBonuses,
            GameActionType::UnlockItemBonus,
        ], true) && isset($payload['target_character_item_id'])) {
            $lockKeys[] = 'game-bonus-item:'.(int) $payload['target_character_item_id'];
        }

        if ($action->requiresActiveBattle() && isset($payload['battle_id'])) {
            $lockKeys[] = 'game-battle:'.(int) $payload['battle_id'];
        }

        sort($lockKeys);

        return array_values(array_unique($lockKeys));
    }

    /**
     * The file lock store keeps concurrency deterministic in tests and on a single app node.
     * If the project later moves to Redis, this is the single swap point for distributed locks.
     *
     * @param  callable(): string  $callback
     */
    private function acquireActionLock(string $lockKey, callable $callback): string
    {
        try {
            return Cache::store('file')
                ->lock($lockKey, 10)
                ->block(5, $callback);
        } catch (LockTimeoutException) {
            throw new GameActionException(__('Another action is already being processed. Please try again.'));
        }
    }

    /**
     * Pull a fully-hydrated, row-locked character so action handlers can work against consistent state.
     */
    private function loadCharacterForAction(User $user, ?GameCharacter $selectedCharacter = null): GameCharacter
    {
        $query = GameCharacter::query()
            ->where('user_id', $user->id)
            ->with([
                'location.outgoingConnections.toLocation',
                'location.areas.shop.shopItems.item',
                'areaProgress.locationArea.monsterAssignments.monster',
                'characterQuests.quest.location',
                'inventoryItems.item',
                'inventoryItems.equipment',
                'equipment.characterItem.item',
                'learnedSkills.skill',
            ])
            ->lockForUpdate();

        if ($selectedCharacter instanceof GameCharacter) {
            $query->whereKey($selectedCharacter->id);
        } else {
            $query
                ->orderBy('created_at')
                ->orderBy('id');
        }

        $character = $query->first();

        if (! $character instanceof GameCharacter) {
            throw new GameActionException(__('The selected character could not be loaded.'));
        }

        // Action handlers frequently access many LocationArea columns on the in-memory map data.
        // We reload the areas explicitly so combat/inventory flows never depend on partially-loaded
        // nested relations from another query path.
        $character->location->setRelation(
            'areas',
            LocationArea::query()
                ->with(['monsterAssignments.monster', 'shop.shopItems.item'])
                ->where('location_id', $character->location_id)
                ->orderBy('sort_order')
                ->get(),
        );

        // Inventory coordinates are now first-class state. Repair any stale or overlapping rows
        // before an action handler relies on bag contents or equipment swaps.
        $this->inventoryService->ensureLayout($character);

        return $character;
    }

    private function findExistingLog(GameCharacter $character, string $idempotencyKey): ?GameActionLog
    {
        return GameActionLog::query()
            ->where('game_character_id', $character->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    /**
     * Reusing the same idempotency key for a different action or payload is suspicious.
     *
     * We reject that explicitly so clients cannot piggy-back on an old successful request
     * to get a misleading response or replay window for a different action.
     *
     * @param  array<string, mixed>  $payload
     */
    private function guardIdempotencyReplay(GameActionLog $existingLog, GameActionType $action, array $payload): void
    {
        $incomingPayload = Arr::except($payload, ['idempotency_key']);
        $loggedPayload = $existingLog->request_payload ?? [];

        if ($existingLog->action !== $action->value || $loggedPayload != $incomingPayload) {
            throw new GameActionException(__('This action key was already used for a different request.'));
        }
    }

    private function loadActiveBattle(GameCharacter $character): ?GameBattle
    {
        return GameBattle::query()
            ->where('game_character_id', $character->id)
            ->where('status', 'ongoing')
            ->lockForUpdate()
            ->latest('id')
            ->first();
    }

    /**
     * Central action guards live here because they are shared across all domains.
     */
    private function guardActionAvailability(
        User $user,
        GameActionType $action,
        GameCharacter $character,
        ?GameBattle $activeBattle,
        array $payload,
    ): void {
        if ($action->isChatAction()) {
            $this->guardChatRateLimit($user, $action);
        }

        $this->guardTutorialActionAvailability($action, $character, $payload, $activeBattle);
        $this->guardFeatureAvailability($action, $character, $payload);

        if ($activeBattle !== null && ! $action->requiresActiveBattle()) {
            if (
                $character->isInTutorial()
                && TutorialFlow::currentStep($character) === TutorialFlow::STEP_BATTLE_UI
                && $action === GameActionType::TutorialNext
            ) {
                return;
            }

            if ($action === GameActionType::StartManualFight) {
                throw new GameActionException(__('Finish the current manual battle before starting a new one.'));
            }

            throw new GameActionException(__('You are already inside a battle. Finish it before using other actions.'));
        }

        if ($activeBattle === null && $action->requiresActiveBattle()) {
            throw new GameActionException(__('There is no active manual battle to continue.'));
        }

        if ($action->usesCharacterCooldown() && $character->isOnCooldown()) {
            throw new GameActionException(
                __('Your character is still recovering. Wait :seconds seconds.', [
                    'seconds' => $character->cooldownSecondsRemaining(),
                ]),
            );
        }
    }

    /**
     * Level-based feature unlocks are enforced here so direct POST requests
     * cannot bypass hidden buttons in the dashboard.
     *
     * @param  array<string, mixed>  $payload
     */
    private function guardFeatureAvailability(GameActionType $action, GameCharacter $character, array $payload): void
    {
        if ($character->isInTutorial()) {
            return;
        }

        if ($action === GameActionType::SearchLumiis && ! $character->lumiisUnlocked()) {
            throw new GameActionException(__('Lumiis hunts unlock at level 2.'));
        }

        if ($action === GameActionType::Travel) {
            throw new GameActionException(__('Fast travel is currently disabled.'));
        }

        if ($action === GameActionType::GlobalChat && ! $character->globalChatUnlocked()) {
            throw new GameActionException(__('Global chat unlocks at level 2.'));
        }

        if ($action === GameActionType::Whisper && ! $character->whispersUnlocked()) {
            throw new GameActionException(__('Whispers unlock at level 3.'));
        }

        if (in_array($action, [
            GameActionType::SubmitQuestItem,
            GameActionType::BoostResearchSubmission,
            GameActionType::SkipResearchCooldown,
        ], true) && ! $character->biologistResearchUnlocked()) {
            throw new GameActionException(__('Biologist research unlocks at level 5.'));
        }

        if ($action !== GameActionType::StartManualFight) {
            return;
        }

        $encounterRequest = trim((string) ($payload['encounter_request'] ?? ''));

        if ($encounterRequest === 'lumiis' && ! $character->lumiisUnlocked()) {
            throw new GameActionException(__('Manual Lumiis fights unlock at level 2.'));
        }

        if ($encounterRequest === 'boss' && ! $character->bossFightsUnlocked()) {
            throw new GameActionException(__('Boss soul fights unlock at level 4.'));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function dispatchAction(
        GameActionType $action,
        GameCharacter $character,
        ?GameBattle $activeBattle,
        array $payload,
        Carbon $processedAt,
    ): array {
        return match ($action) {
            GameActionType::Hunt => $this->hunt($character, $processedAt),
            GameActionType::FightMonsters => $this->fightMonsters($character, $payload, $processedAt),
            GameActionType::SearchLumiis => $this->searchLumiis($character, $payload, $processedAt),
            GameActionType::StartManualFight => $this->startManualFight($character, $payload, $processedAt),
            GameActionType::BattleAttack => $this->battleAttack($character, $activeBattle, $payload, $processedAt),
            GameActionType::BattleUseSkill => $this->battleUseSkill($character, $activeBattle, $payload, $processedAt),
            GameActionType::AbandonManualFight => $this->abandonManualFight($character, $activeBattle, $payload, $processedAt),
            GameActionType::AllocateStatusPoint => $this->allocateStatusPoint($character, $payload),
            GameActionType::ClaimQuest => $this->claimQuest($character, $payload),
            GameActionType::Rest => $this->rest($character, $payload, $processedAt),
            GameActionType::Travel => $this->travel($character, $payload, $processedAt),
            GameActionType::SubmitQuestItem => $this->submitQuestItem($character, $payload, $processedAt),
            GameActionType::BoostResearchSubmission => $this->boostResearchSubmission($character, $payload),
            GameActionType::SkipResearchCooldown => $this->skipResearchCooldown($character, $payload, $processedAt),
            GameActionType::BuyItem => $this->buyItem($character, $payload),
            GameActionType::SellItem => $this->sellItem($character, $payload),
            GameActionType::UpgradeItem => $this->upgradeItem($character, $payload),
            GameActionType::UseItem => $this->useItem($character, $payload, $processedAt),
            GameActionType::EquipItem => $this->equipItem($character, $payload),
            GameActionType::UnequipItem => $this->unequipItem($character, $payload),
            GameActionType::MoveInventoryItem => $this->moveInventoryItem($character, $payload),
            GameActionType::AddItemBonus => $this->addItemBonus($character, $payload),
            GameActionType::RerollItemBonuses => $this->rerollItemBonuses($character, $payload),
            GameActionType::UnlockItemBonus => $this->unlockItemBonus($character, $payload),
            GameActionType::ChooseSkillGroup => $this->chooseSkillGroup($character, $payload),
            GameActionType::LearnSkill => $this->learnSkill($character, $payload),
            GameActionType::AdvanceSkillMastery => $this->advanceSkillMastery($character, $payload, $processedAt),
            GameActionType::GlobalChat => $this->sendGlobalChat($character, $payload),
            GameActionType::Whisper => $this->sendWhisper($character, $payload),
            GameActionType::TutorialNext => $this->tutorialNext($character),
            GameActionType::TutorialPrevious => $this->tutorialPrevious($character),
        };
    }

    /**
     * Post-processing after the action itself has mutated the character.
     * This is where level-ups, caps and log-facing replay metadata are normalized.
     *
     * @param  array<string, mixed>  $result
     */
    private function finalizeCharacterAfterAction(
        GameCharacter $character,
        array &$result,
        int $startingLevel,
        Carbon $processedAt,
    ): void {
        $this->applyLevelProgression($character);
        $questUpdates = $this->questProgressService->syncAfterAction($character, $processedAt);
        $this->applyLevelProgression($character);

        // Quest rewards can push the player into the next level during the same request.
        // Run one additional quest sync after that follow-up level-up so chained
        // level-based quests complete immediately instead of waiting for a second action.
        $questUpdates = $this->mergeQuestUpdates(
            $questUpdates,
            $this->questProgressService->syncAfterAction($character, $processedAt),
        );
        $this->applyLevelProgression($character);
        $this->syncResourceCaps($character);

        if ($character->level > $startingLevel) {
            $result['message'] .= ' Level '.$character->level.' reached.';
            $result['rewards']['levels_gained'] = $character->level - $startingLevel;
            $this->resetPassiveRecoveryClocks($character, $processedAt);
        }

        if (($questUpdates['messages'] ?? []) !== []) {
            $result['quest_updates'] = $questUpdates['messages'];
            $result['rewards'] = array_merge(
                $result['rewards'],
                $questUpdates['rewards'] ?? [],
            );
        }

        if (is_array($result['battle_replay'] ?? null)) {
            $result['battle_replay']['summary']['message'] = $result['message'];
        }

        $character->last_active_at = $processedAt;
        $character->version++;
        $character->save();
    }

    /**
     * @param  array{messages?: list<string>, rewards?: array<string, int>}  $base
     * @param  array{messages?: list<string>, rewards?: array<string, int>}  $append
     * @return array{messages: list<string>, rewards: array<string, int>}
     */
    private function mergeQuestUpdates(array $base, array $append): array
    {
        $rewardKeys = array_unique(array_merge(
            array_keys($base['rewards'] ?? []),
            array_keys($append['rewards'] ?? []),
        ));

        $rewards = [];

        foreach ($rewardKeys as $key) {
            $rewards[$key] = (int) ($base['rewards'][$key] ?? 0) + (int) ($append['rewards'][$key] ?? 0);
        }

        return [
            'messages' => array_values(array_merge($base['messages'] ?? [], $append['messages'] ?? [])),
            'rewards' => array_filter($rewards, static fn (int $value): bool => $value > 0),
        ];
    }

    /**
     * Keep the action log write isolated so new metadata can be added without touching the orchestration flow.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $result
     */
    private function recordActionLog(
        GameCharacter $character,
        GameActionType $action,
        array $payload,
        int $sourceLocationId,
        array $result,
        Carbon $processedAt,
    ): void {
        GameActionLog::query()->create([
            'game_character_id' => $character->id,
            'idempotency_key' => $payload['idempotency_key'],
            'action' => $action->value,
            'source_location_id' => $sourceLocationId,
            'target_location_id' => $result['target_location_id'] ?? $character->location_id,
            'request_payload' => Arr::except($payload, ['idempotency_key']),
            'outcome' => array_filter([
                'message' => $result['message'],
                'rewards' => $result['rewards'],
                'cooldown_seconds' => $result['cooldown_seconds'],
                'battle_log' => $result['battle_log'] ?? null,
                'battle_summary' => $result['battle_summary'] ?? null,
                'battle_replay' => $result['battle_replay'] ?? null,
                'rest_plan' => $result['rest_plan'] ?? null,
                'quest_updates' => $result['quest_updates'] ?? null,
            ], static fn (mixed $value): bool => $value !== null),
            'processed_at' => $processedAt,
        ]);
    }
}
