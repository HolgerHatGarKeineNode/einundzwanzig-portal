<?php

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

    $this->assertDatabaseHas('meetup_events', [
        'meetup_id' => $meetup->id,
        'created_by' => $user->id,
        'recurrence_type' => null,
    ]);
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
