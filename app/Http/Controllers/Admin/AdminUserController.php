<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin support controller for account-level changes.
 */
class AdminUserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()
                ->with('character')
                ->orderByDesc('is_admin')
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user->load('character'),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        // Keep at least one admin account in the system so the panel cannot lock itself out.
        if ($user->is_admin && ! $request->boolean('is_admin') && User::query()->where('is_admin', true)->count() === 1) {
            return back()->withErrors([
                'is_admin' => 'You cannot remove the last admin account.',
            ]);
        }

        $user->fill($request->validated());
        $user->is_admin = $request->boolean('is_admin');
        $user->save();

        return to_route('admin.users.edit', $user)->with('status', 'User updated.');
    }
}
