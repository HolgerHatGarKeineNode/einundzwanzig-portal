<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Concerns\ValidatesOsmPlace;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseEventRequest extends FormRequest
{
    use ValidatesOsmPlace;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('courseEvent'));
    }

    /**
     * Mirrors StoreCourseEventRequest, but every field is `sometimes` so a PATCH can touch
     * a single one. The `osm_*` values stay nullable so a map place can be cleared; the
     * city cannot, for the same reason it is required on create — an event without one
     * drops out of every country-filtered listing.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /**
             * Move the date to a different course. Omit to leave it where it is.
             *
             * @example 42
             */
            'course_id' => ['sometimes', 'required', 'integer', 'exists:courses,id'],

            /**
             * The town the event takes place in. May be changed but not cleared — an event
             * without a city drops out of every country-filtered listing.
             *
             * @example 361
             */
            'city_id' => ['sometimes', 'required', 'integer', 'exists:cities,id'],

            /**
             * The address in plain words. Send `null` to clear it.
             *
             * @example Bürgerhaus Neumarkt, Fischergasse 1
             */
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            ...$this->osmPlaceRules(partial: true),

            /**
             * Start of the event, ISO 8601.
             *
             * @example 2026-09-01T17:00:00+02:00
             */
            'from' => ['sometimes', 'required', 'date'],
            /**
             * End of the event, ISO 8601. Compared against `from` when both are sent; a
             * PATCH of this field alone is taken at face value.
             *
             * @example 2026-09-01T20:00:00+02:00
             */
            // Plain rule on purpose: Laravel 12 skips `after_or_equal:from` silently when
            // `from` is not in the request, so a Rule::when guard around it would add words
            // without adding behaviour. Verified, not assumed.
            'to' => ['sometimes', 'required', 'date', 'after_or_equal:from'],

            /**
             * Where to read more or sign up.
             *
             * @example https://einundzwanzig.space/courses/bitcoin-basics
             */
            'link' => ['sometimes', 'required', 'url', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'course_id.exists' => __('Der angegebene Kurs existiert nicht.'),
            'city_id.exists' => __('Die angegebene Stadt existiert nicht.'),
        ];
    }
}
