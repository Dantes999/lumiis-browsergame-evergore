<?php

use App\Enums\GameActionType;
use App\Http\Controllers\Admin\AdminActionLogController;
use App\Http\Controllers\Admin\AdminAreaController;
use App\Http\Controllers\Admin\AdminBonusBalanceController;
use App\Http\Controllers\Admin\AdminCharacterController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminLocationConnectionController;
use App\Http\Controllers\Admin\AdminLocationController;
use App\Http\Controllers\Admin\AdminMonsterController;
use App\Http\Controllers\Admin\AdminQuestController;
use App\Http\Controllers\Admin\AdminShopController;
use App\Http\Controllers\Admin\AdminSmithRefineRateController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\CharacterSelectionController;
use App\Http\Controllers\GameActionController;
use App\Http\Controllers\GameDashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\RedirectLegacyGameFrontend;
use Illuminate\Support\Facades\Route;

// Public landing page for the project.
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return view('welcome');
});

// Built SPA entry point. In development the SPA runs on Vite (FRONTEND_URL).
// In production `scripts/build-frontend.sh` copies the build into public/play.
Route::get('/play/{any?}', function () {
    $index = public_path('play/index.html');

    abort_unless(is_file($index), 404, 'Frontend build missing. Run scripts/build-frontend.sh.');

    return response()->file($index);
})->where('any', '.*')->name('spa');

// Public endpoint to set a session locale for guests.
Route::patch('/locale', [LocaleController::class, 'update'])->name('locale.update');

// Normal player routes. Everything inside this group requires a signed-in account.
Route::middleware(['auth', 'no-store'])->group(function () {
    Route::get('/characters', [CharacterSelectionController::class, 'index'])
        ->middleware(RedirectLegacyGameFrontend::class)
        ->name('characters.index');
    Route::post('/characters', [CharacterSelectionController::class, 'store'])->name('characters.store');
    Route::patch('/characters/{character}', [CharacterSelectionController::class, 'update'])->name('characters.update');
    Route::delete('/characters/{character}', [CharacterSelectionController::class, 'destroy'])->name('characters.destroy');
    Route::post('/characters/{character}/select', [CharacterSelectionController::class, 'select'])->name('characters.select');

    Route::get('/dashboard', GameDashboardController::class)
        ->middleware(['throttle:dashboard-views', RedirectLegacyGameFrontend::class])
        ->name('dashboard');
    Route::get('/game', GameDashboardController::class)
        ->middleware(['throttle:dashboard-views', RedirectLegacyGameFrontend::class])
        ->name('game.dashboard');

    // All in-game button clicks and form submits funnel through a single action endpoint.
    Route::post('/game/actions/{action}', GameActionController::class)
        ->whereIn('action', GameActionType::values())
        ->middleware('throttle:game-actions')
        ->name('game.actions.store');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/locale', [ProfileController::class, 'updateLocale'])->name('profile.locale.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin routes are intentionally isolated under their own prefix and middleware stack.
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');

    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
    Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');

    Route::get('/characters', [AdminCharacterController::class, 'index'])->name('characters.index');
    Route::get('/characters/{character}/edit', [AdminCharacterController::class, 'edit'])->name('characters.edit');
    Route::patch('/characters/{character}', [AdminCharacterController::class, 'update'])->name('characters.update');

    Route::get('/locations', [AdminLocationController::class, 'index'])->name('locations.index');
    Route::get('/locations/{location}/edit', [AdminLocationController::class, 'edit'])->name('locations.edit');
    Route::patch('/locations/{location}', [AdminLocationController::class, 'update'])->name('locations.update');

    Route::get('/areas', [AdminAreaController::class, 'index'])->name('areas.index');
    Route::get('/areas/{area}/edit', [AdminAreaController::class, 'edit'])->name('areas.edit');
    Route::patch('/areas/{area}', [AdminAreaController::class, 'update'])->name('areas.update');

    Route::get('/monsters', [AdminMonsterController::class, 'index'])->name('monsters.index');
    Route::get('/monsters/{monster}/edit', [AdminMonsterController::class, 'edit'])->name('monsters.edit');
    Route::patch('/monsters/{monster}', [AdminMonsterController::class, 'update'])->name('monsters.update');

    Route::get('/quests', [AdminQuestController::class, 'index'])->name('quests.index');
    Route::get('/quests/{quest}/edit', [AdminQuestController::class, 'edit'])->name('quests.edit');
    Route::patch('/quests/{quest}', [AdminQuestController::class, 'update'])->name('quests.update');

    Route::get('/shops', [AdminShopController::class, 'index'])->name('shops.index');
    Route::get('/shops/{shop}/edit', [AdminShopController::class, 'edit'])->name('shops.edit');
    Route::patch('/shops/{shop}', [AdminShopController::class, 'update'])->name('shops.update');

    Route::get('/refine-rates', [AdminSmithRefineRateController::class, 'index'])->name('refine-rates.index');
    Route::patch('/refine-rates', [AdminSmithRefineRateController::class, 'update'])->name('refine-rates.update');

    Route::get('/bonus-balance', [AdminBonusBalanceController::class, 'index'])->name('bonus-balance.index');
    Route::patch('/bonus-balance', [AdminBonusBalanceController::class, 'update'])->name('bonus-balance.update');

    Route::get('/connections', [AdminLocationConnectionController::class, 'index'])->name('connections.index');
    Route::get('/connections/{connection}/edit', [AdminLocationConnectionController::class, 'edit'])->name('connections.edit');
    Route::patch('/connections/{connection}', [AdminLocationConnectionController::class, 'update'])->name('connections.update');

    Route::get('/logs', [AdminActionLogController::class, 'index'])->name('logs.index');
});

require __DIR__.'/auth.php';
