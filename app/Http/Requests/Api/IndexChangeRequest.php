<?php

namespace App\Http\Requests\Api;

use App\Support\Broadcasting\ChangeRecorder;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reads the change log of the public API (`GET /api/changes`).
 *
 * The cursor accepts two shapes on purpose: a numeric `sequence` (the row id, the
 * exact and gap-free cursor) and an ISO-8601 timestamp (the entry point for a
 * consumer who only knows when it last synced). Anything else is a validation error
 * rather than a silently empty page.
 */
class IndexChangeRequest extends FormRequest
{
    /**
     * Der Vorgabewert von `limit`, wenn der Aufrufer keinen mitschickt.
     */
    public const DEFAULT_LIMIT = 100;

    /**
     * Die Obergrenze von `limit`. Ueber 1000 Zeilen je Antwort waere das Payload
     * (jede Zeile traegt ein vollstaendiges Objekt) mehrere Megabyte gross.
     */
    public const MAX_LIMIT = 1000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * `?resource=city,meetup` und `?resource[]=city&resource[]=meetup` sollen
     * dasselbe bedeuten — die Komma-Form ist die, die ein Mensch von Hand tippt.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('resource')) {
            return;
        }

        $resource = $this->input('resource');

        if (! is_string($resource)) {
            return;
        }

        $this->merge([
            'resource' => array_values(array_filter(
                array_map('trim', explode(',', $resource)),
                static fn (string $value): bool => $value !== '',
            )),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /** Cursor: either a sequence number (exclusive — returns everything after it) or an ISO-8601 timestamp (returns everything that occurred after it). Omit it to receive the newest entries. */
            'since' => ['nullable', 'string', $this->cursorRule()],
            'resource' => ['sometimes', 'array'],
            /** Restrict to one or more resources. Repeatable (resource[]=city&resource[]=meetup) or comma separated (resource=city,meetup). */
            'resource.*' => ['string', Rule::in(ChangeRecorder::resourceNames())],
            /** Maximum number of entries per page. Defaults to 100. */
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ];
    }

    /**
     * Der Cursor als Sequenz, oder null, wenn keine oder eine Zeit-Angabe kam.
     */
    public function cursorSequence(): ?int
    {
        $since = $this->input('since');

        if ($since === null || ! is_numeric($since)) {
            return null;
        }

        return (int) $since;
    }

    /**
     * Der Cursor als Zeitpunkt, oder null, wenn keine oder eine Sequenz kam.
     *
     * Umgerechnet auf die App-Zeitzone: `occurred_at` steht so in der Spalte, und ein
     * Vergleich gegen einen Wert mit fremdem Offset laege sonst um Stunden daneben.
     */
    public function cursorTimestamp(): ?CarbonImmutable
    {
        $since = $this->input('since');

        if ($since === null || is_numeric($since)) {
            return null;
        }

        return CarbonImmutable::parse((string) $since)->setTimezone(config('app.timezone'));
    }

    /**
     * Die gewaehlten Ressourcen-Namen; ein leeres Array heisst "alle".
     *
     * @return array<int, string>
     */
    public function resourceFilter(): array
    {
        $resource = $this->input('resource', []);

        return array_values(array_unique(is_array($resource) ? $resource : [$resource]));
    }

    public function limit(): int
    {
        $limit = $this->input('limit');

        return $limit === null ? self::DEFAULT_LIMIT : (int) $limit;
    }

    /**
     * `since` ist entweder eine Sequenz oder ein Zeitpunkt — beides wird hier
     * geprueft, damit der Controller die Unterscheidung nur noch treffen muss.
     */
    private function cursorRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (is_numeric($value)) {
                if ((float) $value < 0 || (float) $value !== floor((float) $value)) {
                    $fail('The :attribute cursor must be a non-negative sequence number.');
                }

                return;
            }

            try {
                CarbonImmutable::parse((string) $value);
            } catch (InvalidFormatException) {
                $fail('The :attribute cursor must be a sequence number or an ISO-8601 timestamp.');
            }
        };
    }
}
