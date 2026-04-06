<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GameCharacterState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCharacterRequest;
use App\Models\GameCharacter;
use App\Models\Location;
use App\Support\Game\MetinClassCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * CRUD-style admin surface for character balancing and support work.
 */
class AdminCharacterController extends Controller
{
    public function index(): View
    {
        return view('admin.characters.index', [
            'characters' => GameCharacter::query()
                ->with(['user', 'location'])
                ->latest('updated_at')
                ->paginate(15),
        ]);
    }

    public function edit(GameCharacter $character): View
    {
        return view('admin.characters.edit', [
            'character' => $character->load(['user', 'location']),
            'locations' => Location::query()->orderBy('region')->orderBy('name')->get(),
            'gameStates' => GameCharacterState::cases(),
            'raceOptions' => MetinClassCatalog::races(),
            'skillGroupOptions' => MetinClassCatalog::groupsForRace($character->chosenRace()),
        ]);
    }

    /**
     * Direct writes are intentional here because this is an admin-only maintenance screen.
     */
    public function update(UpdateCharacterRequest $request, GameCharacter $character): RedirectResponse
    {
        $validated = $request->validated();

        // Support edits should not leave impossible state combinations behind.
        if (($validated['game_state'] ?? null) === GameCharacterState::Active->value) {
            $validated['tutorial_step'] = null;
        } elseif (($validated['tutorial_step'] ?? null) === null) {
            $validated['tutorial_step'] = 1;
        }

        $character->fill($validated);
        $character->save();

        return to_route('admin.characters.edit', $character)->with('status', 'Character updated.');
    }
}
