<?php

namespace App\Http\Requests\Api;

use App\Enums\RsvpStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RsvpMeetupEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any authenticated user may RSVP to or decline a meetup event.
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(RsvpStatus::class)],
        ];
    }
}
