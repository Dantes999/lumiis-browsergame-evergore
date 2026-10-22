<?php

namespace App\Http\Requests\Admin;

use App\Models\Item;
use App\Models\Quest;
use App\Support\Game\MetinClassCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation for admin-side quest editing.
 */
class UpdateQuestRequest extends FormRequest
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
        /** @var Quest $quest */
        $quest = $this->route('quest');
        $objectiveType = (string) $this->input('objective_type');
        $areaObjectiveTypes = [
            'monster_fights_in_area',
            'lumiis_searches_in_area',
            'boss_fights_in_area',
        ];

        return [
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'slug' => ['required', 'string', 'max:120', Rule::unique('quests', 'slug')->ignore($quest)],
            'title' => ['required', 'string', 'max:255'],
            'story_text' => ['required', 'string', 'max:10000'],
            'description' => ['nullable', 'string', 'max:4000'],
            'feature_unlock_hint' => ['nullable', 'string', 'max:255'],
            'required_level' => ['required', 'integer', 'min:1', 'max:120'],
            'prerequisite_slug' => ['nullable', 'string', Rule::exists('quests', 'slug')],
            'objective_type' => ['required', Rule::in([
                'reach_level',
                'monster_fights_in_area',
                'lumiis_searches_in_area',
                'boss_fights_in_area',
                'deliver_item_to_biologist',
            ])],
            'objective_target' => ['required', 'integer', 'min:1'],
            'objective_area_slug' => [
                Rule::requiredIf(in_array($objectiveType, $areaObjectiveTypes, true)),
                'nullable',
                'string',
                Rule::exists('location_areas', 'slug'),
            ],
            'objective_item_slug' => [
                Rule::requiredIf($objectiveType === 'deliver_item_to_biologist'),
                'nullable',
                'string',
                Rule::exists('items', 'slug'),
            ],
            'objective_item_quantity' => [
                Rule::requiredIf($objectiveType === 'deliver_item_to_biologist'),
                'nullable',
                'integer',
                'min:1',
                'max:999',
            ],
            'objective_submission_cooldown_minutes' => [
                'nullable',
                'integer',
                'min:0',
                'max:10080',
            ],
            'objective_submission_success_rate' => [
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ],
            'reward_experience' => ['required', 'integer', 'min:0'],
            'reward_yang' => ['required', 'integer', 'min:0'],
            'reward_item_slug' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '' || $value === MetinClassCatalog::matchingSkillBookRewardToken()) {
                        return;
                    }

                    if (! Item::query()->where('slug', $value)->exists()) {
                        $fail(__('The selected reward item is invalid.'));
                    }
                },
            ],
            'reward_item_quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'reward_bonus_type' => ['nullable', 'string', Rule::in(array_keys(Quest::rewardBonusOptions()))],
            'reward_bonus_value' => ['nullable', 'integer', 'min:0', 'max:999'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Quest $quest */
            $quest = $this->route('quest');

            if ($this->input('prerequisite_slug') === $quest->slug) {
                $validator->errors()->add('prerequisite_slug', 'A quest cannot require itself.');
            }

            if ($this->input('reward_bonus_type') !== null && (int) $this->input('reward_bonus_value', 0) < 1) {
                $validator->errors()->add('reward_bonus_value', 'A permanent bonus reward needs a value greater than zero.');
            }

            if ($this->input('reward_bonus_type') === null && (int) $this->input('reward_bonus_value', 0) > 0) {
                $validator->errors()->add('reward_bonus_type', 'Choose which permanent bonus should be granted.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $nullableFields = [
            'location_id',
            'prerequisite_slug',
            'objective_area_slug',
            'objective_item_slug',
            'reward_item_slug',
            'reward_bonus_type',
        ];
        $normalized = [];

        foreach ($nullableFields as $field) {
            if ($this->input($field) === '') {
                $normalized[$field] = null;
            }
        }

        $this->merge([
            ...$normalized,
            'is_active' => filter_var($this->input('is_active', false), FILTER_VALIDATE_BOOL),
            'objective_item_quantity' => $this->input('objective_item_quantity', 1),
            'objective_submission_cooldown_minutes' => $this->input('objective_submission_cooldown_minutes', 0),
            'objective_submission_success_rate' => $this->input('objective_submission_success_rate', 100),
            'reward_bonus_value' => $this->input('reward_bonus_value', 0),
        ]);
    }
}
