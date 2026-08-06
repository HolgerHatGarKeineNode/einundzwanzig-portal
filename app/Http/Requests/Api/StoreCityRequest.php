<?php

namespace App\Http\Requests\Api;

use App\Models\City;
use Illuminate\Foundation\Http\FormRequest;

class StoreCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', City::class);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'name' => ['required', 'string', 'max:255'],
            'longitude' => ['required', 'numeric'],
            'latitude' => ['required', 'numeric'],
            'population' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'country_id.exists' => __('Das angegebene Land existiert nicht.'),
        ];
    }
}
