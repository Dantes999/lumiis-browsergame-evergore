<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // If the guest previously picked a session locale, persist it to the
        // user's account so the preference becomes permanent.
        $sessionLocale = $request->session()->get('app_locale');
        if ($sessionLocale && $request->user()) {
            $user = $request->user();
            if (data_get($user->getAttributes(), 'locale') !== $sessionLocale) {
                $user->forceFill(['locale' => $sessionLocale])->save();
                $request->session()->put('app_locale', $sessionLocale);
            }
        }

        return redirect(route('characters.index', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
