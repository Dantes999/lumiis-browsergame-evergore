<?php

namespace App\Services\Game\Concerns;

use App\Enums\CharacterQuestStatus;
use App\Exceptions\GameActionException;
use App\Models\CharacterQuest;
use App\Models\CharacterSkill;
use App\Models\GameCharacter;
use App\Models\LocationConnection;
use App\Models\Skill;
use App\Support\Game\MetinClassCatalog;
use Illuminate\Support\Carbon;

/**
 * Non-combat character progression and movement actions.
 */
trait HandlesCharacterActions
{
    private const STATUS_POINT_ATTRIBUTES = [
        'vitality' => 'Vitality',
        'intelligence' => 'Intelligence',
        'strength' => 'Strength',
        'dexterity' => 'Dexterity',
    ];

    private const RESEARCH_CATALYST_ITEM_SLUG = 'research-catalyst';

    private const RESEARCH_REPORT_ITEM_SLUG = 'research-report';

    private const SOUL_STONE_ITEM_SLUG = 'soul-stone';

    private const RESEARCH_CATALYST_BONUS_PERCENT = 20;

    /**
     * Legacy hunt is still available for non-map combat zones.
     *
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function hunt(GameCharacter $character, Carbon $processedAt): array
    {
        if ($character->location->is_safe_zone) {
            throw new GameActionException(__('There is nothing worth hunting inside the city walls.'));
        }

        if ($character->stamina < 4) {
            throw new GameActionException(__('You need at least 4 stamina to hunt.'));
        }

        if ($character->health <= 15) {
            throw new GameActionException(__('You are too wounded to leave cover. Rest first.'));
        }

        $threat = max(1, $character->location->threat_level);
        $effectiveAttack = $character->effectiveAttack();
        $effectiveDefense = $character->effectiveDefense();
        $yangGain = 25 + ($threat * 10) + (int) floor($effectiveAttack / 2);
        $experienceGain = 16 + ($threat * 6) + (int) floor($effectiveAttack / 4);
        $damageTaken = max(4, (8 + ($threat * 4)) - (int) floor($effectiveDefense / 3));

        $character->stamina -= 4;
        $character->yang += $yangGain;
        $character->experience += $experienceGain;
        $character->health = max(1, $character->health - $damageTaken);
        $this->resetPassiveRecoveryClocks($character, $processedAt);

        $cooldownSeconds = 75;
        $character->action_available_at = $processedAt->copy()->addSeconds($cooldownSeconds);

        return [
            'message' => __('You return from the frontier with bruises, loot and fresh rumors.'),
            'cooldown_seconds' => $cooldownSeconds,
            'rewards' => [
                'stamina' => -4,
                'yang' => $yangGain,
                'experience' => $experienceGain,
                'health' => -$damageTaken,
            ],
            'target_location_id' => $character->location_id,
        ];
    }

    /**
     * Rest is a timed town action.
     *
     * We intentionally resolve the rewards at the end of the wait instead of instantly:
     * - the player cannot cancel the rest
     * - the UI can clearly show one pending package
     * - passive recovery does not double-dip during the locked period
     *
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, rest_plan: array<string, mixed>, target_location_id: int}
     */
    private function rest(GameCharacter $character, array $payload, Carbon $processedAt): array
    {
        if (! $character->location->is_safe_zone) {
            throw new GameActionException(__('Long rests are only possible inside a safe town or camp.'));
        }

        $restMinutes = (int) ($payload['rest_minutes'] ?? 30);

        $restPlan = match ($restMinutes) {
            60 => ['label' => __('Extended Rest'), 'minutes' => 60, 'health_percent' => 70, 'sp_percent' => 70, 'stamina' => 10, 'yang' => 110],
            120 => ['label' => __('Deep Rest'), 'minutes' => 120, 'health_percent' => 100, 'sp_percent' => 100, 'stamina' => 99, 'yang' => 220],
            default => ['label' => __('Short Rest'), 'minutes' => 30, 'health_percent' => 40, 'sp_percent' => 40, 'stamina' => 5, 'yang' => 50],
        };

        $healthRecovered = min(
            $character->effectiveMaxHealth() - $character->health,
            (int) floor($character->effectiveMaxHealth() * ($restPlan['health_percent'] / 100)),
        );
        $staminaRecovered = min(
            $character->effectiveMaxStamina() - $character->stamina,
            $restPlan['stamina'] === 99 ? $character->effectiveMaxStamina() : $restPlan['stamina'],
        );
        $spRecovered = min(
            $character->effectiveMaxSp() - $character->effectiveCurrentSp(),
            (int) floor($character->effectiveMaxSp() * ($restPlan['sp_percent'] / 100)),
        );
        $yangRecovered = max(0, (int) ($restPlan['yang'] ?? 0));

        $cooldownSeconds = $restPlan['minutes'] * 60;
        $restEndsAt = $processedAt->copy()->addSeconds($cooldownSeconds);
        $character->action_available_at = $restEndsAt;

        // Passive recovery should stay frozen while the character is resting because the
        // rest package already includes the stronger active-wait payoff.
        $character->stamina_updated_at = $restEndsAt->copy();
        $character->health_updated_at = $restEndsAt->copy();
        $character->sp_updated_at = $restEndsAt->copy();

        return [
            'message' => __('You settle in for a :minutes minute rest at :location. The rest cannot be cancelled and rewards arrive when the timer ends.', [
                'minutes' => $restPlan['minutes'],
                'location' => $character->location->name,
            ]),
            'cooldown_seconds' => $cooldownSeconds,
            'rewards' => [
                'health' => 0,
                'stamina' => 0,
                'sp' => 0,
                'yang' => 0,
            ],
            'rest_plan' => [
                'label' => $restPlan['label'],
                'minutes' => $restPlan['minutes'],
                'started_at' => $processedAt->toISOString(),
                'ends_at' => $restEndsAt->toISOString(),
                'health_gain' => $healthRecovered,
                'stamina_gain' => $staminaRecovered,
                'sp_gain' => $spRecovered,
                'yang_gain' => $yangRecovered,
            ],
            'target_location_id' => $character->location_id,
        ];
    }

    /**
     * Travel only works through configured graph edges.
     *
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function travel(GameCharacter $character, array $payload, Carbon $processedAt): array
    {
        $targetLocationId = (int) $payload['target_location_id'];

        /** @var LocationConnection|null $connection */
        $connection = $character->location->outgoingConnections
            ->firstWhere('to_location_id', $targetLocationId);

        if ($connection === null) {
            throw new GameActionException(__('This road is closed to your character right now.'));
        }

        if ($character->stamina < $connection->stamina_cost) {
            throw new GameActionException(__('You do not have enough stamina for that journey.'));
        }

        if ($character->level < (int) $connection->required_level) {
            throw new GameActionException(__('You need level :level before traveling there.', [
                'level' => $connection->required_level,
            ]));
        }

        $character->stamina -= $connection->stamina_cost;
        $character->location_id = $connection->to_location_id;
        $this->resetPassiveRecoveryClocks($character, $processedAt);

        $cooldownSeconds = $character->travelCooldownFor($connection->travel_seconds);
        $character->action_available_at = $processedAt->copy()->addSeconds($cooldownSeconds);

        return [
            'message' => __('You travel to :location and keep a low profile on the road.', [
                'location' => $connection->toLocation->name,
            ]),
            'cooldown_seconds' => $cooldownSeconds,
            'rewards' => [
                'stamina' => -$connection->stamina_cost,
            ],
            'target_location_id' => $connection->to_location_id,
        ];
    }

    /**
     * Skill learning stays here because it is direct character progression state.
     *
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function chooseSkillGroup(GameCharacter $character, array $payload): array
    {
        if (! $character->skillsUnlocked()) {
            throw new GameActionException(__('You can only choose a combat school after level 5.'));
        }

        if ($character->hasChosenSkillGroup()) {
            throw new GameActionException(__('Your combat school is already locked in.'));
        }

        $requestedGroup = (string) ($payload['skill_group'] ?? '');
        $race = $character->chosenRace();

        if (! MetinClassCatalog::hasSkillGroup($race, $requestedGroup)) {
            throw new GameActionException(__('That combat school does not belong to your race.'));
        }

        $character->skill_group = $requestedGroup;

        return [
            'message' => __('You commit to the :group path of the :race.', [
                'group' => MetinClassCatalog::skillGroupLabel($race, $requestedGroup),
                'race' => MetinClassCatalog::raceLabel($race),
            ]),
            'cooldown_seconds' => 0,
            'rewards' => [],
            'target_location_id' => $character->location_id,
        ];
    }

    /**
     * Status points are separate from combat skill points so the player can keep growing
     * core attributes without starving the class skill tree.
     *
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function allocateStatusPoint(GameCharacter $character, array $payload): array
    {
        if ($character->unspent_status_points < 1) {
            throw new GameActionException(__('You do not have any unspent status points.'));
        }

        $attribute = (string) ($payload['attribute'] ?? '');

        if (! array_key_exists($attribute, self::STATUS_POINT_ATTRIBUTES)) {
            throw new GameActionException(__('Choose a valid status attribute.'));
        }

        $character->{$attribute} = (int) $character->{$attribute} + 1;
        $character->unspent_status_points -= 1;

        return [
            'message' => __('You invest 1 status point into :attribute.', [
                'attribute' => __(self::STATUS_POINT_ATTRIBUTES[$attribute]),
            ]),
            'cooldown_seconds' => 0,
            'rewards' => [
                'status_points' => -1,
            ],
            'target_location_id' => $character->location_id,
        ];
    }

    /**
     * Skill learning stays here because it is direct character progression state.
     *
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function learnSkill(GameCharacter $character, array $payload): array
    {
        [$skill, $learnedSkill] = $this->resolveOwnedProgressionSkill($character, $payload);
        $normalRankCap = MetinClassCatalog::normalSkillRankCap();

        if ($character->level < $skill->required_level) {
            throw new GameActionException(__('You need level :level to learn that skill.', [
                'level' => $skill->required_level,
            ]));
        }

        if ($character->unspent_skill_points < 1) {
            throw new GameActionException(__('You do not have any unspent skill points.'));
        }

        if ($learnedSkill !== null && $learnedSkill->hasReachedPerfectMastery()) {
            throw new GameActionException(__('That skill already reached perfect mastery.'));
        }

        if ($learnedSkill !== null && $learnedSkill->needsSkillBookProgression()) {
            throw new GameActionException(__('After reaching M1, :skill advances only with the matching skill book.', [
                'skill' => $skill->name,
            ]));
        }

        if ($learnedSkill !== null && $learnedSkill->needsSoulStoneProgression()) {
            throw new GameActionException(__('After reaching G1, :skill advances only with Soul Stones.', [
                'skill' => $skill->name,
            ]));
        }

        $enteredMastery = false;

        if ($learnedSkill === null) {
            $character->learnedSkills()->create([
                'skill_id' => $skill->id,
                'rank' => 1,
            ]);
        } elseif ((int) $learnedSkill->rank < $normalRankCap) {
            $learnedSkill->increment('rank');
        } else {
            $learnedSkill->mastery_rank = max(1, (int) $learnedSkill->mastery_rank);
            $learnedSkill->mastery_progress = 0;
            $learnedSkill->save();
            $enteredMastery = true;
        }

        $character->unspent_skill_points -= 1;
        $this->reloadDerivedRelations($character);

        /** @var CharacterSkill|null $updatedSkill */
        $updatedSkill = $character->learnedSkills->firstWhere('skill_id', $skill->id);
        $rank = (int) ($updatedSkill?->rank ?? 1);

        return [
            'message' => $enteredMastery
                ? __('You deepen :skill until it awakens as :stage.', [
                    'skill' => $skill->name,
                    'stage' => $updatedSkill?->stageLabel() ?? 'M1',
                ])
                : __('You deepen :skill to rank :rank.', [
                    'skill' => $skill->name,
                    'rank' => $rank,
                ]),
            'cooldown_seconds' => 0,
            'rewards' => [
                'skill_points' => -1,
            ],
            'target_location_id' => $character->location_id,
        ];
    }

    /**
     * After the G stage begins, Soul Stones push the skill through the remaining G ranks and finally to P.
     *
     * This is intentionally triggered from the skill panel instead of the bag because the stone is
     * generic and must know which learned skill it should target.
     *
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function advanceSkillMastery(GameCharacter $character, array $payload, Carbon $processedAt): array
    {
        [$skill, $learnedSkill] = $this->resolveOwnedProgressionSkill($character, $payload, true);

        if ($learnedSkill->hasReachedPerfectMastery()) {
            throw new GameActionException(__('That skill already reached perfect mastery.'));
        }

        if (! $learnedSkill->needsSoulStoneProgression()) {
            throw new GameActionException(__('Bring :skill into the G stage with skill books before using Soul Stones.', [
                'skill' => $skill->name,
            ]));
        }

        $soulStoneStack = $this->inventoryService->stackBySlug($character, self::SOUL_STONE_ITEM_SLUG);

        if ($soulStoneStack === null || (int) $soulStoneStack->quantity < 1) {
            throw new GameActionException(__('You need a Soul Stone to advance that skill further.'));
        }

        $currentGrandRank = (int) $learnedSkill->grand_mastery_rank;
        $successChance = $learnedSkill->nextSoulStoneSuccessChance();
        $success = random_int(1, 100) <= $successChance;

        $this->inventoryService->consume($soulStoneStack, 1);

        if ($success) {
            if ($learnedSkill->hasFinishedGrandMastery()) {
                $learnedSkill->perfect_mastered_at = $processedAt;
            } else {
                $learnedSkill->grand_mastery_rank = min(10, $currentGrandRank + 1);
            }
        }

        $learnedSkill->save();
        $this->reloadDerivedRelations($character);

        /** @var CharacterSkill $updatedSkill */
        $updatedSkill = $character->learnedSkills->firstWhere('skill_id', $skill->id);

        return [
            'message' => match (true) {
                ! $success => __('The Soul Stone cracks, but :skill does not advance.', [
                    'skill' => $skill->name,
                ]),
                $updatedSkill->hasReachedPerfectMastery() => __('The Soul Stone resonates perfectly. :skill reaches :stage.', [
                    'skill' => $skill->name,
                    'stage' => $updatedSkill->stageLabel(),
                ]),
                default => __('The Soul Stone deepens :skill to :stage.', [
                    'skill' => $skill->name,
                    'stage' => $updatedSkill->stageLabel(),
                ]),
            },
            'cooldown_seconds' => 0,
            'rewards' => [
                'soul_stones' => -1,
            ],
            'target_location_id' => $character->location_id,
        ];
    }

    /**
     * Centralizes the race/school ownership rules for skill progression actions.
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: Skill, 1: CharacterSkill|null}
     */
    private function resolveOwnedProgressionSkill(GameCharacter $character, array $payload, bool $requireLearnedSkill = false): array
    {
        /** @var Skill $skill */
        $skill = Skill::query()->findOrFail((int) $payload['skill_id']);

        if (! $character->skillsUnlocked()) {
            throw new GameActionException(__('You can only learn skills after level 5.'));
        }

        if (! $character->hasChosenSkillGroup()) {
            throw new GameActionException(__('Choose one of your two combat schools before learning skills.'));
        }

        if ($skill->race === null || $skill->skill_group === null) {
            throw new GameActionException(__('That skill is no longer available.'));
        }

        if ($skill->race !== $character->chosenRace()) {
            throw new GameActionException(__('That skill belongs to another race.'));
        }

        if ($skill->skill_group !== $character->skill_group) {
            throw new GameActionException(__('That skill belongs to the other combat school of your race.'));
        }

        /** @var CharacterSkill|null $learnedSkill */
        $learnedSkill = $character->learnedSkills->firstWhere('skill_id', $skill->id);

        if ($requireLearnedSkill && $learnedSkill === null) {
            throw new GameActionException(__('Learn :skill with skill points before trying to deepen it further.', [
                'skill' => $skill->name,
            ]));
        }

        return [$skill, $learnedSkill];
    }

    /**
     * Biologist turn-ins are a town-side progression action:
     * safe zone, no combat, but still fully server-authoritative.
     *
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int, biologist_submission: array<string, mixed>}
     */
    private function submitQuestItem(GameCharacter $character, array $payload, Carbon $processedAt): array
    {
        [, $characterQuest] = $this->resolveBiologistQuestActionContext($character, $payload);

        $submission = $this->questProgressService->submitBiologistResearch($character, $characterQuest, $processedAt);

        return [
            'message' => $submission['message'],
            'cooldown_seconds' => 0,
            'rewards' => [],
            'target_location_id' => $character->location_id,
            'biologist_submission' => $submission,
        ];
    }

    /**
     * Quest objectives can finish during combat or gathering, but the player now explicitly
     * decides when to collect the payout and unlock the next quest step.
     *
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function claimQuest(GameCharacter $character, array $payload): array
    {
        /** @var CharacterQuest|null $characterQuest */
        $characterQuest = CharacterQuest::query()
            ->where('game_character_id', $character->id)
            ->with('quest.location')
            ->lockForUpdate()
            ->find((int) $payload['character_quest_id']);

        if ($characterQuest === null) {
            throw new GameActionException(__('That quest does not belong to your character.'));
        }

        $claim = $this->questProgressService->claimQuestReward($character, $characterQuest);

        return [
            'message' => $claim['message'],
            'cooldown_seconds' => 0,
            'rewards' => $claim['rewards'],
            'target_location_id' => $character->location_id,
        ];
    }

    /**
     * Research catalysts make one risky Biologist turn-in safer.
     *
     * The boost is stored on the active CharacterQuest row, so it survives a refresh but cannot
     * be moved to another player's quest or stacked indefinitely.
     *
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int, biologist_support: array<string, mixed>}
     */
    private function boostResearchSubmission(GameCharacter $character, array $payload): array
    {
        [, $characterQuest] = $this->resolveBiologistQuestActionContext($character, $payload);
        $supportItem = $this->characterItem($character, (int) $payload['character_item_id']);

        if ($supportItem->item?->slug !== self::RESEARCH_CATALYST_ITEM_SLUG || ! $supportItem->item->isConsumable()) {
            throw new GameActionException(__('Choose a valid research catalyst from your inventory.'));
        }

        if ((int) $characterQuest->submission_bonus_percent > 0) {
            throw new GameActionException(__('This research order is already prepared for the next sample.'));
        }

        $baseRate = (int) ($characterQuest->quest?->objective_submission_success_rate ?? 100);
        $bonusPercent = $baseRate >= 100 ? 0 : self::RESEARCH_CATALYST_BONUS_PERCENT;

        if ($bonusPercent <= 0) {
            throw new GameActionException(__('This research order already has a perfect success chance.'));
        }

        $this->inventoryService->consume($supportItem, 1);
        $characterQuest->submission_bonus_percent = $bonusPercent;
        $characterQuest->save();

        return [
            'message' => __('You stabilize the next Biologist sample with a Research Catalyst. Success chance rises to :rate%.', [
                'rate' => $characterQuest->effectiveSubmissionSuccessRate($baseRate),
            ]),
            'cooldown_seconds' => 0,
            'rewards' => [],
            'target_location_id' => $character->location_id,
            'biologist_support' => [
                'bonus_percent' => $bonusPercent,
            ],
        ];
    }

    /**
     * Research reports skip the current waiting period for the active stage.
     *
     * This keeps the cooldown bypass explicit and limited to the currently focused quest row.
     *
     * @param  array<string, mixed>  $payload
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int, biologist_support: array<string, mixed>}
     */
    private function skipResearchCooldown(GameCharacter $character, array $payload, Carbon $processedAt): array
    {
        [, $characterQuest] = $this->resolveBiologistQuestActionContext($character, $payload);
        $supportItem = $this->characterItem($character, (int) $payload['character_item_id']);

        if ($supportItem->item?->slug !== self::RESEARCH_REPORT_ITEM_SLUG || ! $supportItem->item->isConsumable()) {
            throw new GameActionException(__('Choose a valid research report from your inventory.'));
        }

        if (! $characterQuest->next_submission_at?->isFuture()) {
            throw new GameActionException(__('This research order is already ready for the next sample.'));
        }

        $minutesSkipped = max(1, (int) ceil($processedAt->diffInSeconds($characterQuest->next_submission_at) / 60));

        $this->inventoryService->consume($supportItem, 1);
        $characterQuest->next_submission_at = null;
        $characterQuest->save();

        return [
            'message' => __('You hand over a Research Report. The Biologist clears the waiting time and is ready again now. (:minutes min. skipped)', [
                'minutes' => $minutesSkipped,
            ]),
            'cooldown_seconds' => 0,
            'rewards' => [],
            'target_location_id' => $character->location_id,
            'biologist_support' => [
                'minutes_skipped' => $minutesSkipped,
            ],
        ];
    }

    /**
     * Shared lookup for all Biologist actions.
     *
     * Keeping the town/ownership checks here avoids three near-identical action methods that
     * could drift apart and accidentally allow cross-town or foreign-quest mutations.
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: \App\Models\LocationArea, 1: \App\Models\CharacterQuest}
     */
    private function resolveBiologistQuestActionContext(GameCharacter $character, array $payload): array
    {
        $serviceArea = $this->serviceArea(
            $character,
            (int) $payload['location_area_id'],
            ['biologist'],
            __('Choose a valid Biologist hotspot.'),
        );

        /** @var CharacterQuest|null $characterQuest */
        $characterQuest = CharacterQuest::query()
            ->where('game_character_id', $character->id)
            ->with('quest.location')
            ->lockForUpdate()
            ->find((int) $payload['character_quest_id']);

        if ($characterQuest === null) {
            throw new GameActionException(__('That research order does not belong to your character.'));
        }

        if (
            $characterQuest->quest?->location_id !== null
            && (int) $characterQuest->quest->location_id !== (int) $serviceArea->location_id
        ) {
            throw new GameActionException(__('This research order belongs to another town.'));
        }

        if (! $characterQuest->quest?->requiresBiologistTurnIn() || $characterQuest->status !== CharacterQuestStatus::Active) {
            throw new GameActionException(__('Only an active Biologist research order can be managed here.'));
        }

        return [$serviceArea, $characterQuest];
    }
}
