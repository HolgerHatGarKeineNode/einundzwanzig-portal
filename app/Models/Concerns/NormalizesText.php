<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Model;

/**
 * Raeumt Leerzeichen beim Speichern auf — auf jedem Weg, der ein Model speichert.
 *
 * Der Trait sitzt bewusst im Model und nicht in einer Form Request: Meetups
 * entstehen ueber DREI Wege (REST-API, MCP-Tool, Livewire-Formular), und Laravels
 * `TrimStrings`-Middleware greift nur auf dem HTTP-Pfad. Livewire laeuft daran
 * vorbei — daher stammen die 20 Meetup-Namen mit nachlaufendem Leerzeichen, die
 * das `unique` auf `meetups.name` aushebelten.
 *
 * ZWEI Regeln, und die Trennung ist nicht kosmetisch:
 *
 * - `$normalizedLabels` (name, title, location): trimmen UND innere Mehrfach-
 *   Leerzeichen zu einem zusammenziehen. Bezeichnungen haben keine Innenform,
 *   die man verlieren koennte.
 * - `$normalizedProse` (intro, description): NUR die Raender trimmen. Gemessen am
 *   26.08.2026 tragen 1232 Termin-Beschreibungen und 86 Meetup-Intros
 *   Zeilenumbrueche. Die Label-Regel wuerde diese Absaetze zu einer Zeile
 *   verschmelzen — stiller Datenverlust an 1318 Texten. Am Rand raeumt auch die
 *   Prosa-Regel Leerzeilen ab; das ist Trimmen und kein Verlust.
 *
 * Beide Regeln stehen in {@see TextNormalizer} — geteilt mit dem einmaligen
 * Aufraeum-Lauf, damit Bestand und Neuzugang dieselbe Form erzeugen.
 *
 * Leere Ergebnisse werden zu null, wo die Spalte das zulaesst: ein Feld, das nur
 * aus Leerzeichen bestand, ist nicht gefuellt, und `''` gegen `null` zu
 * unterscheiden hat hier keinen Nutzen — es macht nur zwei Formen fuer nichts.
 */
trait NormalizesText
{
    public static function bootNormalizesText(): void
    {
        static::saving(function (Model $model): void {
            /** @var array<int, string> $labels */
            $labels = property_exists($model, 'normalizedLabels') ? $model->normalizedLabels : [];
            /** @var array<int, string> $prose */
            $prose = property_exists($model, 'normalizedProse') ? $model->normalizedProse : [];

            foreach ($labels as $field) {
                self::applyNormalization($model, $field, collapseInner: true);
            }

            foreach ($prose as $field) {
                self::applyNormalization($model, $field, collapseInner: false);
            }
        });
    }

    private static function applyNormalization(Model $model, string $field, bool $collapseInner): void
    {
        // isDirty() waere hier falsch: ein aus der Datenbank geladener Wert mit
        // Rand-Leerzeichen ist nicht dirty und bliebe unangetastet, obwohl genau
        // er das Problem ist.
        $value = $model->getAttribute($field);

        if (! is_string($value)) {
            return;
        }

        $clean = $collapseInner ? TextNormalizer::label($value) : TextNormalizer::prose($value);

        if ($clean === $value) {
            return;
        }

        $model->setAttribute($field, $clean === '' && self::fieldIsNullable($model, $field) ? null : $clean);
    }

    private static function fieldIsNullable(Model $model, string $field): bool
    {
        $required = property_exists($model, 'normalizedRequired') ? $model->normalizedRequired : [];

        return ! in_array($field, $required, true);
    }
}
