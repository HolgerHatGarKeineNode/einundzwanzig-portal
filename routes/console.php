<?php

use App\Console\Commands\Database\CleanupLoginKeys;
use App\Console\Commands\Database\PruneApiChanges;
use App\Console\Commands\Database\UpdateMeetupActivity;
use App\Console\Commands\Nostr\PublishCalendarEvents;
use App\Console\Commands\Nostr\PublishUnpublishedItems;
use App\Console\Commands\Nostr\RepublishCalendarEvents;

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
|   Meetup, worst case 307 records (a ONE-TIME drain for THIS queue: the query
|   is gated on `nostr_coordinate IS NULL`, so a calendar leaves it for good
|   once published)
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
| Since #104 the MeetupEvent entry also re-sends its meetup's calendar after a
| successful publish (PublishCalendarEvents::refreshCalendarFor), because the
| calendar's `a` tags are built from the events published so far and a kind
| 31924 that never goes out again would stay empty for good. So a run of the
| MeetupEvent entry costs up to TWO relay round trips, not one — the arithmetic
| above is unaffected, since it counts records drained per run and the refresh
| drains none. Re-sending is safe at any rate: both kinds are parameterized-
| replaceable, so the relay replaces in place under the same `d` tag.
|
| Idle cost is one indexed query per run: with nothing to publish the command
| prints "No unpublished items" and exits 0 without opening a socket.
|
| `nostr:republish-calendar` WITHOUT `--changed` is deliberately NOT scheduled.
| It re-sends the whole back catalogue, which is a burst against every relay in
| the list and a decision for an operator, not for cron; its own docblock
| carries the reasoning and its default is a dry run. The `--changed` half of
| it IS scheduled, below, and the difference between the two is the whole of
| issue #92 — see there.
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

/*
|--------------------------------------------------------------------------
| NIP-52 payload repair (issue #92)
|--------------------------------------------------------------------------
|
| The trigger #92 was missing. The two entries above are one-way doors: they
| gate on `nostr_coordinate IS NULL`, so a record they have published keeps the
| payload it went out with for good, and every later change to what the portal
| publishes — the geography `t` tags of #69, the `start_tzid` repair of #104 —
| reached only the records published after it. This entry closes that door from
| the other side.
|
| WHY `--changed` AND NOT A NIGHTLY BLANKET REPUBLISH. A timer that re-sends
| everything ships ~400 signed events to every relay for nothing, and a bad
| payload would be re-broadcast on that timer for ever instead of once.
| `--changed` compares a fingerprint of the payload the current code builds
| against `nostr_payload_hash`, the fingerprint of what was last successfully
| sent (App\Support\NostrPayloadFingerprint). The fingerprint is written on
| success, so the mechanism is self-terminating: one re-send per real change,
| then silence. It is NOT keyed on `updated_at` — the #104 case changed the
| payload of every published event with no database write at all, and an
| `updated_at` check would have missed exactly the records the issue is about.
|
| WORST CASE ON THE WIRE, per relay:
|
|   peak       1 event / 2 s = 0.5 events/s, for 18 s (10 records, `--sleep=2`
|              pauses between them, 9 pauses)
|   sustained  10 events/h = 0.0028 events/s
|   idle       0 events. With nothing stale the run opens no socket.
|
| That peak is the same as a manual `nostr:republish-calendar --force`; what
| the batch cap buys is the sustained figure, which is ~1/40 of the manual
| command's 0.5 events/s held for 13 minutes.
|
| WHY hourly WITH `--limit=10` AND NOT everyFiveMinutes WITH ONE RECORD.
|
| Unlike the publisher above, this command cannot pick its work with an indexed
| WHERE: "has the payload changed" is only answerable by BUILDING the payload,
| so every run costs one event build per already-published record whether or
| not anything is stale. Measured 2026-09-05 on a synthetic catalogue of the
| size production would reach if every meetup opted in — 307 meetups plus 76
| upcoming events, the public-API figures of 2026-09-04:
|
|   383 published records, nothing stale
|   -> 72 ms and 322 queries per idle scan (three runs: 75/71/72 ms, 322 each)
|
| 307 of those queries are one per meetup, because a calendar's `a` tags are a
| query by construction (NostrCalendarEventFactory::publishedEventCoordinates).
| SO THE SCAN IS NOT A BOTTLENECK AT EITHER CADENCE, and pretending otherwise
| would be dressing a preference up as a constraint: 24 runs a day is 7.7k
| queries, 288 runs a day is 93k. What decides it is that the twelvefold cost
| buys nothing — the records already sit on the relays with a readable, merely
| older payload, so nobody is waiting the way the reporter of #49 was waiting
| for a first publish.
|
| The batch cap is what buys the drain rate back, and that is the figure that
| actually matters: a code change that moves every payload at once clears the
| 383-record catalogue in 39 runs — under two days — against 383 runs, 16 days,
| at one record per run. A single stale record, the steady-state case when an
| organiser edits a description, goes out within the hour. An operator who
| wants a repair faster than that runs the command by hand, which is exactly
| what the unflagged form is for.
|
| WHY `--force` IS ON THE SCHEDULER AND NOT THE DEFAULT. The command's default
| is a dry run because forgetting a flag must cost a printed plan rather than
| hundreds of unrecallable writes to public relays. That protects a human at a
| shell; a scheduler entry is written once and read by everyone, so the flag is
| the sentence that says out loud that this line transmits.
|
| `nostr_publishing_enabled` is honoured here as everywhere: a re-send is a new
| signed event, i.e. a publishing act, so a meetup that has opted out is not in
| the candidate set at all.
*/
Schedule::command(RepublishCalendarEvents::class, [
    '--changed',
    '--force',
    '--limit' => 10,
])->hourly();

Schedule::command(UpdateMeetupActivity::class)->dailyAt('03:30');

/*
 * Bewusst NACH dem Aktivitaets-Lauf um 03:30: der schreibt selbst in `api_changes`
 * (siehe Meetup::recalculateActivity), und die beiden sollen nicht gleichzeitig auf
 * derselben Tabelle arbeiten. Die Frist steht in config/einundzwanzig.php und ist
 * zugleich die Reichweite, ueber die ein Konsument per /api/changes nachziehen kann.
 */
Schedule::command(PruneApiChanges::class)->dailyAt('04:00');
