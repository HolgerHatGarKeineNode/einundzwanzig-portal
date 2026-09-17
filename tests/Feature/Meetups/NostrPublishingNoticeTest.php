<?php

use App\Models\Meetup;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Leader notice for default-on Nostr publishing (D16)
|--------------------------------------------------------------------------
|
| Publishing became default-on on 2026-09-17. The one channel that tells leaders
| is a notice at the top of the meetup page: visible to whoever may edit the
| meetup, invisible to everyone else, and only while publishing is actually on.
|
| Asserted on the data-testid, not on the copy, so a wording change does not
| disarm the visibility checks; the copy has its own test per language.
*/

const NOSTR_PUBLISHING_NOTICE_TESTID = 'data-testid="nostr-publishing-notice"';

function noticeMeetup(array $attributes = []): Meetup
{
    return Meetup::factory()->create(array_merge(['nostr_publishing_enabled' => true], $attributes));
}

it('shows the notice to a leader of the meetup, also after a roundtrip', function () {
    $meetup = noticeMeetup();
    $leader = actingAsUser();
    $meetup->promoteLeader($leader);

    Livewire::test('meetups.landingpage', ['meetup' => $meetup])
        ->assertSeeHtml(NOSTR_PUBLISHING_NOTICE_TESTID)
        ->call('$refresh')
        ->assertOk()
        ->assertSeeHtml(NOSTR_PUBLISHING_NOTICE_TESTID);
});

it('shows the notice to the creator of the meetup', function () {
    $creator = actingAsUser();
    $meetup = noticeMeetup(['created_by' => $creator->id]);

    Livewire::test('meetups.landingpage', ['meetup' => $meetup])
        ->assertSeeHtml(NOSTR_PUBLISHING_NOTICE_TESTID);
});

it('does not show the notice to a guest', function () {
    $meetup = noticeMeetup();

    Livewire::test('meetups.landingpage', ['meetup' => $meetup])
        ->assertOk()
        ->assertDontSeeHtml(NOSTR_PUBLISHING_NOTICE_TESTID);
});

it('does not show the notice to a signed-in user who cannot manage the meetup', function () {
    $meetup = noticeMeetup(['created_by' => User::factory()->create()->id]);
    $member = actingAsUser();
    // A plain member (is_leader = false) may not edit, so must not see the notice.
    $meetup->users()->attach($member);

    Livewire::test('meetups.landingpage', ['meetup' => $meetup])
        ->assertOk()
        ->assertDontSeeHtml(NOSTR_PUBLISHING_NOTICE_TESTID)
        // Positive control: the page did render for this user.
        ->assertSee($meetup->name);
});

it('does not show the notice once the leader has switched publishing off', function () {
    $meetup = noticeMeetup(['nostr_publishing_enabled' => false]);
    $leader = actingAsUser();
    $meetup->promoteLeader($leader);

    Livewire::test('meetups.landingpage', ['meetup' => $meetup])
        ->assertDontSeeHtml(NOSTR_PUBLISHING_NOTICE_TESTID)
        // Positive control: the same user still sees the edit button.
        ->assertSee(__('Meetup bearbeiten'));
});

it('links the notice to the meetup edit form', function () {
    $meetup = noticeMeetup();
    $leader = actingAsUser();
    $meetup->promoteLeader($leader);

    $editLink = 'href="'.route_with_country('meetups.edit', ['meetup' => $meetup]).'"';
    $html = Livewire::test('meetups.landingpage', ['meetup' => $meetup])->html();

    // The edit button carries the same link, so count: the notice adds the second one.
    expect(substr_count($html, $editLink))->toBe(2);

    $meetup->update(['nostr_publishing_enabled' => false]);

    expect(substr_count(Livewire::test('meetups.landingpage', ['meetup' => $meetup->fresh()])->html(), $editLink))->toBe(1);
});

it('words the notice in German and English', function (string $locale, string $heading, string $body, string $action) {
    app()->setLocale($locale);

    $meetup = noticeMeetup();
    $leader = actingAsUser();
    $meetup->promoteLeader($leader);

    Livewire::test('meetups.landingpage', ['meetup' => $meetup])
        ->assertSee($heading)
        ->assertSee($body)
        ->assertSee($action);
})->with([
    'de' => [
        'de',
        'Termine dieses Meetups erscheinen jetzt auf Nostr',
        'Sie werden öffentlich als Nostr-Kalendereinträge veröffentlicht, damit Teilnehmer auch mit Nostr zusagen können. Du kannst das in den Meetup-Einstellungen abschalten.',
        'Zu den Meetup-Einstellungen',
    ],
    'en' => [
        'en',
        'Dates of this meetup now appear on Nostr',
        'They are published publicly as Nostr calendar entries so people can also RSVP with Nostr. You can switch this off in the meetup settings.',
        'Go to meetup settings',
    ],
]);
