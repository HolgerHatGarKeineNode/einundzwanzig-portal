<?php

use App\Enums\RecurrenceType;
use App\Models\Meetup;
use App\Models\MeetupEvent;

it('marks a meetup as active when it has a past event within the last year', function () {
    $meetup = Meetup::factory()->create(['is_active' => false, 'last_event_at' => null]);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->subMonths(3),
        'recurrence_type' => null,
    ]);

    $this->artisan('meetups:update-activity')->assertSuccessful();

    $meetup->refresh();
    expect($meetup->is_active)->toBeTrue()
        ->and($meetup->last_event_at)->not->toBeNull();
});

it('marks a meetup as inactive when the last event is older than a year and no future event exists', function () {
    $meetup = Meetup::factory()->create(['is_active' => true, 'last_event_at' => now()]);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->subYears(2),
        'recurrence_type' => null,
    ]);

    $this->artisan('meetups:update-activity')->assertSuccessful();

    $meetup->refresh();
    expect($meetup->is_active)->toBeFalse()
        ->and($meetup->last_event_at)->not->toBeNull();
});

it('keeps a meetup active when it only has a future event', function () {
    $meetup = Meetup::factory()->create(['is_active' => false, 'last_event_at' => null]);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addDays(14),
        'recurrence_type' => null,
    ]);

    $this->artisan('meetups:update-activity')->assertSuccessful();

    $meetup->refresh();
    expect($meetup->is_active)->toBeTrue()
        ->and($meetup->last_event_at)->toBeNull();
});

it('keeps a meetup active when a recurring event has no end date', function () {
    $meetup = Meetup::factory()->create(['is_active' => false, 'last_event_at' => null]);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->subYears(3),
        'recurrence_type' => RecurrenceType::Monthly,
        'recurrence_end_date' => null,
    ]);

    $this->artisan('meetups:update-activity')->assertSuccessful();

    $meetup->refresh();
    expect($meetup->is_active)->toBeTrue();
});

it('marks a meetup as inactive when no events exist at all', function () {
    $meetup = Meetup::factory()->create(['is_active' => true, 'last_event_at' => now()]);

    $this->artisan('meetups:update-activity')->assertSuccessful();

    $meetup->refresh();
    expect($meetup->is_active)->toBeFalse()
        ->and($meetup->last_event_at)->toBeNull();
});

it('marks a meetup as inactive when a recurring event has ended more than a year ago', function () {
    $meetup = Meetup::factory()->create(['is_active' => true, 'last_event_at' => now()]);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->subYears(3),
        'recurrence_type' => RecurrenceType::Monthly,
        'recurrence_end_date' => now()->subYears(2),
    ]);

    $this->artisan('meetups:update-activity')->assertSuccessful();

    $meetup->refresh();
    expect($meetup->is_active)->toBeFalse();
});
