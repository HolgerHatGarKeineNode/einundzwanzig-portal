<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\MeetupSlugRedirect;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Issue #167: the URL slug of a meetup can be changed, without breaking links.
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $this->country->id]);
    $this->creator = actingAsUser();
    $this->meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'name' => 'Testovaci zaznam',
        'slug' => 'testovaci-zaznam-z-nostr',
        'community' => 'einundzwanzig',
        'created_by' => $this->creator->id,
    ]);
});

function editMeetupForm(Meetup $meetup)
{
    return Livewire::test('meetups.edit', ['meetup' => $meetup]);
}

it('saves a new slug and keeps the old one as a redirect', function () {
    editMeetupForm($this->meetup)
        ->set('slug', 'einundzwanzig-praha')
        ->call('updateMeetup')
        ->assertHasNoErrors()
        ->assertSee('/meetup/testovaci-zaznam-z-nostr');

    expect($this->meetup->refresh()->slug)->toBe('einundzwanzig-praha')
        ->and($this->meetup->slugRedirects()->pluck('slug')->all())->toBe(['testovaci-zaznam-z-nostr']);
});

it('normalises what is typed into a slug when the field is left', function () {
    editMeetupForm($this->meetup)
        ->set('slug', '  Bitcoin Köln!  ')
        ->assertSet('slug', 'bitcoin-koeln')
        ->assertHasNoErrors();
});

it('generates the slug from the name on request', function () {
    editMeetupForm($this->meetup)
        ->set('name', 'Einundzwanzig Brno')
        ->call('slugFromName')
        ->assertSet('slug', 'einundzwanzig-brno');
});

it('rejects a slug another meetup carries, already while typing', function () {
    Meetup::factory()->create(['city_id' => $this->city->id, 'name' => 'Einundzwanzig Praha', 'slug' => 'einundzwanzig-praha']);

    editMeetupForm($this->meetup)
        ->set('slug', 'einundzwanzig-praha')
        ->assertHasErrors(['slug'])
        ->assertSee('Einundzwanzig Praha')
        ->call('updateMeetup')
        ->assertHasErrors(['slug']);

    expect($this->meetup->refresh()->slug)->toBe('testovaci-zaznam-z-nostr');
});

it('rejects a slug another meetup gave up, since it still redirects there', function () {
    $other = Meetup::factory()->create(['city_id' => $this->city->id, 'slug' => 'einundzwanzig-praha-neu']);
    MeetupSlugRedirect::factory()->create(['meetup_id' => $other->id, 'slug' => 'einundzwanzig-praha']);

    editMeetupForm($this->meetup)
        ->set('slug', 'einundzwanzig-praha')
        ->call('updateMeetup')
        ->assertHasErrors(['slug']);
});

it('rejects a slug a portal route already answers', function () {
    editMeetupForm($this->meetup)
        ->set('slug', 'meetup-events')
        ->call('updateMeetup')
        ->assertHasErrors(['slug']);
});

it('rejects a slug that is empty after normalising', function () {
    editMeetupForm($this->meetup)
        ->set('slug', '!!!')
        ->call('updateMeetup')
        ->assertHasErrors(['slug']);

    expect($this->meetup->refresh()->slug)->toBe('testovaci-zaznam-z-nostr');
});

it('lets a meetup take back a slug it gave up, which ends that redirect', function () {
    $this->meetup->update(['slug' => 'einundzwanzig-praha']);

    editMeetupForm($this->meetup->refresh())
        ->set('slug', 'testovaci-zaznam-z-nostr')
        ->call('updateMeetup')
        ->assertHasNoErrors();

    expect($this->meetup->refresh()->slug)->toBe('testovaci-zaznam-z-nostr')
        ->and($this->meetup->slugRedirects()->pluck('slug')->all())->toBe(['einundzwanzig-praha']);
});

it('does not check an untouched legacy slug, so other fields still save', function () {
    $this->meetup->update(['slug' => 'Legacy_Slug']);

    editMeetupForm($this->meetup->refresh())
        ->set('intro', 'Neue Einführung')
        ->call('updateMeetup')
        ->assertHasNoErrors();

    expect($this->meetup->refresh())
        ->slug->toBe('Legacy_Slug')
        ->intro->toBe('Neue Einführung');
});

it('records the retired slug however the slug is changed', function () {
    $this->meetup->update(['slug' => 'einundzwanzig-praha']);

    expect(MeetupSlugRedirect::query()->where('slug', 'testovaci-zaznam-z-nostr')->value('meetup_id'))
        ->toBe($this->meetup->id);
});

it('redirects a retired landing page URL permanently, keeping the query string', function () {
    $this->meetup->update(['slug' => 'einundzwanzig-praha']);

    $this->get('/de/meetup/testovaci-zaznam-z-nostr?ref=flyer')
        ->assertStatus(301)
        ->assertRedirect(url('/de/meetup/einundzwanzig-praha?ref=flyer'));
});

it('keeps a retired event URL working', function () {
    // The event page resolves by event id alone; the slug segment is never bound there.
    $event = MeetupEvent::factory()->create(['meetup_id' => $this->meetup->id]);
    $this->meetup->update(['slug' => 'einundzwanzig-praha']);

    $this->get("/de/meetup/testovaci-zaznam-z-nostr/event/{$event->id}")->assertOk();
});

it('still answers an unknown slug with 404', function () {
    $this->get('/de/meetup/gibt-es-nicht')->assertNotFound();
});

it('keeps the BTC Map community id stable across a slug change', function () {
    $this->meetup->update(['slug' => 'einundzwanzig-praha']);
    $this->meetup->update(['slug' => 'einundzwanzig-praha-2']);

    $ids = collect($this->getJson('/api/btc-map-communities')->json())->pluck('id');

    expect($ids)->toContain('testovaci-zaznam-z-nostr')
        ->and($ids)->not->toContain('einundzwanzig-praha-2');
});

it('refuses a duplicate slug at the database even past the form', function () {
    expect(fn () => Meetup::factory()->create(['city_id' => $this->city->id])->forceFill(['slug' => 'testovaci-zaznam-z-nostr'])->saveQuietly())
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});
