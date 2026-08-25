<?php

use App\Enums\RecurrenceType;
use App\Models\ApiChange;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('creates a weekly series of individual events', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    $response = $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-07-01 18:00:00',
        'location' => 'Marktplatz',
        'description' => 'Wöchentlicher Stammtisch',
        'link' => 'https://einundzwanzig.space',
        'recurrence_type' => 'weekly',
        'recurrence_end_date' => '2026-07-29 18:00:00',
    ]);

    // 2026-07-01, 07-08, 07-15, 07-22, 07-29 = 5 occurrences
    $response->assertCreated()->assertJsonCount(5, 'data');

    expect(MeetupEvent::where('meetup_id', $meetup->id)->count())->toBe(5);

    // Bis P5 stand hier `'recurrence_type' => null` — die Vorkommen trugen die Regel
    // nicht, die sie erzeugt hat, obwohl MeetupEventResource sie pro Termin verspricht.
    $this->assertDatabaseHas('meetup_events', [
        'meetup_id' => $meetup->id,
        'created_by' => $user->id,
        'recurrence_type' => 'weekly',
    ]);
});

it('gives every occurrence of a series the same identity and the full rule', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    $response = $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-07-03 18:00:00',
        'end' => '2026-07-03 21:30:00',
        'title' => 'Stammtisch',
        'location' => 'Marktplatz',
        'description' => 'Wöchentlicher Stammtisch',
        'link' => 'https://einundzwanzig.space',
        'osm_type' => 'node',
        'osm_id' => 240109189,
        'osm_name' => 'Bürgerhaus',
        'recurrence_type' => 'weekly',
        'recurrence_day_of_week' => 'friday',
        'recurrence_interval' => 1,
        'recurrence_end_date' => '2026-07-31 18:00:00',
    ]);

    $response->assertCreated();

    $events = MeetupEvent::where('meetup_id', $meetup->id)->orderBy('start')->get();

    expect($events)->toHaveCount(5)
        ->and($events->pluck('recurrence_group')->unique())->toHaveCount(1)
        ->and($events->first()->recurrence_group)->not->toBeNull();

    foreach ($events as $event) {
        // Seit P6 ein echter Enum-Cast; die API liefert unveraendert den String "weekly",
        // weil ein Backed Enum in JSON zu seinem Wert wird (unten geprueft).
        expect($event->recurrence_type)->toBe(RecurrenceType::Weekly)
            ->and($event->recurrence_day_of_week)->toBe('friday')
            ->and($event->recurrence_day_position)->toBeNull()
            ->and($event->recurrence_interval)->toBe(1)
            ->and($event->recurrence_end_date)->not->toBeNull()
            // Der Livewire-Pfad setzt diese fünf seit jeher, die Action bis P5 nicht.
            ->and($event->title)->toBe('Stammtisch')
            ->and($event->created_by)->toBe($user->id)
            ->and($event->osm_type)->toBe('node')
            ->and($event->osm_id)->toBe(240109189)
            ->and($event->osm_name)->toBe('Bürgerhaus')
            // `end` ist das Ende DIESES Vorkommens: gleiche Dauer, eigener Tag.
            ->and($event->start->diffInMinutes($event->end))->toBe(210.0);
    }

    // recurrence_day_position bleibt null, deshalb ist die Regel rein wöchentlich.
    expect($events->last()->start->format('Y-m-d'))->toBe('2026-07-31');
});

it('reports the recurrence fields through the resource instead of null', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    $response = $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-07-01 18:00:00',
        'recurrence_type' => 'monthly',
        'recurrence_interval' => 2,
        'recurrence_end_date' => '2026-11-01 18:00:00',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.0.recurrence_type', 'monthly')
        ->assertJsonPath('data.0.recurrence_interval', 2)
        ->assertJsonPath('data.1.recurrence_type', 'monthly');

    expect($response->json('data.0.recurrence_end_date'))->not->toBeNull();
});

it('expands a daily series into daily dates, not monthly ones', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    $response = $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-07-01 18:00:00',
        'recurrence_type' => 'daily',
        'recurrence_end_date' => '2026-07-05 18:00:00',
    ]);

    // 07-01 bis 07-05 = 5 Tage. Vor P5 fiel `daily` auf addMonth() zurück und ergab 1.
    $response->assertCreated()->assertJsonCount(5, 'data');

    expect(MeetupEvent::where('meetup_id', $meetup->id)->orderBy('start')->pluck('start')
        ->map(fn ($start) => $start->format('Y-m-d'))->all())
        ->toBe(['2026-07-01', '2026-07-02', '2026-07-03', '2026-07-04', '2026-07-05']);
});

it('honours an interval greater than one', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    $response = $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-07-01 18:00:00',
        'recurrence_type' => 'weekly',
        'recurrence_interval' => 2,
        'recurrence_end_date' => '2026-07-29 18:00:00',
    ]);

    // Vierzehntägig: 07-01, 07-15, 07-29 = 3 statt 5.
    $response->assertCreated()->assertJsonCount(3, 'data');
});

it('rejects an interval below one instead of looping on the same date', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-07-01 18:00:00',
        'recurrence_type' => 'weekly',
        'recurrence_interval' => 0,
        'recurrence_end_date' => '2026-07-29 18:00:00',
    ])->assertJsonValidationErrors(['recurrence_interval']);

    expect(MeetupEvent::where('meetup_id', $meetup->id)->count())->toBe(0);
});

it('records one meetup change for a whole series, not one per occurrence', function () {
    config()->set('einundzwanzig.change_log.enabled', true);

    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create(['created_by' => $user->id, 'is_active' => false, 'last_event_at' => null]);

    // Vergangene Termine: `last_event_at` wächst mit JEDEM eingefügten Vorkommen, also
    // meldete recalculateActivity() vor P5 pro Termin eine Änderung am Meetup.
    $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => now()->subMonths(4)->format('Y-m-d H:i:s'),
        'recurrence_type' => 'weekly',
        'recurrence_end_date' => now()->subMonths(3)->format('Y-m-d H:i:s'),
    ])->assertCreated();

    $occurrences = MeetupEvent::where('meetup_id', $meetup->id)->count();

    expect($occurrences)->toBeGreaterThan(1)
        ->and(ApiChange::where('resource', 'meetup')->where('resource_id', $meetup->id)->where('action', 'updated')->count())->toBe(1)
        ->and(ApiChange::where('resource', 'meetup-event')->count())->toBe($occurrences);

    expect($meetup->refresh()->is_active)->toBeTrue();
});

it('creates a monthly series of individual events', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    $response = $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-07-01 18:00:00',
        'link' => 'https://einundzwanzig.space',
        'recurrence_type' => 'monthly',
        'recurrence_end_date' => '2026-10-01 18:00:00',
    ]);

    // 2026-07-01, 08-01, 09-01, 10-01 = 4 occurrences
    $response->assertCreated()->assertJsonCount(4, 'data');
});

it('caps the series at 100 occurrences', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    $response = $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-01-01 18:00:00',
        'link' => 'https://einundzwanzig.space',
        'recurrence_type' => 'weekly',
        'recurrence_end_date' => '2030-01-01 18:00:00',
    ]);

    $response->assertCreated()->assertJsonCount(100, 'data');
});

it('still creates a single event without recurrence fields', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    $response = $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-08-01 18:00:00',
        'location' => 'Marktplatz',
    ]);

    $response->assertCreated()->assertJsonPath('data.location', 'Marktplatz');

    expect(MeetupEvent::where('meetup_id', $meetup->id)->count())->toBe(1);
});

it('creates a single event when recurrence_type is set but no end date', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    $response = $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-08-01 18:00:00',
        'recurrence_type' => 'weekly',
    ]);

    $response->assertCreated()->assertJsonPath('data.recurrence_type', 'weekly');

    expect(MeetupEvent::where('meetup_id', $meetup->id)->count())->toBe(1);
});
