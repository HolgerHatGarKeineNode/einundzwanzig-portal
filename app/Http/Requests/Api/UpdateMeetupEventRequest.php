<?php

namespace App\Http\Requests\Api;

use App\Enums\RecurrenceType;
use App\Http\Requests\Concerns\ValidatesOsmPlace;
use App\Models\Meetup;
use App\Models\MeetupEvent;
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
            /**
             * Move the date to a different meetup. Omit to leave it where it is.
             *
             * @example 17
             */
            'meetup_id' => ['sometimes', 'required', 'integer', 'exists:meetups,id'],

            /**
             * Start of the event, ISO 8601.
             *
             * @example 2026-09-01T19:00:00+02:00
             */
            'start' => ['sometimes', 'required', 'date'],

            /**
             * A headline for this one date. Send `null` to fall back to the meetup's name.
             *
             * @example Einsteigerabend: Wallets einrichten
             */
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * End of THIS occurrence — `recurrence_end_date` ends the series. Send `null`
             * to clear it. Compared against `start` when both are sent; a PATCH of this
             * field alone is taken at face value.
             *
             * @example 2026-09-01T22:00:00+02:00
             */
            // The Rule::when guard that used to sit here was written on the assumption that
            // a bare `after:start` blows up when `start` is absent. Measured against Laravel
            // 12, it does not: the rule is skipped silently, even for a date in the past.
            // The guard behaved identically to the plain form, so the plain form stays.
            'end' => ['sometimes', 'nullable', 'date', 'after:start'],

            /**
             * The address in plain words. Send `null` to clear it.
             *
             * @example Café Mustermann, Hauptstr. 1
             */
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            ...$this->osmPlaceRules(partial: true),

            /**
             * Free text about this date. Plain text, no markup is rendered.
             */
            'description' => ['sometimes', 'nullable', 'string'],

            /**
             * Where to read more or sign up.
             *
             * DEPRECATED (issue #70): use `links`. Still accepted and stored as a
             * one-entry list, which REPLACES whatever list the event had. When both are
             * sent, `links` wins and this value is dropped.
             *
             * @example https://example.com/meetup
             */
            'link' => ['sometimes', 'nullable', 'url', 'max:255'],

            /**
             * Every place this date is announced — Meetup.com, Luma, the group's own
             * site, Telegram, a Nostr note. At most five. Sending it replaces the whole
             * list; send `[]` to remove every link. Omit it — or send `null`, which is
             * read as "not given" — to leave the list alone. A sixth entry is rejected;
             * nothing is silently dropped.
             *
             * The null case is enforced on the MODEL ({@see MeetupEvent::booted()}), not
             * by the `sometimes` below: Laravel counts an explicitly sent null as
             * present, so it passes validation and reaches update() like any other
             * value. Until that guard existed, this very docblock described behaviour
             * the code did not have, and the request quietly emptied a stored list.
             */
            'links' => ['sometimes', 'nullable', 'array', 'max:'.MeetupEvent::MAX_LINKS],

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
             * Makes this a recurring series, or `null` to turn it back into a single date.
             */
            'recurrence_type' => ['sometimes', 'nullable', Rule::enum(RecurrenceType::class)],

            /**
             * Which weekday the series repeats on.
             *
             * @example monday
             */
            'recurrence_day_of_week' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * Which occurrence of that weekday in the month.
             *
             * @example first
             */
            'recurrence_day_position' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * How many units between repeats. At least 1 — 2 with a weekly type means
             * fortnightly.
             *
             * @example 1
             */
            'recurrence_interval' => ['sometimes', 'nullable', 'integer', 'min:1'],

            /**
             * When the SERIES stops. Compared against `start` when both are sent; a PATCH
             * of this field alone is taken at face value.
             *
             * @example 2027-06-30T00:00:00+02:00
             */
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
