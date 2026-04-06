<?php

namespace App\Http\Controllers;

use App\Enums\GameActionType;
use App\Exceptions\GameActionException;
use App\Http\Requests\PerformGameActionRequest;
use App\Models\GameActionLog;
use App\Models\GameBattle;
use App\Services\Game\ProcessGameAction;
use Illuminate\Http\RedirectResponse;

/**
 * Thin HTTP boundary for all game actions.
 *
 * Validation happens in the request object, domain logic in ProcessGameAction,
 * and this controller only decides how the UI should be redirected afterwards.
 */
class GameActionController extends Controller
{
    public function __invoke(
        PerformGameActionRequest $request,
        string $action,
        ProcessGameAction $processGameAction,
    ): RedirectResponse {
        $actionType = GameActionType::from($action);
        $redirectParameters = [];
        $redirectFocus = trim((string) $request->input('redirect_focus'));

        if ($redirectFocus !== '') {
            $redirectParameters['focus'] = $redirectFocus;
        }

        try {
            // From this point on we only pass validated action payload into the domain layer.
            $validated = $request->validated();
            $message = $processGameAction->handle(
                $request->user(),
                $actionType,
                $validated,
            );

            $response = to_route('dashboard', $redirectParameters);
            $characterId = $request->user()->character()->value('id');
            $flashStatus = true;

            if ($characterId !== null) {
                // We look up the just-written action log so the dashboard can open the correct
                // battle window after redirect without recomputing combat client-side.
                $actionLog = GameActionLog::query()
                    ->where('game_character_id', $characterId)
                    ->where('idempotency_key', $validated['idempotency_key'])
                    ->latest('id')
                    ->first();
                $hasBattleReplay = is_array(data_get($actionLog?->outcome, 'battle_replay'));

                $activeBattleExists = GameBattle::query()
                    ->where('game_character_id', $characterId)
                    ->where('status', 'ongoing')
                    ->exists();

                // A live manual battle always wins over any replay metadata.
                // This protects the dashboard from accidentally opening a replay/result layer
                // while the real fight window should still stay on screen.
                if ($activeBattleExists) {
                    $response->with('status', $message);

                    return $response;
                }

                if ($hasBattleReplay) {
                    // Replay/result overlays are the visible end of the fight flow, so the generic
                    // top-of-dashboard flash should stay empty until the player has actually seen
                    // the combat finish on screen.
                    $flashStatus = false;

                    // Manual battles should end inside the fight window itself.
                    // Auto-fights still use the replay overlay after redirect.
                    if ($actionType->isBattleTurnAction()) {
                        $response->with('manual_battle_result_log_id', $actionLog->id);
                    } else {
                        $response->with('battle_replay_log_id', $actionLog->id);
                    }
                }

                $questUpdates = data_get($actionLog?->outcome, 'quest_updates');

                if (is_array($questUpdates) && $questUpdates !== []) {
                    $response->with('quest_updates', $questUpdates);
                }
            }

            if ($flashStatus) {
                $response->with('status', $message);
            }

            return $response;
        } catch (GameActionException $exception) {
            // Domain errors are expected gameplay failures, so we send them back as UI flash messages
            // instead of surfacing them as 500 responses.
            return to_route('dashboard', $redirectParameters)
                ->withInput($request->except('idempotency_key'))
                ->with('error', $exception->getMessage());
        }
    }
}
