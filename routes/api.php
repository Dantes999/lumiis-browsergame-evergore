<?php

use App\Http\Controllers\Api\SimulationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['simulation-api', 'throttle:60,1'])
    ->prefix('simulations')
    ->group(function (): void {
        Route::post('/characters/normal', [SimulationController::class, 'storeNormalCharacter']);
        Route::post('/fights', [SimulationController::class, 'simulateFight']);
    });
