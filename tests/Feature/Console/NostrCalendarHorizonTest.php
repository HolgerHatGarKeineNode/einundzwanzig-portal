<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\NostrCalendarEventFactory;
use App\Support\NostrCalendarHorizon;
use App\Support\NostrEventTransmitter;
use App\Support\NostrPublishFailures;
use App\Support\NostrRelayReader;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use swentel\nostr\Key\Key;
use Tests\Fixtures\RecordingNostrTransmitter;
use Tests\Fixtures\ScriptedNostrRelayReader;

/**
 * The horizon — the operator's rule of 2026-11-17: a date is sent only if
 *
 *     now < start <= now + services.nostr.calendar_horizon_days   (default 365)
 *
 * Recurring series reach as far as 2034; a repair run sent 31 dates of 2027–2030 before
 * it was stopped. Each test below holds ONE place that sends a kind 31923 or decides
 * about a kind 31924, with a date just inside the window next to one just outside it, so
 * that removing the horizon at that one place turns exactly that test red.
 */
const HORIZON_KEY = '7a1c3e5f9b2d4c6e8f0a1b3c5d7e9f2a4c6e8b0d1f3a5c7e9b2d4f6a8c0e1b3d';
const HORIZON_RELAYS = ['wss://a.relay.test', 'wss://b.relay.test'];

function horizonPubkey(): string
{
    return (new Key)->getPublicKey(HORIZON_KEY);
}

function horizonMeetup(bool $published = true): Meetup
{
    $city = City::factory()->create(['country_id' => Country::factory()->create(['code' => 'de'])->id]);
    $meetup = Meetup::factory()->create(['city_id' => $city->id, 'nostr_publishing_enabled' => true]);

    if ($published) {
        $meetup->forceFill(['nostr_coordinate' => '31924:'.horizonPubkey().':meetup-'.$meetup->id])->save();
    }

    return $meetup;
}

function horizonDate(Meetup $meetup, CarbonInterface $start, bool $published = true, array $attributes = []): MeetupEvent
{
    $event = MeetupEvent::factory()->create(array_merge(['meetup_id' => $meetup->id, 'start' => $start], $attributes));

    if ($published) {
        $event->forceFill(['nostr_coordinate' => '31923:'.horizonPubkey().':meetup-event-'.$event->id])->save();
    }

    return $event;
}

/**
 * Relays that answer and hold nothing, and a transmitter that records.
 *
 * @return array{0: ScriptedNostrRelayReader, 1: RecordingNostrTransmitter}
 */
function horizonWorld(): array
{
    $reader = new ScriptedNostrRelayReader;
    $reader->relays = array_fill_keys(HORIZON_RELAYS, []);
    app()->instance(NostrRelayReader::class, $reader);

    $transmitter = new RecordingNostrTransmitter;
    app()->instance(NostrEventTransmitter::class, $transmitter);

    return [$reader, $transmitter];
}

/**
 * The `d` tags of what left, in the order it left, each once.
 *
 * @return list<string>
 */
function horizonSent(RecordingNostrTransmitter $transmitter, int $kind): array
{
    return array_values(array_unique(array_map(
        fn ($event): string => (string) $transmitter->tagValue($event, 'd'),
        $transmitter->ofKind($kind),
    )));
}

/**
 * Every `#d` value a run asked a relay for — what was not asked for was not in scope.
 *
 * @return list<string>
 */
function horizonAsked(ScriptedNostrRelayReader $reader): array
{
    $asked = [];

    foreach ($reader->reads as $read) {
        foreach ($read['filters'] as $filter) {
            $asked = [...$asked, ...($filter['#d'] ?? [])];
        }
    }

    return array_values(array_unique($asked));
}

beforeEach(function () {
    config([
        'services.nostr.publisher_key' => HORIZON_KEY,
        'services.nostr.relays' => HORIZON_RELAYS,
        'services.nostr.calendar_horizon_days' => 365,
    ]);
    Cache::flush();
    $this->freezeSecond();
});

it('takes the window from the config, once, with a year as the default', function () {
    expect(config('services.nostr.calendar_horizon_days'))->toBe(365)
        ->and(NostrCalendarHorizon::end()->equalTo(now()->addDays(365)))->toBeTrue();

    config(['services.nostr.calendar_horizon_days' => 30]);

    $meetup = horizonMeetup();
    $inside = horizonDate($meetup, now()->addDays(30));
    horizonDate($meetup, now()->addDays(31));
    [, $transmitter] = horizonWorld();

    $this->artisan('nostr:rebroadcast-calendar', ['--model' => 'MeetupEvent', '--force' => true, '--sleep' => 0])->assertExitCode(0);

    expect(horizonSent($transmitter, 31923))->toBe(["meetup-event-{$inside->id}"]);
});

/*
|--------------------------------------------------------------------------
| nostr:rebroadcast-calendar
|--------------------------------------------------------------------------
*/

it('rebroadcast neither reads nor sends a date further ahead than the horizon', function () {
    $meetup = horizonMeetup();
    $inside = horizonDate($meetup, now()->addDays(365));
    $outside = horizonDate($meetup, now()->addDays(365)->addSecond());
    $series2030 = horizonDate($meetup, now()->addYears(4));
    [$reader, $transmitter] = horizonWorld();

    $this->artisan('nostr:rebroadcast-calendar', ['--model' => 'MeetupEvent', '--force' => true, '--sleep' => 0])->assertExitCode(0);

    expect(horizonSent($transmitter, 31923))->toBe(["meetup-event-{$inside->id}"])
        ->and(horizonAsked($reader))->toBe(["meetup-event-{$inside->id}"])
        ->and($outside->start->greaterThan(NostrCalendarHorizon::end()))->toBeTrue()
        ->and($series2030->start->greaterThan(NostrCalendarHorizon::end()))->toBeTrue();
});

it('rebroadcast does not send a date that was moved beyond the horizon while the relays were being read', function () {
    $meetup = horizonMeetup();
    $stays = horizonDate($meetup, now()->addDays(10));
    $moved = horizonDate($meetup, now()->addDays(20));
    [$reader, $transmitter] = horizonWorld();

    // In scope when the run began; an organiser turns it into a 2030 date meanwhile.
    $reader->onRead = fn () => MeetupEvent::query()->whereKey($moved->id)->update(['start' => now()->addYears(4)]);

    $this->artisan('nostr:rebroadcast-calendar', ['--model' => 'MeetupEvent', '--force' => true, '--sleep' => 0])->assertExitCode(0);

    expect(horizonAsked($reader))->toContain("meetup-event-{$moved->id}")
        ->and(horizonSent($transmitter, 31923))->toBe(["meetup-event-{$stays->id}"]);
});

it('rebroadcast sends a calendar only for a meetup that has a date within the horizon', function () {
    $near = horizonMeetup();
    horizonDate($near, now()->addDays(40));
    horizonDate($near, now()->addYears(3));

    $farOnly = horizonMeetup();
    horizonDate($farOnly, now()->addDays(366));
    [$reader, $transmitter] = horizonWorld();

    $this->artisan('nostr:rebroadcast-calendar', ['--model' => 'Meetup', '--force' => true, '--sleep' => 0])->assertExitCode(0);

    expect(horizonSent($transmitter, 31924))->toBe(["meetup-{$near->id}"])
        ->and(horizonAsked($reader))->toBe(["meetup-{$near->id}"]);
});

it('rebroadcast does not send a calendar whose only near date was moved beyond the horizon during the reads', function () {
    $keeps = horizonMeetup();
    horizonDate($keeps, now()->addDays(10));

    $loses = horizonMeetup();
    $moved = horizonDate($loses, now()->addDays(20));
    [$reader, $transmitter] = horizonWorld();

    $reader->onRead = fn () => MeetupEvent::query()->whereKey($moved->id)->update(['start' => now()->addYears(4)]);

    $this->artisan('nostr:rebroadcast-calendar', ['--model' => 'Meetup', '--force' => true, '--sleep' => 0])->assertExitCode(0);

    expect(horizonAsked($reader))->toContain("meetup-{$loses->id}")
        ->and(horizonSent($transmitter, 31924))->toBe(["meetup-{$keeps->id}"]);
});

it('rebroadcast sends the nearest start first, whatever the ids say', function () {
    $meetup = horizonMeetup();
    // Created — and therefore numbered — in the opposite order of their start.
    $inHalfAYear = horizonDate($meetup, now()->addDays(180));
    $nextMonth = horizonDate($meetup, now()->addDays(30));
    $tomorrow = horizonDate($meetup, now()->addDay());
    [, $transmitter] = horizonWorld();

    $this->artisan('nostr:rebroadcast-calendar', ['--model' => 'MeetupEvent', '--force' => true, '--sleep' => 0])->assertExitCode(0);

    expect(horizonSent($transmitter, 31923))->toBe([
        "meetup-event-{$tomorrow->id}",
        "meetup-event-{$nextMonth->id}",
        "meetup-event-{$inHalfAYear->id}",
    ]);
});

it('rebroadcast sends the calendar of the meetup that meets soonest first', function () {
    $meetsInAutumn = horizonMeetup();
    horizonDate($meetsInAutumn, now()->addDays(300));

    $meetsNextWeek = horizonMeetup();
    horizonDate($meetsNextWeek, now()->addDays(200));
    horizonDate($meetsNextWeek, now()->addDays(7));

    $meetsNextMonth = horizonMeetup();
    horizonDate($meetsNextMonth, now()->addDays(30));
    [, $transmitter] = horizonWorld();

    $this->artisan('nostr:rebroadcast-calendar', ['--model' => 'Meetup', '--force' => true, '--sleep' => 0])->assertExitCode(0);

    expect(horizonSent($transmitter, 31924))->toBe([
        "meetup-{$meetsNextWeek->id}",
        "meetup-{$meetsNextMonth->id}",
        "meetup-{$meetsInAutumn->id}",
    ]);
});

it('rebroadcast walks on by start from where the last run stopped, and starts over at the nearest', function () {
    $meetup = horizonMeetup();
    $third = horizonDate($meetup, now()->addDays(90));
    $first = horizonDate($meetup, now()->addDays(3));
    $second = horizonDate($meetup, now()->addDays(40));
    [, $transmitter] = horizonWorld();

    $run = function () use ($transmitter): array {
        $transmitter->events = [];
        $this->artisan('nostr:rebroadcast-calendar', ['--model' => 'MeetupEvent', '--force' => true, '--sleep' => 0, '--limit' => 2])->assertExitCode(0);

        return horizonSent($transmitter, 31923);
    };

    expect($run())->toBe(["meetup-event-{$first->id}", "meetup-event-{$second->id}"])
        ->and($run())->toBe(["meetup-event-{$third->id}", "meetup-event-{$first->id}"]);
});

/*
|--------------------------------------------------------------------------
| nostr:republish-calendar
|--------------------------------------------------------------------------
*/

it('republish re-sends no date further ahead than the horizon', function (bool $changedOnly) {
    $meetup = horizonMeetup();
    $inside = horizonDate($meetup, now()->addDays(364));
    horizonDate($meetup, now()->addDays(366));
    horizonDate($meetup, now()->addYears(4));
    [, $transmitter] = horizonWorld();

    $this->artisan('nostr:republish-calendar', [
        '--model' => 'MeetupEvent', '--force' => true, '--sleep' => 0, ...($changedOnly ? ['--changed' => true] : []),
    ])->assertExitCode(0);

    expect(horizonSent($transmitter, 31923))->toBe(["meetup-event-{$inside->id}"]);
})->with(['every published record' => false, '--changed' => true]);

it('republish sends a cancellation notice only for a date within the horizon', function () {
    $meetup = horizonMeetup();
    $cancelledNear = horizonDate($meetup, now()->addDays(20), attributes: ['cancelled_at' => now()]);
    horizonDate($meetup, now()->addYears(2), attributes: ['cancelled_at' => now()]);
    [, $transmitter] = horizonWorld();

    $this->artisan('nostr:republish-calendar', ['--model' => 'MeetupEvent', '--force' => true, '--sleep' => 0, '--changed' => true])
        ->assertExitCode(0);

    expect(horizonSent($transmitter, 31923))->toBe(["meetup-event-{$cancelledNear->id}"]);
});

it('republish re-sends a calendar only for a meetup that has a date within the horizon', function () {
    $near = horizonMeetup();
    horizonDate($near, now()->addDays(40));

    $farOnly = horizonMeetup();
    horizonDate($farOnly, now()->addDays(366));
    [, $transmitter] = horizonWorld();

    $this->artisan('nostr:republish-calendar', ['--model' => 'Meetup', '--force' => true, '--sleep' => 0])->assertExitCode(0);

    expect(horizonSent($transmitter, 31924))->toBe(["meetup-{$near->id}"]);
});

/*
|--------------------------------------------------------------------------
| nostr:publish-calendar
|--------------------------------------------------------------------------
*/

it('publish leaves a date for 2030 alone until it comes within the horizon, then publishes it by itself', function () {
    $meetup = horizonMeetup();
    $far = horizonDate($meetup, now()->addDays(400), published: false);
    [, $transmitter] = horizonWorld();

    // It is the whole queue — and still nothing is sent, nothing fails, nothing is counted.
    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent', '--sleep' => 0])->assertExitCode(0);

    expect($transmitter->events)->toBe([])
        ->and($far->refresh()->nostr_coordinate)->toBeNull()
        ->and((int) $far->{NostrPublishFailures::ATTEMPTS_COLUMN})->toBe(0);

    // 34 days later it starts in 366 days: still outside.
    $this->travel(34)->days();
    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent', '--sleep' => 0])->assertExitCode(0);
    expect($far->refresh()->nostr_coordinate)->toBeNull();

    // One more day and the same queue run picks it up without anybody touching it.
    $this->travel(1)->days();
    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent', '--sleep' => 0])->assertExitCode(0);

    expect($far->refresh()->nostr_coordinate)->toBe('31923:'.horizonPubkey().":meetup-event-{$far->id}")
        ->and(horizonSent($transmitter, 31923))->toBe(["meetup-event-{$far->id}"]);
});

it('publish sends the date within the horizon and neither sends nor counts the far-future ones beside it', function () {
    $meetup = horizonMeetup();
    $far = collect(range(1, 3))->map(fn (int $i) => horizonDate($meetup, now()->addDays(365 + $i), published: false));
    $near = horizonDate($meetup, now()->addDays(300), published: false);
    [, $transmitter] = horizonWorld();

    // No --limit 1 here on purpose: the nearest start goes first anyway, so a limit of
    // one would keep the far dates out of the run whether or not the horizon holds.
    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent', '--sleep' => 0])->assertExitCode(0);

    expect(horizonSent($transmitter, 31923))->toBe(["meetup-event-{$near->id}"])
        ->and($far->every(fn (MeetupEvent $event): bool => $event->refresh()->nostr_coordinate === null
            && (int) $event->{NostrPublishFailures::ATTEMPTS_COLUMN} === 0))->toBeTrue();
});

it('publish sends a calendar first when its meetup has a date within the horizon', function () {
    $farOnly = horizonMeetup(published: false);
    $date = horizonDate($farOnly, now()->addDays(380), published: false);

    $withoutDates = horizonMeetup(published: false);

    $near = horizonMeetup(published: false);
    horizonDate($near, now()->addDays(14), published: false);
    [, $transmitter] = horizonWorld();

    $this->artisan('nostr:publish-calendar', ['--model' => 'Meetup', '--sleep' => 0])->assertExitCode(0);

    expect(horizonSent($transmitter, 31924))->toBe(["meetup-{$near->id}"])
        ->and($farOnly->refresh()->nostr_coordinate)->toBeNull()
        ->and($withoutDates->refresh()->nostr_coordinate)->toBeNull();

    // Sixteen days on, the 380-day date starts in 364: its calendar follows by itself.
    $this->travel(16)->days();
    $transmitter->events = [];
    $this->artisan('nostr:publish-calendar', ['--model' => 'Meetup', '--sleep' => 0])->assertExitCode(0);

    expect(horizonSent($transmitter, 31924))->toBe(["meetup-{$farOnly->id}"])
        ->and($date->refresh()->nostr_coordinate)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| The calendar's `a` tags
|--------------------------------------------------------------------------
*/

it('lists only the dates within the horizon in a calendar', function () {
    $meetup = horizonMeetup();
    $soon = horizonDate($meetup, now()->addDays(7));
    $atTheEdge = horizonDate($meetup, now()->addDays(365));
    horizonDate($meetup, now()->addDays(365)->addMinute());
    horizonDate($meetup, now()->addYears(8));
    horizonDate($meetup, now()->subDay());

    $calendar = NostrCalendarEventFactory::forMeetup($meetup->fresh());

    $listed = array_values(array_map(
        fn (array $tag): string => $tag[1],
        array_filter($calendar->getTags(), fn (array $tag): bool => $tag[0] === 'a'),
    ));

    expect($listed)->toEqualCanonicalizing([$soon->nostr_coordinate, $atTheEdge->nostr_coordinate]);
});
