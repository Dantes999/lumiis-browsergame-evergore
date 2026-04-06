<?php

namespace App\Http\Requests\Admin;

use App\Models\Shop;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for admin-side vendor and shop inventory edits.
 */
class UpdateShopRequest extends FormRequest
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
        /** @var Shop $shop */
        $shop = $this->route('shop');

        return [
            'slug' => ['required', 'string', 'max:120', Rule::unique('shops', 'slug')->ignore($shop)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')],
            'location_area_id' => ['nullable', 'integer', Rule::exists('location_areas', 'id')],
            'shop_item_rows' => ['nullable', 'array'],
            'shop_item_rows.*.item_slug' => ['nullable', 'string', Rule::exists('items', 'slug')],
            'shop_item_rows.*.price' => ['nullable', 'integer', 'min:0'],
            'shop_item_rows.*.stock' => ['nullable', 'integer', 'min:0'],
            'shop_item_rows.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
