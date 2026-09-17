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
        return trim((string) preg_replace('/[^\S\r\n]+/u', ' ', self::withoutLineSeparators($value)));
    }

    /**
     * Freitext: NUR die Raender trimmen.
     *
     * Gemessen am 26.08.2026 tragen 1232 Termin-Beschreibungen und 86
     * Meetup-Intros Zeilenumbrueche. Die Label-Regel wuerde diese Absaetze zu
     * einer Zeile verschmelzen — stiller Datenverlust an 1318 Texten.
     *
     * `trim()` raeumt auch \n und \r ab, aber nur am Rand: eine fuehrende oder
     * abschliessende Leerzeile faellt mit, jeder Umbruch INNERHALB des Textes
     * bleibt. Beim Lauf vom 26.08. betraf das 42 der 291 korrigierten
     * Beschreibungen — bei allen 42 stand der Umbruch ausschliesslich am Rand,
     * kein Text verlor eine innere Zeile.
     */
    public static function prose(string $value): string
    {
        return trim(self::withoutLineSeparators($value));
    }

    /**
     * Replace U+2028 LINE SEPARATOR and U+2029 PARAGRAPH SEPARATOR with a plain
     * newline. Every other line terminator is left exactly as it is.
     *
     * NOT a style rule — these two characters make a signed Nostr event unpublishable.
     * `swentel/nostr-php` builds the NIP-01 event id over
     * `json_encode(..., JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)`
     * ({@see \swentel\nostr\Sign\Sign::serializeEvent()}), and PHP escapes exactly
     * U+2028/U+2029 as `\u2028`/`\u2029` unless `JSON_UNESCAPED_LINE_TERMINATORS` is
     * added, which the library does not do. The id is then the hash of a byte string no
     * relay reconstructs: the relay hashes the raw UTF-8 characters, gets a different
     * id and rejects the event as `invalid: id`.
     *
     * Measured against the installed 1.9.4 on 2026-09-17, one content per case, library
     * id vs. an id recomputed with `JSON_UNESCAPED_LINE_TERMINATORS`:
     * U+2028 and U+2029 DIFFER; `\n`, `\r`, U+0085 NEL, U+000B VT, U+000C FF and
     * U+200B ZWSP are all EQUAL. So the repair is these two characters and no others —
     * the exotic terminators that read as if they belonged here do not break the id and
     * are therefore not rewritten, since rewriting them would change stored texts for
     * no gain.
     *
     * Replaced with `\n` rather than dropped: both ARE line breaks, and a description
     * that uses them (they arrive by copy-and-paste out of a word processor) loses its
     * structure if they vanish. `trim()`/{@see self::label()} then treat the result like
     * any other newline.
     */
    public static function withoutLineSeparators(string $value): string
    {
        return str_replace(["\u{2028}", "\u{2029}"], "\n", $value);
    }
}
