<?php

namespace App\Http\Requests\Admin;

use App\Models\LocationConnection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation for editing map travel routes.
 */
class UpdateLocationConnectionRequest extends FormRequest
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
        return [
            'from_location_id' => ['required', 'integer', Rule::exists('locations', 'id')],
            'to_location_id' => ['required', 'integer', 'different:from_location_id', Rule::exists('locations', 'id')],
            'travel_seconds' => ['required', 'integer', 'min:1', 'max:3600'],
            'stamina_cost' => ['required', 'integer', 'min:0', 'max:99'],
            'required_level' => ['required', 'integer', 'min:1', 'max:120'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filled('from_location_id') || ! $this->filled('to_location_id')) {
                    return;
                }

                /** @var LocationConnection|null $connection */
                $connection = $this->route('connection');

                // Prevent duplicate directed edges in the travel graph.
                $duplicateRoute = LocationConnection::query()
                    ->where('from_location_id', $this->integer('from_location_id'))
                    ->where('to_location_id', $this->integer('to_location_id'))
                    ->when($connection, fn ($query) => $query->whereKeyNot($connection->id))
                    ->exists();

                if ($duplicateRoute) {
                    $validator->errors()->add('to_location_id', 'This route already exists.');
                }
            },
        ];
    }
}
