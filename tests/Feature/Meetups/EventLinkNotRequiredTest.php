<?php

use App\Actions\MeetupEvents\CreateMeetupEventSeries;
use App\Enums\RecurrenceType;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

it('creates an event without a link via the web editor', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->set('startDate', now()->addWeek()->format('Y-m-d'))
        ->set('startTime', '19:00')
        ->set('location', 'Marktplatz')
        ->set('description', 'Ein Test-Event')
        ->set('link', '')
        ->call('save')
        ->assertHasNoErrors();

    $event = MeetupEvent::query()->latest('id')->first();

    expect($event->link)->toBeNull();
});

it('clears an existing link when editing an event via the web editor', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);
    $event = MeetupEvent::factory()->for($meetup)->create([
        'link' => 'https://example.com',
        'recurrence_type' => null,
    ]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event])
        ->assertSet('link', 'https://example.com')
        ->set('link', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($event->refresh()->link)->toBeNull();
});

it('still rejects a malformed link in the web editor', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->set('startDate', now()->addWeek()->format('Y-m-d'))
        ->set('startTime', '19:00')
        ->set('location', 'Marktplatz')
        ->set('description', 'Ein Test-Event')
        ->set('link', 'not-a-url')
        ->call('save')
        ->assertHasErrors(['link' => 'url']);
});

it('creates a recurring series without a link', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup])
        ->set('seriesMode', true)
        ->set('startDate', '2026-07-01')
        ->set('startTime', '18:00')
        ->set('endDate', '2026-07-15')
        ->set('recurrenceType', RecurrenceType::Weekly->value)
        ->set('location', 'Marktplatz')
        ->set('description', 'Wöchentlicher Stammtisch')
        ->set('link', '')
        ->call('save')
        ->assertHasNoErrors();

    $events = MeetupEvent::where('meetup_id', $meetup->id)->get();

    expect($events)->toHaveCount(3)
        ->and($events->pluck('link')->unique()->all())->toBe([null]);
});

it('accepts an event created via the API without a link', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    $response = $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-08-01 18:00:00',
        'location' => 'Marktplatz',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.link', null);

    $this->assertDatabaseHas('meetup_events', [
        'location' => 'Marktplatz',
        'link' => null,
    ]);
});

it('validates the link format via the API only when a value is given', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    $response = $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-08-01 18:00:00',
        'location' => 'Marktplatz',
        'link' => 'not-a-url',
    ]);

    $response->assertJsonValidationErrors(['link']);
});

it('omits the URL property from the ICS feed for an event without a link', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);
    MeetupEvent::factory()->for($meetup)->create([
        'link' => null,
        'start' => now()->addWeek(),
        'recurrence_type' => null,
    ]);

    $response = $this->get(route('ics'));

    $response->assertSuccessful();
    expect($response->getContent())->not->toContain('URL:');
});

it('carries a missing link through the recurring-series action untouched', function () {
    $meetup = Meetup::factory()->create(['created_by' => actingAsUser()->id]);

    $events = app(CreateMeetupEventSeries::class)->handle([
        'meetup_id' => $meetup->id,
        'start' => '2026-08-01 18:00:00',
        'location' => 'Marktplatz',
        'recurrence_type' => RecurrenceType::Weekly->value,
        'recurrence_end_date' => '2026-08-15 00:00:00',
    ]);

    expect($events)->not->toBeEmpty();

    foreach ($events as $event) {
        expect($event->link)->toBeNull();
    }
});
