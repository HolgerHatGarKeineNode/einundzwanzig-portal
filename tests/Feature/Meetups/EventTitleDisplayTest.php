<?php

use App\Console\Commands\Nostr\PublishUnpublishedItems;
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

it('names the event in the delete dialog on the meetup page', function () {
    $user = actingAsUser();
    $event = MeetupEvent::factory()
        ->for(Meetup::factory()->create(['created_by' => $user->id]))
        ->create(['title' => 'Einsteigerabend', 'start' => now()->addWeek()]);

    Livewire::test('meetups.landingpage', ['meetup' => $event->meetup])
        ->assertStatus(200)
        ->assertSee('("Einsteigerabend")', false);
});

it('shows the next event title in the meetup list and the map popup', function () {
    $event = eventWithTitle('Einsteigerabend');
    $meetup = $event->meetup;

    $this->withoutVite()
        ->get(route('meetups.index', ['country' => $meetup->city->country->code]))
        ->assertOk()
        ->assertSeeInOrder(['data-testid="event-title"', 'Einsteigerabend'], false);

    $popup = view('components.meetup-popup', ['meetup' => $meetup, 'url' => '#', 'eventUrl' => '#'])->render();

    expect($popup)->toContain('data-testid="event-title"')->toContain('Einsteigerabend');
});

it('leads the date line of the Nostr note with the event title', function () {
    $titled = eventWithTitle('Einsteigerabend');
    $untitled = MeetupEvent::factory()->for($titled->meetup)->create(['title' => null]);
    $command = app(PublishUnpublishedItems::class);

    expect($command->getText($titled, 'de'))
        ->toContain('Einsteigerabend — '.$titled->start->asDateTime())
        ->and($command->getText($untitled, 'de'))
        ->toContain("\n".$untitled->start->asDateTime()."\n");
});
