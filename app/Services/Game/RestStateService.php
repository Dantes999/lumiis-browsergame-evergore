<?php

namespace App\Services\Game;

use App\Models\GameActionLog;
use App\Models\GameCharacter;
use Illuminate\Support\Carbon;

/**
 * Rest actions are time-based and resolve after a waiting period instead of immediately.
 *
 * The actual pending plan is stored on the latest rest action log so we do not need extra
 * character columns just to track a short-lived waiting state. This service centralizes that
 * read/resolve logic for both dashboard reads and action processing.
 */
class RestStateService
{
    /**
     * Apply the pending rest rewards once the lock has fully expired.
     *
     * @return array<string, mixed>|null
     */
    public function resolveCompletedRest(GameCharacter $character, ?Carbon $moment = null): ?array
    {
        $resolvedMoment = $moment ?? now();
        $pendingRest = $this->latestPendingRestLog($character);

        if ($pendingRest === null) {
            return null;
        }

        $restPlan = (array) data_get($pendingRest->outcome, 'rest_plan', []);
        $endsAt = $this->restPlanTimestamp($restPlan, 'ends_at');

        if ($endsAt === null || $endsAt->isFuture()) {
            return null;
        }

        $character->health = min(
            $character->effectiveMaxHealth(),
            (int) $character->health + max(0, (int) ($restPlan['health_gain'] ?? 0)),
        );
        $character->sp = min(
            $character->effectiveMaxSp(),
            (int) $character->sp + max(0, (int) ($restPlan['sp_gain'] ?? 0)),
        );
        $character->stamina = min(
            $character->effectiveMaxStamina(),
            (int) $character->stamina + max(0, (int) ($restPlan['stamina_gain'] ?? 0)),
        );
        $character->yang += max(0, (int) ($restPlan['yang_gain'] ?? 0));
        $character->action_available_at = null;
        $character->health_updated_at = $endsAt->copy();
        $character->sp_updated_at = $endsAt->copy();
        $character->stamina_updated_at = $endsAt->copy();
        $character->save();

        $restPlan['resolved_at'] = $resolvedMoment->toISOString();
        $outcome = $pendingRest->outcome ?? [];
        $outcome['rest_plan'] = $restPlan;
        $pendingRest->outcome = $outcome;
        $pendingRest->save();

        return $restPlan;
    }

    /**
     * Build the current rest read model for the UI while the character is locked by a rest action.
     *
     * @return array<string, mixed>|null
     */
    public function currentRestState(GameCharacter $character, ?Carbon $moment = null): ?array
    {
        $resolvedMoment = $moment ?? now();
        $pendingRest = $this->latestPendingRestLog($character);

        if ($pendingRest === null) {
            return null;
        }

        $restPlan = (array) data_get($pendingRest->outcome, 'rest_plan', []);
        $endsAt = $this->restPlanTimestamp($restPlan, 'ends_at');
        $startedAt = $this->restPlanTimestamp($restPlan, 'started_at');

        if ($endsAt === null) {
            return null;
        }

        return [
            'label' => (string) ($restPlan['label'] ?? __('Rest')),
            'minutes' => (int) ($restPlan['minutes'] ?? 0),
            'remaining_seconds' => max(0, $resolvedMoment->diffInSeconds($endsAt, false)),
            'started_at_label' => $startedAt?->format('H:i'),
            'ends_at_label' => $endsAt->format('H:i'),
            'health_gain' => max(0, (int) ($restPlan['health_gain'] ?? 0)),
            'sp_gain' => max(0, (int) ($restPlan['sp_gain'] ?? 0)),
            'stamina_gain' => max(0, (int) ($restPlan['stamina_gain'] ?? 0)),
            'yang_gain' => max(0, (int) ($restPlan['yang_gain'] ?? 0)),
        ];
    }

    private function latestPendingRestLog(GameCharacter $character): ?GameActionLog
    {
        $latestRestLog = GameActionLog::query()
            ->where('game_character_id', $character->id)
            ->where('action', 'rest')
            ->latest('id')
            ->first();

        if ($latestRestLog === null) {
            return null;
        }

        $restPlan = (array) data_get($latestRestLog->outcome, 'rest_plan', []);

        if ($restPlan === [] || filled($restPlan['resolved_at'] ?? null)) {
            return null;
        }

        return $latestRestLog;
    }

    private function restPlanTimestamp(array $restPlan, string $key): ?Carbon
    {
        $value = $restPlan[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value);
    }
}
