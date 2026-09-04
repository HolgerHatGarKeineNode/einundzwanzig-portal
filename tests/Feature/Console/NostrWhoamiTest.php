<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use swentel\nostr\Key\Key;

/*
|--------------------------------------------------------------------------
| nostr:whoami (Issue #49)
|--------------------------------------------------------------------------
|
| The command answers "who publishes and where" so an operator can paste the
| answer into a public issue reply. That makes its output a publication
| surface, and the leak guard below is the test that keeps it one.
|
| The key here is a throwaway generated for the test suite and used nowhere
| else. It is the same constant PublishCalendarEventsTest already publishes
| under, kept identical on purpose: both tests derive the same npub, so a
| change in the derivation breaks both instead of silently disagreeing.
*/

const WHOAMI_TEST_KEY = '4f964f6b93a5b1e5f6f9b1d3a4f5e6d7c8b9a0f1e2d3c4b5a6978869504132a1';

/**
 * Own helper rather than PublishCalendarEventsTest's `meetupWithCity`: Pest loads test
 * files in an order this file must not depend on, and a shared global function would
 * couple the two suites through PHP's function table.
 *
 * @param  array<string, mixed>  $attributes
 */
function whoamiMeetup(array $attributes = []): Meetup
{
    $city = City::factory()->create([
        'country_id' => Country::factory()->create(['code' => 'de'])->id,
    ]);

    return Meetup::factory()->create(array_merge(['city_id' => $city->id], $attributes));
}

function whoamiOutput(?string $key, bool $json = false): string
{
    config([
        'services.nostr.publisher_key' => $key,
        'services.nostr.relays' => ['wss://nos.lol', 'wss://relay.damus.io'],
    ]);

    Artisan::call('nostr:whoami', $json ? ['--json' => true] : []);

    return Artisan::output();
}

it('reports the npub and hex pubkey for a hex key', function () {
    $output = whoamiOutput(WHOAMI_TEST_KEY);

    $expectedHex = (new Key)->getPublicKey(WHOAMI_TEST_KEY);
    $expectedNpub = (new Key)->convertPublicKeyToBech32($expectedHex);

    expect($output)->toContain($expectedNpub)
        ->and($output)->toContain($expectedHex)
        ->and($expectedNpub)->toStartWith('npub1');
});

it('accepts an nsec key and derives the same identity as the hex form', function () {
    $nsec = (new Key)->convertPrivateKeyToBech32(WHOAMI_TEST_KEY);

    $fromNsec = json_decode(whoamiOutput($nsec, json: true), true);
    $fromHex = json_decode(whoamiOutput(WHOAMI_TEST_KEY, json: true), true);

    expect($fromNsec['npub'])->toBe($fromHex['npub'])
        ->and($fromNsec['pubkey_hex'])->toBe($fromHex['pubkey_hex'])
        ->and($fromNsec['key_format'])->toBe('nsec (bech32)')
        ->and($fromHex['key_format'])->toBe('hex');
});

it('lists the configured relays', function () {
    $output = whoamiOutput(WHOAMI_TEST_KEY);

    expect($output)->toContain('wss://nos.lol')
        ->and($output)->toContain('wss://relay.damus.io');
});

it('fails with a clear message when the key is unset', function () {
    config([
        'services.nostr.publisher_key' => null,
        'services.nostr.relays' => ['wss://nos.lol'],
    ]);

    $this->artisan('nostr:whoami')
        ->expectsOutputToContain('NOSTR_PUBLISHER_NSEC ist nicht gesetzt.')
        ->assertExitCode(1);
});

it('fails without leaking library internals when the key is malformed', function () {
    config([
        'services.nostr.publisher_key' => 'nsec1definitelynotavalidkey',
        'services.nostr.relays' => ['wss://nos.lol'],
    ]);

    $this->artisan('nostr:whoami')
        ->expectsOutputToContain('could not be decoded')
        ->assertExitCode(1);
});

/*
 * The guard, not a nicety.
 *
 * A support answer is pasted from this command's output into a public issue. If any
 * run of it ever echoed the configured value — a prefix, a decoded half, a library
 * exception carrying the input — the portal's signing key would be public and
 * unrecoverable. Asserting "no npub-only" is not enough: the check has to be that no
 * RUN of the secret appears anywhere in any output mode, including the failure paths,
 * because those are the branches that print exception text.
 */
it('never prints any part of the private key, in any output mode', function () {
    $nsec = (new Key)->convertPrivateKeyToBech32(WHOAMI_TEST_KEY);

    $outputs = [
        'hex/table' => whoamiOutput(WHOAMI_TEST_KEY),
        'hex/json' => whoamiOutput(WHOAMI_TEST_KEY, json: true),
        'nsec/table' => whoamiOutput($nsec),
        'nsec/json' => whoamiOutput($nsec, json: true),
        'malformed' => whoamiOutput('nsec1definitelynotavalidkey'),
        'garbage' => whoamiOutput('not-a-key-at-all'),
    ];

    $secrets = [WHOAMI_TEST_KEY, $nsec, 'nsec1definitelynotavalidkey', 'not-a-key-at-all'];

    // Eight characters: short enough that a partial echo cannot slip through, long
    // enough that it cannot collide with ordinary output by chance.
    $runLength = 8;

    /**
     * Collected rather than asserted in the loop, and with str_contains rather than
     * expect()->not->toContain().
     *
     * toContain() takes a variadic list of needles and asserts that ALL are present, so
     * negating it asserts only that they are not ALL present — passing a message as the
     * second argument turns it into a second needle and the assertion can then never
     * fail. That is not a hypothetical: this test was written that way, a mutation that
     * appended substr($privateKey, 0, 10) to the key-format line was run against it, and
     * the guard stayed green while the key really was in the output.
     */
    $leaks = [];

    foreach ($outputs as $mode => $output) {
        foreach ($secrets as $secret) {
            for ($i = 0; $i + $runLength <= strlen($secret); $i++) {
                $run = substr($secret, $i, $runLength);

                if (str_contains($output, $run)) {
                    $leaks[] = "output mode [{$mode}] contains the run \"{$run}\" of a configured private key";
                }
            }
        }
    }

    expect($leaks)->toBe([]);

    // Positive control: the scan above is only meaningful if it can fail at all.
    $planted = 'key='.WHOAMI_TEST_KEY;
    $detected = [];

    for ($i = 0; $i + $runLength <= strlen(WHOAMI_TEST_KEY); $i++) {
        if (str_contains($planted, substr(WHOAMI_TEST_KEY, $i, $runLength))) {
            $detected[] = $i;
        }
    }

    expect($detected)->not->toBeEmpty();
});

/*
 * Until 2026-09-04 this asserted FALSE, and that was the whole of issue #49:
 * routes/console.php registered nostr:publish (kind 1) but never
 * nostr:publish-calendar (NIP-52), so an operator with a perfectly healthy key saw a
 * perfectly healthy report while nothing was being sent. The entry now exists
 * (NostrCalendarScheduleTest owns that fact), so this asserts the other direction —
 * the point being that whoami must READ the live schedule rather than hardcode an
 * answer, in either direction.
 */
it('reports that the publish command is scheduled', function () {
    $data = json_decode(whoamiOutput(WHOAMI_TEST_KEY, json: true), true);

    expect($data['publish_command_scheduled'])->toBeTrue();
});

it('warns in the table output only while the publish command is unscheduled', function () {
    // The warning is the operator-facing half of the same fact. Asserting its absence
    // alone would pass for a command that never prints anything, so the positive
    // control below drives the detector with a schedule that really lacks the entry.
    expect(whoamiOutput(WHOAMI_TEST_KEY))
        ->not->toContain('is not registered in the scheduler');

    $withoutEntry = new Schedule;
    $withoutEntry->command('nostr:publish', ['--model' => 'Meetup'])->hourly();
    app()->instance(Schedule::class, $withoutEntry);

    expect(whoamiOutput(WHOAMI_TEST_KEY))
        ->toContain('is not registered in the scheduler');
});

it('counts opted-in and published records when the schema is present', function () {
    whoamiMeetup(['nostr_publishing_enabled' => true]);
    whoamiMeetup(['nostr_publishing_enabled' => true, 'nostr_coordinate' => '31924:abc:meetup-x']);

    $data = json_decode(whoamiOutput(WHOAMI_TEST_KEY, json: true), true);

    expect($data['schema_ready'])->toBeTrue()
        ->and($data['meetups_opted_in'])->toBe(2)
        ->and($data['meetups_published'])->toBe(1);
});

/*
 * Without this guard the command would report a confident, wrong number.
 *
 * SQLite degrades a double-quoted identifier that matches no column into a string
 * literal instead of raising an error, and Laravel quotes identifiers with double
 * quotes. On a database that has not run the 2026_08_29 migrations, `whereNotNull`
 * therefore matches every row. Measured on this repo's dev database: 31 of 31
 * meetups counted as "published" while the column did not exist at all.
 */
it('says the counts are unknown instead of guessing when the column is missing', function () {
    Schema::table('meetups', fn ($table) => $table->dropColumn('nostr_coordinate'));

    $data = json_decode(whoamiOutput(WHOAMI_TEST_KEY, json: true), true);

    expect($data['schema_ready'])->toBeFalse()
        ->and($data['meetups_published'])->toBeNull()
        ->and($data['meetups_opted_in'])->toBeNull();
});
