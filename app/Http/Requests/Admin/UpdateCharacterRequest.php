<?php

namespace App\Http\Requests\Admin;

use App\Enums\GameCharacterState;
use App\Models\GameCharacter;
use App\Support\Game\MetinClassCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Validation for direct character edits inside the admin panel.
 */
class UpdateCharacterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->input('skill_group') === '') {
            $this->merge([
                'skill_group' => null,
            ]);
        }
    }

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
        /** @var GameCharacter $character */
        $character = $this->route('character');
        $tutorialStepCount = count(config('game_tutorial.steps', []));

        // Admin edits write raw character state, so the rule set mirrors the persisted columns closely.
        return [
            'name' => ['required', 'string', 'min:3', 'max:20', Rule::unique('game_characters', 'name')->ignore($character)],
            'faction' => ['required', Rule::in(['Shinsoo', 'Chunjo', 'Jinno'])],
            'race' => ['required', Rule::in(array_keys(MetinClassCatalog::races()))],
            'skill_group' => ['nullable', 'string', 'max:40'],
            'game_state' => ['required', Rule::in(GameCharacterState::values())],
            'tutorial_step' => ['nullable', 'integer', 'min:1', 'max:'.$tutorialStepCount],
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')],
            'level' => ['required', 'integer', 'min:1'],
            'experience' => ['required', 'integer', 'min:0'],
            'yang' => ['required', 'integer', 'min:0'],
            'health' => ['required', 'integer', 'min:0'],
            'max_health' => ['required', 'integer', 'min:1'],
            'stamina' => ['required', 'integer', 'min:0'],
            'max_stamina' => ['required', 'integer', 'min:1'],
            'attack' => ['required', 'integer', 'min:0'],
            'defense' => ['required', 'integer', 'min:0'],
            'speed' => ['required', 'integer', 'min:0'],
            'action_available_at' => ['nullable', 'date'],
            'last_active_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $race = (string) $this->input('race');
            $skillGroup = $this->input('skill_group');

            if ($skillGroup !== null && $skillGroup !== '' && ! MetinClassCatalog::hasSkillGroup($race, (string) $skillGroup)) {
                $validator->errors()->add('skill_group', 'This combat school does not belong to the selected race.');
            }
        });
    }
}
