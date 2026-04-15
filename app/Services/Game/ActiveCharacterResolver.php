<?php

namespace App\Services\Game;

use App\Models\GameCharacter;
use App\Models\User;
use Illuminate\Http\Request;

class ActiveCharacterResolver
{
    public const SESSION_KEY = 'active_character_id';

    /**
     * @param  array<int, string>  $with
     */
    public function current(?User $user, ?Request $request = null, array $with = []): ?GameCharacter
    {
        if (! $user instanceof User) {
            return null;
        }

        $activeCharacterId = $request?->session()->get(self::SESSION_KEY);

        if (is_numeric($activeCharacterId)) {
            $selectedCharacter = $this->baseQuery($user)
                ->with($with)
                ->whereKey((int) $activeCharacterId)
                ->first();

            if ($selectedCharacter instanceof GameCharacter) {
                return $selectedCharacter;
            }

            $request?->session()->forget(self::SESSION_KEY);
        }

        $fallbackCharacter = $this->baseQuery($user)
            ->with($with)
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        if ($fallbackCharacter instanceof GameCharacter) {
            $request?->session()->put(self::SESSION_KEY, $fallbackCharacter->id);
        }

        return $fallbackCharacter;
    }

    public function remember(Request $request, GameCharacter $character): void
    {
        $request->session()->put(self::SESSION_KEY, $character->id);
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    private function baseQuery(User $user)
    {
        return GameCharacter::query()->where('user_id', $user->id);
    }
}
