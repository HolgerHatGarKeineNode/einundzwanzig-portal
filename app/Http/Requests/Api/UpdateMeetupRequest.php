<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('meetup'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'city_id' => ['sometimes', 'required', 'integer', 'exists:cities,id'],
            'intro' => ['sometimes', 'nullable', 'string'],
            'telegram_link' => ['sometimes', 'nullable', 'url', 'max:255'],
            'webpage' => ['sometimes', 'nullable', 'url', 'max:255'],
            'twitter_username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'matrix_group' => ['sometimes', 'nullable', 'string', 'max:255'],
            'nostr' => ['sometimes', 'nullable', 'string'],
            'simplex' => ['sometimes', 'nullable', 'string'],
            'signal' => ['sometimes', 'nullable', 'string', 'max:255'],
            'community' => ['sometimes', 'nullable', 'string', 'max:255'],
            'visible_on_map' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'rsvp_enabled' => ['sometimes', 'boolean'],
            'attendees_public' => ['sometimes', 'boolean'],
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
