<?php

namespace App\Http\Requests\Api;

use App\Enums\RecurrenceType;
use App\Http\Requests\Concerns\ValidatesOsmPlace;
use App\Models\Meetup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeetupEventRequest extends FormRequest
{
    use ValidatesOsmPlace;

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
            /**
             * The meetup this date belongs to. Find ids via `GET /api/meetups`.
             *
             * @example 17
             */
            'meetup_id' => ['required', 'integer', 'exists:meetups,id'],

            /**
             * Start of the event, ISO 8601. Stored and returned in UTC.
             *
             * @example 2026-09-01T19:00:00+02:00
             */
            'start' => ['required', 'date'],

            /**
             * A headline for this one date, e.g. "Beginners evening: setting up wallets".
             *
             * Optional — without it the meetup's own name is shown. Use it when a single
             * date has a topic of its own; leave it empty for the regular get-together.
             *
             * @example Einsteigerabend: Wallets einrichten
             */
            'title' => ['nullable', 'string', 'max:255'],

            /**
             * End of THIS occurrence, ISO 8601 — not the end of a recurring series, which is
             * `recurrence_end_date`.
             *
             * Optional: many meetups run open-ended. A time earlier in the day than `start`
             * is rejected here; an event that runs past midnight needs the next day's date.
             *
             * @example 2026-09-01T22:00:00+02:00
             */
            'end' => ['nullable', 'date', 'after:start'],

            /**
             * The address in plain words, as the organiser would write it on a flyer.
             *
             * Always applies — "Café Mustermann, Hauptstr. 1" as much as "watch the Signal
             * group". The `osm_*` fields are the machine-readable addition, and optional.
             *
             * @example Café Mustermann, Hauptstr. 1
             */
            'location' => ['nullable', 'string', 'max:255'],
            ...$this->osmPlaceRules(),

            /**
             * Free text about this date. Plain text, no markup is rendered.
             */
            'description' => ['nullable', 'string'],

            /**
             * Where to read more or sign up.
             *
             * @example https://example.com/meetup
             */
            'link' => ['nullable', 'url', 'max:255'],

            /**
             * Makes this a recurring series instead of a single date.
             */
            'recurrence_type' => ['nullable', Rule::enum(RecurrenceType::class)],

            /**
             * Which weekday the series repeats on, for weekly and monthly patterns.
             *
             * @example monday
             */
            'recurrence_day_of_week' => ['nullable', 'string', 'max:255'],

            /**
             * Which occurrence of that weekday in the month, e.g. "first" or "last".
             *
             * @example first
             */
            'recurrence_day_position' => ['nullable', 'string', 'max:255'],

            /**
             * How many units between repeats — 2 with a weekly type means fortnightly.
             *
             * @example 1
             */
            'recurrence_interval' => ['nullable', 'integer'],

            /**
             * When the series stops. The end of the SERIES — for the end of a single
             * occurrence use `end`.
             *
             * @example 2027-06-30T00:00:00+02:00
             */
            'recurrence_end_date' => ['nullable', 'date', 'after_or_equal:start'],
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
