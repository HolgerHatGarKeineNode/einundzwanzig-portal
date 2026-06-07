<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('city'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'country_id' => ['sometimes', 'required', 'integer', 'exists:countries,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'longitude' => ['sometimes', 'required', 'numeric'],
            'latitude' => ['sometimes', 'required', 'numeric'],
            'population' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'country_id.exists' => 'Das angegebene Land existiert nicht.',
        ];
    }
}
