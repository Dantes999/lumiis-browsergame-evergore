<?php

use App\Enums\GameActionType;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\SimulationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['simulation-api', 'throttle:simulation-requests'])
    ->prefix('simulations')
    ->group(function (): void {
        Route::post('/characters/normal', [SimulationController::class, 'storeNormalCharacter']);
        Route::post('/fights', [SimulationController::class, 'simulateFight']);
    });

/*
|--------------------------------------------------------------------------
| Player API
|--------------------------------------------------------------------------
|
| These routes intentionally use the "web" middleware so the SPA can reuse
| Laravel's existing session authentication and CSRF protection. Requests
| are JSON-only and the client sends the X-XSRF-TOKEN header.
|
*/
Route::middleware(['web', 'auth'])
    ->prefix('v1')
    ->group(function (): void {
        Route::get('/state', [GameController::class, 'state'])
            ->middleware('throttle:game-api-state')
            ->name('api.game.state');

        Route::get('/characters', [GameController::class, 'charactersIndex'])
            ->middleware('throttle:game-api-reads')
            ->name('api.characters.index');
        Route::post('/characters', [GameController::class, 'storeCharacter'])
            ->middleware('throttle:game-api-writes')
            ->name('api.characters.store');
        Route::patch('/characters/{character}', [GameController::class, 'updateCharacter'])
            ->middleware('throttle:game-api-writes')
            ->name('api.characters.update');
        Route::delete('/characters/{character}', [GameController::class, 'destroyCharacter'])
            ->middleware('throttle:game-api-writes')
            ->name('api.characters.destroy');
        Route::post('/characters/{character}/select', [GameController::class, 'selectCharacter'])
            ->middleware('throttle:game-api-writes')
            ->name('api.characters.select');

        Route::get('/regions/{slug}', [GameController::class, 'region'])
            ->middleware('throttle:game-api-reads')
            ->name('api.regions.show');
        Route::get('/cities/{slug}', [GameController::class, 'city'])
            ->middleware('throttle:game-api-reads')
            ->name('api.cities.show');

        Route::post('/actions/{action}', [GameController::class, 'action'])
            ->whereIn('action', GameActionType::values())
            ->middleware('throttle:game-api-writes')
            ->name('api.actions.store');
    });
