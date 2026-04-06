<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLocationRequest;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin editor for map-level location metadata.
 */
class AdminLocationController extends Controller
{
    public function index(): View
    {
        return view('admin.locations.index', [
            'locations' => Location::query()->orderBy('region')->orderBy('name')->paginate(15),
        ]);
    }

    public function edit(Location $location): View
    {
        return view('admin.locations.edit', [
            'location' => $location,
        ]);
    }

    public function update(UpdateLocationRequest $request, Location $location): RedirectResponse
    {
        $location->fill($request->validated());
        // Checkbox values are normalized in the request, but we still write explicitly for clarity.
        $location->is_safe_zone = $request->boolean('is_safe_zone');
        $location->save();

        return to_route('admin.locations.edit', $location)->with('status', 'Location updated.');
    }
}
