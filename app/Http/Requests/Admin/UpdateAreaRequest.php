<?php

namespace App\Http\Requests\Admin;

use App\Models\LocationArea;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for hotspot and hunting area balancing edits.
 */
class UpdateAreaRequest extends FormRequest
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
        /** @var LocationArea $area */
        $area = $this->route('area');

        return [
            'slug' => ['required', 'string', 'max:120', Rule::unique('location_areas', 'slug')->ignore($area)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:4000'],
            'area_kind' => ['required', Rule::in(['service', 'hunting'])],
            'service_type' => ['nullable', 'string', 'max:60'],
            'hotspot_x' => ['required', 'numeric', 'between:0,100'],
            'hotspot_y' => ['required', 'numeric', 'between:0,100'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'recommended_level' => ['nullable', 'integer', 'min:1'],
            'monster_name' => ['nullable', 'string', 'max:255'],
            'lumiis_name' => ['nullable', 'string', 'max:255'],
            'boss_name' => ['nullable', 'string', 'max:255'],
            'monster_cooldown_seconds' => ['nullable', 'integer', 'min:0'],
            'lumiis_cooldown_seconds' => ['nullable', 'integer', 'min:0'],
            'boss_cooldown_seconds' => ['nullable', 'integer', 'min:0'],
            'monster_exp_min' => ['nullable', 'integer', 'min:0'],
            'monster_exp_max' => ['nullable', 'integer', 'min:0'],
            'monster_yang_min' => ['nullable', 'integer', 'min:0'],
            'monster_yang_max' => ['nullable', 'integer', 'min:0'],
            'monster_damage_min' => ['nullable', 'integer', 'min:0'],
            'monster_damage_max' => ['nullable', 'integer', 'min:0'],
            'lumiis_exp_min' => ['nullable', 'integer', 'min:0'],
            'lumiis_exp_max' => ['nullable', 'integer', 'min:0'],
            'lumiis_yang_min' => ['nullable', 'integer', 'min:0'],
            'lumiis_yang_max' => ['nullable', 'integer', 'min:0'],
            'lumiis_damage_min' => ['nullable', 'integer', 'min:0'],
            'lumiis_damage_max' => ['nullable', 'integer', 'min:0'],
            'boss_exp_min' => ['nullable', 'integer', 'min:0'],
            'boss_exp_max' => ['nullable', 'integer', 'min:0'],
            'boss_yang_min' => ['nullable', 'integer', 'min:0'],
            'boss_yang_max' => ['nullable', 'integer', 'min:0'],
            'boss_damage_min' => ['nullable', 'integer', 'min:0'],
            'boss_damage_max' => ['nullable', 'integer', 'min:0'],
            'monster_pressure_gain' => ['nullable', 'integer', 'min:0'],
            'lumiis_pressure_gain' => ['nullable', 'integer', 'min:0'],
            'boss_pressure_threshold' => ['nullable', 'integer', 'min:0'],
            'boss_base_chance' => ['nullable', 'integer', 'min:0'],
            'boss_pressure_chance_per_point' => ['nullable', 'integer', 'min:0'],
            'monster_loot_rows' => ['nullable', 'array'],
            'monster_loot_rows.*.item_slug' => ['nullable', 'string', Rule::exists('items', 'slug')],
            'monster_loot_rows.*.chance' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'monster_loot_rows.*.quantity_min' => ['nullable', 'integer', 'min:1'],
            'monster_loot_rows.*.quantity_max' => ['nullable', 'integer', 'min:1'],
            'lumiis_loot_rows' => ['nullable', 'array'],
            'lumiis_loot_rows.*.item_slug' => ['nullable', 'string', Rule::exists('items', 'slug')],
            'lumiis_loot_rows.*.chance' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'lumiis_loot_rows.*.quantity_min' => ['nullable', 'integer', 'min:1'],
            'lumiis_loot_rows.*.quantity_max' => ['nullable', 'integer', 'min:1'],
            'boss_loot_rows' => ['nullable', 'array'],
            'boss_loot_rows.*.item_slug' => ['nullable', 'string', Rule::exists('items', 'slug')],
            'boss_loot_rows.*.chance' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'boss_loot_rows.*.quantity_min' => ['nullable', 'integer', 'min:1'],
            'boss_loot_rows.*.quantity_max' => ['nullable', 'integer', 'min:1'],
            'monster_assignment_rows' => ['nullable', 'array'],
            'monster_assignment_rows.*.monster_slug' => ['nullable', 'string', Rule::exists('monsters', 'slug')],
            'monster_assignment_rows.*.weight' => ['nullable', 'integer', 'min:1'],
            'monster_assignment_rows.*.minimum_character_level' => ['nullable', 'integer', 'min:1'],
            'monster_assignment_rows.*.maximum_character_level' => ['nullable', 'integer', 'min:1'],
            'monster_assignment_rows.*.pack_size' => ['nullable', 'integer', 'min:1'],
            'monster_assignment_rows.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
