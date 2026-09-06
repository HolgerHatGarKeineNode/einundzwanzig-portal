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
| Presence alone is not enough. A regeneration that reinstated a
| Boost-owned copy inside the block would keep a presence-only check green
| while the passage is once again one `boost:install` away from deletion.
| Both halves are therefore asserted: present below the tag, absent above.
|
| EVERY hand-written passage needs its own needles, and this is not a
| formality. The Code Formatting section of #132 was written below the tag
| and was still completely unguarded: moving the whole section INSIDE the
| block — the exact violation — left this file reporting "2 passed",
| because its needle list only knew the Browser-testsuite sentences. A guard
| whose scope silently lags the text it guards is worth less than none, so
| adding a passage below the tag means adding its needles here.
|--------------------------------------------------------------------------
*/

/**
 * The two load-bearing sentences of the Browser-testsuite warning, added by
 * hand in 01766c7 and ca131fc. It carries a measurement (25+ minutes of
 * silence, twice) which is the only reason anyone believes the hazard;
 * rediscovering it costs the next agent the same time. The measurement is
 * quoted verbatim on purpose — a reworded measurement is a weaker one.
 *
 * @var array<int, string>
 */
const BROWSER_SUITE_WARNING_NEEDLES = [
    'Never run the full suite unfiltered in an automated context.',
    'Measured 2026-09-01: 25+ minutes of silence, twice, on a change that touched only `lang/en.json`.',
];

/**
 * The three load-bearing lines of the Code Formatting section (#132): the
 * measurement that proves `--dirty` cannot see a single-file component, the
 * command that actually reaches one, and the sentence saying the narrowed
 * fixer set is insurance rather than a repair. Lose the first and the rule
 * reads as a preference; lose the second and there is no instruction left;
 * lose the third and the next maintainer re-litigates a decision that was
 * already measured.
 *
 * @var array<int, string>
 */
const CODE_FORMATTING_NEEDLES = [
    'Measured 2026-09-06 for issue #132: with `resources/views/livewire/dashboard.blade.php` modified **and** unformatted, `--dirty --test` reported `{"tool":"pint","result":"passed"}`.',
    "vendor/bin/pint --format agent \$(git diff --name-only --diff-filter=ACMR HEAD -- 'resources/views/*.blade.php')",
    'All 52 components were also formatted with the FULL preset and compile-checked: none broke.',
];

/**
 * Every hand-written passage that must survive the next `boost:install`.
 *
 * @return array<int, string>
 */
function handWrittenClaudeMdNeedles(): array
{
    return array_merge(BROWSER_SUITE_WARNING_NEEDLES, CODE_FORMATTING_NEEDLES);
}

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

it('keeps every hand-written passage in CLAUDE.md below the Boost block', function () {
    $parts = splitClaudeMdAtBoostBlock();

    // Deliberately str_contains() rather than expect()->not->toContain(): Pest's
    // toContain() is variadic, so a failure message passed as a second argument
    // becomes a second needle and a negated assertion passes whenever only one of
    // the two is present. Same reason as BoostProjectRulesDisabledTest.
    foreach (handWrittenClaudeMdNeedles() as $needle) {
        expect(str_contains($parts['outside'], $needle))->toBeTrue(
            'CLAUDE.md must keep this text below </laravel-boost-guidelines>, where '
            ."boost:install cannot overwrite it: \"{$needle}\""
        );
    }
});

it('keeps every hand-written passage out of the Boost block', function () {
    $parts = splitClaudeMdAtBoostBlock();

    foreach (handWrittenClaudeMdNeedles() as $needle) {
        expect(str_contains($parts['inside'], $needle))->toBeFalse(
            'This text sits inside <laravel-boost-guidelines> and the next boost:install '
            ."will delete it without saying so. Move it below the closing tag: \"{$needle}\""
        );
    }
});
