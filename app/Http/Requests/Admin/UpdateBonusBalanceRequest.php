<?php

namespace App\Http\Requests\Admin;

use App\Support\Game\ItemBonusCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for the bonus balancing admin surface.
 */
class UpdateBonusBalanceRequest extends FormRequest
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
        return [
            'bonus_definitions' => ['required', 'array', 'min:1'],
            'bonus_definitions.*.id' => ['required', 'integer', Rule::exists('item_bonus_definitions', 'id')],
            'bonus_definitions.*.bonus_type' => ['required', 'string', 'max:50'],
            'bonus_definitions.*.label' => ['required', 'string', 'max:80'],
            'bonus_definitions.*.minimum_value' => ['required', 'integer', 'min:0'],
            'bonus_definitions.*.maximum_value' => ['required', 'integer', 'min:0'],
            'bonus_definitions.*.suffix' => ['nullable', 'string', 'max:20'],
            'bonus_definitions.*.allowed_slots' => ['nullable', 'array'],
            'bonus_definitions.*.allowed_slots.*' => ['string', Rule::in(ItemBonusCatalog::supportedSlots())],
            'bonus_definitions.*.enabled' => ['nullable', 'boolean'],
            'bonus_definitions.*.sort_order' => ['required', 'integer', 'min:0'],

            'soul_items' => ['required', 'array', 'min:1'],
            'soul_items.*.id' => ['required', 'integer', Rule::exists('items', 'id')],
            'soul_items.*.name' => ['required', 'string', 'max:255'],
            'soul_items.*.description' => ['required', 'string', 'max:2000'],
            'soul_items.*.sell_price' => ['required', 'integer', 'min:0'],
            'soul_items.*.required_level' => ['required', 'integer', 'min:1'],

            'area_drops' => ['required', 'array', 'min:1'],
            'area_drops.*.area_id' => ['required', 'integer', Rule::exists('location_areas', 'id')],
            'area_drops.*.lumiis_soul_imprint_chance' => ['required', 'integer', 'min:0', 'max:10000'],
            'area_drops.*.lumiis_soul_reweaver_chance' => ['required', 'integer', 'min:0', 'max:10000'],
            'area_drops.*.boss_soul_imprint_chance' => ['required', 'integer', 'min:0', 'max:10000'],
            'area_drops.*.boss_soul_reweaver_chance' => ['required', 'integer', 'min:0', 'max:10000'],
            'area_drops.*.boss_soul_anchor_chance' => ['required', 'integer', 'min:0', 'max:10000'],
            'area_drops.*.boss_soul_stone_chance' => ['required', 'integer', 'min:0', 'max:10000'],
            'area_drops.*.boss_soul_prism_chance' => ['required', 'integer', 'min:0', 'max:10000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $bonusDefinitions = collect($this->input('bonus_definitions', []))
            ->map(function (array $definition): array {
                $definition['enabled'] = filter_var($definition['enabled'] ?? false, FILTER_VALIDATE_BOOL);

                return $definition;
            })
            ->all();

        $this->merge([
            'bonus_definitions' => $bonusDefinitions,
        ]);
    }
}
