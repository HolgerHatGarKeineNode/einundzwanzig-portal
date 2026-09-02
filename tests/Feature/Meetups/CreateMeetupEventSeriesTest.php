<?php

use App\Enums\RecurrenceType;
use App\Models\ApiChange;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('creates a weekly series via the web editor using the shared action', function () {
    // Termin-Verwaltung erfordert Leaderschaft; Ersteller ist per Hook Leader.
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->set('seriesMode', true)
        ->set('startDate', '2026-07-01')
        ->set('startTime', '18:00')
        ->set('endDate', '2026-07-29')
        ->set('recurrenceType', RecurrenceType::Weekly->value)
        ->set('location', 'Marktplatz')
        ->set('description', 'Wöchentlicher Stammtisch')
        ->set('link', 'https://einundzwanzig.space')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    // Das Enddatum aus dem Datums-Picker gilt inklusiv bis zum Tagesende
    // (endOfDay), daher ist das Vorkommen am Enddatum-Abend dabei:
    // 2026-07-01, 07-08, 07-15, 07-22, 07-29 = 5. Deterministisch, unabhängig
    // von der Laufzeit-Uhrzeit.
    expect(MeetupEvent::where('meetup_id', $meetup->id)->count())->toBe(5);
});

it('previews the same dates it will create', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->set('seriesMode', true)
        ->set('startDate', '2026-07-01')
        ->set('startTime', '18:00')
        ->set('endDate', '2026-07-29')
        ->set('recurrenceType', RecurrenceType::Weekly->value)
        ->assertSet('previewDates', fn ($dates) => count($dates) === 5);
});

it('gives every occurrence created in the editor the same series identity', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->set('seriesMode', true)
        ->set('startDate', '2026-07-01')
        ->set('startTime', '18:00')
        ->set('endDate', '2026-07-29')
        ->set('recurrenceType', RecurrenceType::Weekly->value)
        ->set('recurrenceDayOfWeek', 'wednesday')
        ->set('location', 'Marktplatz')
        ->set('description', 'Wöchentlicher Stammtisch')
        ->set('link', 'https://einundzwanzig.space')
        ->call('save')
        ->assertHasNoErrors();

    $events = MeetupEvent::where('meetup_id', $meetup->id)->orderBy('start')->get();

    expect($events)->toHaveCount(5)
        ->and($events->pluck('recurrence_group')->unique())->toHaveCount(1)
        ->and($events->first()->recurrence_group)->not->toBeNull();

    foreach ($events as $event) {
        // Seit P6 traegt `recurrence_type` einen echten Enum-Cast (die alte Eigenschaft
        // `$enumCasts` gab es in Eloquent nicht und sie lief ins Leere).
        expect($event->recurrence_type)->toBe(RecurrenceType::Weekly)
            ->and($event->recurrence_day_of_week)->toBe('wednesday')
            ->and($event->recurrence_interval)->toBe(1)
            ->and($event->recurrence_end_date)->not->toBeNull();
    }
});

it('records one meetup change for an editor series, not one per occurrence', function () {
    config()->set('einundzwanzig.change_log.enabled', true);

    $meetup = Meetup::factory()->create([
        'created_by' => actingAsUser()->id,
        'is_active' => false,
        'last_event_at' => null,
    ]);

    $before = ApiChange::where('resource', 'meetup')->where('resource_id', $meetup->id)->count();

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->set('seriesMode', true)
        ->set('startDate', now()->subMonths(4)->format('Y-m-d'))
        ->set('startTime', '18:00')
        ->set('endDate', now()->subMonths(3)->format('Y-m-d'))
        ->set('recurrenceType', RecurrenceType::Weekly->value)
        ->set('location', 'Marktplatz')
        ->set('description', 'Wöchentlicher Stammtisch')
        ->set('link', 'https://einundzwanzig.space')
        ->call('save')
        ->assertHasNoErrors();

    $occurrences = MeetupEvent::where('meetup_id', $meetup->id)->count();
    $after = ApiChange::where('resource', 'meetup')->where('resource_id', $meetup->id)->count();

    expect($occurrences)->toBeGreaterThan(1)
        ->and($after - $before)->toBe(1);
});

it('resolves the selectable tag list once for an event series, not once per occurrence', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);
    $tag = Tag::factory()->ofType('meetup_event')->create();

    DB::enableQueryLog();

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->set('seriesMode', true)
        ->set('startDate', '2026-07-01')
        ->set('startTime', '18:00')
        ->set('endDate', '2026-07-29')
        ->set('recurrenceType', RecurrenceType::Weekly->value)
        ->set('location', 'Marktplatz')
        ->set('description', 'Wöchentlicher Stammtisch')
        ->set('link', 'https://einundzwanzig.space')
        ->set('tagIds', [$tag->id])
        ->call('save')
        ->assertHasNoErrors();

    // Die 5 Termine (siehe Test oben) muessen alle den Tag tragen — sonst waere die
    // Anfrage bloss weggefallen statt aus der Schleife gehoben.
    $eventsWithTag = MeetupEvent::where('meetup_id', $meetup->id)
        ->whereHas('tags', fn ($query) => $query->whereKey($tag->id))
        ->count();

    // "id" in (...) unterscheidet allowedTags() vom Tag-Picker-Subkomponenten-Query
    // (das selbe "tags"/"type"-Muster, aber ohne die whereIn()-Einschraenkung).
    $tagSelects = collect(DB::getQueryLog())
        ->filter(fn (array $entry): bool => str_contains($entry['query'], 'from "tags"') && str_contains($entry['query'], '"id" in'))
        ->count();

    DB::disableQueryLog();

    expect($eventsWithTag)->toBe(5)
        // Vorher: einmal PRO Termin (5x identisch). Die Auswahl darf sich waehrend
        // einer Serie nicht aendern, also genuegt eine einzige Anfrage.
        ->and($tagSelects)->toBe(1);
});
