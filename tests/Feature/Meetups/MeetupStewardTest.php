<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use swentel\nostr\Key\Key as NostrKey;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
    $this->creator = User::factory()->create();
    // Der created-Hook trägt den Ersteller automatisch als Leader ein.
    $this->meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'created_by' => $this->creator->id,
    ]);
    $this->otherMeetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'created_by' => User::factory()->create()->id,
    ]);

    $this->steward = User::factory()->create();
    $this->steward->assignRole(Role::findOrCreate(User::ROLE_MEETUP_STEWARD, 'web'));
});

/** Deterministischer, gültiger npub aus einem 64-stelligen Hex-Pubkey. */
function stewardTestNpub(string $hex): string
{
    return (new NostrKey)->convertPublicKeyToBech32($hex);
}

it('grants manageLeaders and update on every meetup', function () {
    expect($this->steward->can('manageLeaders', $this->meetup))->toBeTrue()
        ->and($this->steward->can('manageLeaders', $this->otherMeetup))->toBeTrue()
        ->and($this->steward->can('update', $this->meetup))->toBeTrue()
        ->and($this->steward->can('update', $this->otherMeetup))->toBeTrue();
});

it('appoints a leader on a foreign meetup via the API', function () {
    $npub = stewardTestNpub(str_pad('a', 64, 'a'));

    Sanctum::actingAs($this->steward);

    $this->postJson("/api/meetup/{$this->meetup->id}/leaders", ['npub' => $npub])
        ->assertCreated();

    $appointed = User::query()->where('nostr', $npub)->firstOrFail();

    $this->assertDatabaseHas('meetup_user', [
        'meetup_id' => $this->meetup->id,
        'user_id' => $appointed->id,
        'is_leader' => true,
    ]);
});

it('revokes a leader on a foreign meetup via the API', function () {
    $leader = User::factory()->create();
    $this->meetup->promoteLeader($leader);

    Sanctum::actingAs($this->steward);

    $this->deleteJson("/api/meetup/{$this->meetup->id}/leaders/{$leader->id}")
        ->assertSuccessful();

    $this->assertDatabaseHas('meetup_user', [
        'meetup_id' => $this->meetup->id,
        'user_id' => $leader->id,
        'is_leader' => false,
    ]);
});

it('cannot revoke the creator of a meetup', function () {
    Sanctum::actingAs($this->steward);

    $this->deleteJson("/api/meetup/{$this->meetup->id}/leaders/{$this->creator->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('meetup_user', [
        'meetup_id' => $this->meetup->id,
        'user_id' => $this->creator->id,
        'is_leader' => true,
    ]);
});

it('lists the leaders of a foreign meetup', function () {
    Sanctum::actingAs($this->steward);

    $this->getJson("/api/meetup/{$this->meetup->id}/leaders")
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $this->creator->id)
        ->assertJsonPath('data.0.is_creator', true);
});

it('keeps a user without the role locked out', function () {
    $stranger = User::factory()->create();

    Sanctum::actingAs($stranger);

    $this->getJson("/api/meetup/{$this->meetup->id}/leaders")->assertForbidden();
    $this->postJson("/api/meetup/{$this->meetup->id}/leaders", ['npub' => stewardTestNpub(str_pad('b', 64, 'b'))])
        ->assertForbidden();
});

it('does not add any meetup to the stewards own meetups', function () {
    $npub = stewardTestNpub(str_pad('c', 64, 'c'));

    Sanctum::actingAs($this->steward);

    $this->postJson("/api/meetup/{$this->meetup->id}/leaders", ['npub' => $npub])->assertCreated();
    $this->deleteJson("/api/meetup/{$this->meetup->id}/leaders/".User::query()->where('nostr', $npub)->value('id'))
        ->assertSuccessful();

    // Keine Pivot-Zeile, kein created_by — weder REST-Liste noch MCP-Scope sehen etwas.
    $this->assertDatabaseMissing('meetup_user', ['user_id' => $this->steward->id]);

    expect(Meetup::query()->associatedWith($this->steward->id)->count())->toBe(0)
        ->and($this->steward->meetups()->count())->toBe(0);

    $this->getJson('/api/my-meetups')
        ->assertSuccessful()
        ->assertJsonCount(0, 'data');
});

it('appoints and revokes leaders through the portal edit page', function () {
    $npub = stewardTestNpub(str_pad('d', 64, 'd'));

    $this->actingAs($this->steward);

    Livewire::test('meetups.edit', ['meetup' => $this->otherMeetup])
        ->set('leaderNpub', $npub)
        ->call('addLeader')
        ->assertHasNoErrors();

    $appointed = User::query()->where('nostr', $npub)->firstOrFail();

    $this->assertDatabaseHas('meetup_user', [
        'meetup_id' => $this->otherMeetup->id,
        'user_id' => $appointed->id,
        'is_leader' => true,
    ]);

    Livewire::test('meetups.edit', ['meetup' => $this->otherMeetup])
        ->call('removeLeader', $appointed->id);

    $this->assertDatabaseHas('meetup_user', [
        'meetup_id' => $this->otherMeetup->id,
        'user_id' => $appointed->id,
        'is_leader' => false,
    ]);
});

it('does not make a steward a leader of the meetup event itself', function () {
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'start' => now()->addWeek(),
        'created_by' => $this->creator->id,
    ]);

    // MeetupEventPolicy hängt an isLeader() — die Rolle allein reicht dort nicht.
    // ACHTUNG: das ist KEINE Aussage über den Portal-Pfad; die Termin-Seite
    // autorisiert gegen die update-Ability des Meetups, die der Steward hat.
    expect($this->steward->can('update', $event))->toBeFalse()
        ->and($this->steward->can('delete', $event))->toBeFalse();
});

it('refuses to let a steward appoint himself as leader via the API', function () {
    $this->steward->update(['nostr' => stewardTestNpub(str_pad('e', 64, 'e'))]);

    Sanctum::actingAs($this->steward);

    $this->postJson("/api/meetup/{$this->meetup->id}/leaders", ['npub' => $this->steward->nostr])
        ->assertForbidden();

    $this->assertDatabaseMissing('meetup_user', ['user_id' => $this->steward->id]);
});

it('refuses to let a steward appoint himself as leader via the portal', function () {
    $this->steward->update(['nostr' => stewardTestNpub(str_pad('f', 64, 'f'))]);

    $this->actingAs($this->steward);

    Livewire::test('meetups.edit', ['meetup' => $this->otherMeetup])
        ->set('leaderNpub', $this->steward->nostr)
        ->call('addLeader')
        ->assertForbidden();

    $this->assertDatabaseMissing('meetup_user', ['user_id' => $this->steward->id]);
});

it('still lets a real leader re-appoint himself', function () {
    $this->creator->update(['nostr' => stewardTestNpub(str_pad('9', 64, '9'))]);

    Sanctum::actingAs($this->creator);

    $this->postJson("/api/meetup/{$this->meetup->id}/leaders", ['npub' => $this->creator->nostr])
        ->assertCreated();

    $this->assertDatabaseHas('meetup_user', [
        'meetup_id' => $this->meetup->id,
        'user_id' => $this->creator->id,
        'is_leader' => true,
    ]);
});
