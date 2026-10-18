<?php

namespace App\Http\Requests\Api;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

/**
 * Filters of the public event list (`GET /api/meetup-events`), issue #163.
 *
 * Every filter is optional and they combine with AND.
 *
 * Without `from`, `to` and `upcoming`, the bare list path starts
 * {@see self::DEFAULT_PAST_DAYS} days back instead of serving the whole table: the
 * history back to 2022 made up most of a 5 MB response that no consumer showed. A
 * deliberate change of the default, decided on #163; `from` reaches any older day.
 * The dated path `/meetup-events/{date}` is a month view and gets no default window.
 *
 * An unknown query parameter is a validation error, not a no-op. Before this request
 * existed every parameter was silently ignored, and a consumer that misspelled a filter
 * received the whole table without any sign that its filter had not been applied.
 */
class ListMeetupEventsRequest extends FormRequest
{
    /**
     * Every query parameter this endpoint understands. `locale` predates the filters and
     * is read by the controller directly.
     */
    public const KNOWN_PARAMETERS = ['locale', 'meetup_id', 'country', 'from', 'to', 'upcoming'];

    /**
     * How far back the bare list reaches when the caller sets no date range.
     */
    public const DEFAULT_PAST_DAYS = 7;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * `?meetup_id=1,2` and `?meetup_id[]=1&meetup_id[]=2` mean the same, as `resource`
     * does on `/api/changes`; `?from=today` is the date of today in UTC.
     */
    protected function prepareForValidation(): void
    {
        $meetupId = $this->query('meetup_id');

        if (is_string($meetupId)) {
            $this->merge([
                'meetup_id' => array_values(array_filter(
                    array_map('trim', explode(',', $meetupId)),
                    static fn (string $value): bool => $value !== '',
                )),
            ]);
        }

        if ($this->query('from') === 'today') {
            $this->merge(['from' => CarbonImmutable::now('UTC')->toDateString()]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'meetup_id' => ['sometimes', 'array', 'min:1'],
            /** Restrict to these meetups. Repeatable (meetup_id[]=1&meetup_id[]=2) or comma separated (meetup_id=1,2). */
            'meetup_id.*' => ['integer', 'min:1'],
            /** Restrict to meetups in this country (ISO 3166-1 alpha-2, case-insensitive, e.g. "cz"). */
            'country' => ['sometimes', 'string', 'size:2', 'alpha'],
            /** Events that have not ended before this day (Y-m-d, UTC): the end, or the start when there is no end, is on or after it. `today` is accepted. Without `from`, `to` and `upcoming`, the list without a date path starts 7 days back; pass an older `from` for the history. */
            'from' => ['sometimes', 'date_format:Y-m-d'],
            /** Events that start on or before this day (Y-m-d, UTC). */
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            /** Shortcut for from=today. Cannot be combined with `from`. */
            'upcoming' => ['sometimes', 'boolean', 'prohibits:from'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (array_diff(array_keys($this->query()), self::KNOWN_PARAMETERS) as $unknown) {
                    $validator->errors()->add(
                        (string) $unknown,
                        sprintf('Unknown query parameter "%s". Supported: %s.', $unknown, implode(', ', self::KNOWN_PARAMETERS)),
                    );
                }
            },
        ];
    }

    /**
     * Always answers with the JSON 422, whatever the `Accept` header says. The default
     * redirects a request that does not ask for JSON, and a `curl` or a browser address
     * bar with a mistyped filter would then land on an HTML page instead of the error.
     */
    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }

    /**
     * @return array<int, int>
     */
    public function meetupIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->validated('meetup_id', []))));
    }

    public function country(): ?string
    {
        $country = $this->validated('country');

        return $country === null ? null : strtolower($country);
    }

    /**
     * Start of the `from` day, or of today when `upcoming` is set. With neither, and no
     * `to` either, the default window applies when the caller asks for it.
     */
    public function from(bool $withDefaultWindow = false): ?CarbonImmutable
    {
        if ($this->boolean('upcoming')) {
            return CarbonImmutable::now('UTC')->startOfDay();
        }

        $from = $this->validated('from');

        if ($from !== null) {
            return CarbonImmutable::createFromFormat('Y-m-d', $from, 'UTC')->startOfDay();
        }

        if ($withDefaultWindow && $this->validated('to') === null) {
            return CarbonImmutable::now('UTC')->subDays(self::DEFAULT_PAST_DAYS)->startOfDay();
        }

        return null;
    }

    /**
     * End of the `to` day.
     */
    public function to(): ?CarbonImmutable
    {
        $to = $this->validated('to');

        return $to === null ? null : CarbonImmutable::createFromFormat('Y-m-d', $to, 'UTC')->endOfDay();
    }
}
