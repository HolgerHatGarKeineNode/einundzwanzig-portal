<?php

/*
|--------------------------------------------------------------------------
| Issue #70 — a published calendar event carries EVERY link, as `r` tags
|--------------------------------------------------------------------------
|
| `r` is the tag NIP-52 names for references/links on both calendar kinds,
| and the tag forMeetup() already uses for a meetup's social links. The
| LABEL is deliberately not published: NIP-52 defines no label position on
| `r`, and the third element is a marker slot elsewhere (NIP-65 read/write,
| NIP-34 euc). See the docblock in NostrCalendarEventFactory.
|
*/

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\NostrCalendarEventFactory;
use Illuminate\Support\Facades\DB;

function linkTestPubkey(): string
{
    return str_repeat('b2', 32);
}

function meetupEventWithLinks(array $links): MeetupEvent
{
    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create([
        'country_id' => $country->id,
        'name' => 'Berlin',
        'latitude' => 52.5200,
        'longitude' => 13.4050,
    ]);

    return MeetupEvent::factory()
        ->for(Meetup::factory()->create(['city_id' => $city->id]))
        ->create(['links' => $links, 'start' => '2026-08-01 18:00:00']);
}

it('emits one r tag per link, in the organiser order', function () {
    $event = meetupEventWithLinks([
        ['url' => 'https://www.meetup.com/bitcoin-berlin/', 'label' => 'Meetup.com'],
        ['url' => 'https://luma.com/berlin', 'label' => 'Luma'],
        ['url' => 'https://t.me/berlin_btc'],
    ]);

    $published = NostrCalendarEventFactory::forMeetupEvent($event, linkTestPubkey());

    expect($published->getTag('r'))->toBe([
        ['r', 'https://www.meetup.com/bitcoin-berlin/'],
        ['r', 'https://luma.com/berlin'],
        ['r', 'https://t.me/berlin_btc'],
    ]);
});

it('never writes the label into the r tag', function () {
    $event = meetupEventWithLinks([
        ['url' => 'https://luma.com/berlin', 'label' => 'Luma'],
    ]);

    $published = NostrCalendarEventFactory::forMeetupEvent($event, linkTestPubkey());

    // Two elements, not three: the third position of an `r` tag is a marker slot in
    // other NIPs and must not be handed a free-text label.
    expect($published->getTag('r'))->toBe([['r', 'https://luma.com/berlin']]);
});

it('emits no r tag for an event without links', function () {
    $event = meetupEventWithLinks([]);

    expect(NostrCalendarEventFactory::forMeetupEvent($event, linkTestPubkey())->getTag('r'))->toBe([]);
});

it('publishes the pre-#70 single link of a row the backfill never reached', function () {
    $event = meetupEventWithLinks([]);

    // `link` filled, `links` still NULL — the state linkList() falls back for.
    DB::table('meetup_events')->where('id', $event->id)->update([
        'link' => 'https://example.com/old',
        'links' => null,
    ]);

    $published = NostrCalendarEventFactory::forMeetupEvent($event->refresh(), linkTestPubkey());

    expect($published->getTag('r'))->toBe([['r', 'https://example.com/old']]);
});
