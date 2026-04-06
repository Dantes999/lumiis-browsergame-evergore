<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for bulk updates to the smith refine rate table.
 */
class UpdateSmithRefineRatesRequest extends FormRequest
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
            'rates' => ['required', 'array', 'min:1'],
            'rates.*.id' => ['required', 'integer', Rule::exists('smith_refine_rates', 'id')],
            'rates.*.upgrade_level' => ['required', 'integer', 'min:0', 'max:8'],
            'rates.*.success_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'rates.*.yang_cost' => ['required', 'integer', 'min:0'],
            'rates.*.lumiis_shard_cost' => ['required', 'integer', 'min:0'],
            'rates.*.destroy_on_fail' => ['nullable', 'boolean'],
            'rates.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $rates = collect($this->input('rates', []))
            ->map(function (array $rate): array {
                $rate['destroy_on_fail'] = filter_var($rate['destroy_on_fail'] ?? false, FILTER_VALIDATE_BOOL);

                return $rate;
            })
            ->all();

        $this->merge([
            'rates' => $rates,
        ]);
    }
}
