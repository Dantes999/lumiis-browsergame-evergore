<?php

namespace App\Http\Requests\Admin;

use App\Models\Monster;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for monster template edits in the admin area.
 */
class UpdateMonsterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Monster $monster */
        $monster = $this->route('monster');

        return [
            'slug' => ['required', 'string', 'max:120', Rule::unique('monsters', 'slug')->ignore($monster)],
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', 'integer', 'min:1'],
            'rank' => ['required', 'integer', 'min:1'],
            'race' => ['required', 'string', 'max:120'],
            'damage_type' => ['required', 'string', 'max:120'],
            'sprite' => ['nullable', 'string', 'max:255'],
            'maximum_health' => ['required', 'integer', 'min:1'],
            'minimum_attack' => ['required', 'integer', 'min:0'],
            'maximum_attack' => ['required', 'integer', 'gte:minimum_attack'],
            'defense' => ['required', 'integer', 'min:0'],
            'experience_reward' => ['required', 'integer', 'min:0'],
            'yang_min' => ['required', 'integer', 'min:0'],
            'yang_max' => ['required', 'integer', 'gte:yang_min'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
