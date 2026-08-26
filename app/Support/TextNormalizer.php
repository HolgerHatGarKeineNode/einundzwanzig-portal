<?php

declare(strict_types=1);

namespace App\Support;

use App\Console\Commands\Database\NormalizeTextFields;
use App\Models\Concerns\NormalizesText;

/**
 * Die zwei Whitespace-Regeln des Portals, an einer Stelle.
 *
 * Geteilt vom Trait {@see NormalizesText} (Neuzugaenge, beim
 * Speichern) und vom einmaligen Aufraeum-Lauf {@see NormalizeTextFields}
 * (Altbestand). Beide muessen dieselbe Form erzeugen, sonst korrigiert der eine,
 * was der andere gerade geschrieben hat.
 */
final class TextNormalizer
{
    /**
     * Bezeichnung: trimmen UND innere Mehrfach-Leerzeichen zu einem zusammenziehen.
     *
     * Zeilenumbrueche bleiben trotzdem verschont ([^\S\r\n] statt \s) — eine
     * Bezeichnung sollte keine enthalten, aber falls doch, ist ihr Verlust ein
     * Schaden und kein Aufraeumen.
     */
    public static function label(string $value): string
    {
        return trim((string) preg_replace('/[^\S\r\n]+/u', ' ', $value));
    }

    /**
     * Freitext: NUR die Raender trimmen.
     *
     * Gemessen am 26.08.2026 tragen 1232 Termin-Beschreibungen und 86
     * Meetup-Intros Zeilenumbrueche. Die Label-Regel wuerde diese Absaetze zu
     * einer Zeile verschmelzen — stiller Datenverlust an 1318 Texten.
     */
    public static function prose(string $value): string
    {
        return trim($value);
    }
}
