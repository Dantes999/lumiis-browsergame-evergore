<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\GameActionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateNormalSimulationCharacterRequest;
use App\Http\Requests\Api\SimulateFightRequest;
use App\Services\Game\GameSimulationService;
use App\Services\Game\NormalSimulationCharacterFactory;
use Illuminate\Http\JsonResponse;

class SimulationController extends Controller
{
    public function storeNormalCharacter(
        CreateNormalSimulationCharacterRequest $request,
        NormalSimulationCharacterFactory $characterFactory,
    ): JsonResponse {
        $character = $characterFactory->create($request->validated());

        return response()->json([
            'character' => [
                'id' => $character->id,
                'name' => $character->name,
                'race' => $character->chosenRace(),
                'level' => (int) $character->level,
                'location_slug' => $character->location?->slug,
                'location_name' => $character->location?->name,
                'game_state' => $character->game_state?->value,
                'simulation_email_domain' => $characterFactory->emailDomain(),
            ],
        ], 201);
    }

    public function simulateFight(
        SimulateFightRequest $request,
        GameSimulationService $simulationService,
    ): JsonResponse {
        try {
            return response()->json(
                $simulationService->simulate($request->validated()),
            );
        } catch (GameActionException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
