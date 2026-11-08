<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| `nostr_address` on the public event payloads (hybrid RSVP groundwork)
|--------------------------------------------------------------------------
|
| Clients RSVP over Nostr with a kind 31925 whose `a` tag names the portal's kind
| 31923. The address is the stored `nostr_coordinate`, and the key is present on
| every row — null while unpublished — so a typed client sees one stable shape.
|
| The pubkey is a throwaway, used only to build syntactically valid coordinates.
*/

const NOSTR_ADDRESS_PUBKEY = '45df061ee03c855bdd2c3ecea528d5725e3331e465cd38f14bfe403422952a03';

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
    $this->meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'visible_on_map' => true]);
});

function nostrAddressEvent(array $attributes = []): MeetupEvent
{
    return MeetupEvent::factory()->create(array_merge([
        'meetup_id' => test()->meetup->id,
        'start' => now()->addWeek()->setTime(19, 0),
    ], $attributes));
}

it('carries the 31923 address of a published event on both list paths', function () {
    $coordinate = '31923:'.NOSTR_ADDRESS_PUBKEY.':meetup-event-42';
    $event = nostrAddressEvent(['nostr_coordinate' => $coordinate]);

    $bare = collect($this->getJson('/api/meetup-events')->assertOk()->json())->firstWhere('id', $event->id);
    $dated = collect($this->getJson('/api/meetup-events/'.now()->addWeek()->format('Y-m-d'))->assertOk()->json())
        ->firstWhere('id', $event->id);

    expect($bare['nostr_address'])->toBe($coordinate)
        ->and($dated['nostr_address'])->toBe($coordinate);
});

it('keeps nostr_address present and null for an unpublished event on both list paths', function () {
    $event = nostrAddressEvent(['nostr_coordinate' => null]);

    $bare = collect($this->getJson('/api/meetup-events')->assertOk()->json())->firstWhere('id', $event->id);
    $dated = collect($this->getJson('/api/meetup-events/'.now()->addWeek()->format('Y-m-d'))->assertOk()->json())
        ->firstWhere('id', $event->id);

    expect($bare)->toHaveKey('nostr_address')
        ->and($bare['nostr_address'])->toBeNull()
        ->and($dated)->toHaveKey('nostr_address')
        ->and($dated['nostr_address'])->toBeNull();
});

it('does not hand out a coordinate that is not a kind 31923 address', function () {
    $calendarCoordinate = nostrAddressEvent(['nostr_coordinate' => '31924:'.NOSTR_ADDRESS_PUBKEY.':meetup-1']);
    $malformed = nostrAddressEvent(['nostr_coordinate' => '31923:not-a-pubkey:meetup-event-1']);

    $rows = collect($this->getJson('/api/meetup-events')->assertOk()->json());

    expect($rows->firstWhere('id', $calendarCoordinate->id)['nostr_address'])->toBeNull()
        ->and($rows->firstWhere('id', $malformed->id)['nostr_address'])->toBeNull();
});

it('carries the 31923 address of the next event in the mobile meetup list', function () {
    $coordinate = '31923:'.NOSTR_ADDRESS_PUBKEY.':meetup-event-7';
    nostrAddressEvent(['nostr_coordinate' => $coordinate]);

    $entry = collect($this->getJson('/api/mobile/meetups')->assertOk()->json())->firstWhere('id', $this->meetup->id);

    expect($entry['next_event_nostr_address'])->toBe($coordinate)
        // The existing fields are untouched by the second subquery.
        ->and($entry['next_event_start'])->toBe(now()->addWeek()->setTime(19, 0)->format('Y-m-d H:i'));
});

/*
 * The address must belong to the SAME event as `next_event_start`. A published past
 * event and a published later event both exist here; only the unpublished next one may
 * decide the value, and it says null.
 */
it('takes the mobile address from the next event, not from another published one', function () {
    nostrAddressEvent([
        'start' => now()->subDay(),
        'nostr_coordinate' => '31923:'.NOSTR_ADDRESS_PUBKEY.':meetup-event-past',
    ]);
    nostrAddressEvent([
        'start' => now()->addDays(3)->setTime(19, 0),
        'nostr_coordinate' => null,
    ]);
    nostrAddressEvent([
        'start' => now()->addDays(10),
        'nostr_coordinate' => '31923:'.NOSTR_ADDRESS_PUBKEY.':meetup-event-later',
    ]);

    $entry = collect($this->getJson('/api/mobile/meetups')->assertOk()->json())->firstWhere('id', $this->meetup->id);

    expect($entry['next_event_start'])->toBe(now()->addDays(3)->setTime(19, 0)->format('Y-m-d H:i'))
        ->and($entry)->toHaveKey('next_event_nostr_address')
        ->and($entry['next_event_nostr_address'])->toBeNull();
});

it('keeps next_event_nostr_address present and null for a meetup without a next event', function () {
    $entry = collect($this->getJson('/api/mobile/meetups')->assertOk()->json())->firstWhere('id', $this->meetup->id);

    expect($entry['next_event_start'])->toBeNull()
        ->and($entry)->toHaveKey('next_event_nostr_address')
        ->and($entry['next_event_nostr_address'])->toBeNull();
});

/*
 * Usage probe: shipped app builds keep calling the REST RSVP endpoint, and this log
 * line is how the end of that is measured. The user agent names the build.
 */
it('logs a REST RSVP call with the event id, the user id and the user agent', function () {
    Log::spy();

    Sanctum::actingAs($user = User::factory()->create(['name' => 'Satoshi']));
    $event = nostrAddressEvent();

    $this->withHeader('User-Agent', 'EinundzwanzigApp/1.4.2 (Android)')
        ->postJson("/api/meetup-events/{$event->id}/rsvp", ['status' => 'attending'])
        ->assertSuccessful()
        ->assertJson(['status' => 'attending', 'attendees' => 1]);

    Log::shouldHaveReceived('info')->once()->with('REST RSVP received', [
        'meetup_event_id' => $event->id,
        'user_id' => $user->id,
        'user_agent' => 'EinundzwanzigApp/1.4.2 (Android)',
    ]);
});

it('logs a REST RSVP call that is rejected because RSVP is off', function () {
    Log::spy();

    $this->meetup->update(['rsvp_enabled' => false]);
    Sanctum::actingAs($user = User::factory()->create());
    $event = nostrAddressEvent();

    $this->postJson("/api/meetup-events/{$event->id}/rsvp", ['status' => 'attending'])
        ->assertUnprocessable();

    Log::shouldHaveReceived('info')->once()->withArgs(
        fn (string $message, array $context): bool => $message === 'REST RSVP received'
            && $context['meetup_event_id'] === $event->id
            && $context['user_id'] === $user->id,
    );
});
