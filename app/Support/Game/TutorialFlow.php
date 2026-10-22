<?php

namespace App\Support\Game;

use App\Enums\GameActionType;
use App\Models\GameCharacter;
use App\Models\LocationArea;

/**
 * Single source of truth for the forced onboarding flow.
 *
 * Keeping the tutorial definition in one small class makes it much easier to
 * understand three separate concerns at once:
 * - which step the player is currently on
 * - which hotspots and features should be visible
 * - which actions are legally allowed at that step
 */
class TutorialFlow
{
    public const STEP_INTRO = 1;

    public const STEP_MAP_OVERVIEW = 2;

    public const STEP_GENERAL_STORE = 3;

    public const STEP_AREA_OVERVIEW = 4;

    public const STEP_HUNTING_AREA = 5;

    public const STEP_EQUIPMENT = 6;

    public const STEP_START_FIGHT = 7;

    public const STEP_BATTLE_UI = 8;

    public const STEP_FIRST_ATTACK = 9;

    public const STEP_FINISH_FIGHT = 10;

    public const STEP_REWARD_EXPLAINED = 11;

    public const STEP_RESULT_EXPLAINED = 12;

    public const FINAL_STEP = self::STEP_RESULT_EXPLAINED;

    public static function enabled(): bool
    {
        return (bool) config('game_tutorial.enabled', false);
    }

    /**
     * Tutorial step values can be edited by admins, so we normalize them before
     * every comparison.
     */
    public static function normalizeStep(?int $step): int
    {
        return max(self::STEP_INTRO, min(self::FINAL_STEP, (int) ($step ?? self::STEP_INTRO)));
    }

    public static function currentStep(GameCharacter $character): int
    {
        return self::normalizeStep($character->tutorial_step);
    }

    /**
     * @return array<string, mixed>
     */
    public static function definition(int $step): array
    {
        $step = self::normalizeStep($step);
        $definition = config('game_tutorial.steps.'.$step, []);
        $translatedCopy = trans('game.tutorial.steps.'.$step);

        return [
            'title' => (string) data_get($translatedCopy, 'title', data_get($definition, 'title', 'Tutorial')),
            'body' => (string) data_get($translatedCopy, 'body', data_get($definition, 'body', 'Follow the guided steps to learn the game flow.')),
            'highlight' => data_get($definition, 'highlight'),
            'next_label' => data_get($translatedCopy, 'next_label', data_get($definition, 'next_label')),
            'previous_label' => data_get($translatedCopy, 'previous_label', data_get($definition, 'previous_label')),
            'forced_focus_slug' => data_get($definition, 'forced_focus_slug'),
            'visible_area_slugs' => data_get($definition, 'visible_area_slugs', ['inn']),
        ];
    }

    public static function forcedFocusSlug(int $step): ?string
    {
        return data_get(self::definition($step), 'forced_focus_slug', 'inn');
    }

    /**
     * The tutorial only reveals a tiny slice of the map at a time.
     *
     * @return list<string>
     */
    public static function visibleAreaSlugs(int $step): array
    {
        return collect(data_get(self::definition($step), 'visible_area_slugs', ['inn']))
            ->filter(fn (mixed $slug): bool => is_string($slug) && $slug !== '')
            ->values()
            ->all();
    }

    /**
     * Feature visibility lives here so the controller and Blade template can
     * both ask the same question without duplicating the rules.
     */
    public static function areaIsVisible(GameCharacter $character, LocationArea $area): bool
    {
        if ($character->isInTutorial()) {
            return in_array($area->slug, self::visibleAreaSlugs(self::currentStep($character)), true);
        }

        return FeatureUnlocks::areaIsVisible($character, $area);
    }

    /**
     * The tutorial intentionally allows only a very small set of actions.
     */
    public static function actionIsAllowed(int $step, GameActionType $action): bool
    {
        return match (self::normalizeStep($step)) {
            self::STEP_INTRO,
            self::STEP_MAP_OVERVIEW,
            self::STEP_GENERAL_STORE,
            self::STEP_AREA_OVERVIEW,
            self::STEP_HUNTING_AREA,
            self::STEP_REWARD_EXPLAINED,
            self::STEP_RESULT_EXPLAINED => in_array($action, [GameActionType::TutorialNext, GameActionType::TutorialPrevious], true),
            self::STEP_EQUIPMENT => in_array($action, [GameActionType::TutorialNext, GameActionType::TutorialPrevious, GameActionType::EquipItem], true),
            self::STEP_BATTLE_UI => $action === GameActionType::TutorialNext,
            self::STEP_START_FIGHT => in_array($action, [GameActionType::TutorialPrevious, GameActionType::StartManualFight], true),
            self::STEP_FIRST_ATTACK,
            self::STEP_FINISH_FIGHT => in_array($action, [GameActionType::StartManualFight, GameActionType::BattleAttack], true),
            default => false,
        };
    }

    public static function previousStep(int $step): ?int
    {
        return match (self::normalizeStep($step)) {
            self::STEP_MAP_OVERVIEW,
            self::STEP_GENERAL_STORE,
            self::STEP_AREA_OVERVIEW,
            self::STEP_HUNTING_AREA,
            self::STEP_EQUIPMENT,
            self::STEP_START_FIGHT,
            self::STEP_RESULT_EXPLAINED => self::normalizeStep($step - 1),
            default => null,
        };
    }

    public static function nextStep(int $step): ?int
    {
        return match (self::normalizeStep($step)) {
            self::STEP_INTRO,
            self::STEP_MAP_OVERVIEW,
            self::STEP_GENERAL_STORE,
            self::STEP_AREA_OVERVIEW,
            self::STEP_HUNTING_AREA,
            self::STEP_EQUIPMENT,
            self::STEP_BATTLE_UI,
            self::STEP_REWARD_EXPLAINED => self::normalizeStep($step + 1),
            self::STEP_RESULT_EXPLAINED => null,
            default => null,
        };
    }

    public static function canGoBack(int $step): bool
    {
        return self::previousStep($step) !== null;
    }

    public static function canGoNext(int $step): bool
    {
        return self::nextStep($step) !== null || self::normalizeStep($step) === self::STEP_RESULT_EXPLAINED;
    }

    /**
     * @return array<string, mixed>
     */
    public static function viewData(GameCharacter $character): array
    {
        if (! self::enabled() || ! $character->isInTutorial()) {
            return [
                'active' => false,
            ];
        }

        $step = self::currentStep($character);

        return [
            'active' => true,
            'step' => $step,
            'definition' => self::definition($step),
            'forced_focus_slug' => self::forcedFocusSlug($step),
            'visible_area_slugs' => self::visibleAreaSlugs($step),
            'can_go_back' => self::canGoBack($step),
            'can_go_next' => self::canGoNext($step),
            'show_chat' => false,
            'show_right_sidebar' => $step === self::STEP_EQUIPMENT,
            'show_system_grid' => false,
        ];
    }
}
