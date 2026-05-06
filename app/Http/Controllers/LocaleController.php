<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLocalePreferenceRequest;
use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    /**
     * Set a session-scoped locale for guests.
     */
    public function update(UpdateLocalePreferenceRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Store the chosen locale in session so the SetPreferredLocale middleware
        // will pick it up on subsequent requests. We intentionally do not persist
        // anything to the user record here because guests are not authenticated.
        $request->session()->put('app_locale', $validated['locale_preference']);

        return redirect()->back();
    }
}
