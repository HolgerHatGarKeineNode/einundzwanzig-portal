<?php

use App\Models\Meetup;
use App\Models\MeetupEvent;
use Livewire\Livewire;

/*
 * An event's own title is shown wherever events are listed (#157).
 *
 * The `title` column has existed since 2026_08_17_164213, but until this issue only the
 * calendar feed and the API read it — every page headed an event with its date alone.
 *
 * The rule pinned here: where a line has to name the event (the event page), an event
 * without a title goes by its meetup's name; where the meetup is already named next to
 * it (the meetup's card grid, the dashboard lists), an untitled event adds nothing.
 *
 * The assertions look at `data-testid="event-title"`, not at the surrounding markup.
 */

function eventWithTitle(?string $title): MeetupEvent
{
    return MeetupEvent::factory()
        ->for(Meetup::factory()->create(['name' => 'Bitcoin Meetup Erfurt']))
        ->create([
            'title' => $title,
            'start' => now()->addWeek(),
        ]);
}

it('heads the event page with the event title', function () {
    $event = eventWithTitle('Einsteigerabend');

    Livewire::test('meetups.landingpage-event', ['event' => $event])
        ->assertStatus(200)
        ->assertSeeHtmlInOrder(['data-testid="event-title"', 'Einsteigerabend'])
        ->assertSee($event->start->asDateTime());
});

it('heads the event page with the meetup name when the event has no title', function () {
    $event = eventWithTitle(null);

    Livewire::test('meetups.landingpage-event', ['event' => $event])
        ->assertStatus(200)
        ->assertSeeHtmlInOrder(['data-testid="event-title"', 'Bitcoin Meetup Erfurt'])
        ->assertSee($event->start->asDateTime());
});

it('shows the event title on its card on the meetup page', function () {
    $event = eventWithTitle('Einsteigerabend');

    Livewire::test('meetups.landingpage', ['meetup' => $event->meetup])
        ->assertStatus(200)
        ->assertSeeHtmlInOrder(['data-testid="event-title"', 'Einsteigerabend'])
        ->assertSee($event->start->asDate());
});

it('keeps the date as card heading when the event has no title', function () {
    $event = eventWithTitle(null);

    Livewire::test('meetups.landingpage', ['meetup' => $event->meetup])
        ->assertStatus(200)
        ->assertDontSeeHtml('data-testid="event-title"')
        ->assertSee($event->start->asDate());
});

it('shows the event title in the dashboard lists', function () {
    $event = eventWithTitle('Einsteigerabend');
    actingAsUser()->meetups()->attach($event->meetup);

    Livewire::test('dashboard')
        ->assertStatus(200)
        ->assertSeeHtmlInOrder(['data-testid="event-title"', 'Einsteigerabend']);

    Livewire::withoutLazyLoading()->test('dashboard.activities')
        ->assertStatus(200)
        ->assertSeeHtmlInOrder(['data-testid="event-title"', 'Einsteigerabend']);
});
