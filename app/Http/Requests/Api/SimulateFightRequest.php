<?php

namespace App\Http\Requests\Api;

use App\Support\Game\MetinClassCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SimulateFightRequest extends FormRequest
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
            'character_id' => ['nullable', 'integer', Rule::exists('game_characters', 'id')],
            'area_slug' => ['required', 'string', Rule::exists('location_areas', 'slug')],
            'mode' => ['nullable', Rule::in(['auto', 'manual'])],
            'encounter' => ['nullable', Rule::in(['monster', 'lumiis', 'boss'])],
            'strategy' => ['nullable', Rule::in(['attack-only', 'auto'])],
            'iterations' => ['nullable', 'integer', 'min:1', 'max:100'],
            'include_runs' => ['nullable', 'boolean'],
            'profile' => ['nullable', 'array'],
            'profile.name' => ['nullable', 'string', 'min:3', 'max:20', 'regex:/^[A-Za-z0-9 _-]+$/'],
            'profile.race' => ['nullable', Rule::in(array_keys(MetinClassCatalog::races()))],
            'profile.level' => ['nullable', 'integer', 'min:1', 'max:40'],
            'profile.location_slug' => ['nullable', 'string', Rule::exists('locations', 'slug')],
            'profile.equip_loadout' => ['nullable', 'boolean'],
            'profile.include_supplies' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $characterId = $this->integer('character_id');
            $iterations = max(1, $this->integer('iterations', 1));
            $mode = (string) $this->input('mode', '');
            $encounter = (string) $this->input('encounter', 'monster');
            $profile = $this->input('profile');

            if ($characterId > 0 && is_array($profile) && $profile !== []) {
                $validator->errors()->add('profile', 'Use either character_id or profile, not both.');
            }

            if ($characterId > 0 && $iterations > 1) {
                $validator->errors()->add('iterations', 'iterations can only exceed 1 when the API creates fresh simulation characters.');
            }

            if ($mode === 'auto' && $encounter === 'boss') {
                $validator->errors()->add('mode', 'Boss encounters are manual-only.');
            }
        });
    }
}
