<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/*
|--------------------------------------------------------------------------
| nostr:publish-calendar must be registered in the scheduler (issue #49)
|--------------------------------------------------------------------------
|
| This is the guard for the actual defect behind #49. The NIP-52 publisher
| shipped in two commits — 19b405f (11 files) and d480004 (10 files) — and
| neither touched routes/console.php. The command was complete, tested and
| correct, and it had simply never run: an organiser enabled publishing,
| searched five relays, and found nothing, because nothing was ever sent.
|
| Asserted against the RESOLVED SCHEDULE, never against the text of
| routes/console.php. A grep for "PublishCalendarEvents" in that file would
| pass on a commented-out line, on an entry inside an unreachable branch, and
| on a mention in a docblock — including the long one this fix added, which is
| exactly the kind of false green that would let the defect return unnoticed.
| app(Schedule::class)->events() is what Laravel will actually run.
*/

/**
 * Every command string Laravel would run, e.g.
 * "'/usr/bin/php' 'artisan' nostr:publish-calendar --model='Meetup'".
 *
 * @return array<int, Event>
 */
function scheduledEvents(): array
{
    return app(Schedule::class)->events();
}

function scheduledMatching(string $needle): array
{
    return array_values(array_filter(
        scheduledEvents(),
        fn (Event $event): bool => str_contains((string) $event->command, $needle)
    ));
}

it('schedules the calendar publisher for meetups and for events', function () {
    $forMeetups = scheduledMatching("nostr:publish-calendar --model='Meetup'");
    $forEvents = scheduledMatching("nostr:publish-calendar --model='MeetupEvent'");

    // "--model='Meetup'" is a prefix of "--model='MeetupEvent'" only up to the closing
    // quote, so the two filters above are genuinely disjoint — but assert the count
    // rather than trust that, or one missing entry would hide behind the other.
    expect($forMeetups)->toHaveCount(1, 'nostr:publish-calendar is not scheduled for the Meetup model')
        ->and($forEvents)->toHaveCount(1, 'nostr:publish-calendar is not scheduled for the MeetupEvent model');
});

/*
 * The interval is load-bearing, not cosmetic: PublishCalendarEvents handles ONE record
 * per run, so a backlog of N needs N runs. Measured from the public API on 2026-09-04,
 * as upper bounds: 307 meetups and 76 upcoming events portal-wide. At hourly the
 * one-time meetup drain takes 12.8 days, during which a leader who switched publishing
 * on keeps reading "not yet published" on his own page — the complaint that produced
 * issue #49. At five minutes it is 25.6 h, and a new opt-in on an idle queue publishes
 * in under five rather than under sixty minutes.
 *
 * NOT because hourly would lose events. An earlier version of this comment said the 5
 * events starting within 48 h would drop out; that was computed against the queue order
 * in force at the time (`created_at DESC`) and stopped being true when the queue was
 * reordered to `start` ascending. Under deadline order the k-th soonest event publishes
 * on the k-th run, so those five are k=1..5 and go out 1 to 5 hours in even at hourly.
 * Loss now sets the MARGIN, not the interval: 24 sooner-starting events would be needed
 * to lose one with 24 h of lead time at hourly, 288 at five minutes, against 2 measured.
 *
 * So a "let's not hammer the scheduler" edit to hourly is a regression in
 * responsiveness and in margin rather than immediate data loss — still worth turning
 * this test red so the trade is made deliberately.
 */
it('drains fast enough to keep the backlog and the safety margin where they were chosen', function () {
    foreach (['Meetup', 'MeetupEvent'] as $model) {
        $event = scheduledMatching("nostr:publish-calendar --model='{$model}'")[0] ?? null;

        expect($event)->not->toBeNull("no schedule entry for {$model}");
        expect($event->expression)->toBe('*/5 * * * *', "cadence for {$model} is no longer every five minutes");
    }
});

/*
 * Negative control. Without it, a typo in the needle would make every filter above
 * return an empty array and toHaveCount(1) would simply fail — but a typo in the
 * OPPOSITE direction (a needle so loose it matches anything) would pass silently.
 * This proves the matcher discriminates.
 */
it('does not report a command that is not scheduled', function () {
    expect(scheduledMatching('nostr:publish-calendar --model=Course'))->toBe([])
        ->and(scheduledMatching('nostr:this-command-does-not-exist'))->toBe([]);

    // And the matcher still finds the sibling publisher, so an empty result above is a
    // real absence rather than a broken lookup.
    expect(scheduledMatching('nostr:publish '))->not->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| nostr:republish-calendar --changed must be registered too (issue #92)
|--------------------------------------------------------------------------
|
| Same guard, same reason, one issue later. The two publisher entries above
| gate on `nostr_coordinate IS NULL`, so a record they published keeps its
| payload for good; #92 is that nothing ever re-sends it. The command that
| does exists and is tested, and — exactly as in #49 — being complete and
| correct is not the same as running.
*/
it('schedules the payload repair for already-published records', function () {
    $entries = scheduledMatching('nostr:republish-calendar');

    expect($entries)->toHaveCount(1, 'nostr:republish-calendar is not scheduled at all');
});

/*
 * THE FLAGS ARE THE DECISION, not decoration on it.
 *
 * `--changed` is what separates this entry from the thing the repo owner
 * rejected: a nightly blanket republish ships ~400 signed events to every
 * relay for nothing, and a bad payload is then re-broadcast on a timer instead
 * of once. Dropping the flag leaves a line that still runs, still exits 0 and
 * quietly becomes that blanket republish — so the flag is asserted, not just
 * the command name.
 *
 * `--force` is what makes it transmit at all; without it the command is a dry
 * run by default and the schedule entry would print a plan into a log nobody
 * reads, while the back catalogue stayed exactly as broken as #92 found it.
 *
 * `--limit` is the batch cap the pacing arithmetic in routes/console.php is
 * computed against: 10 records, `--sleep=2` between them, i.e. a peak of 0.5
 * events/s per relay for 18 s and a sustained 10 events/h.
 */
it('repairs only changed payloads, transmits, and caps the batch', function () {
    $command = (string) scheduledMatching('nostr:republish-calendar')[0]->command;

    // NOT `toContain($needle, $message)`: Pest's toContain is VARIADIC, so a second
    // argument is read as a second needle and the assertion then demands the whole
    // sentence appear in the command string. Measured here — it failed on the first
    // run, which is the only reason this note exists rather than a silent green.
    expect(str_contains($command, '--changed'))
        ->toBeTrue('the repair entry lost --changed and is now a blanket republish')
        ->and(str_contains($command, '--force'))
        ->toBeTrue('the repair entry is a dry run and repairs nothing')
        ->and(str_contains($command, '--limit=10'))
        ->toBeTrue('the repair entry lost its batch cap');
});

/*
 * Hourly, and the interval is a trade rather than a taste.
 *
 * Unlike the publisher above, this command cannot pick its work with an
 * indexed WHERE — "has the payload changed" is only answerable by BUILDING the
 * payload, so every run costs one event build per already-published record
 * whether or not anything is stale. Measured 2026-09-05 on 383 published
 * records, the size production reaches if every meetup opts in: 72 ms and 322
 * queries per idle scan. That is cheap at either cadence, which is the honest
 * reading — every five minutes would simply pay it twelve times over for a
 * repair nobody is waiting on. The batch cap is what carries the drain rate:
 * a code change that moves every payload clears 383 records in 39 runs, under
 * two days, instead of 383 runs.
 */
it('repairs hourly, the cadence its scan cost and batch size were chosen for', function () {
    expect(scheduledMatching('nostr:republish-calendar')[0]->expression)
        ->toBe('0 * * * *', 'the payload repair is no longer hourly');
});
