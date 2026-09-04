<?php

use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\NostrCalendarAddress;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| #49 — the NIP-52 address on the meetup and event pages
|--------------------------------------------------------------------------
|
| The reporter enabled Nostr publishing, searched five relays and found
| nothing, and had no way to tell "nothing was sent" from "I'm looking in the
| wrong place". So the page owes three distinguishable states, and the tests
| below hold all three apart:
|
|   data-testid="nostr-calendar-address"          published, address shown
|   data-testid="nostr-calendar-address-pending"  opted in, nothing sent yet
|   neither                                       not opted in
|
| Asserted on the attributes rather than on the copy: every string in the
| block is translatable, and a copy edit must not silently disarm the guard.
|
| The pubkey below is a throwaway, used only to build syntactically valid
| coordinates.
*/

const DISPLAY_PUBKEY = '45df061ee03c855bdd2c3ecea528d5725e3331e465cd38f14bfe403422952a03';

function publishedMeetup(array $attributes = []): Meetup
{
    return Meetup::factory()->create($attributes);
}

it('shows the calendar address on the meetup page once it has been published', function () {
    $meetup = publishedMeetup([
        'nostr_publishing_enabled' => true,
        'nostr_coordinate' => '31924:'.DISPLAY_PUBKEY.':meetup-359',
    ]);

    $expected = NostrCalendarAddress::fromCoordinate($meetup->nostr_coordinate)->naddr();

    Livewire::test('meetups.landingpage', ['meetup' => $meetup])
        ->assertSeeHtml('data-testid="nostr-calendar-address"')
        ->assertDontSeeHtml('data-testid="nostr-calendar-address-pending"')
        ->assertSee($expected);
});

it('says it is not published yet when the switch is on but nothing has been sent', function () {
    $meetup = publishedMeetup([
        'nostr_publishing_enabled' => true,
        'nostr_coordinate' => null,
    ]);

    Livewire::test('meetups.landingpage', ['meetup' => $meetup])
        ->assertSeeHtml('data-testid="nostr-calendar-address-pending"')
        ->assertDontSeeHtml('data-testid="nostr-calendar-address"');
});

it('shows nothing at all when the meetup has not opted in', function () {
    $meetup = publishedMeetup([
        'nostr_publishing_enabled' => false,
        'nostr_coordinate' => null,
    ]);

    Livewire::test('meetups.landingpage', ['meetup' => $meetup])
        ->assertDontSeeHtml('data-testid="nostr-calendar-address"')
        ->assertDontSeeHtml('data-testid="nostr-calendar-address-pending"');
});

/*
 * A published event cannot be unpublished from a relay. Hiding a coordinate that
 * already went out would tell the reader the address no longer exists, which is the
 * one thing that is certainly untrue.
 */
it('keeps showing an address that was already published after the switch is turned off', function () {
    $meetup = publishedMeetup([
        'nostr_publishing_enabled' => false,
        'nostr_coordinate' => '31924:'.DISPLAY_PUBKEY.':meetup-359',
    ]);

    Livewire::test('meetups.landingpage', ['meetup' => $meetup])
        ->assertSeeHtml('data-testid="nostr-calendar-address"');
});

it('names the relays the address was published to', function () {
    config(['services.nostr.relays' => ['wss://nos.lol', 'wss://relay.damus.io']]);

    $meetup = publishedMeetup([
        'nostr_publishing_enabled' => true,
        'nostr_coordinate' => '31924:'.DISPLAY_PUBKEY.':meetup-359',
    ]);

    Livewire::test('meetups.landingpage', ['meetup' => $meetup])
        ->assertSee('wss://nos.lol')
        ->assertSee('wss://relay.damus.io');
});

it('names the target relays even while nothing has been published yet', function () {
    config(['services.nostr.relays' => ['wss://relay.example.test']]);

    $meetup = publishedMeetup([
        'nostr_publishing_enabled' => true,
        'nostr_coordinate' => null,
    ]);

    Livewire::test('meetups.landingpage', ['meetup' => $meetup])
        ->assertSeeHtml('data-testid="nostr-calendar-address-pending"')
        ->assertSee('wss://relay.example.test');
});

/*
 * Measured 2026-09-04 against a real kind 31924 on live relays: plektos.app renders a
 * calendar through its single-event template ("About this party", "When & where — No
 * time specified") and lists none of its member events — on a calendar carrying 22 `a`
 * tags it produced 470 characters and not one of them. A link that misrepresents the
 * object is worse than none, because the reader believes the page.
 *
 * Not because it drops the description: that earlier claim was falsified, the sampled
 * calendar had empty content. NostrCalendarAddress carries the correction.
 */
it('does not link plektos for a calendar, but does for an event', function () {
    $meetup = publishedMeetup([
        'nostr_publishing_enabled' => true,
        'nostr_coordinate' => '31924:'.DISPLAY_PUBKEY.':meetup-359',
    ]);

    Livewire::test('meetups.landingpage', ['meetup' => $meetup])
        ->assertSee('mynostr.app')
        ->assertSee('njump.me')
        ->assertDontSee('plektos.app');

    $event = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
        'nostr_coordinate' => '31923:'.DISPLAY_PUBKEY.':meetup-event-1',
    ]);

    Livewire::test('meetups.landingpage-event', ['event' => $event])
        ->assertSee('plektos.app');
});

it('shows the event address on the event page and routes letsmiti to its event view', function () {
    $meetup = publishedMeetup(['nostr_publishing_enabled' => true]);

    $event = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
        'nostr_coordinate' => '31923:'.DISPLAY_PUBKEY.':meetup-event-1',
    ]);

    $expected = NostrCalendarAddress::fromCoordinate($event->nostr_coordinate)->naddr();

    Livewire::test('meetups.landingpage-event', ['event' => $event])
        ->assertSeeHtml('data-testid="nostr-calendar-address"')
        ->assertSeeHtml('data-nostr-kind="31923"')
        ->assertSee($expected)
        ->assertSeeHtml('https://letsmiti.app/event/'.$expected);
});

it('inherits the meetup switch on the event page when the event has not been published', function () {
    $meetup = publishedMeetup(['nostr_publishing_enabled' => true]);

    $event = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
        'nostr_coordinate' => null,
    ]);

    Livewire::test('meetups.landingpage-event', ['event' => $event])
        ->assertSeeHtml('data-testid="nostr-calendar-address-pending"');
});

/*
 * The edit form is where the reporter was standing when he filed #49 (he linked
 * /us/meetup-edit/359). The switch there promises what SHOULD happen; this block is
 * the only thing on that page that reports what DID happen, so it belongs next to it.
 */
it('shows the address in the edit form once the meetup has been published', function () {
    $creator = actingAsUser();
    $meetup = publishedMeetup([
        'created_by' => $creator->id,
        'nostr_publishing_enabled' => true,
        'nostr_coordinate' => '31924:'.DISPLAY_PUBKEY.':meetup-359',
    ]);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->assertSeeHtml('data-testid="nostr-calendar-address"');
});

it('tells the editing leader that nothing has been published yet', function () {
    $creator = actingAsUser();
    $meetup = publishedMeetup([
        'created_by' => $creator->id,
        'nostr_publishing_enabled' => true,
        'nostr_coordinate' => null,
    ]);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->assertSeeHtml('data-testid="nostr-calendar-address-pending"');
});

it('shows no nostr status block in the edit form while the switch is off', function () {
    $creator = actingAsUser();
    $meetup = publishedMeetup([
        'created_by' => $creator->id,
        'nostr_publishing_enabled' => false,
        'nostr_coordinate' => null,
    ]);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->assertDontSeeHtml('data-testid="nostr-calendar-address-pending"')
        ->assertDontSeeHtml('data-testid="nostr-calendar-address"');
});

it('falls back to the configured relays when the address is built without an explicit list', function () {
    config(['services.nostr.relays' => ['wss://relay.example.test']]);

    $address = NostrCalendarAddress::fromCoordinate('31924:'.DISPLAY_PUBKEY.':meetup-359');

    expect($address->relays())->toBe(['wss://relay.example.test']);
});
