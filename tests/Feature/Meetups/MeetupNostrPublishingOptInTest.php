<?php

/*
|--------------------------------------------------------------------------
| Nachtrag zu Issue #34 — Opt-in statt Opt-out
|
| `nostr_coordinate` (siehe NostrCalendarEventFactory/PublishCalendarEvents) gatet
| nur, OB bereits publiziert wurde. Ohne einen eigenen Schalter würde ein
| künftiger Cron-Eintrag jedes Meetup automatisch veröffentlichen. Dieser Test
| belegt: der Schalter ist standardmäßig aus, nur der Ersteller/Leader/Super-Admin
| darf ihn umlegen — über API, Portal-Frontend und MCP —, und die Publish-Gating-
| Tests dazu liegen in PublishCalendarEventsTest.
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

it('defaults nostr publishing to disabled for new meetups', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id])->fresh();

    expect($meetup->nostr_publishing_enabled)->toBeFalse();
});

it('lets the owner enable nostr publishing over the API and returns it in the resource', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create(['created_by' => $user->id, 'city_id' => $this->city->id]);

    $this->patchJson('/api/meetup/'.$meetup->id, [
        'nostr_publishing_enabled' => true,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.nostr_publishing_enabled', true);

    expect($meetup->fresh()->nostr_publishing_enabled)->toBeTrue();
});

it('forbids a stranger from enabling nostr publishing over the API', function () {
    $owner = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $owner->id, 'city_id' => $this->city->id]);

    Sanctum::actingAs(User::factory()->create());

    $this->patchJson('/api/meetup/'.$meetup->id, [
        'nostr_publishing_enabled' => true,
    ])->assertForbidden();

    expect($meetup->fresh()->nostr_publishing_enabled)->toBeFalse();
});

it('forbids a pivot member who is not the creator or leader from enabling nostr publishing', function () {
    $owner = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $owner->id, 'city_id' => $this->city->id]);

    Sanctum::actingAs($member = User::factory()->create());
    $meetup->users()->attach($member);

    $this->patchJson('/api/meetup/'.$meetup->id, [
        'nostr_publishing_enabled' => true,
    ])->assertForbidden();

    expect($meetup->fresh()->nostr_publishing_enabled)->toBeFalse();
});

it('lets a delegated leader enable nostr publishing over the API', function () {
    $owner = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $owner->id, 'city_id' => $this->city->id]);

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
    $meetup = Meetup::factory()->create(['created_by' => $user->id, 'city_id' => $this->city->id]);

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateMeetupTool::class, ['id' => $meetup->id, 'nostr_publishing_enabled' => true])
        ->assertOk();

    expect($meetup->fresh()->nostr_publishing_enabled)->toBeTrue();
});

it('forbids a stranger from enabling nostr publishing via the MCP update-meetup tool', function () {
    $owner = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $owner->id, 'city_id' => $this->city->id]);

    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(UpdateMeetupTool::class, ['id' => $meetup->id, 'nostr_publishing_enabled' => true])
        ->assertHasErrors();

    expect($meetup->fresh()->nostr_publishing_enabled)->toBeFalse();
});
