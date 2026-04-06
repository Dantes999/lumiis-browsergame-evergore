<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMonsterRequest;
use App\Models\Monster;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin editor for raw monster template values.
 */
class AdminMonsterController extends Controller
{
    public function index(): View
    {
        return view('admin.monsters.index', [
            'monsters' => Monster::query()
                ->orderBy('sort_order')
                ->orderBy('level')
                ->paginate(20),
        ]);
    }

    public function edit(Monster $monster): View
    {
        return view('admin.monsters.edit', [
            'monster' => $monster,
        ]);
    }

    public function update(UpdateMonsterRequest $request, Monster $monster): RedirectResponse
    {
        $monster->fill($request->validated());
        $monster->save();

        return to_route('admin.monsters.edit', $monster)->with('status', 'Monster updated.');
    }
}
