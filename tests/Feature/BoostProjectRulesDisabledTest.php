<?php

/*
|--------------------------------------------------------------------------
| Guard against #60 regressing: CLAUDE.md must not order agents to read a
| /.ai/rules/ directory this project does not keep.
|
| The paragraph is not hand-written. Boost renders it from
| vendor/laravel/boost/.ai/boost/core.blade.php, gated on
| config('boost.rules.enabled'), and rewrites CLAUDE.md on every
| `boost:install`. Deleting the paragraph alone would therefore bring it
| back at the next install; disabling the config alone would leave the
| stale paragraph in place until someone regenerates. Both have to hold,
| which is what this test asserts.
|
| Why the paragraph is harmful rather than merely unused: `grep -rin 'x'
| .ai/rules` against a missing directory returns nothing that reads as an
| error, so an agent following the instruction cannot tell "the rules were
| read and none applied" from "the rules never existed".
|--------------------------------------------------------------------------
*/

it('keeps Boost project rules disabled', function () {
    expect(config('boost.rules.enabled'))->toBeFalse(
        'config/boost.php must keep boost.rules.enabled false, or boost:install writes the '
        .'.ai/rules paragraph back into CLAUDE.md.'
    );
});

it('does not mandate a .ai/rules directory in CLAUDE.md', function () {
    $claudeMd = file_get_contents(base_path('CLAUDE.md'));

    // Deliberately str_contains() rather than expect()->not->toContain(): Pest's
    // toContain() is variadic, so a failure message passed as a second argument
    // becomes a second needle and the negated assertion passes whenever only one
    // of the two is present. Caught by a mutation probe, which stayed green.
    expect(str_contains($claudeMd, '.ai/rules'))->toBeFalse(
        'CLAUDE.md must not reference .ai/rules while no such directory exists.'
    );
});

it('has no .ai/rules directory to justify the instruction', function () {
    expect(is_dir(base_path('.ai/rules')))->toBeFalse(
        'A .ai/rules directory appeared. Either remove it, or re-enable '
        .'boost.rules.enabled and drop this guard — the two must not disagree.'
    );
});
