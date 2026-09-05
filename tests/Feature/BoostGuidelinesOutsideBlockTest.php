<?php

/*
|--------------------------------------------------------------------------
| Guard against #83 regressing: hand-written guidance in CLAUDE.md must sit
| BELOW </laravel-boost-guidelines>, never inside the block.
|
| `boost:install` does not append to CLAUDE.md, it regenerates the whole
| <laravel-boost-guidelines> block from the Blade templates under
| vendor/laravel/boost/.ai/. Anything hand-written inside that block is
| dropped at the next install, and the command reports success while doing
| it — the loss is silent. Text below the closing tag survives.
|
| The passage guarded here is the Browser-testsuite warning, added by hand
| in 01766c7 and ca131fc. It carries a measurement (25+ minutes of silence,
| twice) which is the only reason anyone believes the hazard; rediscovering
| it costs the next agent the same time.
|
| Presence alone is not enough. A regeneration that reinstated a
| Boost-owned copy inside the block would keep a presence-only check green
| while the passage is once again one `boost:install` away from deletion.
| Both halves are therefore asserted: present below the tag, absent above.
|--------------------------------------------------------------------------
*/

/**
 * The two load-bearing sentences of the warning. The measurement is quoted
 * verbatim on purpose — a reworded measurement is a weaker one.
 *
 * @var array<int, string>
 */
const BROWSER_SUITE_WARNING_NEEDLES = [
    'Never run the full suite unfiltered in an automated context.',
    'Measured 2026-09-01: 25+ minutes of silence, twice, on a change that touched only `lang/en.json`.',
];

/**
 * @return array{inside: string, outside: string}
 */
function splitClaudeMdAtBoostBlock(): array
{
    $claudeMd = file_get_contents(base_path('CLAUDE.md'));
    $closingTag = '</laravel-boost-guidelines>';

    $position = strpos($claudeMd, $closingTag);

    expect($position)->not->toBeFalse(
        'CLAUDE.md no longer contains a '.$closingTag.' tag. This guard cannot tell '
        .'Boost-owned text from hand-written text without it.'
    );

    return [
        'inside' => substr($claudeMd, 0, $position),
        'outside' => substr($claudeMd, $position + strlen($closingTag)),
    ];
}

it('keeps the Browser-testsuite warning in CLAUDE.md, below the Boost block', function () {
    $parts = splitClaudeMdAtBoostBlock();

    // Deliberately str_contains() rather than expect()->not->toContain(): Pest's
    // toContain() is variadic, so a failure message passed as a second argument
    // becomes a second needle and a negated assertion passes whenever only one of
    // the two is present. Same reason as BoostProjectRulesDisabledTest.
    foreach (BROWSER_SUITE_WARNING_NEEDLES as $needle) {
        expect(str_contains($parts['outside'], $needle))->toBeTrue(
            'CLAUDE.md must keep this text below </laravel-boost-guidelines>, where '
            ."boost:install cannot overwrite it: \"{$needle}\""
        );
    }
});

it('keeps the Browser-testsuite warning out of the Boost block', function () {
    $parts = splitClaudeMdAtBoostBlock();

    foreach (BROWSER_SUITE_WARNING_NEEDLES as $needle) {
        expect(str_contains($parts['inside'], $needle))->toBeFalse(
            'This text sits inside <laravel-boost-guidelines> and the next boost:install '
            ."will delete it without saying so. Move it below the closing tag: \"{$needle}\""
        );
    }
});
