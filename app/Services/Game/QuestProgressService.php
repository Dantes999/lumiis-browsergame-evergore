<?php

namespace App\Services\Game;

use App\Enums\CharacterQuestStatus;
use App\Exceptions\GameActionException;
use App\Models\CharacterAreaProgress;
use App\Models\CharacterQuest;
use App\Models\GameCharacter;
use App\Models\Item;
use App\Models\Quest;
use Illuminate\Support\Carbon;
use App\Support\Game\MetinClassCatalog;
use Illuminate\Support\Str;

/**
 * Small quest/story progression engine.
 *
 * The rules are intentionally conservative:
 * - objectives are derived from existing persistent game state
 * - rewards are granted only when the player manually claims a finished quest
 * - only one source of truth calculates progress
 *
 * That keeps the system easy to maintain and hard to exploit.
 */
class QuestProgressService
{
    public function __construct(
        private readonly CharacterInventoryService $inventoryService,
    ) {}

    /**
     * Ensure quest rows exist once the character is eligible for them.
     *
     * This method is safe to run on both action writes and dashboard reads
     * because it only creates missing active quest rows.
     */
    public function ensureAvailableQuests(GameCharacter $character, ?Carbon $moment = null): void
    {
        $resolvedMoment = $moment ?? now();
        $character->loadMissing(['characterQuests.quest', 'location']);

        $existingQuestIds = $character->characterQuests->pluck('quest_id')->all();
        $completedQuestSlugs = $character->characterQuests
            ->filter(fn (CharacterQuest $characterQuest): bool => $characterQuest->status === CharacterQuestStatus::Completed)
            ->map(fn (CharacterQuest $characterQuest): ?string => $characterQuest->quest?->slug)
            ->filter()
            ->all();

        $quests = Quest::query()
            ->where('is_active', true)
            ->where(function ($query) use ($character): void {
                $query
                    ->whereNull('location_id')
                    ->orWhere('location_id', $character->location_id);
            })
            ->orderBy('sort_order')
            ->get();

        foreach ($quests as $quest) {
            if (in_array($quest->id, $existingQuestIds, true)) {
                continue;
            }

            if ($character->level < $quest->required_level) {
                continue;
            }

            if ($quest->prerequisite_slug !== null && ! in_array($quest->prerequisite_slug, $completedQuestSlugs, true)) {
                continue;
            }

            $characterQuest = CharacterQuest::query()->create([
                'game_character_id' => $character->id,
                'quest_id' => $quest->id,
                'status' => CharacterQuestStatus::Active,
                'progress' => min($quest->objective_target, $this->currentProgressFor($character, $quest, null)),
                'started_at' => $resolvedMoment,
            ]);

            $character->setRelation(
                'characterQuests',
                $character->characterQuests->push($characterQuest->setRelation('quest', $quest)),
            );

            $existingQuestIds[] = $quest->id;
        }
    }

    /**
     * Reconcile active quests after one action has changed player state.
     *
     * @return array{messages: list<string>, rewards: array<string, int>}
     */
    public function syncAfterAction(GameCharacter $character, ?Carbon $moment = null): array
    {
        $resolvedMoment = $moment ?? now();
        $messages = [];

        // Refresh these relations on every sync pass.
        // Action handlers may update quest rows or area progress through separate
        // locked queries, and relying on already-loaded relations here can leave
        // quest completion one request behind.
        $character->load([
            'areaProgress.locationArea',
            'characterQuests.quest.location',
        ]);

        $this->ensureAvailableQuests($character, $resolvedMoment);

        foreach ($character->characterQuests as $characterQuest) {
            $quest = $characterQuest->quest;

            if ($quest === null || $characterQuest->status !== CharacterQuestStatus::Active) {
                continue;
            }

            $progress = min($quest->objective_target, $this->currentProgressFor($character, $quest, $characterQuest));

            if ($progress !== $characterQuest->progress) {
                $characterQuest->progress = $progress;
                $characterQuest->save();
            }

            if ($progress < $quest->objective_target) {
                continue;
            }

            $characterQuest->status = CharacterQuestStatus::ReadyToClaim;
            $characterQuest->completed_at = $characterQuest->completed_at ?? $resolvedMoment;
            $characterQuest->save();

            $messages[] = __('Quest ready: :title. Claim it in the quest log.', [
                'title' => $quest->translated('title'),
            ]);
        }

        return [
            'messages' => $messages,
            'rewards' => [],
        ];
    }

    /**
     * @return array{message: string, rewards: array<string, int>}
     */
    public function claimQuestReward(GameCharacter $character, CharacterQuest $characterQuest): array
    {
        $characterQuest->loadMissing('quest.location');

        $quest = $characterQuest->quest;

        if ($quest === null || $characterQuest->status !== CharacterQuestStatus::ReadyToClaim) {
            throw new GameActionException(__('This quest is not ready to be claimed yet.'));
        }

        $rewardItem = $this->resolveQuestRewardItem($character, $quest, false);

        if ($quest->reward_item_slug !== null && $rewardItem === null) {
            throw new GameActionException(__('Choose a combat school before claiming a matching skill book reward.'));
        }

        $characterQuest->status = CharacterQuestStatus::Completed;
        $characterQuest->save();

        $character->experience += (int) $quest->reward_experience;
        $character->yang += (int) $quest->reward_yang;

        $rewardSummary = [
            'experience' => (int) $quest->reward_experience,
            'yang' => (int) $quest->reward_yang,
            'quests_completed' => 1,
        ];

        if ($rewardItem !== null) {
            $this->inventoryService->grant($character, $rewardItem['slug'], (int) $rewardItem['quantity']);
            $rewardSummary['items_found'] = (int) $rewardItem['quantity'];
        }

        if ($quest->reward_bonus_type !== null && $quest->reward_bonus_value > 0) {
            $rewardSummary['permanent_bonuses'] = 1;
        }

        $this->applyRewardBonus($character, $quest);

        return [
            'message' => $this->completionMessage($character, $quest),
            'rewards' => array_filter($rewardSummary, static fn (int $value): bool => $value > 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardData(GameCharacter $character): array
    {
        $this->ensureAvailableQuests($character);
        $character->loadMissing(['characterQuests.quest.location', 'location']);

        $locationQuests = Quest::query()
            ->where('is_active', true)
            ->where('location_id', $character->location_id)
            ->orderBy('sort_order')
            ->get();
        $characterQuestByQuestId = $character->characterQuests->keyBy('quest_id');

        $active = [];
        $readyToClaim = [];
        $completed = [];
        $upcoming = [];

        foreach ($locationQuests as $quest) {
            /** @var CharacterQuest|null $characterQuest */
            $characterQuest = $characterQuestByQuestId->get($quest->id);
            $progress = $characterQuest === null
                ? min($quest->objective_target, $this->currentProgressFor($character, $quest, null))
                : $this->dashboardProgressFor($character, $quest, $characterQuest);
            $payload = [
                'title' => $quest->translated('title'),
                'story_text' => $quest->translated('story_text'),
                'description' => $quest->translated('description'),
                'feature_unlock_hint' => $quest->translated('feature_unlock_hint'),
                'objective_target' => $quest->objective_target,
                'progress' => $progress,
                'progress_percent' => min(100, (int) round(($progress / max(1, $quest->objective_target)) * 100)),
                'status' => $characterQuest?->status?->value ?? 'upcoming',
                'required_level' => $quest->required_level,
                'character_quest_id' => $characterQuest?->id,
                'reward_experience' => (int) $quest->reward_experience,
                'reward_yang' => (int) $quest->reward_yang,
                'reward_item_label' => $this->rewardItemLabelForQuest($character, $quest),
                'reward_item_quantity' => (int) $quest->reward_item_quantity,
                'reward_bonus_description' => Quest::rewardBonusDescription($quest->reward_bonus_type, (int) $quest->reward_bonus_value),
                'completed_at_label' => $characterQuest?->completed_at?->format('d.m.Y H:i'),
            ];

            if ($characterQuest === null) {
                $upcoming[] = $payload;

                continue;
            }

            if ($characterQuest->status === CharacterQuestStatus::Completed) {
                $completed[] = $payload;

                continue;
            }

            if ($characterQuest->status === CharacterQuestStatus::ReadyToClaim) {
                $readyToClaim[] = $payload;

                continue;
            }

            $active[] = $payload;
        }

        return [
            'title' => __(':location Questline', ['location' => $character->location->name]),
            'active' => $active,
            'ready_to_claim' => $readyToClaim,
            'completed' => $completed,
            'upcoming' => $upcoming,
            'ready_count' => count($readyToClaim),
        ];
    }

    /**
     * Build the Biologist panel read model for the current town.
     *
     * This keeps the Blade template simple and makes item turn-in rules readable in one place.
     *
     * @return array<string, mixed>|null
     */
    public function biologistPanelData(GameCharacter $character, ?Carbon $moment = null): ?array
    {
        $resolvedMoment = $moment ?? now();

        $this->ensureAvailableQuests($character, $resolvedMoment);
        $character->loadMissing(['characterQuests.quest.location', 'inventoryItems.item']);

        $quests = Quest::query()
            ->where('is_active', true)
            ->where('objective_type', 'deliver_item_to_biologist')
            ->where(function ($query) use ($character): void {
                $query
                    ->whereNull('location_id')
                    ->orWhere('location_id', $character->location_id);
            })
            ->orderBy('sort_order')
            ->get();

        if ($quests->isEmpty()) {
            return null;
        }

        $characterQuestByQuestId = $character->characterQuests->keyBy('quest_id');
        $activeQuest = null;
        $upcomingQuest = null;
        $latestCompletedQuest = null;
        $completedCount = 0;
        $completedQuests = [];
        $completedBonusSummary = [];
        $totalStages = $quests->count();
        $completedQuestSlugs = $character->characterQuests
            ->filter(fn (CharacterQuest $entry): bool => $entry->status === CharacterQuestStatus::Completed)
            ->map(fn (CharacterQuest $entry): ?string => $entry->quest?->slug)
            ->filter()
            ->values()
            ->all();
        $prerequisiteQuests = Quest::query()
            ->whereIn('slug', $quests->pluck('prerequisite_slug')->filter()->values()->all())
            ->get()
            ->keyBy('slug');
        $itemNameMap = Item::query()
            ->whereIn('slug', $quests->pluck('objective_item_slug')->filter()->all())
            ->pluck('name', 'slug');

        foreach ($quests->values() as $index => $quest) {
            $stageNumber = $index + 1;
            /** @var CharacterQuest|null $characterQuest */
            $characterQuest = $characterQuestByQuestId->get($quest->id);

            if ($characterQuest?->status === CharacterQuestStatus::Completed) {
                $completedCount++;
                $latestCompletedQuest = $this->biologistQuestPayload(
                    $character,
                    $quest,
                    $characterQuest,
                    $resolvedMoment,
                    $itemNameMap,
                    $stageNumber,
                    $totalStages,
                    $completedQuestSlugs,
                    $prerequisiteQuests->all(),
                );
                $completedQuests[] = $latestCompletedQuest;
                $bonusType = $quest->reward_bonus_type;
                $bonusValue = (int) $quest->reward_bonus_value;

                if ($bonusType !== null && $bonusValue > 0) {
                    $entry = $completedBonusSummary[$bonusType] ?? [
                        'value' => 0,
                        'sort' => $stageNumber,
                    ];
                    $entry['value'] += $bonusValue;
                    $entry['sort'] = min((int) $entry['sort'], $stageNumber);
                    $completedBonusSummary[$bonusType] = $entry;
                }

                continue;
            }

            if ($characterQuest?->status === CharacterQuestStatus::ReadyToClaim && $activeQuest === null) {
                $activeQuest = $this->biologistQuestPayload(
                    $character,
                    $quest,
                    $characterQuest,
                    $resolvedMoment,
                    $itemNameMap,
                    $stageNumber,
                    $totalStages,
                    $completedQuestSlugs,
                    $prerequisiteQuests->all(),
                );

                continue;
            }

            if ($characterQuest?->status === CharacterQuestStatus::Active && $activeQuest === null) {
                $activeQuest = $this->biologistQuestPayload(
                    $character,
                    $quest,
                    $characterQuest,
                    $resolvedMoment,
                    $itemNameMap,
                    $stageNumber,
                    $totalStages,
                    $completedQuestSlugs,
                    $prerequisiteQuests->all(),
                );

                continue;
            }

            if ($characterQuest === null && $upcomingQuest === null) {
                $upcomingQuest = $this->biologistQuestPayload(
                    $character,
                    $quest,
                    null,
                    $resolvedMoment,
                    $itemNameMap,
                    $stageNumber,
                    $totalStages,
                    $completedQuestSlugs,
                    $prerequisiteQuests->all(),
                );
            }
        }

        return [
            'active' => $activeQuest,
            'upcoming' => $upcomingQuest,
            'completed' => $completedQuests,
            'latest_completed' => $latestCompletedQuest,
            'completed_count' => $completedCount,
            'total_count' => $totalStages,
            'bonus_summary' => collect($completedBonusSummary)
                ->sortBy('sort')
                ->map(fn (array $entry, string $bonusType): ?string => Quest::rewardBonusDescription(
                    $bonusType,
                    (int) ($entry['value'] ?? 0),
                ))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /**
     * Turn in one Biologist sample packet and persist the quest-local cooldown/progress.
     *
     * Quest completion itself still flows through syncAfterAction(), so rewards stay centralized.
     *
     * @return array{message: string, progress: int, target: int, cooldown_seconds: int, success: bool}
     */
    public function submitBiologistResearch(
        GameCharacter $character,
        CharacterQuest $characterQuest,
        Carbon $moment,
    ): array {
        $characterQuest->loadMissing('quest.location');

        $quest = $characterQuest->quest;

        if ($quest === null || ! $quest->requiresBiologistTurnIn() || $characterQuest->status !== CharacterQuestStatus::Active) {
            throw new GameActionException(__('Only an active Biologist research order can be submitted here.'));
        }

        if ($characterQuest->next_submission_at?->isFuture()) {
            $remainingMinutes = max(1, (int) ceil($moment->diffInSeconds($characterQuest->next_submission_at) / 60));

            throw new GameActionException(__('The Biologist is still examining your last sample. Come back in :minutes min.', [
                'minutes' => $remainingMinutes,
            ]));
        }

        $requiredItemSlug = $quest->objective_item_slug;

        if ($requiredItemSlug === null) {
            throw new GameActionException(__('This research order is missing its required sample item.'));
        }

        $requiredItemQuantity = max(1, (int) $quest->objective_item_quantity);
        $ownedQuantity = $this->inventoryService->quantityForSlug($character, $requiredItemSlug);

        if ($ownedQuantity < $requiredItemQuantity) {
            $itemLabel = Item::query()->where('slug', $requiredItemSlug)->value('name') ?? $requiredItemSlug;

            throw new GameActionException(__('You need :count x :item before the Biologist can continue.', [
                'count' => $requiredItemQuantity,
                'item' => $itemLabel,
            ]));
        }

        $this->inventoryService->consumeBySlug($character, $requiredItemSlug, $requiredItemQuantity);

        $baseSuccessRate = min(100, max(0, (int) ($quest->objective_submission_success_rate ?? 100)));
        $effectiveSuccessRate = $characterQuest->effectiveSubmissionSuccessRate($baseSuccessRate);
        $submissionSucceeded = $this->submissionRollSucceeds($effectiveSuccessRate);
        $newProgress = $submissionSucceeded
            ? min((int) $quest->objective_target, (int) $characterQuest->progress + 1)
            : (int) $characterQuest->progress;
        $cooldownMinutes = max(0, (int) $quest->objective_submission_cooldown_minutes);
        $characterQuest->progress = $newProgress;
        $characterQuest->submission_bonus_percent = 0;
        $characterQuest->next_submission_at = $newProgress >= (int) $quest->objective_target || $cooldownMinutes === 0
            ? null
            : $moment->copy()->addMinutes($cooldownMinutes);
        $characterQuest->save();

        $itemLabel = Item::query()->where('slug', $requiredItemSlug)->value('name') ?? $requiredItemSlug;

        return [
            'message' => $submissionSucceeded
                ? __('You deliver :count x :item to the Biologist. Research progress: :progress/:target.', [
                    'count' => $requiredItemQuantity,
                    'item' => $itemLabel,
                    'progress' => $newProgress,
                    'target' => $quest->objective_target,
                ])
                : __('You deliver :count x :item, but the sample breaks down during examination. Research remains at :progress/:target.', [
                    'count' => $requiredItemQuantity,
                    'item' => $itemLabel,
                    'progress' => $newProgress,
                    'target' => $quest->objective_target,
                ]),
            'progress' => $newProgress,
            'target' => (int) $quest->objective_target,
            'cooldown_seconds' => $characterQuest->next_submission_at?->diffInSeconds($moment) ?? 0,
            'success' => $submissionSucceeded,
            'base_success_rate' => $baseSuccessRate,
            'effective_success_rate' => $effectiveSuccessRate,
        ];
    }

    private function currentProgressFor(GameCharacter $character, Quest $quest, ?CharacterQuest $characterQuest): int
    {
        return match ($quest->objective_type) {
            'reach_level' => $character->level,
            'monster_fights_in_area' => $this->areaCounter($character, $quest->objective_area_slug, 'monster_fights'),
            'lumiis_searches_in_area' => $this->areaCounter($character, $quest->objective_area_slug, 'lumiis_searches'),
            'boss_fights_in_area' => $this->areaCounter($character, $quest->objective_area_slug, 'boss_fights'),
            'deliver_item_to_biologist' => (int) ($characterQuest?->progress ?? 0),
            default => 0,
        };
    }

    private function dashboardProgressFor(GameCharacter $character, Quest $quest, CharacterQuest $characterQuest): int
    {
        if ($characterQuest->status === CharacterQuestStatus::Completed) {
            return (int) $quest->objective_target;
        }

        return min($quest->objective_target, $this->currentProgressFor($character, $quest, $characterQuest));
    }

    private function areaCounter(GameCharacter $character, ?string $areaSlug, string $column): int
    {
        if ($areaSlug === null) {
            return 0;
        }

        /** @var CharacterAreaProgress|null $progress */
        $progress = $character->areaProgress
            ->first(function (CharacterAreaProgress $entry) use ($areaSlug): bool {
                return $entry->locationArea?->slug === $areaSlug;
            });

        return (int) ($progress?->{$column} ?? 0);
    }

    private function completionMessage(GameCharacter $character, Quest $quest): string
    {
        $message = __('Quest complete: :title. +:experience EXP, +:yang Yang', [
            'title' => $quest->translated('title'),
            'experience' => $quest->reward_experience,
            'yang' => $quest->reward_yang,
        ]);

        if ($rewardItem = $this->resolveQuestRewardItem($character, $quest)) {
            $message .= ', '.$rewardItem['label'].($rewardItem['quantity'] > 1 ? ' x'.$rewardItem['quantity'] : '');
        }

        if ($bonusDescription = Quest::rewardBonusDescription($quest->reward_bonus_type, (int) $quest->reward_bonus_value)) {
            $message .= ', '.$bonusDescription;
        }

        return $message.'.';
    }

    private function applyRewardBonus(GameCharacter $character, Quest $quest): void
    {
        $bonusType = $quest->reward_bonus_type;
        $bonusValue = (int) $quest->reward_bonus_value;

        if ($bonusType === null || $bonusValue < 1) {
            return;
        }

        $column = Quest::rewardBonusColumns()[$bonusType] ?? null;

        if ($column === null) {
            return;
        }

        $character->{$column} = (int) $character->{$column} + $bonusValue;
    }

    /**
     * Biologist turn-ins intentionally use a simple server-side roll.
     * Tests can make this deterministic by setting the quest rate to 0 or 100.
     */
    private function submissionRollSucceeds(int $successRate): bool
    {
        if ($successRate >= 100) {
            return true;
        }

        if ($successRate <= 0) {
            return false;
        }

        return random_int(1, 100) <= $successRate;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, string>  $itemNameMap
     * @param  list<string>  $completedQuestSlugs
     * @param  array<string, Quest>  $prerequisiteQuestMap
     * @return array<string, mixed>
     */
    private function biologistQuestPayload(
        GameCharacter $character,
        Quest $quest,
        ?CharacterQuest $characterQuest,
        Carbon $moment,
        $itemNameMap,
        int $stageNumber,
        int $totalStages,
        array $completedQuestSlugs,
        array $prerequisiteQuestMap,
    ): array {
        $requiredItemSlug = $quest->objective_item_slug;
        $requiredItemQuantity = max(1, (int) $quest->objective_item_quantity);
        $ownedQuantity = $requiredItemSlug === null
            ? 0
            : $this->inventoryService->quantityForSlug($character, $requiredItemSlug);
        $cooldownSeconds = $characterQuest?->next_submission_at?->isFuture()
            ? $moment->diffInSeconds($characterQuest->next_submission_at)
            : 0;
        $rewardItemLabel = $this->rewardItemLabelForQuest($character, $quest);
        $baseSuccessRate = min(100, max(0, (int) ($quest->objective_submission_success_rate ?? 100)));
        $pendingBonusPercent = (int) ($characterQuest?->submission_bonus_percent ?? 0);
        $effectiveSuccessRate = $characterQuest?->effectiveSubmissionSuccessRate($baseSuccessRate) ?? $baseSuccessRate;
        $progress = (int) ($characterQuest?->progress ?? 0);
        $target = (int) $quest->objective_target;
        $missingQuantity = max(0, $requiredItemQuantity - $ownedQuantity);
        $canSubmit = $characterQuest?->status === CharacterQuestStatus::Active
            && $requiredItemSlug !== null
            && $cooldownSeconds === 0
            && $ownedQuantity >= $requiredItemQuantity;
        $lockReason = null;

        if ($characterQuest === null) {
            if ($character->level < (int) $quest->required_level) {
                $lockReason = __('Reach level :level first.', [
                    'level' => $quest->required_level,
                ]);
            } elseif (
                $quest->prerequisite_slug !== null
                && ! in_array($quest->prerequisite_slug, $completedQuestSlugs, true)
            ) {
                $prerequisiteQuest = $prerequisiteQuestMap[$quest->prerequisite_slug] ?? null;
                $prerequisiteTitle = $prerequisiteQuest?->translated('title')
                    ?? Str::headline((string) $quest->prerequisite_slug);
                $lockReason = __('Finish :quest first.', [
                    'quest' => $prerequisiteTitle,
                ]);
            }
        }

        return [
            'character_quest_id' => $characterQuest?->id,
            'title' => $quest->translated('title'),
            'story_text' => $quest->translated('story_text'),
            'description' => $quest->translated('description'),
            'stage_number' => $stageNumber,
            'total_stages' => $totalStages,
            'status' => $characterQuest?->status?->value ?? 'upcoming',
            'progress' => $progress,
            'objective_target' => $target,
            'submissions_remaining' => max(0, $target - $progress),
            'required_level' => (int) $quest->required_level,
            'objective_item_slug' => $requiredItemSlug,
            'objective_item_label' => $requiredItemSlug === null
                ? null
                : ($itemNameMap[$requiredItemSlug] ?? $requiredItemSlug),
            'objective_item_quantity' => $requiredItemQuantity,
            'owned_quantity' => $ownedQuantity,
            'missing_quantity' => $missingQuantity,
            'cooldown_seconds' => $cooldownSeconds,
            'submission_success_rate' => $effectiveSuccessRate,
            'submission_success_rate_base' => $baseSuccessRate,
            'submission_success_bonus_percent' => $pendingBonusPercent,
            'reward_bonus_description' => Quest::rewardBonusDescription($quest->reward_bonus_type, (int) $quest->reward_bonus_value),
            'reward_experience' => (int) $quest->reward_experience,
            'reward_yang' => (int) $quest->reward_yang,
            'reward_item_label' => $rewardItemLabel,
            'reward_item_quantity' => (int) $quest->reward_item_quantity,
            'completed_at_label' => $characterQuest?->completed_at?->format('d.m.Y H:i'),
            'can_submit' => $canSubmit,
            'ready_to_submit' => $canSubmit,
            'can_claim' => $characterQuest?->status === CharacterQuestStatus::ReadyToClaim,
            'lock_reason' => $lockReason,
        ];
    }

    private function rewardItemLabelForQuest(GameCharacter $character, Quest $quest): ?string
    {
        $rewardItem = $this->resolveQuestRewardItem($character, $quest);

        return $rewardItem['label'] ?? null;
    }

    /**
     * @return array{slug: string, label: string, quantity: int}|null
     */
    private function resolveQuestRewardItem(GameCharacter $character, Quest $quest, bool $allowGenericMatch = true): ?array
    {
        if ($quest->reward_item_slug === null) {
            return null;
        }

        if ($quest->reward_item_slug === MetinClassCatalog::matchingSkillBookRewardToken()) {
            $resolvedSlug = MetinClassCatalog::preferredSkillBookSlugForCharacter($character);

            if ($resolvedSlug === null) {
                return $allowGenericMatch
                    ? [
                        'slug' => MetinClassCatalog::matchingSkillBookRewardToken(),
                        'label' => __('Matching Skill Book'),
                        'quantity' => max(1, (int) $quest->reward_item_quantity),
                    ]
                    : null;
            }

            $item = Item::query()->where('slug', $resolvedSlug)->first();

            return [
                'slug' => $resolvedSlug,
                'label' => $item?->name ?? __('Matching Skill Book'),
                'quantity' => max(1, (int) $quest->reward_item_quantity),
            ];
        }

        $item = Item::query()->where('slug', $quest->reward_item_slug)->first();

        return [
            'slug' => $quest->reward_item_slug,
            'label' => $item?->name ?? $quest->reward_item_slug,
            'quantity' => max(1, (int) $quest->reward_item_quantity),
        ];
    }
}
