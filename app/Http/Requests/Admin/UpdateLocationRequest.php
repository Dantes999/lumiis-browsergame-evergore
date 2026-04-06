<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for editable map metadata in the admin area.
 */
class UpdateLocationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $location = $this->route('location');

        return [
            'slug' => ['required', 'string', 'alpha_dash', 'max:255', Rule::unique('locations', 'slug')->ignore($location)],
            'name' => ['required', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'threat_level' => ['required', 'integer', 'min:0', 'max:10'],
            'is_safe_zone' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // HTML checkboxes send inconsistent values, so we normalize once here.
        $this->merge([
            'is_safe_zone' => $this->boolean('is_safe_zone'),
        ]);
    }
}
