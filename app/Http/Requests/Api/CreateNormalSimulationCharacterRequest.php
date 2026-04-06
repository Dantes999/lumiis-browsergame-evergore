<?php

namespace App\Http\Requests\Api;

use App\Support\Game\MetinClassCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateNormalSimulationCharacterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'min:3', 'max:20', 'regex:/^[A-Za-z0-9 _-]+$/', Rule::unique('game_characters', 'name')],
            'race' => ['nullable', Rule::in(array_keys(MetinClassCatalog::races()))],
            'level' => ['nullable', 'integer', 'min:1', 'max:40'],
            'location_slug' => ['nullable', 'string', Rule::exists('locations', 'slug')],
            'equip_loadout' => ['nullable', 'boolean'],
            'include_supplies' => ['nullable', 'boolean'],
        ];
    }
}
