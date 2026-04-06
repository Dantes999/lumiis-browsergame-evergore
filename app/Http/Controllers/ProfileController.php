<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\UpdateLocalePreferenceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

/**
 * Handles the small account profile surface outside the actual game systems.
 */
class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $request->user()->fill([
            'email' => $validated['email'],
            'locale' => $this->storedLocalePreference($validated['locale_preference']),
        ]);

        // Changing the login email invalidates verification so the security state stays trustworthy.
        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();
        $this->syncLocaleSession($request);

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Small dedicated endpoint for the dashboard settings overlay.
     */
    public function updateLocale(UpdateLocalePreferenceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $request->user()->forceFill([
            'locale' => $this->storedLocalePreference($validated['locale_preference']),
        ])->save();
        $this->syncLocaleSession($request);

        return Redirect::back()->with('status', __('Language updated.'));
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // We log the user out first so the session is not left referencing a deleted account.
        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    private function syncLocaleSession(Request $request): void
    {
        $storedLocale = data_get($request->user()->getAttributes(), 'locale');

        if ($storedLocale === null) {
            // Legacy users without a stored locale should keep following browser
            // detection until they explicitly save a language.
            $request->session()->forget('app_locale');

            return;
        }

        $request->session()->put('app_locale', $storedLocale);
    }

    private function storedLocalePreference(string $localePreference): string
    {
        return $localePreference;
    }
}
