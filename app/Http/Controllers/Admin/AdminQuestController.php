<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateQuestRequest;
use App\Models\Item;
use App\Models\Location;
use App\Models\LocationArea;
use App\Models\Quest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin surface for story missions and reward balancing.
 */
class AdminQuestController extends Controller
{
    public function index(): View
    {
        return view('admin.quests.index', [
            'quests' => Quest::query()
                ->with('location')
                ->orderBy('location_id')
                ->orderBy('sort_order')
                ->paginate(30),
        ]);
    }

    public function edit(Quest $quest): View
    {
        return view('admin.quests.edit', [
            'quest' => $quest->load('location'),
            'locations' => Location::query()->orderBy('name')->get(),
            'prerequisiteOptions' => Quest::query()
                ->whereKeyNot($quest->id)
                ->orderBy('sort_order')
                ->orderBy('slug')
                ->pluck('slug'),
            'areaSlugOptions' => LocationArea::query()
                ->orderBy('location_id')
                ->orderBy('sort_order')
                ->pluck('slug'),
            'itemSlugOptions' => Item::query()->orderBy('slug')->pluck('slug'),
            'objectiveTypes' => [
                'reach_level' => 'Reach Level',
                'monster_fights_in_area' => 'Monster fights in area',
                'lumiis_searches_in_area' => 'Lumiis hunts in area',
                'boss_fights_in_area' => 'Boss fights in area',
                'deliver_item_to_biologist' => 'Deliver item to Biologist',
            ],
            'rewardBonusTypes' => Quest::rewardBonusOptions(),
        ]);
    }

    public function update(UpdateQuestRequest $request, Quest $quest): RedirectResponse
    {
        $validated = $request->validated();

        $quest->fill($validated);
        $quest->is_active = (bool) $validated['is_active'];
        $quest->save();

        return to_route('admin.quests.edit', $quest)->with('status', 'Quest updated.');
    }
}
