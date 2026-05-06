<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // We intentionally return the same outward success message even when the
        // account does not exist so the reset form cannot be used to enumerate users.
        Password::sendResetLink($request->only('email'));

        // Use explicit translation key so translations live in our files,
        // rather than relying on facade-returned strings.
        return back()->with('status', __('passwords.sent'));
    }
}
