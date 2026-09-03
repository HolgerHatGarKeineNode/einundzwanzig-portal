<?php

/*
|--------------------------------------------------------------------------
| Guard against #38 regressing: every literal translation key the codebase
| actually calls must exist in all nine lang/*.json files, and no locale
| other than de.json may carry an empty value for such a key. Laravel has
| no cross-locale fallback for JSON keys (Translator::get() treats "" as
| found, isset("") === true, so the fallback branch never runs) — an empty
| value silently renders the German source string to every other locale.
| This lives in the Feature suite (php artisan test), not behind a tag or
| in CI only, and needs no network or browser.
|--------------------------------------------------------------------------
*/

const LANG_CODE_COVERAGE_LOCALES = ['cs', 'de', 'en', 'es', 'hu', 'lv', 'nl', 'pl', 'pt'];

/**
 * Extract literal translation keys passed to __(), trans(), trans_choice()
 * or @lang() across app/, resources/, routes/, config/ and database/.
 *
 * Deliberately scoped to STATIC string-literal arguments only. A handful of
 * call sites pass a variable instead (e.g. `__($step['title'])` iterating an
 * array of pre-written strings, or `__($status)` resolving a Password
 * Broker status constant) — those keys cannot be recovered without
 * executing the surrounding code, so they are out of this guard's reach.
 * Measured 2026-09-03: every one of those variable-argument keys already
 * has a non-empty value in all nine locales today, so the gap does not
 * currently hide a real regression; it is a known, accepted limitation of
 * a static scan, not an oversight.
 *
 * Keys shaped like "group.item" (e.g. "auth.failed") are excluded when a
 * matching lang/<locale>/<group>.php file exists. Those resolve through
 * Laravel's OTHER translator path (PHP array group files), which — unlike
 * JSON keys — genuinely does fall back across locales. They are not part
 * of #38's problem and not part of this guard.
 */
function extractCodebaseTranslationKeys(): array
{
    $dirs = ['app', 'resources', 'routes', 'config', 'database'];

    $files = [];
    foreach ($dirs as $dir) {
        $path = base_path($dir);
        if (! is_dir($path)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            // Covers both *.php and *.blade.php (the latter also ends in .php).
            if (str_ends_with($file->getFilename(), '.php')) {
                $files[] = $file->getPathname();
            }
        }
    }

    // Matches __(...), trans(...), trans_choice(...) and @lang(...), but only
    // when the first argument is a single- or double-quoted string literal.
    $pattern = '/(?<![A-Za-z0-9_])(?:__|trans_choice|trans|@lang)\s*\(\s*'
        .'(\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*")/s';

    $keys = [];
    foreach ($files as $file) {
        $contents = file_get_contents($file);
        if ($contents === false || ! preg_match_all($pattern, $contents, $matches)) {
            continue;
        }

        foreach ($matches[1] as $literal) {
            $quote = $literal[0];
            $raw = substr($literal, 1, -1);
            $key = $quote === "'"
                ? str_replace(["\\'", '\\\\'], ["'", '\\'], $raw)
                : stripcslashes($raw);

            if (str_contains($key, '.')) {
                $group = strstr($key, '.', true);
                if ($group !== false && $group !== '' && file_exists(lang_path('en/'.$group.'.php'))) {
                    continue;
                }
            }

            $keys[$key] = true;
        }
    }

    return array_keys($keys);
}

it('extracts a realistic number of static translation keys from the codebase', function () {
    // Sanity floor so a broken extractor (regex stops matching after a Blade
    // syntax change, directory list goes stale, etc.) fails loudly here
    // instead of letting the two guard tests below pass vacuously. Measured
    // 2026-09-03: 802 static keys; 700 leaves comfortable headroom for
    // future removals while still catching "found almost nothing".
    $keys = extractCodebaseTranslationKeys();

    expect(count($keys))->toBeGreaterThan(700, 'Extractor sanity check failed: found only '.count($keys).' static translation keys (expected 700+). The regex or scanned directories likely regressed.')
        ->and($keys)->toContain('Meetup :name öffnen')
        ->and($keys)->toContain(':count Event|:count Events');
});

it('has every code-used translation key present in all nine locale files', function () {
    $codeKeys = extractCodebaseTranslationKeys();

    $missingByLocale = [];
    foreach (LANG_CODE_COVERAGE_LOCALES as $locale) {
        $data = json_decode(file_get_contents(lang_path("$locale.json")), true, flags: JSON_THROW_ON_ERROR);
        $missing = array_values(array_diff($codeKeys, array_keys($data)));

        if ($missing !== []) {
            $missingByLocale[$locale] = $missing;
        }
    }

    expect($missingByLocale)->toBe([], "Translation keys used in code but missing from a locale file:\n"
        .json_encode($missingByLocale, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
});

it('has no empty value in a non-German locale for a key reachable from code', function () {
    $codeKeys = extractCodebaseTranslationKeys();

    // de.json is exempt by convention: its keys ARE the German source text,
    // so an empty value there is correct, not a gap.
    $locales = array_values(array_diff(LANG_CODE_COVERAGE_LOCALES, ['de']));

    $emptyByLocale = [];
    foreach ($locales as $locale) {
        $data = json_decode(file_get_contents(lang_path("$locale.json")), true, flags: JSON_THROW_ON_ERROR);

        $empty = [];
        foreach ($codeKeys as $key) {
            if (array_key_exists($key, $data) && $data[$key] === '') {
                $empty[] = $key;
            }
        }

        if ($empty !== []) {
            $emptyByLocale[$locale] = $empty;
        }
    }

    expect($emptyByLocale)->toBe([], "Code-reachable translation keys with an empty (German-falling-through) value:\n"
        .json_encode($emptyByLocale, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
});

/*
|--------------------------------------------------------------------------
| Deliberately not tested: keys present in a lang/*.json file that
| extractCodebaseTranslationKeys() never finds (e.g. because they are only
| ever passed to __() through a variable). Failing on those would make the
| suite red for something nobody can fix from the code — the key IS used,
| the scan just cannot see it. LangKeyParityTest.php separately guards that
| all nine files carry the same key SET; this file only ever adds keys via
| the extractor, so it cannot introduce cross-file drift.
|--------------------------------------------------------------------------
*/
