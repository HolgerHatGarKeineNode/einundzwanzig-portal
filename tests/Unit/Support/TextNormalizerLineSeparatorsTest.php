<?php

use App\Support\TextNormalizer;

/*
 * U+2028 LINE SEPARATOR and U+2029 PARAGRAPH SEPARATOR are not a tidiness question:
 * `swentel/nostr-php` computes the NIP-01 event id over
 * `json_encode(..., JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)`, and PHP escapes
 * exactly these two as `\u2028`/`\u2029` unless JSON_UNESCAPED_LINE_TERMINATORS is
 * added, which the library does not add. The id is then the hash of a byte string no
 * relay reproduces, and the event is rejected. NostrCanonicalEventIdTest measures that;
 * this file pins the repair itself.
 */

it('replaces U+2028 and U+2029 in prose with a plain newline', function () {
    expect(TextNormalizer::prose("Erste Zeile\u{2028}Zweite Zeile"))
        ->toBe("Erste Zeile\nZweite Zeile")
        ->and(TextNormalizer::prose("Erster Absatz\u{2029}Zweiter Absatz"))
        ->toBe("Erster Absatz\nZweiter Absatz");
});

it('replaces them in a label too, before the label rule collapses spaces', function () {
    expect(TextNormalizer::label("Bitcoin\u{2028}Meetup"))->toBe("Bitcoin\nMeetup")
        ->and(TextNormalizer::label("Bitcoin  \u{2028}  Meetup"))->toBe("Bitcoin \n Meetup");
});

it('trims one that sits at the edge of a text away entirely', function () {
    expect(TextNormalizer::prose("\u{2028}Text\u{2029}"))->toBe('Text');
});

it('leaves ordinary newlines untouched', function () {
    $text = "Erste Zeile\n\nZweite Zeile\r\nDritte Zeile";

    expect(TextNormalizer::prose($text))->toBe($text);
});

/*
 * The exotic terminators that read as if they belonged in the list above. Measured
 * against the installed swentel/nostr-php 1.9.4 on 2026-09-17 (one signed event per
 * character, library id vs. an id recomputed with JSON_UNESCAPED_LINE_TERMINATORS):
 * U+0085 NEL, U+000B VT, U+000C FF and U+200B ZWSP all produce an EQUAL id, because
 * PHP's escaping of them matches what a relay reconstructs. They are therefore left
 * alone — rewriting stored texts that publish perfectly well would be a change with a
 * cost and no effect.
 */
it('leaves the other exotic line terminators alone', function () {
    $text = "A\u{0085}B\u{000B}C\u{000C}D\u{200B}E";

    expect(TextNormalizer::prose($text))->toBe($text);
});
