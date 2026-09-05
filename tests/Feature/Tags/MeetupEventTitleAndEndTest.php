<?php

use App\Enums\RecurrenceType;
use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use Livewire\Livewire;

function meetupFor(User $owner): Meetup
{
    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create(['country_id' => $country->id]);

    return Meetup::factory()->create(['city_id' => $city->id, 'created_by' => $owner->id]);
}

function fillEvent($test): object
{
    return $test
        ->set('startDate', now()->addWeek()->format('Y-m-d'))
        ->set('startTime', '19:00')
        ->set('location', 'Café Test')
        ->set('description', 'Ein Test-Event')
        ->set('links', [['url' => 'https://example.com', 'label' => null]]);
}

it('saves an optional title', function () {
    $meetup = meetupFor(actingAsUser());

    fillEvent(Livewire::test('meetups.create-edit-events', ['meetup' => $meetup]))
        ->set('title', 'Einsteigerabend: Wallets einrichten')
        ->call('save')
        ->assertHasNoErrors();

    expect(MeetupEvent::query()->latest('id')->first()->title)
        ->toBe('Einsteigerabend: Wallets einrichten');
});

it('accepts an event without title or end, as before', function () {
    $meetup = meetupFor(actingAsUser());

    fillEvent(Livewire::test('meetups.create-edit-events', ['meetup' => $meetup]))
        ->call('save')
        ->assertHasNoErrors();

    $event = MeetupEvent::query()->latest('id')->first();

    expect($event->title)->toBeNull()->and($event->end)->toBeNull();
});

it('stores the end on the same day when it is later than the start', function () {
    $user = actingAsUser();
    $user->update(['timezone' => 'Europe/Berlin']);
    $meetup = meetupFor($user);

    fillEvent(Livewire::test('meetups.create-edit-events', ['meetup' => $meetup]))
        ->set('endTime', '22:30')
        ->call('save')
        ->assertHasNoErrors();

    $event = MeetupEvent::query()->latest('id')->first();
    $start = $event->start->setTimezone('Europe/Berlin');
    $end = $event->end->setTimezone('Europe/Berlin');

    expect($end->format('H:i'))->toBe('22:30')
        ->and($end->toDateString())->toBe($start->toDateString());
});

it('rolls the end over midnight when it is earlier than the start', function () {
    $user = actingAsUser();
    $user->update(['timezone' => 'Europe/Berlin']);
    $meetup = meetupFor($user);

    fillEvent(Livewire::test('meetups.create-edit-events', ['meetup' => $meetup]))
        ->set('endTime', '01:00')
        ->call('save')
        ->assertHasNoErrors();

    $event = MeetupEvent::query()->latest('id')->first();
    $start = $event->start->setTimezone('Europe/Berlin');
    $end = $event->end->setTimezone('Europe/Berlin');

    expect($end->format('H:i'))->toBe('01:00')
        ->and($end->toDateString())->toBe($start->copy()->addDay()->toDateString())
        ->and($end->greaterThan($start))->toBeTrue();
});

it('loads title and end back into the edit form', function () {
    $user = actingAsUser();
    $user->update(['timezone' => 'Europe/Berlin']);
    $meetup = meetupFor($user);

    fillEvent(Livewire::test('meetups.create-edit-events', ['meetup' => $meetup]))
        ->set('title', 'Filmabend')
        ->set('endTime', '23:00')
        ->call('save');

    $event = MeetupEvent::query()->latest('id')->first();

    Livewire::test('meetups.create-edit-events', ['meetup' => $meetup, 'event' => $event])
        ->assertSet('title', 'Filmabend')
        ->assertSet('endTime', '23:00');
});

it('gives every occurrence of a series the same title and end', function () {
    $user = actingAsUser();
    $user->update(['timezone' => 'Europe/Berlin']);
    $meetup = meetupFor($user);

    fillEvent(Livewire::test('meetups.create-edit-events', ['meetup' => $meetup]))
        ->set('seriesMode', true)
        ->set('endDate', now()->addWeeks(4)->format('Y-m-d'))
        ->set('recurrenceType', RecurrenceType::Weekly->value)
        ->set('title', 'Wöchentlicher Stammtisch')
        ->set('endTime', '22:00')
        ->call('save')
        ->assertHasNoErrors();

    $events = MeetupEvent::query()->where('meetup_id', $meetup->id)->get();

    expect($events->count())->toBeGreaterThan(1)
        ->and($events->pluck('title')->unique()->all())->toBe(['Wöchentlicher Stammtisch'])
        ->and($events->every(fn (MeetupEvent $e): bool => $e->end !== null))->toBeTrue();
});

it('keeps the series end distinct from the event end', function () {
    // recurrence_end_date stops the series; end stops the single occurrence. Conflating
    // them would make a two-hour meetup look like it runs for a month.
    $user = actingAsUser();
    $user->update(['timezone' => 'Europe/Berlin']);
    $meetup = meetupFor($user);

    fillEvent(Livewire::test('meetups.create-edit-events', ['meetup' => $meetup]))
        ->set('seriesMode', true)
        ->set('endDate', now()->addWeeks(4)->format('Y-m-d'))
        ->set('recurrenceType', RecurrenceType::Weekly->value)
        ->set('endTime', '22:00')
        ->call('save');

    $event = MeetupEvent::query()->where('meetup_id', $meetup->id)->first();

    expect($event->end->diffInHours($event->start))->toBeLessThan(24);
});
