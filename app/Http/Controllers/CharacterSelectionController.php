<?php

namespace App\Http\Controllers;

use App\Models\GameBattle;
use App\Models\GameCharacter;
use App\Models\User;
use App\Services\Game\ActiveCharacterResolver;
use App\Services\Game\CharacterProvisioningService;
use App\Support\Game\MetinClassCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CharacterSelectionController extends Controller
{
    public function index(
        Request $request,
        ActiveCharacterResolver $activeCharacterResolver,
        CharacterProvisioningService $characterProvisioningService,
    ): View {
        $user = $request->user();
        $characters = $user->characters()
            ->with('location')
            ->withCount([
                'battles as ongoing_battle_count' => fn ($query) => $query->where('status', 'ongoing'),
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $activeCharacter = $activeCharacterResolver->current($user, $request);
        $maxCharactersPerAccount = $characterProvisioningService->maxCharactersPerAccount();

        return view('characters.index', [
            'activeCharacter' => $activeCharacter,
            'characters' => $characters,
            'maxCharactersPerAccount' => $maxCharactersPerAccount,
            'remainingSlots' => max(0, $maxCharactersPerAccount - $characters->count()),
            'raceOptions' => MetinClassCatalog::races(),
        ]);
    }

    public function store(
        Request $request,
        ActiveCharacterResolver $activeCharacterResolver,
        CharacterProvisioningService $characterProvisioningService,
    ): RedirectResponse {
        $request->validateWithBag('createCharacter', [
            'new_character_name' => ['required', 'string', 'min:3', 'max:20', 'regex:/^[A-Za-z0-9 _-]+$/', 'unique:game_characters,name'],
            'new_race' => ['required', Rule::in(array_keys(MetinClassCatalog::races()))],
        ]);

        $character = DB::transaction(function () use ($request, $characterProvisioningService): GameCharacter {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();

            if ($user->characters()->count() >= $characterProvisioningService->maxCharactersPerAccount()) {
                throw ValidationException::withMessages([
                    'new_character_name' => __('Each account can only hold :count characters.', ['count' => $characterProvisioningService->maxCharactersPerAccount()]),
                ])->errorBag('createCharacter');
            }

            return $characterProvisioningService->create(
                $user,
                $request->string('new_character_name')->trim()->value(),
                $request->string('new_race')->value(),
            );
        });

        $currentCharacter = $activeCharacterResolver->current($request->user(), $request);
        $currentCharacterHasActiveBattle = $currentCharacter instanceof GameCharacter
            && GameBattle::query()
                ->where('game_character_id', $currentCharacter->id)
                ->where('status', 'ongoing')
                ->exists();

        if (! $currentCharacterHasActiveBattle) {
            $activeCharacterResolver->remember($request, $character);

            return to_route('characters.index')->with('status', __(':name is ready for the realm.', ['name' => $character->name]));
        }

        return to_route('characters.index')->with('status', __(':name is ready, but finish or abandon the current manual fight before switching to that slot.', ['name' => $character->name]));
    }

    public function update(Request $request, GameCharacter $character): RedirectResponse
    {
        $this->guardOwnedCharacter($request, $character);

        $request->merge([
            'character_name' => $request->string('character_name')->trim()->value(),
        ]);

        $request->validateWithBag('renameCharacter_'.$character->id, [
            'character_name' => [
                'required',
                'string',
                'min:3',
                'max:20',
                'regex:/^[A-Za-z0-9 _-]+$/',
                Rule::unique('game_characters', 'name')->ignore($character),
            ],
        ]);

        if ($this->characterHasOngoingBattle($character)) {
            return back()->with('error', __('Finish or abandon the manual fight on :name before renaming that slot.', ['name' => $character->name]));
        }

        $character->forceFill([
            'name' => $request->string('character_name')->value(),
        ])->save();

        return to_route('characters.index')->with('status', __('Character renamed to :name.', ['name' => $character->name]));
    }

    public function destroy(
        Request $request,
        GameCharacter $character,
        ActiveCharacterResolver $activeCharacterResolver,
    ): RedirectResponse {
        $this->guardOwnedCharacter($request, $character);

        if ($this->characterHasOngoingBattle($character)) {
            return back()->with('error', __('Finish or abandon the manual fight on :name before deleting that slot.', ['name' => $character->name]));
        }

        $activeCharacter = $activeCharacterResolver->current($request->user(), $request);
        $replacementCharacter = $request->user()->characters()
            ->whereKeyNot($character->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
        $deletedName = $character->name;

        DB::transaction(function () use ($character): void {
            $character->delete();
        });

        if ($activeCharacter?->is($character)) {
            if ($replacementCharacter instanceof GameCharacter) {
                $activeCharacterResolver->remember($request, $replacementCharacter);
            } else {
                $activeCharacterResolver->clear($request);
            }
        }

        return to_route('characters.index')->with('status', __(':name was deleted.', ['name' => $deletedName]));
    }

    public function select(
        Request $request,
        GameCharacter $character,
        ActiveCharacterResolver $activeCharacterResolver,
    ): RedirectResponse {
        $this->guardOwnedCharacter($request, $character);

        $currentCharacter = $activeCharacterResolver->current($request->user(), $request);

        if (
            $currentCharacter instanceof GameCharacter
            && ! $currentCharacter->is($character)
            && $this->characterHasOngoingBattle($currentCharacter)
        ) {
            return back()->with('error', __('Finish or abandon the current manual fight before switching characters.'));
        }

        $activeCharacterResolver->remember($request, $character);

        return to_route('dashboard')->with('status', __('Now entering the realm as :name.', ['name' => $character->name]));
    }

    private function guardOwnedCharacter(Request $request, GameCharacter $character): void
    {
        if ($character->user_id !== $request->user()->id) {
            abort(404);
        }
    }

    private function characterHasOngoingBattle(GameCharacter $character): bool
    {
        return GameBattle::query()
            ->where('game_character_id', $character->id)
            ->where('status', 'ongoing')
            ->exists();
    }
}
