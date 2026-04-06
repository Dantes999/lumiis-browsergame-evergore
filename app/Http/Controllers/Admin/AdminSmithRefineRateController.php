<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSmithRefineRatesRequest;
use App\Models\SmithRefineRate;
use Illuminate\Support\Arr;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin surface for the global smith refinement table.
 */
class AdminSmithRefineRateController extends Controller
{
    public function index(): View
    {
        return view('admin.refine-rates.index', [
            'rates' => SmithRefineRate::query()
                ->orderBy('sort_order')
                ->orderBy('upgrade_level')
                ->get(),
        ]);
    }

    public function update(UpdateSmithRefineRatesRequest $request): RedirectResponse
    {
        foreach ($request->validated('rates') as $rateData) {
            $rate = SmithRefineRate::query()->findOrFail($rateData['id']);
            $rate->fill(Arr::except($rateData, ['id']));
            $rate->destroy_on_fail = (bool) $rateData['destroy_on_fail'];
            $rate->save();
        }

        return to_route('admin.refine-rates.index')->with('status', 'Refine rates updated.');
    }
}
