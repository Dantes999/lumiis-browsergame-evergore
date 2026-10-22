<?php

namespace App\Http\Controllers;

use App\Models\GameActionLog;
use App\Models\GameBattle;
use App\Models\GameCharacter;
use App\Models\GameMessage;
use App\Models\LocationArea;
use App\Models\Skill;
use App\Services\Game\ActiveCharacterResolver;
use App\Services\Game\CharacterInventoryService;
use App\Services\Game\ManualEncounterPreviewService;
use App\Services\Game\QuestProgressService;
use App\Services\Game\RestStateService;
use App\Support\Game\FeatureUnlocks;
use App\Support\Game\MetinClassCatalog;
use App\Support\Game\TutorialFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Composes the entire player dashboard in one query-oriented place.
 *
 * The controller does not compute game mechanics. It only gathers the current read model:
 * map state, chat, inventory, active battles and the most recent logs needed by the UI.
 */
class GameDashboardController extends Controller
{
    public function __invoke(
        Request $request,
        ActiveCharacterResolver $activeCharacterResolver,
        CharacterInventoryService $inventoryService,
        ManualEncounterPreviewService $manualEncounterPreviewService,
        QuestProgressService $questProgressService,
        RestStateService $restStateService,
    ): View|RedirectResponse {
        // Load everything the dashboard needs up front so the Blade template can stay mostly declarative.
        // Location areas are refreshed with their full column set below because the dashboard accesses
        // many area attributes directly (cooldowns, pressure thresholds, names, descriptions).
        $character = $activeCharacterResolver->current(
            $request->user(),
            $request,
            [
                'location.outgoingConnections.toLocation',
                'location.areas.shop.shopItems.item',
                'actionLogs' => fn ($query) => $query
                    ->with(['sourceLocation', 'targetLocation'])
                    // Several actions can land inside the same second, especially when
                    // players chain fights quickly. We therefore sort by timestamp and id
                    // so the newest log always wins deterministically for replay/summary UI.
                    ->orderByDesc('processed_at')
                    ->orderByDesc('id')
                    ->limit(8),
                'areaProgress.locationArea',
                'characterQuests.quest.location',
                'inventoryItems.item',
                'inventoryItems.bonuses',
                'inventoryItems.equipment',
                'equipment.characterItem.item',
                'equipment.characterItem.bonuses',
                'learnedSkills.skill',
            ],
        );

        if (! $character instanceof GameCharacter) {
            return to_route('characters.index');
        }

        // The dashboard is the main inventory read surface, so we repair stale slot data here too.
        // That keeps older characters compatible after bag-layout schema changes.
        $inventoryService->ensureLayout($character);

        // Re-query map hotspots explicitly instead of relying on nested eager-loaded relation state.
        // This keeps the dashboard safe even if another read path ever loads partial LocationArea rows.
        $locationAreas = LocationArea::query()
            ->with(['monsterAssignments.monster', 'shop.shopItems.item'])
            ->where('location_id', $character->location_id)
            ->orderBy('sort_order')
            ->get();
        $character->location->setRelation('areas', $locationAreas);

        // Chat is intentionally limited to small windows because the game is still session-rendered.
        $globalMessages = GameMessage::query()
            ->with('sender')
            ->where('channel', 'global')
            ->latest()
            ->limit(12)
            ->get()
            ->reverse()
            ->values();

        // Whispers are filtered to the current character from both directions.
        $whispers = GameMessage::query()
            ->with(['sender', 'recipient'])
            ->where('channel', 'whisper')
            ->where(function ($query) use ($character): void {
                $query
                    ->where('sender_character_id', $character->id)
                    ->orWhere('recipient_character_id', $character->id);
            })
            ->latest()
            ->limit(12)
            ->get()
            ->reverse()
            ->values();

        $chosenRace = $character->chosenRace();
        $skillGroupOptions = collect(MetinClassCatalog::groupsForRace($chosenRace))
            ->map(function (array $groupOption, string $groupKey) use ($chosenRace): array {
                return [
                    ...$groupOption,
                    'identity' => MetinClassCatalog::skillGroupIdentityLabel($chosenRace, $groupKey),
                    'passive_summary' => MetinClassCatalog::skillGroupPassiveSummary($chosenRace, $groupKey),
                    'combat_summary' => MetinClassCatalog::skillGroupCombatSummary($chosenRace, $groupKey),
                ];
            })
            ->all();
        $selectedSkillGroup = $character->hasChosenSkillGroup()
            ? [
                'key' => $character->skill_group,
                'label' => MetinClassCatalog::skillGroupLabel($chosenRace, $character->skill_group),
                'identity' => MetinClassCatalog::skillGroupIdentityLabel($chosenRace, $character->skill_group),
                'description' => data_get($skillGroupOptions, $character->skill_group.'.description'),
                'passive_summary' => MetinClassCatalog::skillGroupPassiveSummary($chosenRace, (string) $character->skill_group),
                'combat_summary' => MetinClassCatalog::skillGroupCombatSummary($chosenRace, (string) $character->skill_group),
            ]
            : null;
        $availableSkills = $character->hasChosenSkillGroup()
            ? Skill::query()
                ->where('race', $chosenRace)
                ->where('skill_group', $character->skill_group)
                ->orderBy('required_level')
                ->orderBy('name')
                ->get()
            : collect();

        // The visible hotspot list depends on onboarding and level-based feature locks.
        $locationAreas = $locationAreas
            ->filter(fn (LocationArea $area): bool => TutorialFlow::areaIsVisible($character, $area))
            ->values();
        $character->location->setRelation('areas', $locationAreas);

        $dashboardMoment = now();
        $restStateService->resolveCompletedRest($character, $dashboardMoment);
        $questline = $character->isInTutorial()
            ? null
            : $questProgressService->dashboardData($character);
        $biologistResearch = $character->isInTutorial()
            ? null
            : $questProgressService->biologistPanelData($character, $dashboardMoment);
        $staminaPreview = $character->staminaRecoveryState($dashboardMoment);
        $healthPreview = $character->healthRecoveryState($dashboardMoment);
        $spPreview = $character->spRecoveryState($dashboardMoment);
        $tutorial = TutorialFlow::viewData($character);
        $restingState = $restStateService->currentRestState($character, $dashboardMoment);
        $storyline = $character->isInTutorial()
            ? null
            : $this->buildStoryline($character, $locationAreas);
        $featureUnlocks = FeatureUnlocks::dashboardData($character);
        $activeBattle = GameBattle::query()
            ->with('locationArea')
            ->where('game_character_id', $character->id)
            ->where('status', 'ongoing')
            ->latest('id')
            ->first();
        $requestedFocus = $tutorial['active'] ?? false
            ? ($tutorial['forced_focus_slug'] ?? $request->query('focus'))
            : $request->query('focus');
        $hasExplicitFocus = is_string($requestedFocus) && $requestedFocus !== '';
        $activeArea = $this->resolveActiveArea($locationAreas, $requestedFocus);

        if ($activeBattle !== null) {
            $activeArea = $locationAreas->firstWhere('id', $activeBattle->location_area_id) ?? $activeArea;
        }

        $manualEncounterFamilies = $manualEncounterPreviewService->buildForArea($character, $activeArea);
        $encounterActionPreviews = $manualEncounterPreviewService->buildActionPreviews($character, $activeArea);

        // Replay overlays are driven by flashed action log ids from the redirect after an action.
        $activeAreaProgress = $activeArea === null
            ? null
            : $character->areaProgress->firstWhere('location_area_id', $activeArea->id);
        $latestBattleLog = $character->actionLogs
            ->first(fn ($log): bool => is_array(data_get($log->outcome, 'battle_log')) && data_get($log->outcome, 'battle_log') !== []);
        $battleReplayLogId = (int) $request->session()->get('battle_replay_log_id', 0);
        $battleReplayLog = $battleReplayLogId > 0
            ? GameActionLog::query()
                ->where('game_character_id', $character->id)
                ->find($battleReplayLogId)
            : null;
        $manualBattleResultLogId = (int) $request->session()->get('manual_battle_result_log_id', 0);
        $manualBattleResultLog = $manualBattleResultLogId > 0
            ? GameActionLog::query()
                ->where('game_character_id', $character->id)
                ->find($manualBattleResultLogId)
            : null;

        return view('dashboard', [
            'character' => $character,
            'recentLogs' => $character->actionLogs,
            'availableSkills' => $availableSkills,
            'raceDefinition' => MetinClassCatalog::races()[$chosenRace],
            'skillGroupOptions' => $skillGroupOptions,
            'selectedSkillGroup' => $selectedSkillGroup,
            'globalMessages' => $globalMessages,
            'whispers' => $whispers,
            'locationAreas' => $locationAreas,
            'storyline' => $storyline,
            'questline' => $questline,
            'biologistResearch' => $biologistResearch,
            'featureUnlocks' => $featureUnlocks,
            'tutorial' => $tutorial,
            'restingState' => $restingState,
            'activeArea' => $activeArea,
            'manualEncounterFamilies' => $manualEncounterFamilies,
            'encounterActionPreviews' => $encounterActionPreviews,
            'activeAreaProgress' => $activeAreaProgress,
            'hasExplicitFocus' => $hasExplicitFocus,
            'staminaPreview' => $staminaPreview,
            'healthPreview' => $healthPreview,
            'spPreview' => $spPreview,
            'latestBattleLog' => $latestBattleLog,
            'battleReplayLog' => $battleReplayLog,
            'manualBattleResultLog' => $manualBattleResultLog,
            'activeBattle' => $activeBattle,
        ]);
    }

    /**
     * @param  Collection<int, LocationArea>  $locationAreas
     */
    private function resolveActiveArea($locationAreas, mixed $focus): ?LocationArea
    {
        // If the UI asked for a specific hotspot, prefer that.
        if (is_string($focus) && $focus !== '') {
            $focusedArea = $locationAreas->firstWhere('slug', $focus);

            if ($focusedArea !== null) {
                return $focusedArea;
            }
        }

        // Outside the tutorial and active battles, the map should stay a true overview until the
        // player explicitly clicks a hotspot. Returning null here avoids opening an area window
        // that the player never chose, which was especially problematic on mobile.
        return null;
    }

    /**
     * Story progression is a lightweight, map-level summary next to the explicit questline.
     *
     * The quest system tracks exact objective completion and rewards, while this summary keeps the
     * "cleanse the whole map" fantasy visible in one glance without needing extra content rows.
     *
     * @param  Collection<int, LocationArea>  $locationAreas
     * @return array<string, mixed>
     */
    private function buildStoryline(GameCharacter $character, $locationAreas): array
    {
        $huntingAreas = $locationAreas->filter(fn (LocationArea $area): bool => $area->isHunting())->values();
        $huntingAreaIds = $huntingAreas->pluck('id');
        $progressEntries = $character->areaProgress
            ->whereIn('location_area_id', $huntingAreaIds)
            ->values();
        $monsterFights = (int) $progressEntries->sum('monster_fights');
        $lumiisBreaks = (int) $progressEntries->sum('lumiis_searches');
        $bossSoulsReleased = (int) $progressEntries->sum('boss_fights');
        $monsterTarget = max(8, $huntingAreas->count() * 6);
        $lumiisTarget = max(3, $huntingAreas->count() * 2);
        $steps = [
            [
                'label' => __('Free wandering souls'),
                'description' => __('Defeat roaming monster packs to weaken the corruption across the map.'),
                'current' => $monsterFights,
                'target' => $monsterTarget,
            ],
            [
                'label' => __('Shatter Lumiis stones'),
                'description' => __('Break Lumiis cores to release the souls they have trapped inside.'),
                'current' => $lumiisBreaks,
                'target' => $lumiisTarget,
            ],
            [
                'label' => __('Release the area boss soul'),
                'description' => __('Fill a hunting area pressure meter, then defeat its boss in a manual fight.'),
                'current' => $bossSoulsReleased,
                'target' => 1,
            ],
        ];

        $steps = array_map(function (array $step): array {
            $step['completed'] = $step['current'] >= $step['target'];
            $step['progress_percent'] = min(
                100,
                (int) round(($step['current'] / max(1, $step['target'])) * 100),
            );

            return $step;
        }, $steps);

        $completedSteps = collect($steps)->where('completed', true)->count();
        $totalObjectiveCount = (int) collect($steps)->sum(fn (array $step): int => (int) $step['target']);
        $currentObjectiveCount = (int) collect($steps)->sum(
            fn (array $step): int => min((int) $step['current'], (int) $step['target']),
        );

        return [
            'title' => __('Cure :location', ['location' => $character->location->name]),
            'summary' => $completedSteps === count($steps)
                ? __('This map is breathing again. Keep moving and cleanse the next corrupted land.')
                : __('Lumiis stones are trapping the souls of this land. Clear monsters, shatter Lumiis cores, and expose the boss soul to heal the map.'),
            // The story badge should reflect real incremental progress, not only fully
            // finished objectives. Otherwise the UI stays at 0% for a long time even
            // though the player is already cleansing the area step by step.
            'completion_percent' => (int) round(($currentObjectiveCount / max(1, $totalObjectiveCount)) * 100),
            'steps' => $steps,
        ];
    }
}
