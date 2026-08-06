<?php

namespace App\Http\Requests\Api;

use App\Enums\RecurrenceType;
use App\Models\Meetup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeetupEventRequest extends FormRequest
{
    /**
     * Only someone allowed to edit the associated meetup (creator/leader/super
     * admin) may create meetup events — the same permission as for the master
     * data. rules() checks that meetup_id is required and exists (422); if a
     * valid meetup is given, the user must be authorized for it.
     */
    public function authorize(): bool
    {
        $meetup = Meetup::find($this->input('meetup_id'));

        return $meetup === null || $this->user()->can('update', $meetup);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'meetup_id' => ['required', 'integer', 'exists:meetups,id'],
            'start' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'link' => ['nullable', 'url', 'max:255'],
            'recurrence_type' => ['nullable', Rule::enum(RecurrenceType::class)],
            'recurrence_day_of_week' => ['nullable', 'string', 'max:255'],
            'recurrence_day_position' => ['nullable', 'string', 'max:255'],
            'recurrence_interval' => ['nullable', 'integer'],
            'recurrence_end_date' => ['nullable', 'date', 'after_or_equal:start'],
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
