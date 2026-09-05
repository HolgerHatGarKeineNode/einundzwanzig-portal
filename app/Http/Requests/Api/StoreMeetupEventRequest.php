<?php

namespace App\Http\Requests\Api;

use App\Enums\RecurrenceType;
use App\Http\Requests\Concerns\ValidatesOsmPlace;
use App\Models\Meetup;
use App\Models\MeetupEvent;
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
             * DEPRECATED (issue #70): use `links`. Still accepted and stored as a
             * one-entry list, so an existing client keeps working unchanged. When both
             * are sent, `links` wins and this value is dropped.
             *
             * @example https://example.com/meetup
             */
            'link' => ['nullable', 'url', 'max:255'],

            /**
             * Every place this date is announced — Meetup.com, Luma, the group's own
             * site, Telegram, a Nostr note. At most five, and `[]` or omitted means none.
             * A sixth entry is rejected; nothing is silently dropped.
             *
             * An explicit `null` is read as "not given", not as "no links" — so a client
             * that sends every field it knows about does not wipe a `link` it sent in
             * the same request. Enforced on the model ({@see MeetupEvent::booted()}),
             * which is also what keeps the same null from emptying a STORED list on
             * `PATCH`.
             */
            'links' => ['nullable', 'array', 'max:'.MeetupEvent::MAX_LINKS],

            /**
             * The link itself.
             *
             * @example https://www.meetup.com/bitcoin-berlin/events/123456789/
             */
            'links.*.url' => ['required', 'url', 'max:255'],

            /**
             * What to call this link, e.g. "Meetup.com". Optional: an entry without a
             * label is stored and published as the bare URL.
             *
             * @example Meetup.com
             */
            'links.*.label' => ['nullable', 'string', 'max:100'],

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
             * At least 1. An interval of 0 would advance a series by nothing and fill the
             * whole 100-occurrence allowance with the same date.
             *
             * @example 1
             */
            'recurrence_interval' => ['nullable', 'integer', 'min:1'],

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
