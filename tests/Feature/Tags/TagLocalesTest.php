<?php

/**
 * The tag picker renders one hidden alias per configured locale so a tag can be found
 * in any language. If that list silently disagrees with the languages the app actually
 * ships, the cross-language search narrows without any error surfacing — which is
 * exactly what would have happened had we read config/translatable.php (en, fr, es),
 * a leftover from the unused astrotomic package.
 */
it('keeps tag locales in sync with the shipped language directories', function () {
    $shipped = collect(File::directories(lang_path()))
        ->map(fn (string $path): string => basename($path))
        ->reject(fn (string $dir): bool => $dir === 'lang-country-overrides')
        ->sort()
        ->values()
        ->all();

    $configured = collect(config('einundzwanzig.tag_locales'))->sort()->values()->all();

    expect($configured)->toBe($shipped);
});

it('does not fall back to the unused translatable config', function () {
    expect(config('einundzwanzig.tag_locales'))
        ->not->toBe(config('translatable.locales'))
        ->toContain('cs', 'de', 'pl');
});
