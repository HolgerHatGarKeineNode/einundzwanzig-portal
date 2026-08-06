<?php

namespace App\Http\Requests\Api;

use App\Models\Lecturer;
use Illuminate\Foundation\Http\FormRequest;

class StoreLecturerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Lecturer::class);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string'],
            'intro' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'active' => ['boolean'],
            'website' => ['nullable', 'url', 'max:255'],
            'twitter_username' => ['nullable', 'string', 'max:255'],
            'nostr' => ['nullable', 'string', 'max:255'],
            'lightning_address' => ['nullable', 'string', 'max:255'],
            'lnurl' => ['nullable', 'string'],
            'node_id' => ['nullable', 'string', 'max:255'],
            'paynym' => ['nullable', 'string'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
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
