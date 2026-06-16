<?php

namespace App\Http\Requests\Api;

use App\Enums\RecurrenceType;
use App\Models\Meetup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeetupEventRequest extends FormRequest
{
    /**
     * Bearbeiten darf der Ersteller des Termins oder ein Leader des Meetups
     * (siehe MeetupEventPolicy::update). Ein Verschieben in ein anderes Meetup
     * (geändertes meetup_id) ist nur erlaubt, wenn der Nutzer auch dieses
     * Ziel-Meetup führt.
     */
    public function authorize(): bool
    {
        if (! $this->user()->can('update', $this->route('meetupEvent'))) {
            return false;
        }

        $target = $this->filled('meetup_id') ? Meetup::find($this->input('meetup_id')) : null;

        return $target === null || $this->user()->can('update', $target);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'meetup_id' => ['sometimes', 'required', 'integer', 'exists:meetups,id'],
            'start' => ['sometimes', 'required', 'date'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'link' => ['sometimes', 'nullable', 'url', 'max:255'],
            'recurrence_type' => ['sometimes', 'nullable', Rule::enum(RecurrenceType::class)],
            'recurrence_day_of_week' => ['sometimes', 'nullable', 'string', 'max:255'],
            'recurrence_day_position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'recurrence_interval' => ['sometimes', 'nullable', 'integer'],
            'recurrence_end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'meetup_id.exists' => 'Das angegebene Meetup existiert nicht.',
        ];
    }
}
