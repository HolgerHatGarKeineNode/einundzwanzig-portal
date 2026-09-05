<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\NostrEventTransmitter;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use swentel\nostr\Event\Event;

/*
 * Issue #76: both Nostr publishers key PHP arrays on the stored country code, and a
 * PHP array lookup is case-sensitive. The `Country::matchingCode()` scope added for
 * #58 compares in SQL and therefore does not reach them, so an uppercase stored code
 * misses every key and falls through to the German defaults: a Spanish meetup is
 * published with `start_tzid: Europe/Berlin`, and a Dutch one with the German domain
 * and locale. A published Nostr event is immutable on the relays, so a later
 * correction leaves both versions in circulation.
 *
 * The fixtures store 'ES' and 'NL' because that is what `CountryFactory` writes into
 * every development and test database — the uppercase code is the natural shape here,
 * not a contrived one. The existing tests for both commands all seed lowercase codes,
 * which is exactly why none of them could see this.
 */

const UPPERCASE_CODE_TEST_KEY = '4f964f6b93a5b1e5f6f9b1d3a4f5e6d7c8b9a0f1e2d3c4b5a6978869504132a1';

function uppercaseCodeMeetup(string $countryCode, array $attributes = []): Meetup
{
    $city = City::factory()->create([
        'country_id' => Country::factory()->create(['code' => $countryCode])->id,
        'latitude' => 40.4168,
        'longitude' => -3.7038,
    ]);

    return Meetup::factory()->create(array_merge(['city_id' => $city->id], $attributes));
}

beforeEach(function () {
    config([
        'services.nostr.publisher_key' => UPPERCASE_CODE_TEST_KEY,
        'services.nostr.relays' => ['wss://fake.relay.test'],
    ]);
});

it('publishes a Spanish calendar event with the Madrid timezone when the stored code is uppercase', function () {
    $transmitted = null;

    $this->mock(NostrEventTransmitter::class, function ($mock) use (&$transmitted) {
        $mock->shouldReceive('transmit')
            ->once()
            ->andReturnUsing(function (Event $event, array $relayUrls) use (&$transmitted) {
                $transmitted = $event;

                return true;
            });
    });

    $meetup = uppercaseCodeMeetup('ES', ['nostr_publishing_enabled' => true]);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
    ]);

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent'])
        ->assertExitCode(0);

    expect($transmitted?->getTag('start_tzid'))->toBe([['start_tzid', 'Europe/Madrid']]);
});

it('publishes a Dutch note with the Dutch domain, timezone and locale when the stored code is uppercase', function () {
    $publishedText = null;

    // `noscl publish <text>` is invoked with an argument array; the note text is its third element.
    Process::fake(function (PendingProcess $process) use (&$publishedText): string {
        $publishedText = (string) ($process->command[2] ?? '');

        return 'note1'.str_repeat('a', 58);
    });

    $meetup = uppercaseCodeMeetup('NL');
    $meetupEvent = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addDay(),
        // MeetupEventFactory fills nostr_status from NostrHelper::fakeNostrEventStatus(),
        // which returns a status in 10 % of calls — that would take the record out of the
        // command's `whereNull` gate and flake this test one run in ten.
        'nostr_status' => null,
    ]);

    $this->artisan('nostr:publish', ['--model' => 'MeetupEvent'])
        ->assertExitCode(0);

    // Scheme-free on purpose: `URL::useOrigin('https://…')` only forces the ROOT, the
    // scheme still comes from the environment (http in the test suite). That is not
    // what #76 is about — the assertion here is the host and the country segment.
    $expectedUrl = '//portal.eenentwintig.net/nl/meetup/'
        ."{$meetup->slug}/event/{$meetupEvent->id}";

    expect($publishedText)->toContain($expectedUrl)
        ->and(config('app.user-timezone'))->toBe('Europe/Amsterdam')
        ->and(config('app.locale'))->toBe('nl');
});
