<?php

namespace App\Http\Requests\Api;

use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;

class StoreVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Venue::class);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'name' => ['required', 'string', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
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
