<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLecturerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('lecturer'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'subtitle' => ['sometimes', 'nullable', 'string'],
            'intro' => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'twitter_username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'nostr' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lightning_address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lnurl' => ['sometimes', 'nullable', 'string'],
            'node_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'paynym' => ['sometimes', 'nullable', 'string'],
            'team_id' => ['sometimes', 'nullable', 'integer', 'exists:teams,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'team_id.exists' => __('Das angegebene Team existiert nicht.'),
        ];
    }
}
