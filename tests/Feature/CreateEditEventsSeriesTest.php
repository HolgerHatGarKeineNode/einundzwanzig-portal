<?php

use App\Enums\RecurrenceType;
use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseCount;

beforeEach(function () {
    $this->user = User::factory()->create(['timezone' => 'Europe/Berlin']);
    $this->country = Country::factory()->create();
    $this->city = City::factory()->for($this->country)->create();
    $this->meetup = Meetup::factory()->for($this->city)->create();
});

it('creates a weekly recurring series of events', function () {
    Livewire::actingAs($this->user)
        ->test('meetups.create-edit-events', ['meetup' => $this->meetup])
        ->set('seriesMode', true)
        ->set('startDate', '2026-01-19')
        ->set('startTime', '19:00')
        ->set('endDate', '2026-02-14')
        ->set('recurrenceType', RecurrenceType::Weekly)
        ->set('location', 'Test Location')
        ->set('description', 'Test Description')
        ->set('link', 'https://example.com')
        ->call('save')
        ->assertHasNoErrors();

    assertDatabaseCount('meetup_events', 4);
});

it('creates a monthly recurring series of events', function () {
    Livewire::actingAs($this->user)
        ->test('meetups.create-edit-events', ['meetup' => $this->meetup])
        ->set('seriesMode', true)
        ->set('startDate', '2026-01-19')
        ->set('startTime', '19:00')
        ->set('endDate', '2026-03-31')
        ->set('recurrenceType', RecurrenceType::Monthly)
        ->set('location', 'Test Location')
        ->set('description', 'Test Description')
        ->set('link', 'https://example.com')
        ->call('save')
        ->assertHasNoErrors();

    assertDatabaseCount('meetup_events', 3);
});

it('creates a series for last Friday of each month', function () {
    Livewire::actingAs($this->user)
        ->test('meetups.create-edit-events', ['meetup' => $this->meetup])
        ->set('seriesMode', true)
        ->set('startDate', '2026-01-01')
        ->set('startTime', '19:00')
        ->set('endDate', '2026-03-31')
        ->set('recurrenceType', RecurrenceType::Monthly)
        ->set('recurrenceDayOfWeek', 'friday')
        ->set('recurrenceDayPosition', 'last')
        ->set('location', 'Test Location')
        ->set('description', 'Test Description')
        ->set('link', 'https://example.com')
        ->call('save')
        ->assertHasNoErrors();

    assertDatabaseCount('meetup_events', 3);

    $events = $this->meetup->meetupEvents()->get();

    expect($events[0]->start->format('Y-m-d'))->toBe('2026-01-30')
        ->and($events[1]->start->format('Y-m-d'))->toBe('2026-02-27')
        ->and($events[2]->start->format('Y-m-d'))->toBe('2026-03-27');
});

it('creates a series for first Monday of each month', function () {
    Livewire::actingAs($this->user)
        ->test('meetups.create-edit-events', ['meetup' => $this->meetup])
        ->set('seriesMode', true)
        ->set('startDate', '2026-01-01')
        ->set('startTime', '19:00')
        ->set('endDate', '2026-03-31')
        ->set('recurrenceType', RecurrenceType::Monthly)
        ->set('recurrenceDayOfWeek', 'monday')
        ->set('recurrenceDayPosition', 'first')
        ->set('location', 'Test Location')
        ->set('description', 'Test Description')
        ->set('link', 'https://example.com')
        ->call('save')
        ->assertHasNoErrors();

    assertDatabaseCount('meetup_events', 3);

    $events = $this->meetup->meetupEvents()->get();

    expect($events[0]->start->format('Y-m-d'))->toBe('2026-01-05')
        ->and($events[1]->start->format('Y-m-d'))->toBe('2026-02-02')
        ->and($events[2]->start->format('Y-m-d'))->toBe('2026-03-02');
});

it('creates first Friday series when start date is Saturday', function () {
    Livewire::actingAs($this->user)
        ->test('meetups.create-edit-events', ['meetup' => $this->meetup])
        ->set('seriesMode', true)
        ->set('startDate', '2026-01-17') // Saturday
        ->set('startTime', '19:00')
        ->set('endDate', '2026-04-30')
        ->set('recurrenceType', RecurrenceType::Monthly)
        ->set('recurrenceDayOfWeek', 'friday')
        ->set('recurrenceDayPosition', 'first')
        ->set('location', 'Test Location')
        ->set('description', 'Test Description')
        ->set('link', 'https://example.com')
        ->call('save')
        ->assertHasNoErrors();

    assertDatabaseCount('meetup_events', 3);

    $events = $this->meetup->meetupEvents()->get();

    expect($events[0]->start->format('Y-m-d'))->toBe('2026-02-06')
        ->and($events[1]->start->format('Y-m-d'))->toBe('2026-03-06')
        ->and($events[2]->start->format('Y-m-d'))->toBe('2026-04-03');
});

it('updates preview when recurrenceDayOfWeek is changed for weekly recurrence', function () {
    $component = Livewire::actingAs($this->user)
        ->test('meetups.create-edit-events', ['meetup' => $this->meetup])
        ->set('seriesMode', true)
        ->set('startDate', '2026-01-19') // Monday
        ->set('startTime', '19:00')
        ->set('endDate', '2026-02-28')
        ->set('recurrenceType', RecurrenceType::Weekly)
        ->set('recurrenceDayOfWeek', 'tuesday') // Change to Tuesday
        ->set('location', 'Test Location')
        ->set('description', 'Test Description')
        ->set('link', 'https://example.com');

    $preview = $component->get('previewDates');

    expect($preview)->toHaveCount(6)
        ->and($preview[0]['formatted'])->toBe('Dienstag, 20.01.2026')
        ->and($preview[1]['formatted'])->toBe('Dienstag, 27.01.2026')
        ->and($preview[2]['formatted'])->toBe('Dienstag, 03.02.2026')
        ->and($preview[3]['formatted'])->toBe('Dienstag, 10.02.2026')
        ->and($preview[4]['formatted'])->toBe('Dienstag, 17.02.2026')
        ->and($preview[5]['formatted'])->toBe('Dienstag, 24.02.2026');
});

it('validates required fields when creating a series', function () {
    Livewire::actingAs($this->user)
        ->test('meetups.create-edit-events', ['meetup' => $this->meetup])
        ->set('seriesMode', true)
        ->set('startDate', '')
        ->set('startTime', '')
        ->set('endDate', '')
        ->set('recurrenceType', null)
        ->set('location', '')
        ->set('description', '')
        ->set('link', '')
        ->call('save')
        ->assertHasErrors([
            'startDate',
            'startTime',
            'endDate',
            'recurrenceType',
            'location',
            'description',
            'link',
        ]);
});

it('shows correct preview for first Friday when start date is Saturday', function () {
    $component = Livewire::actingAs($this->user)
        ->test('meetups.create-edit-events', ['meetup' => $this->meetup])
        ->set('seriesMode', true)
        ->set('startDate', '2026-01-17') // Saturday
        ->set('startTime', '19:00')
        ->set('endDate', '2026-04-30')
        ->set('recurrenceType', RecurrenceType::Monthly)
        ->set('recurrenceDayOfWeek', 'friday')
        ->set('recurrenceDayPosition', 'first')
        ->set('location', 'Test Location')
        ->set('description', 'Test Description')
        ->set('link', 'https://example.com');

    $preview = $component->get('previewDates');

    expect($preview)->toHaveCount(3)
        ->and($preview[0]['formatted'])->toBe('Freitag, 06.02.2026')
        ->and($preview[1]['formatted'])->toBe('Freitag, 06.03.2026')
        ->and($preview[2]['formatted'])->toBe('Freitag, 03.04.2026');
});
