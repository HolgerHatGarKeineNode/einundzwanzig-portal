<?php

/*
|--------------------------------------------------------------------------
| Guard against #38 regressing: every literal translation key the codebase
| actually calls must exist in all nine lang/*.json files, and no locale
| other than de.json may carry an empty value for such a key. Laravel has
| no cross-locale fallback for JSON keys (Translator::get() treats "" as
| found, isset("") === true, so the fallback branch never runs) — an empty
| value silently renders the German source string to every other locale.
|
| de.json is the source file, so an empty value there is normally correct —
| except for keys whose text is English, where it serves that English text to
| German visitors (#86). The last two tests guard that subset.
|
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

    // de.json is exempt HERE because most of its keys ARE the German source
    // text, so an empty value is correct for them (619 of 911 values are empty
    // for exactly that reason, measured 2026-09-07 after #121 filled 30 of
    // them; it read 651 of 908 on 2026-09-05). The subset of German keys for
    // which an empty value is NOT correct — those whose text is English — is
    // guarded separately below; see #86.
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

/**
 * Code-reachable keys whose text is English and whose German value is empty,
 * so a German visitor is served the raw English key text (#86).
 *
 * Baseline, still a ratchet and not a blessing — the entry after the last one
 * here turns the guard below red.
 *
 * #86 froze 70 keys on 2026-09-05 without sorting them, because no static
 * check can tell a brand name from an untranslated label and labelling them
 * here would have buried the second group under a word that says "fine".
 * #121 did the sorting by hand, one judgement per key: 30 got German text in
 * lang/de.json and left this list, 40 remain and are grouped below with the
 * reason they stay, so the next reader does not judge them a third time.
 *
 * What stays is NOT "untranslatable in principle" — it is "the German text is
 * this text". Two kinds:
 *
 *  - Already correct German. Loanwords and product terms the German UI writes
 *    exactly this way ("Status", "Details", "Tags", "Top Meetups"), each
 *    corroborated by a German source key in lang/de.json that uses the same
 *    word.
 *  - Brand, protocol and network names ("Nostr", "Telegram", "LNURL",
 *    "URL (Onion/Tor)"). These are names, not words.
 *
 * @var list<string>
 */
const LANG_GERMAN_EMPTY_BASELINE = [
    // Already correct German: the key text IS what the German UI says. Each
    // reason names the German source key or sibling that spells it the same.
    'API Tokens',                          // house spelling: 'API Tokens - Einstellungen', 'Du hast noch keine API Tokens erstellt.'
    'App',                                 // German noun; cf. 'Mit der App verbinden'
    'Bitcoin - Rabbit Hole',               // established term in the German Bitcoin scene, and the page's own name
    'Bitcoin Event Details',               // "Event" and "Details" are German here; cf. 'Details über das Event'
    'Bitcoin Meetups',                     // the site's own name; cf. 'Willkommen bei Bitcoin Meetups'
    'Bitcoin Meetups - Community Events',  // same vocabulary as 'Erstelle und bearbeite Bitcoin Meetup Events für deine Community.'
    'Dashboard - Bitcoin Meetups',         // 'Dashboard' already carries the value "Dashboard" in de.json
    'Details',                             // German noun; cf. 'Details über das Event', 'Details/Anmelden'
    'Event Details',                       // as 'Details'; the house writes open compounds ('Matrix Gruppe', 'IP Adresse')
    'ID',                                  // cf. its own description 'System-generierte ID (nur lesbar)'
    'Link',                                // "der Link"; cf. 'Link zu weiteren Informationen oder zur Anmeldung'
    'Links',                               // cf. 'Kontakt & Links'
    'Meetups',                             // the product's term; cf. 'Meetups erstellt', 'Meetups in :region'
    'Name (:lang)',                        // "Name" is identical in German; de.json even carries "Name": "Name"
    'Podcasts',                            // German plural of "der Podcast"; sits beside 'Episoden', 'Bibliotheken'
    'Service Details',                     // as 'Details'; cf. 'Erfahre mehr über diesen Self-Hosted Service…'
    'Services',                            // the product word: 'Service erstellen', 'Suche nach Services...'
    'Status',                              // "der Status"; cf. its own description 'Ist dieser Dozent aktiv?'
    'Tags',                                // the product's German term: 'Tags wählen', 'Tags verwalten', 'Tag-Vorschläge'
    'Telegram Link',                       // matches its sibling labels 'Matrix Gruppe', 'Twitter Benutzername'
    'Top Meetups',                         // the sibling dashboard card is 'Top Länder' — German, same shape
    'Tor Hidden Service URL',              // Tor's own term; siblings are 'I2P Adresse', 'Pkarr DNS Adresse'
    'Webhooks',                            // used as a German word: 'Deine Webhooks', 'Webhook-Freigaben'

    // Brand, protocol and network names. Names, not words.
    'LNURL',                               // Lightning specification
    'Lightning',                           // protocol; cf. the German 'kein Lightning' beside it
    'Lightning Node ID',                   // protocol field
    'Matrix',                              // messaging protocol
    'Node ID',                             // protocol field; its description is 'Lightning Node ID'
    'Nostr',                               // protocol
    'Nostr (NIP-52)',                      // protocol plus specification number
    'PayNym',                              // product name
    'Signal',                              // messenger
    'SimpleX',                             // messenger
    'Simplex',                             // the same messenger, miscapitalised at two call sites (#121)
    'Telegram',                            // messenger
    'Twitter',                             // product name
    'URL (Clearnet)',                      // network name
    'URL (I2P)',                           // network name
    'URL (Onion/Tor)',                     // network name
    'URL (pkdns)',                         // network name
];

/**
 * Code-reachable keys that render their raw English key text to a German
 * visitor: empty in de.json, and translated to themselves in en.json.
 *
 * An empty de.json value never falls back to another locale — Translator::get()
 * finds the empty string, skips the group-file branch (isset('') is true) and
 * ends in `$line ?: $key`, so the key text itself is rendered. That is correct
 * for the 576 keys whose text IS the German source string, and wrong for the
 * ones whose text is English.
 *
 * en.json is what decides which is which: if the English rendering of a key is
 * the key itself, then what German renders is literally the English string. It
 * is the only mechanical signal in the repo for "this key was authored in
 * English" — a German-source key such as "Abbrechen" carries "Cancel" in
 * en.json and is therefore never flagged.
 *
 * @return list<string>
 */
function keysRenderingEnglishToGermanVisitors(): array
{
    $de = json_decode(file_get_contents(lang_path('de.json')), true, flags: JSON_THROW_ON_ERROR);
    $en = json_decode(file_get_contents(lang_path('en.json')), true, flags: JSON_THROW_ON_ERROR);

    $offenders = [];
    foreach (extractCodebaseTranslationKeys() as $key) {
        if (($de[$key] ?? null) === '' && ($en[$key] ?? null) === $key) {
            $offenders[] = $key;
        }
    }

    sort($offenders, SORT_STRING);

    return $offenders;
}

it('adds no further English key text served to German visitors', function () {
    $added = array_values(array_diff(keysRenderingEnglishToGermanVisitors(), LANG_GERMAN_EMPTY_BASELINE));

    expect($added)->toBe([], "Keys with an empty de.json value whose text is English, so a German visitor reads the English key:\n"
        .json_encode($added, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ."\nGive them a German value in lang/de.json. Do not add them to LANG_GERMAN_EMPTY_BASELINE.");
});

it('keeps the German-empty baseline free of entries that no longer apply', function () {
    $stale = array_values(array_diff(LANG_GERMAN_EMPTY_BASELINE, keysRenderingEnglishToGermanVisitors()));

    // Without this, a translated key would keep its exemption forever and a
    // later re-emptying of the same value would pass unnoticed.
    expect($stale)->toBe([], "LANG_GERMAN_EMPTY_BASELINE lists keys that no longer render English to German visitors:\n"
        .json_encode($stale, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ."\nRemove them from the baseline so the exemption does not outlive the problem.");
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
