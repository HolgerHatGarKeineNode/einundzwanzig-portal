<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Issue #56 — a cancelled event is REPORTED, not silently dropped
|--------------------------------------------------------------------------
|
| The behaviour this file guards is a before/after pair on the SAME UID, and
| that is the whole point: a subscriber's client has already materialised the
| entry, so an entry that merely stops being delivered tells it nothing. Only a
| VEVENT it still receives, carrying STATUS:CANCELLED and a higher SEQUENCE, can.
|
| Lives beside DownloadMeetupCalendarTest.php rather than inside it: that file is
| the feed's own suite and defines the shared unfoldIcs()/escapeIcsText() helpers
| at global scope, which cannot be redeclared here.
|
*/

/**
 * RFC 5545 §3.1 folds any line past 75 octets with "\r\n "; assertions read
 * logical values, so folded lines are joined back first. Same helper as in
 * DownloadMeetupCalendarTest, under a name that does not collide with it.
 */
function unfoldCancellationIcs(string $ics): string
{
    return preg_replace("/\r\n[ \t]/", '', $ics);
}

/**
 * The VEVENT block carrying $uid, or null. Assertions have to be made against
 * ONE component: "the feed contains STATUS:CANCELLED somewhere" would pass even
 * if the cancellation landed on a different event.
 */
function veventFor(string $ics, string $uid): ?string
{
    foreach (collect(explode('BEGIN:VEVENT', $ics))->skip(1) as $block) {
        if (str_contains($block, 'UID:'.$uid)) {
            return $block;
        }
    }

    return null;
}

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
    $this->meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'name' => 'Bitcoin Meetup Erfurt']);
});

/*
 * DoD 4 — the one the issue is actually about. Two fetches of the same feed, the
 * same UID in both, CONFIRMED before and CANCELLED after, with SEQUENCE strictly
 * higher so a client accepts the second as a revision of the first instead of
 * ignoring it as a stale duplicate.
 */
it('re-delivers a previously delivered UID as STATUS:CANCELLED instead of dropping it', function () {
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'title' => 'Stammtisch',
        'start' => now()->addWeek()->setTime(19, 0),
    ]);

    $before = unfoldCancellationIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar')->getContent());
    $uid = 'meetup-event-'.$event->id.'@einundzwanzig.space';

    $entryBefore = veventFor($before, $uid);
    expect($entryBefore)->not->toBeNull()
        ->and($entryBefore)->toContain('STATUS:CONFIRMED');

    preg_match('/SEQUENCE:(?<sequence>\d+)/', $entryBefore, $sequenceBefore);

    $this->travelTo(now()->addMinute());
    $event->update(['cancelled_at' => now()]);

    $after = unfoldCancellationIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar')->getContent());

    $entryAfter = veventFor($after, $uid);
    expect($entryAfter)->not->toBeNull()
        ->and($entryAfter)->toContain('STATUS:CANCELLED')
        ->and($entryAfter)->not->toContain('STATUS:CONFIRMED');

    preg_match('/SEQUENCE:(?<sequence>\d+)/', $entryAfter, $sequenceAfter);

    expect((int) $sequenceAfter['sequence'])->toBeGreaterThan((int) $sequenceBefore['sequence']);

    $this->travelBack();
});

/*
 * The bump must not depend on the clock ticking over. `updated_at` has
 * one-second resolution, so an organiser who saves and then cancels inside the
 * same second would otherwise emit the identical SEQUENCE twice — and a client
 * that fetched in between keeps its CONFIRMED copy. Same second here on purpose:
 * no travelTo().
 */
it('bumps SEQUENCE even when the cancellation lands in the same second as the previous save', function () {
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'start' => now()->addWeek(),
    ]);

    $uid = 'meetup-event-'.$event->id.'@einundzwanzig.space';
    $before = unfoldCancellationIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar')->getContent());
    preg_match('/SEQUENCE:(?<sequence>\d+)/', veventFor($before, $uid), $sequenceBefore);

    // Not travelling: cancelled_at is written while updated_at still holds the
    // very second the factory wrote.
    $event->update(['cancelled_at' => now()]);
    $event->refresh();

    $after = unfoldCancellationIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar')->getContent());
    preg_match('/SEQUENCE:(?<sequence>\d+)/', veventFor($after, $uid), $sequenceAfter);

    expect($event->updated_at->getTimestamp())->toBe((int) $sequenceBefore['sequence'])
        ->and((int) $sequenceAfter['sequence'])->toBeGreaterThan((int) $sequenceBefore['sequence']);
});

/*
 * DoD 5 — the window is bounded, or the feed grows without limit. Anchored on
 * the event's own start, so both edges are asserted against the same event.
 */
it('keeps a cancelled event in the feed inside the 30-day window and drops it afterwards', function () {
    $start = now()->addDay()->setTime(19, 0);
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'start' => $start,
    ]);
    $event->update(['cancelled_at' => now()]);

    $uid = 'meetup-event-'.$event->id.'@einundzwanzig.space';

    // The edges below are hard-coded days, NOT MeetupEvent::CANCELLED_FEED_WINDOW_DAYS.
    // Measured: with the constant on both sides, widening it from 30 to 3000 left this
    // test green — it would then assert that the code honours whatever window it
    // declares, which is not the decision #56 took. The length is the decision, so it
    // is pinned here and a change to it has to edit this file too.
    expect(MeetupEvent::CANCELLED_FEED_WINDOW_DAYS)->toBe(30);

    // A day after the event was supposed to happen: past, cancelled, still delivered.
    $this->travelTo($start->copy()->addDay());
    $inside = unfoldCancellationIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar')->getContent());
    expect(veventFor($inside, $uid))->not->toBeNull()
        ->and(veventFor($inside, $uid))->toContain('STATUS:CANCELLED');

    // Last hours of the window.
    $this->travelTo($start->copy()->addDays(30)->subHour());
    $edge = unfoldCancellationIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar')->getContent());
    expect(veventFor($edge, $uid))->not->toBeNull();

    // One hour past it: gone for good.
    $this->travelTo($start->copy()->addDays(30)->addHour());
    $outside = unfoldCancellationIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar')->getContent());
    expect(veventFor($outside, $uid))->toBeNull()
        ->and(substr_count($outside, 'BEGIN:VEVENT'))->toBe(0);

    $this->travelBack();
});

/*
 * DoD 6, first half. The window is for CANCELLED events only. The scope adds an
 * OR branch to a query that previously had one condition, and the cheapest way
 * to get that wrong is a branch that also lets ordinary past events back in —
 * which would put every meetup's history into every subscription.
 */
it('does not let the cancellation window drag ordinary past events back into the feed', function () {
    $past = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'title' => 'Letzte Woche',
        'start' => now()->subWeek(),
    ]);
    $upcoming = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'title' => 'Nächste Woche',
        'start' => now()->addWeek(),
    ]);

    $ics = unfoldCancellationIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar')->getContent());

    expect(substr_count($ics, 'BEGIN:VEVENT'))->toBe(1)
        ->and(veventFor($ics, 'meetup-event-'.$past->id.'@einundzwanzig.space'))->toBeNull()
        ->and(veventFor($ics, 'meetup-event-'.$upcoming->id.'@einundzwanzig.space'))->not->toBeNull()
        ->and($ics)->toContain('STATUS:CONFIRMED')
        ->and($ics)->not->toContain('STATUS:CANCELLED');
});

/*
 * DoD 6, second half. The scope's OR must stay inside its own group. An `orWhere`
 * that escapes into the caller's WHERE would OR straight past the country filter
 * and ship every country's events to a subscription that asked for one — the
 * fail-open shape #78 was about, and the reason the count is the assertion.
 */
it('still scopes the feed to the requested country when a cancelled event is in the window', function () {
    $czechCountry = Country::factory()->create(['code' => 'cz']);
    $czechCity = City::factory()->create(['country_id' => $czechCountry->id]);
    $czechMeetup = Meetup::factory()->create(['city_id' => $czechCity->id, 'name' => 'Czech Meetup']);

    $german = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'title' => 'German Event',
        'start' => now()->addWeek(),
    ]);
    $czechCancelled = MeetupEvent::factory()->create([
        'meetup_id' => $czechMeetup->id,
        'title' => 'Czech Event',
        'start' => now()->addWeek(),
    ]);
    $czechCancelled->update(['cancelled_at' => now()]);

    $ics = unfoldCancellationIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar?country=de')->getContent());

    expect(substr_count($ics, 'BEGIN:VEVENT'))->toBe(1)
        ->and(veventFor($ics, 'meetup-event-'.$german->id.'@einundzwanzig.space'))->not->toBeNull()
        ->and(veventFor($ics, 'meetup-event-'.$czechCancelled->id.'@einundzwanzig.space'))->toBeNull();
});

/*
 * DoD 2 — the organiser reaches cancellation where they reach deletion, and the
 * two do different things to the row.
 */
it('lets an organiser cancel an event without removing it', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'created_by' => actingAsUser()->id]);
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
    ]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event])
        ->call('cancel')
        ->assertOk();

    expect(MeetupEvent::query()->find($event->id))->not->toBeNull()
        ->and(MeetupEvent::query()->find($event->id)->isCancelled())->toBeTrue();
});

it('leaves deletion as deletion — the row is gone and the feed says nothing', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'created_by' => actingAsUser()->id]);
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
    ]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event])
        ->call('delete');

    $ics = unfoldCancellationIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar')->getContent());

    expect(MeetupEvent::query()->find($event->id))->toBeNull()
        ->and(veventFor($ics, 'meetup-event-'.$event->id.'@einundzwanzig.space'))->toBeNull();
});

it('refuses to open the event editor for someone who may not manage the meetup', function () {
    actingAsUser();
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
    ]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event])
        ->assertStatus(403);

    expect(MeetupEvent::query()->find($event->id)->isCancelled())->toBeFalse();
});

/*
 * The gate above only proves mount() refuses — it never reaches cancel(). This is
 * the case that does: the component was mounted by someone entitled to it, and by
 * the time the button is pressed they are not any more (leader removed, meetup
 * handed over). save() re-authorizes for exactly this reason, so cancel() has to
 * as well; a Livewire action is a request of its own, not a continuation of the
 * one that rendered the page.
 */
it('refuses to delete when the caller lost the right to manage after mounting', function () {
    // Issue #96. mount() guards the page, but a Livewire action is a request of
    // its own -- a component left open across a role change still dispatches
    // delete(), and deletion is the least reversible action on this form.
    $organiser = actingAsUser();
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'created_by' => $organiser->id]);
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
    ]);

    $component = Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event]);

    actingAsUser();

    $component->call('delete')->assertStatus(403);

    expect(MeetupEvent::query()->find($event->id))->not->toBeNull();
});

it('refuses to cancel when the caller lost the right to manage after mounting', function () {
    $organiser = actingAsUser();
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'created_by' => $organiser->id]);
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
    ]);

    $component = Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event]);

    actingAsUser();

    $component->call('cancel')->assertStatus(403);

    expect(MeetupEvent::query()->find($event->id)->isCancelled())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Issue #97 — the reversal, and what a subscriber who already holds the
| cancellation actually sees
|--------------------------------------------------------------------------
|
| The organiser can put `cancelled_at` back to null, and the point of the
| operation is NOT the database row: it is the second re-emission on the same
| UID. RFC 5546 §2.1.4 names "STATUS" among the properties whose change by the
| organizer MUST increment SEQUENCE, and it does not name a direction —
| CONFIRMED → CANCELLED and CANCELLED → CONFIRMED are both revisions.
|
| What the subscriber's client does with the second one is decided by the same
| rule that made the cancellation land, RFC 5546 §2.1.5: "the component with
| the highest numeric value for the SEQUENCE property obsoletes all other
| revisions of the component with lower values". A reinstatement that repeats
| the cancellation's number is therefore NOT a revision of it — §2.1.5 falls
| back to "the component with the latest DTSTAMP", and this feed's DTSTAMP is
| stamped at render time (see DownloadMeetupCalendar::resolveSequence), so it
| differs on every fetch of an entry nobody touched and cannot carry that
| decision. Hence the numbers are pinned here, not merely ordered: n for the
| confirmed entry, n+1 for the cancellation, n+2 for the reversal.
|
| Time is frozen in these tests on purpose. SEQUENCE is `updated_at` plus the
| stored offset, so a real clock would let a second tick between two saves and
| make the assertion "n+1" accidentally true for the wrong reason. Frozen, the
| only thing that can move the number is the offset this issue adds.
|
*/

/**
 * The VEVENT for $uid in the feed as it stands at this moment, unfolded.
 *
 * Every assertion below is a before/after on one UID across several fetches,
 * which is three lines of setup each time; the helper keeps the tests about the
 * numbers rather than about fetching.
 */
function cancellationFeedEntryFor(string $uid): ?string
{
    return veventFor(
        unfoldCancellationIcs(test()->get('http://portal.einundzwanzig.space/stream-calendar')->getContent()),
        $uid,
    );
}

/**
 * The SEQUENCE of a VEVENT block, as an integer.
 */
function sequenceIn(string $vevent): int
{
    preg_match('/SEQUENCE:(?<sequence>\d+)/', $vevent, $matches);

    return (int) ($matches['sequence'] ?? -1);
}

it('re-delivers an un-cancelled UID as STATUS:CONFIRMED with a SEQUENCE above the cancellation', function () {
    $this->freezeTime();

    $organiser = actingAsUser();
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'created_by' => $organiser->id]);
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'title' => 'Stammtisch',
        'start' => now()->addWeek()->setTime(19, 0),
    ]);

    $uid = 'meetup-event-'.$event->id.'@einundzwanzig.space';
    $component = Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event]);

    $announced = cancellationFeedEntryFor($uid);
    expect($announced)->not->toBeNull()
        ->and($announced)->toContain('STATUS:CONFIRMED');

    $base = sequenceIn($announced);

    $component->call('cancel')->assertOk();

    $cancelled = cancellationFeedEntryFor($uid);
    expect($cancelled)->not->toBeNull()
        ->and($cancelled)->toContain('STATUS:CANCELLED')
        ->and(sequenceIn($cancelled))->toBe($base + 1);

    $component->call('uncancel')->assertOk();

    $reinstated = cancellationFeedEntryFor($uid);
    expect($reinstated)->not->toBeNull()
        ->and($reinstated)->toContain('STATUS:CONFIRMED')
        ->and($reinstated)->not->toContain('STATUS:CANCELLED')
        // The failure mode this issue is about: a reversal carrying the
        // cancellation's own number, which §2.1.5 does not oblige any client to
        // accept over the cancelled copy it already holds.
        ->and(sequenceIn($reinstated))->toBe($base + 2)
        ->and(sequenceIn($reinstated))->toBeGreaterThan(sequenceIn($cancelled));

    expect(MeetupEvent::query()->find($event->id)->isCancelled())->toBeFalse();
});

/*
 * The offset counts STATUS revisions, not saves. If an ordinary edit bumped it
 * too, the number would drift away from `updated_at` for reasons no subscriber
 * can see, and the pinned n+1/n+2 above would only hold for an event nobody
 * edits.
 */
it('leaves SEQUENCE at the updated_at base for edits and for an un-cancel that reverses nothing', function () {
    $this->freezeTime();

    $organiser = actingAsUser();
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'created_by' => $organiser->id]);
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek()->setTime(19, 0),
    ]);

    $uid = 'meetup-event-'.$event->id.'@einundzwanzig.space';
    $base = sequenceIn(cancellationFeedEntryFor($uid));

    expect($base)->toBe($event->updated_at->getTimestamp());

    $event->update(['description' => 'Neuer Text']);
    expect(sequenceIn(cancellationFeedEntryFor($uid)))->toBe($base);

    // Never cancelled, so there is nothing to reverse and nothing to announce.
    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event])
        ->call('uncancel')
        ->assertOk();

    expect(sequenceIn(cancellationFeedEntryFor($uid)))->toBe($base);
});

/*
 * A cancelled event keeps the exact number #56 emitted for it — `updated_at`
 * plus one. This is what makes the stored offset a continuation of the old
 * hard-wired `+1` rather than a new counter: every subscriber out there already
 * holds that number, and an offset starting from zero on already-cancelled rows
 * would have made the next revision look OLDER than the copy they hold.
 */
it('keeps a cancellation at exactly the updated_at base plus one', function () {
    $this->freezeTime();

    $organiser = actingAsUser();
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'created_by' => $organiser->id]);
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek()->setTime(19, 0),
    ]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event])->call('cancel');

    $event->refresh();
    $entry = cancellationFeedEntryFor('meetup-event-'.$event->id.'@einundzwanzig.space');

    expect(sequenceIn($entry))->toBe($event->updated_at->getTimestamp() + 1);
});

/*
 * DoD 4 — the reversal has to be reachable where the cancellation is. The
 * cancel path is one button on the organiser's own event form, so the un-cancel
 * is the same button in the other state; nothing else in the application writes
 * `cancelled_at` (checked: the REST API's UpdateMeetupEventRequest and the MCP
 * tools do not expose the column at all).
 */
it('offers the reversal on the editor where it offers the cancellation', function () {
    $organiser = actingAsUser();
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'created_by' => $organiser->id]);
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
    ]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event])
        ->assertSee(__('Event absagen'))
        ->assertDontSee(__('Absage zurücknehmen'))
        ->call('cancel')
        ->assertSee(__('Absage zurücknehmen'))
        ->assertDontSee(__('Event absagen'));
});

/*
 * Same reasoning as for cancel() and delete(): a Livewire action is a request of
 * its own, so the component being open is not authorisation. Un-cancelling
 * re-announces an event to every subscriber, which is not something a former
 * organiser gets to do.
 */
it('refuses to un-cancel when the caller lost the right to manage after mounting', function () {
    $organiser = actingAsUser();
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'created_by' => $organiser->id]);
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
    ]);
    $event->update(['cancelled_at' => now()]);

    $component = Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event]);

    actingAsUser();

    $component->call('uncancel')->assertStatus(403);

    expect(MeetupEvent::query()->find($event->id)->isCancelled())->toBeTrue();
});
