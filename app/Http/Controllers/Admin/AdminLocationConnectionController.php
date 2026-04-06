<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLocationConnectionRequest;
use App\Models\Location;
use App\Models\LocationConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Maintains the world travel graph.
 */
class AdminLocationConnectionController extends Controller
{
    public function index(): View
    {
        return view('admin.connections.index', [
            'connections' => LocationConnection::query()
                ->with(['fromLocation', 'toLocation'])
                ->orderBy('from_location_id')
                ->orderBy('to_location_id')
                ->paginate(20),
        ]);
    }

    public function edit(LocationConnection $connection): View
    {
        return view('admin.connections.edit', [
            'connection' => $connection->load(['fromLocation', 'toLocation']),
            'locations' => Location::query()->orderBy('region')->orderBy('name')->get(),
        ]);
    }

    /**
     * Travel routes are edited directly because they are purely configuration data.
     */
    public function update(UpdateLocationConnectionRequest $request, LocationConnection $connection): RedirectResponse
    {
        $connection->fill($request->validated());
        $connection->save();

        return to_route('admin.connections.edit', $connection)->with('status', 'Route updated.');
    }
}
