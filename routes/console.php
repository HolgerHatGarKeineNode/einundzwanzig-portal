<?php

use App\Console\Commands\Database\CleanupLoginKeys;
use App\Console\Commands\Database\PruneApiChanges;
use App\Console\Commands\Database\UpdateMeetupActivity;
use App\Console\Commands\Nostr\PublishCalendarEvents;
use App\Console\Commands\Nostr\PublishUnpublishedItems;

Schedule::command(CleanupLoginKeys::class)->everyFifteenMinutes();

Schedule::command(PublishUnpublishedItems::class, [
    '--model' => 'MeetupEvent',
])->hourly();

Schedule::command(PublishUnpublishedItems::class, [
    '--model' => 'Meetup',
])->dailyAt('18:00');

/*
|--------------------------------------------------------------------------
| NIP-52 calendar publishing (issue #49)
|--------------------------------------------------------------------------
|
| These two entries are the fix for #49. The feature shipped in two commits
| (19b405f, 11 files; d480004, 10 files) and neither touched this file, so
| `nostr:publish-calendar` had never run: an organiser switched publishing on,
| searched five relays and found nothing, because nothing was ever sent.
| Measured 2026-09-04 across nos.lol, primal and snort — zero events under any
| of 1398 possible portal d-tags, with a positive control proving the query
| worked. `tests/Feature/Console/NostrCalendarScheduleTest.php` is the guard
| that keeps a third commit from leaving this file alone again.
|
| WHY EVERY FIVE MINUTES, AND NOT `hourly()` LIKE ITS SIBLING ABOVE
|
| PublishCalendarEvents handles exactly ONE record per run (`$query->first()`),
| so the interval is not a refresh rate — it is a drain rate, and a backlog of
| N records needs N runs. Production numbers, read from the public API on
| 2026-09-04: 307 meetups and 76 upcoming events portal-wide. Those are the
| upper bounds of the backlog, reached only if every meetup opted in.
|
|   Meetup, worst case 307 records (a ONE-TIME drain; a published calendar is
|   never re-sent, the query is gated on `nostr_coordinate IS NULL`)
|     dailyAt             307 days
|     hourly               12.8 days
|     everyFiveMinutes     25.6 h
|
|   MeetupEvent, worst case 76 records
|     hourly              76 h  = 3.2 days
|     everyFiveMinutes     6.3 h
|
| THE MEETUP DRAIN IS THE REASON, and it is about the complaint in #49 rather
| than about data loss: for up to 12.8 days at hourly, a leader who switched
| publishing on would keep seeing "not yet published" on his own page. That is
| precisely the experience that produced this issue. Five minutes puts the
| one-time backlog at just over a day, and a NEW opt-in on an idle queue at
| under five minutes instead of under an hour.
|
| WHAT THIS ARGUMENT IS NOT. An earlier version of this comment claimed hourly
| would LOSE five of the 76 events. That was computed against the queue order
| this file shipped with at the time — `created_at DESC` — and it stopped
| being true in the same change that reordered the queue to `start` ascending
| (PublishCalendarEvents, "MeetupEvent" arm). Under deadline order the k-th
| soonest event publishes on the k-th run, so the 2 events starting within 24 h
| are k=1,2 and the 5 within 48 h are k=1..5: at hourly they publish 1 to 5
| hours in, and NONE is lost. The correct loss condition is the one stated at
| PublishCalendarEvents — more events starting before it than the schedule can
| drain inside its lead time — and it is self-limiting, because everything
| ahead of an urgent record is more urgent still.
|
| So loss no longer decides the interval; it decides the MARGIN, and that is
| the secondary reason to keep five minutes. Losing an event with 24 h of lead
| time needs 24 sooner-starting events at hourly and 288 at five minutes,
| against 2 measured — a 12x margin versus 144x. The same ratio is what absorbs
| a relay outage that lets the backlog build, and what protects a
| short-notice event, where hourly tolerates one sooner-starting event and five
| minutes tolerates 24.
|
| The Meetup entry runs at the same rate rather than slower, because a kind
| 31923 carries an `a` tag pointing at its calendar's coordinate (see
| NostrCalendarEventFactory::forMeetupEvent). That address is computed, so it
| is never wrong — but until the calendar itself is published, a viewer that
| follows the tag finds nothing. Keeping the calendars close behind their
| events keeps that window short.
|
| Idle cost is one indexed query per run: with nothing to publish the command
| prints "No unpublished items" and exits 0 without opening a socket.
|
| NO `withoutOverlapping()` / `onOneServer()` — MATCHING THE HOUSE PATTERN
|
| No entry in this file uses either, and that is deliberate here rather than
| copied. An overlap would be HARMLESS: both runs would select the same record,
| sign the same parameterized-replaceable event under the same deterministic
| d-tag, and the relay would replace it in place. The write is idempotent, so
| there is no duplicate to prevent. A `withoutOverlapping()` lock would trade
| that harmless case for a worse one, since a hard-killed process leaves the
| lock behind for its expiry (24 h by default) and publishing stops silently —
| which is the exact failure mode #49 was about.
|
| Timing is the secondary argument, and it is weaker than it first looks.
| swentel/nostr-php defaults to a 60 s websocket timeout (Request::$timeout)
| and NostrEventTransmitter walks the relays sequentially, so with the two
| configured relays a hung run ends after ~120 s. But that bound only holds for
| a SILENT socket: Request::getResponseFromRelay applies the timeout per
| `receive()` call inside `while ($response = $client->receive())`, so a relay
| that keeps sending frames resets the window on every iteration and a run has
| no hard cap. The silent hang is the usual one, which is why ~120 s is the
| useful figure rather than the guaranteed one — and why the idempotence above,
| not the arithmetic here, is what makes the missing guard safe.
|
| WHEN TO REVISIT. Growing NOSTR_RELAYS does not weaken the correctness half:
| concurrent runs still write the same coordinate idempotently, at any relay
| count. What stops being bounded is the PROCESS COUNT, since steady-state
| concurrency is ceil(runtime / interval) — at six relays a silent-hang run
| (~360 s) overlaps the five-minute tick and two publishers sit in memory
| continuously, more as the list grows. At two relays that number is 1 and the
| lock would only add a way to stop publishing, so it is not worth it here;
| past roughly five relays, `withoutOverlapping()` with a SHORT explicit expiry
| (minutes, never the 24 h default) becomes the better trade.
*/
Schedule::command(PublishCalendarEvents::class, [
    '--model' => 'MeetupEvent',
])->everyFiveMinutes();

Schedule::command(PublishCalendarEvents::class, [
    '--model' => 'Meetup',
])->everyFiveMinutes();

Schedule::command(UpdateMeetupActivity::class)->dailyAt('03:30');

/*
 * Bewusst NACH dem Aktivitaets-Lauf um 03:30: der schreibt selbst in `api_changes`
 * (siehe Meetup::recalculateActivity), und die beiden sollen nicht gleichzeitig auf
 * derselben Tabelle arbeiten. Die Frist steht in config/einundzwanzig.php und ist
 * zugleich die Reichweite, ueber die ein Konsument per /api/changes nachziehen kann.
 */
Schedule::command(PruneApiChanges::class)->dailyAt('04:00');
