<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Concerns\ValidatesOsmPlace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'course_id' => ['sometimes', 'required', 'integer', 'exists:courses,id'],
            'city_id' => ['sometimes', 'required', 'integer', 'exists:cities,id'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            ...$this->osmPlaceRules(partial: true),
            'from' => ['sometimes', 'required', 'date'],
            /*
             * after_or_equal only applies when `from` actually travels with the request.
             * Left unconditional, a PATCH that sends just `to` makes Laravel read "from"
             * as a date literal instead of a field reference, and the comparison silently
             * measures against nonsense.
             */
            'to' => ['sometimes', 'required', 'date', Rule::when($this->has('from'), ['after_or_equal:from'])],
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
