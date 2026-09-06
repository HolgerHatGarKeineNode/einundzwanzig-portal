<?php

/*
|--------------------------------------------------------------------------
| Machine-translation spacing artefacts in the language files (#126)
|--------------------------------------------------------------------------
|
| A batch translator that splits on word boundaries writes back a space in
| front of a hyphen it did not put there: "E-mail" becomes "E -mail",
| "jelszó-visszaállítás" becomes "jelszó -visszaállítás". Twelve values in
| hu.json and lv.json carried it, on the login, password-reset and profile
| screens.
|
| Nothing else in the suite can see it. LangKeyParityTest compares key SETS,
| LangCodeCoverageTest asks whether a value exists and is non-empty — an
| artefact leaves both green because the key is there and the value is not
| empty. Only the value's own shape gives it away, and that shape is
| mechanical, which is the whole reason it is worth a test: the next
| machine-translated batch will reintroduce it and nobody will read 900
| values by hand.
|
| The pattern is the one the issue names: a word character, a space, a
| hyphen, a word character. It deliberately does NOT fire on " - " used as a
| dash ("Bitcoin - Rabbit Hole") — a hyphen with a space on BOTH sides is
| punctuation somebody meant to write.
|
| SCOPE: lang/*.json, which is where #126 measured the artefact. The group
| files (lang/<locale>/*.php) are deliberately not globbed, and not because
| nobody looked — running the same pattern over them on 2026-09-07 returns
| exactly two values, and neither can be dealt with here:
|
|   - lang/de/validation.php `ascii`: "…Single-Byte-Zeichen und -Symbole…"
|     is CORRECT German. A suspended hyphen after "und" is the language's own
|     spelling, so the pattern has a genuine false positive in German and
|     widening the guard needs a rule for it first, not a suppression entry.
|   - lang/hu/validation.php `prohibited_if_declined`: ":other -at" looks
|     like the same artefact, but it sits in an unreviewed Hungarian
|     validation string outside #126's scope. #126's standing condition is
|     that no native speaker has reviewed any translation here; changing one
|     more on the way past is exactly what that condition forbids.
|
| So the group files stay out until somebody decides those two. Reported with
| #126 rather than fixed in passing.
|--------------------------------------------------------------------------
*/

const LANG_SPACING_ARTEFACT_PATTERN = '/\w -\w/u';

/**
 * Every string value in lang/*.json, labelled with the file it came from.
 *
 * @return array<int, array{file: string, key: string, value: string}>
 */
function langTranslationValues(): array
{
    $files = collect(glob(lang_path('*.json')))->sort()->values();

    $values = [];
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

        foreach ($data as $key => $value) {
            if (! is_string($value)) {
                continue;
            }

            $values[] = [
                'file' => basename($file),
                'key' => (string) $key,
                'value' => $value,
            ];
        }
    }

    return $values;
}

/**
 * @param  array<int, array{file: string, key: string, value: string}>  $values
 * @return list<string>
 */
function spacingArtefactsAmong(array $values): array
{
    $offenders = [];
    foreach ($values as $entry) {
        if (preg_match(LANG_SPACING_ARTEFACT_PATTERN, $entry['value']) === 1) {
            $offenders[] = $entry['file'].' | '.$entry['key'].' => '.$entry['value'];
        }
    }

    return $offenders;
}

it('reads a realistic number of translatable values from the language files', function () {
    // Sanity floor: a broken glob or a changed file layout would leave the
    // guard below iterating over nothing and passing vacuously. Measured
    // 2026-09-07: 8199 values across the nine lang/*.json files.
    expect(count(langTranslationValues()))->toBeGreaterThan(7000);
});

it('has no machine-translation spacing artefact in any language file', function () {
    $offenders = spacingArtefactsAmong(langTranslationValues());

    expect($offenders)->toBe([], "Values with a stray space before a hyphen (\"E -mail\" instead of \"E-mail\"):\n"
        .implode("\n", $offenders)
        ."\nRemove the space. It is a batch-translation artefact, not spelling.");
});

it('flags a spacing artefact and spares a hyphen somebody meant to write', function () {
    // The detector's own control. Without it the guard above could go blind —
    // \w is only Unicode-aware because PHP's /u modifier sets PCRE2_UCP, so
    // "jelszó -visszaállítás" (non-ASCII on the left of the space) is the
    // case that would stop matching first if that ever changed. A guard that
    // silently stops matching is worse than no guard.
    $flagged = [
        ['file' => 'x', 'key' => 'ascii', 'value' => 'E -mail cím'],
        ['file' => 'x', 'key' => 'non-ascii left', 'value' => 'jelszó -visszaállítás link'],
        ['file' => 'x', 'key' => 'mid sentence', 'value' => 'Ievadiet savu e -pastu, lai saņemtu'],
    ];

    $spared = [
        ['file' => 'x', 'key' => 'repaired', 'value' => 'E-mail cím'],
        ['file' => 'x', 'key' => 'dash', 'value' => 'Bitcoin - Rabbit Hole'],
        ['file' => 'x', 'key' => 'entity', 'value' => '&laquo; předchozí'],
        ['file' => 'x', 'key' => 'placeholder', 'value' => 'Showing :first to :last of :total results'],
    ];

    expect(spacingArtefactsAmong($flagged))->toHaveCount(3)
        ->and(spacingArtefactsAmong($spared))->toBe([]);
});
