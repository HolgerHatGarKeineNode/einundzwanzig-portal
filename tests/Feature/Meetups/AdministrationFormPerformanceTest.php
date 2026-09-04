<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/**
 * Issue 657eb9fb: organiser forms and the events list were "noticeably slow to
 * open". These two tests pin the two concrete causes found by reading the code —
 * not every slowdown is a query-count problem, but these two were.
 *
 * Issue #53 added the three operations #42 named but never measured: opening the
 * event creation form, opening an event for editing, and saving a single event.
 * They are measured further down, and the finding was that none of the three has
 * an N+1 — see the comment above {@see administrationFormShape()} for how the
 * "does the count move with the data?" question is asked in a way that can fail.
 */
it('loads the meetup creation form without selecting the heavy city columns', function () {
    $country = Country::factory()->create();
    // 50 cities is a realistic mid-size country's worth on this portal (production
    // carries roughly 300 across all countries) — enough to make a per-row SELECT *
    // difference show up in the query log rather than needing thousands of rows.
    City::factory()->count(50)->create([
        'country_id' => $country->id,
        // Representative of a real OSM boundary payload, not the empty default the
        // factory would otherwise leave in place — the column this fix stops
        // selecting only matters once it is actually sized.
        'simplified_geojson' => ['type' => 'Polygon', 'coordinates' => [array_fill(0, 500, [13.4, 52.5])]],
    ]);

    actingAsUser();

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    Livewire::test('meetups.create')->assertStatus(200);

    $cityQuery = collect($queries)->first(fn (string $sql): bool => str_contains($sql, 'from "cities"'));

    // "select *" carries no column names to grep for — the query has to be checked
    // for an explicit column list instead, or this assertion would pass either way.
    expect($cityQuery)->not->toBeNull()
        ->and($cityQuery)->not->toContain('select *')
        ->and($cityQuery)->toContain('"id"')
        ->and($cityQuery)->toContain('"name"');
});

it('opens the events list without re-querying leader status once per event', function () {
    $leader = actingAsUser();
    $meetup = Meetup::factory()->create(['created_by' => $leader->id]);
    MeetupEvent::factory()->count(8)->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addDay(),
    ]);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    Livewire::test('meetups.landingpage', ['meetup' => $meetup])->assertStatus(200);

    $leaderChecks = collect($queries)->filter(
        fn (string $sql): bool => str_contains($sql, 'from "meetup_user"') && str_contains($sql, '"is_leader"')
    )->count();

    // Before the fix: one per event card PLUS one for the page-level "create event"
    // button — 9 for 8 events. leadByMe is a boolean accessor, and Eloquent only
    // caches object-returning ones by default, so every read re-ran the query.
    expect($leaderChecks)->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Issue #53 — the three organiser operations #42 named but never measured
|--------------------------------------------------------------------------
|
| A bare query count answers the wrong question. A count of 40 that is one
| INSERT per picked tag is a different animal from a count of 40 spread over
| forty tables, and only the first is a defect worth a fix. So every test below
| measures the same operation TWICE — once on a small dataset, once on a much
| larger one — and asserts the two measurements are *identical in shape*. That
| is the assertion that can catch an N+1: a constant count stays constant, a
| linear one does not.
|
| The absolute ceiling is asserted as well, but it is the weaker of the two: it
| catches a query added outright, not one added per row.
|
| MEASURED 2026-09-04, on this commit, with the shapes printed by
| administrationFormShape():
|
|   creation form   4 queries — permissions, meetup->city, city->country,
|                   the tag picker's list. Constant at 1 vs 30 selectable tags
|                   and at 1 vs 50 sibling events.
|   edit form       5 queries — the four above plus the event's own tags.
|                   Constant on the same two axes and on 1 vs 30 tags already
|                   attached to the event.
|   save (single)   14 queries for a three-tag selection: 2x the meetup (Livewire
|                   rehydrating the property), city, country, the event row, the
|                   UPDATE, three MeetupEventObserver aggregates over
|                   meetup_events, the allowed-tag resolution, the current-pivot
|                   read, and one INSERT per newly attached tag. Constant against
|                   the tag catalogue and against sibling events; linear ONLY in
|                   the number of tags the organiser picked in that one form.
|
| That last linearity is spatie/laravel-tags' relation declaring ->using($pivot)
| (HasTags::tags()), which sends Laravel down attachUsingCustomClass() — a
| per-record ->save() loop instead of the single multi-row insert attach() does
| without a custom pivot class (InteractsWithPivotTable::attach, line 338). It is
| vendor behaviour bounded by a human's selection in one form, not by data
| volume, so it is pinned here rather than fixed.
*/

/**
 * The queries of one operation, folded to their shapes and sorted.
 *
 * Bindings are already placeholders in the query log; only the variable-length
 * `in (?, ?, ?)` list has to be folded, or the same statement would read as a new
 * shape for every list length and the small/large comparison would be vacuous.
 *
 * Sorted rather than kept in execution order: a reordering is not a performance
 * regression, and sorting keeps the failure diff about what actually changed.
 *
 * @param  array<int, string>  $queries
 */
function administrationFormShape(array $queries): string
{
    return collect($queries)
        ->map(fn (string $sql): string => preg_replace(
            '/in \((\?(, )?)+\)/',
            'in (?, …)',
            (string) preg_replace('/\s+/', ' ', trim($sql))
        ))
        ->countBy()
        ->sortKeys()
        ->map(fn (int $count, string $sql): string => sprintf('%3dx  %s', $count, $sql))
        ->implode("\n");
}

/**
 * Run one operation and return [shape, query count].
 *
 * The permission cache is dropped first so the second measurement in a test pays
 * the same `select * from permissions` as the first. Without it the two counts
 * differ by one for a reason that has nothing to do with the code under test —
 * and the invariance assertion would fail on its own bookkeeping.
 *
 * @param  array<int, string>  $log  the shared listener buffer, appended to in place
 * @return array{0: string, 1: int}
 */
function administrationFormMeasure(array &$log, callable $operation): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $from = count($log);
    $operation();
    $slice = array_slice($log, $from);

    return [administrationFormShape($slice), count($slice)];
}

/**
 * A listener that appends every executed statement to $log.
 *
 * One listener for the whole test, with each measurement taking a slice: a second
 * DB::listen() would not replace the first, so the earlier measurement's buffer
 * would keep growing while the later one runs and the comparison would be against
 * a moving target.
 *
 * @param  array<int, string>  $log
 */
function administrationFormListen(array &$log): void
{
    DB::listen(function ($query) use (&$log): void {
        $log[] = $query->sql;
    });
}

it('opens the event creation form with a query count that does not grow with the data', function () {
    $leader = actingAsUser();
    $meetup = Meetup::factory()->create(['created_by' => $leader->id]);
    Tag::factory()->create(['type' => 'meetup_event']);
    MeetupEvent::factory()->create(['meetup_id' => $meetup->id, 'start' => now()->addDay()]);

    $log = [];
    administrationFormListen($log);

    // A FRESH Meetup per measurement, resolved outside the measurement window — the
    // route resolves the model before the component ever runs, so its own query is
    // not part of this operation. Reusing one instance across both measurements
    // would be worse than noise: Eloquent caches ->city on the object, so the second
    // open would skip two queries the first one paid and the invariance assertion
    // would report a shrinking count as proof of nothing. (Measured: it did.)
    $measureOpen = function () use ($meetup, &$log): array {
        $fresh = Meetup::findOrFail($meetup->id);

        return administrationFormMeasure(
            $log,
            fn () => Livewire::test('meetups.create-edit-events', ['meetup' => $fresh])->assertStatus(200)
        );
    };

    [$smallShape, $smallCount] = $measureOpen();

    // 30 selectable tags and 50 events on the same meetup. Both are the axes a
    // form like this plausibly grows along: the tag catalogue is global and shared
    // by every organiser, and a weekly meetup passes 50 events inside a year.
    Tag::factory()->count(29)->create(['type' => 'meetup_event']);
    MeetupEvent::factory()->count(49)->create(['meetup_id' => $meetup->id, 'start' => now()->addDay()]);

    [$largeShape, $largeCount] = $measureOpen();

    // The load-bearing assertion. Thirty times the tags and fifty times the events
    // must not buy a single extra query — anything per-row shows up here as a
    // count next to a repeated statement.
    expect($largeShape)->toBe($smallShape);

    // Measured 4 on 2026-09-04: permissions, meetup->city, city->country, the tag
    // picker's list. 6 leaves room for two more without a test edit; a third would
    // be worth looking at rather than waving through.
    expect($largeCount)->toBe($smallCount)
        ->and($largeCount)->toBeLessThanOrEqual(6);

    // The picker still resolves its list, once. Without this the ceiling above
    // would also be satisfied by a form that stopped offering tags at all.
    expect(substr_count($largeShape, 'from "tags"'))->toBe(1)
        ->and($largeShape)->toContain('  1x  select * from "tags"');
});

it('opens an event for editing with a query count that does not grow with the data', function () {
    $leader = actingAsUser();
    $meetup = Meetup::factory()->create(['created_by' => $leader->id]);
    $tag = Tag::factory()->create(['type' => 'meetup_event']);
    // recurrence_type pinned to null on purpose: MeetupEventFactory rolls it at 40%,
    // and mount() takes a different branch when it is set. Left to chance this test
    // would measure two different code paths on 40% of runs.
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addDay(),
        'recurrence_type' => null,
    ]);
    $event->syncTagsWithType([$tag], 'meetup_event');

    $log = [];
    administrationFormListen($log);

    // Both models fresh per measurement and resolved outside the window, for the
    // reason spelled out in the creation-form test above.
    $measureOpen = function () use ($meetup, $event, &$log): array {
        $freshMeetup = Meetup::findOrFail($meetup->id);
        $freshEvent = MeetupEvent::findOrFail($event->id);

        return administrationFormMeasure(
            $log,
            fn () => Livewire::test('meetups.create-edit-events', ['meetup' => $freshMeetup, 'event' => $freshEvent])
                ->assertStatus(200)
        );
    };

    [$smallShape, $smallCount] = $measureOpen();

    // Three axes at once: the global catalogue, the meetup's other events, and the
    // tags already on THIS event — the last one is the one mount() reads
    // ($this->event->tags->pluck('id')) and therefore the likeliest place for a
    // per-tag query to hide.
    $more = Tag::factory()->count(29)->create(['type' => 'meetup_event']);
    MeetupEvent::factory()->count(49)->create(['meetup_id' => $meetup->id, 'start' => now()->addDay()]);
    $event->syncTagsWithType($more->push($tag)->all(), 'meetup_event');

    [$largeShape, $largeCount] = $measureOpen();

    expect($largeShape)->toBe($smallShape);

    // Measured 5 on 2026-09-04: the creation form's four plus the event's own tags,
    // eager-loaded in one statement. Same headroom of two as the creation form.
    expect($largeCount)->toBe($smallCount)
        ->and($largeCount)->toBeLessThanOrEqual(7);

    // Exactly two statements read the tags table: the picker's catalogue and the
    // event's own selection. A third would mean one of them started running per row.
    //
    // Counted on 'from "tags"', not on '"tags"' — the pivot join names the table
    // four times in one statement (select list, FROM, ON, ORDER BY), so the shorter
    // needle counts SQL syntax rather than statements. It read 4 and expected 2 on
    // the first run of this file.
    expect(substr_count($largeShape, 'from "tags"'))->toBe(2);
});

it('saves a single event with a query count that grows only with the tags picked, not with the data', function () {
    $leader = actingAsUser();
    $meetup = Meetup::factory()->create(['created_by' => $leader->id]);
    $picked = Tag::factory()->count(3)->create(['type' => 'meetup_event'])->pluck('id')->all();

    $log = [];
    administrationFormListen($log);

    // Issue #43 established that editing never takes the series branch, so this is
    // the single-event path throughout — seriesMode stays false and $event is set.
    // The series path is covered by CreateMeetupEventSeriesTest and is not this
    // measurement's subject.
    $saveOn = function (MeetupEvent $event) use ($meetup, $picked, &$log): array {
        $component = Livewire::test('meetups.create-edit-events', [
            'meetup' => Meetup::findOrFail($meetup->id),
            'event' => $event,
        ])
            // Guards the "single event" scope of this measurement. It is not
            // decoration: MeetupEventFactory rolls recurrence_type at 40%, and an
            // event that carries one makes mount() flip seriesMode to true. This
            // assertion is what turned that into a visible failure instead of a
            // test that silently measured the wrong branch two runs in five.
            ->assertSet('seriesMode', false)
            ->set('description', 'Updated description')
            ->set('location', 'Marktplatz')
            ->set('tagIds', $picked);

        // Mounting and the property writes are deliberately outside the window:
        // the operation being measured is the save, not the form open, which has
        // its own test above.
        return administrationFormMeasure($log, function () use ($component): void {
            $component->call('save')->assertHasNoErrors();
        });
    };

    /*
     * startOfMinute() is load-bearing, not tidiness. mount() reads the stored start
     * back as 'Y-m-d H:i' and save() rebuilds it from those two fields, so the
     * seconds are dropped and written back as :00. With a start that carries seconds,
     * `start` is therefore dirty and Eloquent puts it in the UPDATE's column list;
     * with a start already on :00 it is clean and the column is absent. now()->addDay()
     * lands on :00 about one time in sixty, so the two measurements below disagreed on
     * the shape of ONE statement at roughly that rate — seen once in ~55 runs of this
     * file and then reproduced deliberately by giving the second event 37 seconds:
     *
     *   -  1x  update "meetup_events" set "location" = ?, "description" = ?, ...
     *   +  1x  update "meetup_events" set "start" = ?, "location" = ?, "description" = ?, ...
     *
     * The query COUNT is 14 either way — only the column list moved. Pinned to :00
     * because that is what this form itself writes: every event created here is built
     * from a date and an H:i time and never carries seconds.
     */
    $plainEvent = fn (): MeetupEvent => MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addDay()->startOfMinute(),
        'recurrence_type' => null,
    ]);

    [$smallShape, $smallCount] = $saveOn($plainEvent());

    // Same three tags picked, but a catalogue ten times the size and fifty sibling
    // events. Neither is the organiser's doing, so neither may cost anything.
    Tag::factory()->count(27)->create(['type' => 'meetup_event']);
    MeetupEvent::factory()->count(49)->create(['meetup_id' => $meetup->id, 'start' => now()->addDay()]);

    [$largeShape, $largeCount] = $saveOn($plainEvent());

    expect($largeShape)->toBe($smallShape);

    // Measured 14 on 2026-09-04 for a three-tag selection. 16 of headroom, i.e. two
    // more statements — deliberately NOT enough to absorb a fourth picked tag going
    // unnoticed, which would be the interesting regression.
    expect($largeCount)->toBe($smallCount)
        ->and($largeCount)->toBeLessThanOrEqual(16);

    // The one part that is linear, pinned as exactly linear: one pivot INSERT per
    // newly attached tag, three for three. Not a defect and not fixable in app code
    // — spatie's tags() relation declares ->using($pivotClass), which routes
    // Laravel into attachUsingCustomClass()'s per-record save() loop instead of
    // attach()'s single multi-row insert. Pinned so that a regression to one
    // insert per *catalogue* tag, which would be a defect, cannot pass.
    expect(substr_count($largeShape, 'insert into "taggables"'))->toBe(1)
        ->and($largeShape)->toContain('  3x  insert into "taggables"');

    // The allowed-tag resolution stays one statement for the whole save. It is the
    // fix #42 landed for the series loop; the single-event path must not drift back.
    expect(substr_count($largeShape, 'from "tags"'))->toBe(1);
});
