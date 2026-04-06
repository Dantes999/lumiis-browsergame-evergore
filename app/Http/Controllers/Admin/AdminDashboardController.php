<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameActionLog;
use App\Models\GameCharacter;
use App\Models\Location;
use App\Models\LocationArea;
use App\Models\LocationConnection;
use App\Models\Monster;
use App\Models\Quest;
use App\Models\Shop;
use App\Models\SmithRefineRate;
use App\Models\User;
use Illuminate\View\View;

/**
 * Lightweight operational overview for the built-in admin panel.
 */
class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'metrics' => [
                'users' => User::query()->count(),
                'admins' => User::query()->where('is_admin', true)->count(),
                'characters' => GameCharacter::query()->count(),
                'locations' => Location::query()->count(),
                'areas' => LocationArea::query()->count(),
                'monsters' => Monster::query()->count(),
                'quests' => Quest::query()->count(),
                'shops' => Shop::query()->count(),
                'refine rates' => SmithRefineRate::query()->count(),
                'connections' => LocationConnection::query()->count(),
                'logs' => GameActionLog::query()->count(),
            ],
            'databasePath' => database_path('database.sqlite'),
            'recentLogs' => GameActionLog::query()
                ->with(['character.user', 'sourceLocation', 'targetLocation'])
                ->latest('processed_at')
                ->limit(10)
                ->get(),
        ]);
    }
}
