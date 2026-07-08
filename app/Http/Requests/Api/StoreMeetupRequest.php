<?php

namespace App\Http\Requests\Api;

use App\Models\Meetup;
use Illuminate\Foundation\Http\FormRequest;

class StoreMeetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Meetup::class);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'intro' => ['nullable', 'string'],
            'telegram_link' => ['nullable', 'url', 'max:255'],
            'webpage' => ['nullable', 'url', 'max:255'],
            'twitter_username' => ['nullable', 'string', 'max:255'],
            'matrix_group' => ['nullable', 'string', 'max:255'],
            'nostr' => ['nullable', 'string'],
            'simplex' => ['nullable', 'string'],
            'signal' => ['nullable', 'string', 'max:255'],
            'community' => ['nullable', 'string', 'max:255'],
            'visible_on_map' => ['boolean'],
            'is_active' => ['boolean'],
            'rsvp_enabled' => ['boolean'],
            'attendees_public' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'city_id.exists' => 'Die angegebene Stadt existiert nicht.',
        ];
    }
}
