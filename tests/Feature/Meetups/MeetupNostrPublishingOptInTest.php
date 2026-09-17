<?php

/*
|--------------------------------------------------------------------------
| Nachtrag zu Issue #34 — Opt-in statt Opt-out
|
| `nostr_coordinate` (siehe NostrCalendarEventFactory/PublishCalendarEvents) gatet
| nur, OB bereits publiziert wurde. Ohne einen eigenen Schalter würde ein
| künftiger Cron-Eintrag jedes Meetup automatisch veröffentlichen. Dieser Test
| belegt: nur der Ersteller/Leader/Super-Admin darf den Schalter umlegen — über
| API, Portal-Frontend und MCP —, und die Publish-Gating-Tests dazu liegen in
| PublishCalendarEventsTest.
|
| Since 2026-09-17 the switch is opt-OUT: default on (migration
| 2026_09_17_200000), so Nostr RSVPs have a kind 31923 to address. The
| authorisation tests below therefore start from an explicit `false` — they
| assert that a stranger cannot flip the switch, which needs a state the
| request would change.
|--------------------------------------------------------------------------
*/

use App\Mcp\Servers\EinundzwanzigServer;
use App\Mcp\Tools\Meetup\UpdateMeetupTool;
use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
});

it('defaults nostr publishing to enabled for new meetups', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id])->fresh();

    expect($meetup->nostr_publishing_enabled)->toBeTrue();
});

it('lets the owner enable nostr publishing over the API and returns it in the resource', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create(['created_by' => $user->id, 'city_id' => $this->city->id, 'nostr_publishing_enabled' => false]);

    $this->patchJson('/api/meetup/'.$meetup->id, [
        'nostr_publishing_enabled' => true,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.nostr_publishing_enabled', true);

    expect($meetup->fresh()->nostr_publishing_enabled)->toBeTrue();
});

it('forbids a stranger from enabling nostr publishing over the API', function () {
    $owner = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $owner->id, 'city_id' => $this->city->id, 'nostr_publishing_enabled' => false]);

    Sanctum::actingAs(User::factory()->create());

    $this->patchJson('/api/meetup/'.$meetup->id, [
        'nostr_publishing_enabled' => true,
    ])->assertForbidden();

    expect($meetup->fresh()->nostr_publishing_enabled)->toBeFalse();
});

it('forbids a pivot member who is not the creator or leader from enabling nostr publishing', function () {
    $owner = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $owner->id, 'city_id' => $this->city->id, 'nostr_publishing_enabled' => false]);

    Sanctum::actingAs($member = User::factory()->create());
    $meetup->users()->attach($member);

    $this->patchJson('/api/meetup/'.$meetup->id, [
        'nostr_publishing_enabled' => true,
    ])->assertForbidden();

    expect($meetup->fresh()->nostr_publishing_enabled)->toBeFalse();
});

it('lets a delegated leader enable nostr publishing over the API', function () {
    $owner = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $owner->id, 'city_id' => $this->city->id, 'nostr_publishing_enabled' => false]);

    Sanctum::actingAs($leader = User::factory()->create());
    $meetup->promoteLeader($leader);

    $this->patchJson('/api/meetup/'.$meetup->id, [
        'nostr_publishing_enabled' => true,
    ])->assertSuccessful();

    expect($meetup->fresh()->nostr_publishing_enabled)->toBeTrue();
});

it('persists the nostr publishing toggle from the edit component', function () {
    $creator = actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'created_by' => $creator->id,
        'community' => 'einundzwanzig',
        'nostr_publishing_enabled' => false,
    ]);
    $meetup->users()->attach($creator);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->assertSet('nostr_publishing_enabled', false)
        ->set('name', $meetup->name)
        ->set('city_id', $this->city->id)
        ->set('community', 'einundzwanzig')
        ->set('nostr_publishing_enabled', true)
        ->call('updateMeetup')
        ->assertHasNoErrors();

    expect($meetup->fresh()->nostr_publishing_enabled)->toBeTrue();
});

it('lets a leader opt out of nostr publishing from the edit component', function () {
    $creator = actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'created_by' => $creator->id,
        'community' => 'einundzwanzig',
        'nostr_publishing_enabled' => true,
    ]);
    $meetup->users()->attach($creator);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->assertSet('nostr_publishing_enabled', true)
        ->set('name', $meetup->name)
        ->set('city_id', $this->city->id)
        ->set('community', 'einundzwanzig')
        ->set('nostr_publishing_enabled', false)
        ->call('updateMeetup')
        ->assertHasNoErrors();

    expect($meetup->fresh()->nostr_publishing_enabled)->toBeFalse();
});

it('tells the leader on the edit form that dates are published publicly on Nostr', function () {
    $creator = actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'created_by' => $creator->id,
    ]);
    $meetup->users()->attach($creator);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->assertSee(__('Meetup und Termine auf Nostr veröffentlichen'))
        ->assertSee(__('Aus: Es werden keine Nostr-Kalender-Events für dieses Meetup gesendet. An: Meetup und kommende Termine werden nach und nach als NIP-52-Kalender-Events an die konfigurierten Relays veröffentlicht — meist innerhalb weniger Minuten, öffentlich und für jeden Nostr-Client sichtbar.'));
});

it('leaves the nostr publishing toggle alone when the form saves without touching it', function () {
    $creator = actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'created_by' => $creator->id,
        'community' => 'einundzwanzig',
        'nostr_publishing_enabled' => false,
    ]);
    $meetup->users()->attach($creator);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->set('name', 'Umbenannt ohne Nostr-Schalter')
        ->set('community', 'einundzwanzig')
        ->call('updateMeetup')
        ->assertHasNoErrors();

    expect($meetup->fresh()->nostr_publishing_enabled)->toBeFalse();
});

it('renders a wire:model element for nostr_publishing_enabled in the edit form', function () {
    $creator = actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'created_by' => $creator->id,
    ]);
    $meetup->users()->attach($creator);

    $html = Livewire::test('meetups.edit', ['meetup' => $meetup])->html();

    expect(substr_count($html, 'wire:model="nostr_publishing_enabled"'))->toBe(1);
});

it('lets the owner enable nostr publishing via the MCP update-meetup tool', function () {
    $user = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $user->id, 'city_id' => $this->city->id, 'nostr_publishing_enabled' => false]);

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateMeetupTool::class, ['id' => $meetup->id, 'nostr_publishing_enabled' => true])
        ->assertOk();

    expect($meetup->fresh()->nostr_publishing_enabled)->toBeTrue();
});

it('forbids a stranger from enabling nostr publishing via the MCP update-meetup tool', function () {
    $owner = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $owner->id, 'city_id' => $this->city->id, 'nostr_publishing_enabled' => false]);

    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(UpdateMeetupTool::class, ['id' => $meetup->id, 'nostr_publishing_enabled' => true])
        ->assertHasErrors();

    expect($meetup->fresh()->nostr_publishing_enabled)->toBeFalse();
});
