<?php

namespace App\Services\Game\Concerns;

use App\Enums\GameActionType;
use App\Enums\GameCharacterState;
use App\Exceptions\GameActionException;
use App\Models\GameBattle;
use App\Models\GameCharacter;
use App\Models\LocationArea;
use App\Support\Game\TutorialFlow;

/**
 * Tutorial flow gates and transitions.
 *
 * The tutorial must be enforced server-side. Hiding buttons alone would not be
 * enough because players can always post crafted requests directly.
 */
trait HandlesTutorialActions
{
    /**
     * Tutorial navigation is intentionally tiny: one step forward, one step back.
     *
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function tutorialNext(GameCharacter $character): array
    {
        if (! $character->isInTutorial()) {
            throw new GameActionException(__('The tutorial is already finished for this character.'));
        }

        $currentStep = TutorialFlow::currentStep($character);
        $nextStep = TutorialFlow::nextStep($currentStep);

        if ($nextStep === null) {
            $character->game_state = GameCharacterState::Active;
            $character->tutorial_step = null;

            return [
                'message' => __('The tutorial ends. The full map opens and your real journey begins.'),
                'cooldown_seconds' => 0,
                'rewards' => [],
                'target_location_id' => $character->location_id,
            ];
        }

        $character->tutorial_step = $nextStep;
        $stepDefinition = TutorialFlow::definition($nextStep);

        return [
            'message' => __('Tutorial step :step: :title', ['step' => $nextStep, 'title' => $stepDefinition['title']]),
            'cooldown_seconds' => 0,
            'rewards' => [],
            'target_location_id' => $character->location_id,
        ];
    }

    /**
     * Back-navigation is only allowed on the informational steps where no
     * battle state would become inconsistent.
     *
     * @return array{message: string, cooldown_seconds: int, rewards: array<string, int>, target_location_id: int}
     */
    private function tutorialPrevious(GameCharacter $character): array
    {
        if (! $character->isInTutorial()) {
            throw new GameActionException(__('The tutorial is already finished for this character.'));
        }

        $currentStep = TutorialFlow::currentStep($character);
        $previousStep = TutorialFlow::previousStep($currentStep);

        if ($previousStep === null) {
            throw new GameActionException(__('There is no previous tutorial step from here.'));
        }

        $character->tutorial_step = $previousStep;
        $stepDefinition = TutorialFlow::definition($previousStep);

        return [
            'message' => __('Tutorial step :step: :title', ['step' => $previousStep, 'title' => $stepDefinition['title']]),
            'cooldown_seconds' => 0,
            'rewards' => [],
            'target_location_id' => $character->location_id,
        ];
    }

    /**
     * All tutorial restrictions are checked here before the action is dispatched.
     *
     * That keeps the gameplay traits simpler because they can assume that the
     * player is at least on a legal step for the requested action.
     *
     * @param  array<string, mixed>  $payload
     */
    private function guardTutorialActionAvailability(
        GameActionType $action,
        GameCharacter $character,
        array $payload,
        ?GameBattle $activeBattle,
    ): void {
        if (! $character->isInTutorial()) {
            return;
        }

        $currentStep = TutorialFlow::currentStep($character);

        if (! TutorialFlow::actionIsAllowed($currentStep, $action)) {
            throw new GameActionException($this->tutorialBlockedActionMessage($currentStep));
        }

        if ($activeBattle !== null) {
            if (
                $currentStep === TutorialFlow::STEP_BATTLE_UI
                && $action === GameActionType::TutorialNext
            ) {
                return;
            }

            if ($action !== GameActionType::BattleAttack) {
                throw new GameActionException(__('The tutorial fight only allows normal attacks right now.'));
            }

            return;
        }

        if ($action === GameActionType::StartManualFight) {
            $this->guardTutorialManualFightStart($character, $payload);
        }

        if ($action === GameActionType::TutorialNext && $currentStep === TutorialFlow::STEP_EQUIPMENT) {
            $this->guardTutorialEquipmentReady($character);
        }
    }

    /**
     * The tutorial battle is fixed: one beginner monster pack in the Riverbank.
     *
     * @param  array<string, mixed>  $payload
     */
    private function guardTutorialManualFightStart(GameCharacter $character, array $payload): void
    {
        $this->guardTutorialEquipmentReady($character);

        $area = $this->resolveHuntingArea($character, (int) $payload['location_area_id']);

        if ($area->slug !== 'riverbank' || (string) ($payload['encounter_request'] ?? '') !== 'monster') {
            throw new GameActionException(__('The tutorial currently expects the first monster pack at Riverbank.'));
        }
    }

    private function tutorialBlockedActionMessage(int $currentStep): string
    {
        return match ($currentStep) {
            TutorialFlow::STEP_EQUIPMENT => __('Equip your starter weapon and armor before moving on.'),
            TutorialFlow::STEP_START_FIGHT => __('Use the tutorial fight button to start the first manual battle.'),
            TutorialFlow::STEP_BATTLE_UI => __('Read the fight window first, then continue to the first attack step.'),
            TutorialFlow::STEP_FIRST_ATTACK,
            TutorialFlow::STEP_FINISH_FIGHT => __('Finish the tutorial battle before using other features.'),
            default => __('Follow the tutorial step first. Other actions stay locked for now.'),
        };
    }

    private function shouldUseTutorialManualBattle(
        GameCharacter $character,
        LocationArea $area,
        string $requestedEncounter,
    ): bool {
        return $character->isInTutorial()
            && in_array(TutorialFlow::currentStep($character), [
                TutorialFlow::STEP_EQUIPMENT,
                TutorialFlow::STEP_START_FIGHT,
                TutorialFlow::STEP_BATTLE_UI,
                TutorialFlow::STEP_FIRST_ATTACK,
                TutorialFlow::STEP_FINISH_FIGHT,
            ], true)
            && $requestedEncounter === 'monster'
            && $area->slug === 'riverbank';
    }

    private function guardTutorialEquipmentReady(GameCharacter $character): void
    {
        $character->loadMissing('equipment.characterItem.item');

        $hasWeapon = $character->equipment->contains(fn ($equipment): bool => $equipment->slot === 'weapon');
        $hasArmor = $character->equipment->contains(fn ($equipment): bool => $equipment->slot === 'armor');

        if (! $hasWeapon || ! $hasArmor) {
            throw new GameActionException(__('Equip your Recruit Blade and Cotton Armor before continuing.'));
        }
    }
}
