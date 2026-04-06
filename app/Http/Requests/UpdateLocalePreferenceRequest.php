<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tiny dedicated request for the in-game language switcher.
 *
 * Keeping this separate from the full profile form lets the dashboard settings
 * overlay update only the locale without having to repost the login email.
 */
class UpdateLocalePreferenceRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // The dashboard settings only persist a concrete language. Browser
            // detection remains an automatic fallback when no preference exists.
            'locale_preference' => ['required', Rule::in(['de', 'en'])],
        ];
    }
}
