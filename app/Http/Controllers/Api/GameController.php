<?php

namespace App\Http\Controllers\Api;

use App\Enums\GameActionType;
use App\Exceptions\GameActionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PerformGameActionRequest;
use App\Models\GameActionLog;
use App\Models\GameBattle;
use App\Models\GameCharacter;
use App\Models\Location;
use App\Models\LocationArea;
use App\Models\User;
use App\Services\Game\ActiveCharacterResolver;
use App\Services\Game\CharacterProvisioningService;
use App\Services\Game\GameApiStateService;
use App\Services\Game\ProcessGameAction;
use App\Support\Game\MetinClassCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GameController extends Controller
{
    public function __construct(
        private readonly GameApiStateService $stateService,
        private readonly ProcessGameAction $processGameAction,
        private readonly CharacterProvisioningService $characterProvisioningService,
        private readonly ActiveCharacterResolver $activeCharacterResolver,
    ) {}

    public function state(Request $request): JsonResponse
    {
        $character = $this->activeCharacter($request);

        return response()->json([
            ...($character ? $this->stateService->snapshot($character) : ['player' => null, 'map' => null]),
            'characters' => $this->characters($request->user()),
        ]);
    }

    public function charactersIndex(Request $request): JsonResponse
    {
        return response()->json([
            'characters' => $this->characters($request->user()),
            'activeCharacterId' => $this->activeCharacter($request)?->id,
            'maxCharactersPerAccount' => $this->characterProvisioningService->maxCharactersPerAccount(),
            'races' => collect(MetinClassCatalog::races())->map(fn (array $race, string $key): array => [
                'key' => $key,
                'label' => $race['label'],
                'description' => $race['description'],
            ])->values()->all(),
        ]);
    }

    public function storeCharacter(Request $request): JsonResponse
    {
        $request->validate([
            'new_character_name' => ['required', 'string', 'min:3', 'max:20', 'regex:/^[A-Za-z0-9 _-]+$/', 'unique:game_characters,name'],
            'new_race' => ['required', Rule::in(array_keys(MetinClassCatalog::races()))],
        ]);

        $character = DB::transaction(function () use ($request): GameCharacter {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();

            if ($user->characters()->count() >= $this->characterProvisioningService->maxCharactersPerAccount()) {
                throw ValidationException::withMessages([
                    'new_character_name' => __('Each account can only hold :count characters.', [
                        'count' => $this->characterProvisioningService->maxCharactersPerAccount(),
                    ]),
                ]);
            }

            return $this->characterProvisioningService->create(
                $user,
                $request->string('new_character_name')->trim()->value(),
                $request->string('new_race')->value(),
            );
        });

        $this->activeCharacterResolver->remember($request, $character);

        return response()->json([
            'message' => __(':name is ready for the realm.', ['name' => $character->name]),
            'state' => $this->stateResponse($request),
        ], 201);
    }

    public function selectCharacter(Request $request, GameCharacter $character): JsonResponse
    {
        abort_unless($character->user_id === $request->user()->id, 404);

        $current = $this->activeCharacter($request);

        if ($current && ! $current->is($character) && $this->hasOngoingBattle($current)) {
            return response()->json([
                'message' => __('Finish or abandon the current manual fight before switching characters.'),
            ], 409);
        }

        $this->activeCharacterResolver->remember($request, $character);

        return response()->json([
            'message' => __('Now entering the realm as :name.', ['name' => $character->name]),
            'state' => $this->stateResponse($request),
        ]);
    }

    public function updateCharacter(Request $request, GameCharacter $character): JsonResponse
    {
        abort_unless($character->user_id === $request->user()->id, 404);

        $request->merge([
            'character_name' => $request->string('character_name')->trim()->value(),
        ]);

        $request->validate([
            'character_name' => [
                'required',
                'string',
                'min:3',
                'max:20',
                'regex:/^[A-Za-z0-9 _-]+$/',
                Rule::unique('game_characters', 'name')->ignore($character),
            ],
        ]);

        if ($this->hasOngoingBattle($character)) {
            return response()->json([
                'message' => __('Finish or abandon the manual fight on :name before renaming that slot.', ['name' => $character->name]),
            ], 409);
        }

        $character->forceFill(['name' => $request->string('character_name')->value()])->save();

        return response()->json([
            'message' => __('Character renamed to :name.', ['name' => $character->name]),
            'state' => $this->stateResponse($request),
        ]);
    }

    public function destroyCharacter(Request $request, GameCharacter $character): JsonResponse
    {
        abort_unless($character->user_id === $request->user()->id, 404);

        if ($this->hasOngoingBattle($character)) {
            return response()->json([
                'message' => __('Finish or abandon the manual fight on :name before deleting that slot.', ['name' => $character->name]),
            ], 409);
        }

        $active = $this->activeCharacter($request);
        $replacement = $request->user()->characters()
            ->whereKeyNot($character->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
        $deletedName = $character->name;
        $character->delete();

        if ($active?->is($character)) {
            $replacement ? $this->activeCharacterResolver->remember($request, $replacement) : $this->activeCharacterResolver->clear($request);
        }

        return response()->json([
            'message' => __(':name was deleted.', ['name' => $deletedName]),
            'state' => $this->stateResponse($request),
        ]);
    }

    public function region(Request $request, string $slug): JsonResponse
    {
        $character = $this->requireCharacter($request);
        $area = LocationArea::query()
            ->where('slug', $slug)
            ->where('location_id', $character->location_id)
            ->firstOrFail();

        return response()->json($this->stateService->regionPayload($character, $area));
    }

    public function city(Request $request, string $slug): JsonResponse
    {
        $character = $this->requireCharacter($request);
        $location = Location::query()->where('slug', $slug)->firstOrFail();

        abort_unless($location->id === $character->location_id, 404);

        return response()->json($this->stateService->cityPayload($character, $location));
    }

    public function action(PerformGameActionRequest $request, string $action): JsonResponse
    {
        $character = $this->activeCharacter($request);

        if (! $character) {
            return response()->json(['message' => __('Create or select a character before entering the realm.')], 409);
        }

        $actionType = GameActionType::from($action);

        try {
            $message = $this->processGameAction->handle(
                $request->user(),
                $actionType,
                $request->validated(),
                $character,
            );
        } catch (GameActionException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $actionLog = GameActionLog::query()
            ->where('game_character_id', $character->id)
            ->where('idempotency_key', $request->validated('idempotency_key'))
            ->latest('id')
            ->first();

        return response()->json([
            'message' => $message,
            'outcome' => $actionLog?->outcome,
            'state' => $this->stateResponse($request),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function stateResponse(Request $request): array
    {
        $character = $this->activeCharacter($request);

        return [
            ...($character ? $this->stateService->snapshot($character) : ['player' => null, 'map' => null]),
            'characters' => $this->characters($request->user()),
        ];
    }

    private function activeCharacter(Request $request): ?GameCharacter
    {
        return $this->activeCharacterResolver->current($request->user(), $request);
    }

    private function requireCharacter(Request $request): GameCharacter
    {
        $character = $this->activeCharacter($request);
        abort_if($character === null, 409, __('Create or select a character before entering the realm.'));

        return $character;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function characters(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return $user->characters()
            ->with('location')
            ->withCount([
                'battles as ongoing_battle_count' => fn ($query) => $query->where('status', 'ongoing'),
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (GameCharacter $character): array => [
                'id' => $character->id,
                'name' => $character->name,
                'level' => (int) $character->level,
                'race' => $character->chosenRace(),
                'location' => $character->location?->name,
                'ongoingBattle' => (int) $character->ongoing_battle_count > 0,
            ])
            ->values()
            ->all();
    }

    private function hasOngoingBattle(GameCharacter $character): bool
    {
        return GameBattle::query()
            ->where('game_character_id', $character->id)
            ->where('status', 'ongoing')
            ->exists();
    }
}
