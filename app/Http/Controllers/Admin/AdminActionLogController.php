<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameActionLog;
use Illuminate\View\View;

/**
 * Read-only audit surface for processed player actions.
 */
class AdminActionLogController extends Controller
{
    /**
     * Logs are paginated because action history can grow quickly during active testing.
     */
    public function index(): View
    {
        return view('admin.logs.index', [
            'logs' => GameActionLog::query()
                ->with(['character.user', 'sourceLocation', 'targetLocation'])
                ->latest('processed_at')
                ->paginate(25),
        ]);
    }
}
