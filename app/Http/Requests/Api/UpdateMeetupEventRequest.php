<?php

namespace App\Http\Requests\Api;

use App\Enums\RecurrenceType;
use App\Http\Requests\Concerns\ValidatesOsmPlace;
use App\Models\Meetup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeetupEventRequest extends FormRequest
{
    use ValidatesOsmPlace;

    /**
     * The creator of the meetup event or a leader of the meetup may edit it
     * (see MeetupEventPolicy::update). Moving it to another meetup (changed
     * meetup_id) is only allowed if the user also leads that target meetup.
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
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            /*
             * End of THIS occurrence — recurrence_end_date below ends the series.
             *
             * after:start only applies when start is part of the same request. On a
             * PATCH that touches end alone there is no start field to compare against,
             * and the rule would resolve "start" as a literal date string and always fail.
             */
            'end' => ['sometimes', 'nullable', 'date', Rule::when($this->has('start'), ['after:start'])],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            ...$this->osmPlaceRules(partial: true),
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
            'meetup_id.exists' => __('Das angegebene Meetup existiert nicht.'),
        ];
    }
}
