<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('venue'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'city_id' => ['sometimes', 'required', 'integer', 'exists:cities,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'street' => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'city_id.exists' => __('Die angegebene Stadt existiert nicht.'),
        ];
    }
}
