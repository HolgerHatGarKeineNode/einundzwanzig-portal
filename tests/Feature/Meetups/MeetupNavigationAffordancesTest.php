<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $this->country->id]);
    // Der created-Hook trägt den Ersteller automatisch als Leader ein.
    $this->creator = User::factory()->create();
    $this->meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'created_by' => $this->creator->id,
        'name' => 'Bitcoin Meetup Testhausen',
    ]);
});

/*
|--------------------------------------------------------------------------
| #45 — an edit action on the meetup detail page
|--------------------------------------------------------------------------
| Editing a meetup used to be reachable only from the list view; the detail
| header offered "Neues Event erstellen" and nothing else.
*/

it('shows the edit action in the detail header for a user who may edit the meetup', function () {
    $this->actingAs($this->creator);

    $this->get(route('meetups.landingpage', ['meetup' => $this->meetup, 'country' => 'de']))
        ->assertSuccessful()
        ->assertSee(__('Meetup bearbeiten'))
        ->assertSee(route_with_country('meetups.edit', ['meetup' => $this->meetup, 'country' => 'de']), false);
});

it('hides the edit action in the detail header from a user who may not edit the meetup', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('meetups.landingpage', ['meetup' => $this->meetup, 'country' => 'de']))
        ->assertSuccessful()
        ->assertDontSee(__('Meetup bearbeiten'))
        ->assertDontSee(route_with_country('meetups.edit', ['meetup' => $this->meetup, 'country' => 'de']), false);
});

it('hides the edit action in the detail header from a guest', function () {
    $this->get(route('meetups.landingpage', ['meetup' => $this->meetup, 'country' => 'de']))
        ->assertSuccessful()
        ->assertDontSee(__('Meetup bearbeiten'));
});

/*
|--------------------------------------------------------------------------
| #45 — meetup link and event link must be distinguishable
|--------------------------------------------------------------------------
| The meetup name was a bare <a> with no affordance, the event link a green
| date badge whose whole accessible name was a date — so the badge read as
| the only link in the row.
*/

it('renders the meetup link and the event link with different markup and different accessible names', function () {
    MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'start' => now()->addDays(7)->setTime(19, 0),
    ]);

    $html = $this->get(route('meetups.index', ['country' => 'de']))
        ->assertSuccessful()
        ->getContent();

    $meetupLabel = __('Meetup :name öffnen', ['name' => $this->meetup->name]);

    // The date inside the event label is NOT precomputed here: app.user-timezone
    // is resolved per request (Europe/Berlin in the request, UTC in the test
    // process), so a hardcoded date would assert the wrong hour. Matched on the
    // wording instead, with the date as a wildcard.
    $eventLabelRegex = '/aria-label="('.str_replace(
        'DATEPLACEHOLDER',
        '[^"]+',
        preg_quote(__('Event am :date öffnen', ['date' => 'DATEPLACEHOLDER']), '/')
    ).')"/u';

    preg_match_all('/<a\b[^>]*>/', $html, $anchors);

    $meetupAnchor = collect($anchors[0])
        ->first(fn (string $tag): bool => str_contains($tag, 'aria-label="'.$meetupLabel.'"'));
    $eventAnchor = collect($anchors[0])
        ->first(fn (string $tag): bool => preg_match($eventLabelRegex, $tag) === 1);

    expect($meetupAnchor)->not->toBeNull('the meetup name carries no accessible name')
        ->and($eventAnchor)->not->toBeNull('the event link carries no accessible name');

    // Markup difference: the meetup name is a flux:link (underlined, accent
    // colour, data-flux-link), the event link wraps an icon-bearing badge.
    expect($meetupAnchor)->toContain('data-flux-link')
        ->and($eventAnchor)->not->toContain('data-flux-link')
        ->and(str($html)->after($eventAnchor)->before('</a>')->value())
        ->toContain('data-flux-badge-icon');

    // Accessible-name difference: the event's says it opens an event, the
    // meetup's names the meetup — and the event's is no longer a bare date.
    preg_match($eventLabelRegex, $eventAnchor, $matched);

    $eventLabel = $matched[1];
    [$prefix, $suffix] = explode("\x00", __('Event am :date öffnen', ['date' => "\x00"]));
    $renderedDate = str($eventLabel)->after($prefix)->before($suffix)->value();

    expect($renderedDate)->not->toBe('')
        // The badge's visible text is still exactly that date — what changed is
        // that the link's accessible name no longer stops there.
        ->and(str($html)->after($eventAnchor)->before('</a>')->value())->toContain($renderedDate)
        ->and($eventLabel)->not->toBe($renderedDate)
        ->and($eventLabel)->not->toBe($meetupLabel);
});

/*
|--------------------------------------------------------------------------
| #42 leftover — pending state on the two meetup forms
|--------------------------------------------------------------------------
| Measured on 2026-09-03: both save buttons are button[type=submit] inside a
| <form wire:submit>, which Livewire v4 disables for the duration of the
| request all by itself (supportDisablingFormsDuringRequest). The missing
| half was the visible acknowledgement, not the double-submit guard.
*/

it('renders a loading target on the create-meetup save button', function () {
    $this->actingAs(User::factory()->create());

    $html = Livewire::test('meetups.create')->html();

    expect($html)
        ->toContain('wire:loading wire:target="createMeetup"')
        ->toContain(__('Wird gespeichert…'))
        ->toContain('wire:submit="createMeetup"');
});

it('renders a loading target on the edit-meetup save button', function () {
    $this->actingAs($this->creator);

    $html = Livewire::test('meetups.edit', ['meetup' => $this->meetup])->html();

    expect($html)
        ->toContain('wire:loading wire:target="updateMeetup"')
        ->toContain(__('Wird gespeichert…'))
        ->toContain('wire:submit="updateMeetup"');
});

/*
|--------------------------------------------------------------------------
| #45 — the map must not push the event list below the fold
|--------------------------------------------------------------------------
| Asserted on the declared height, not on rendered pixels: the value is the
| only thing a server-side test can see, and it is the thing that changed.
*/

it('bounds the detail page map height instead of scaling it with the viewport', function () {
    $response = $this->get(route('meetups.landingpage', ['meetup' => $this->meetup, 'country' => 'de']))
        ->assertSuccessful();

    $response->assertSee('height: clamp(240px, 34vh, 420px);', false)
        ->assertSee('min-height: 240px;', false)
        ->assertDontSee('height: 70vh;', false)
        ->assertDontSee('min-height: 500px;', false);
});
