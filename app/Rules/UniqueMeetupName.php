<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Concerns\NormalizesText;
use App\Support\TextNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Meetup-Name eindeutig, ohne auf die Schreibweise zu achten.
 *
 * Laravels `unique:meetups,name` prueft in PostgreSQL exakt — 'EINUNDZWANZIG
 * Mannheim' und 'Einundzwanzig Mannheim' galten damit als zwei Namen. Genau so
 * entstand das Duplikat, das am 26.08.2026 aufgeraeumt wurde.
 *
 * Die Regel spiegelt den Index `meetups_lower_name_unique`. Ohne sie waere die
 * Kollision zwar verhindert, aber als 500er statt als Formularfehler — der
 * Index kennt keine Fehlermeldung.
 *
 * Verglichen wird der NORMALISIERTE Wert, denn {@see NormalizesText}
 * normalisiert beim Speichern: eine Regel, die den Rohwert prueft, liesse
 * ' Einundzwanzig Ulm ' durch und der Insert scheiterte trotzdem.
 */
class UniqueMeetupName implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $vergleich = mb_strtolower(TextNormalizer::label($value));

        if ($vergleich === '') {
            return;
        }

        $treffer = DB::table('meetups')
            ->whereRaw('LOWER(name) = ?', [$vergleich])
            ->when($this->ignoreId !== null, fn ($query) => $query->where('id', '<>', $this->ignoreId))
            ->first(['id', 'name']);

        if ($treffer === null) {
            return;
        }

        $fail(__('Es gibt bereits ein Meetup mit diesem Namen: :name. Gross- und Kleinschreibung zaehlt dabei nicht.', [
            'name' => $treffer->name,
        ]));
    }
}
