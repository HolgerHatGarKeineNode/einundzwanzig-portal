<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
});

/**
 * Wohlgeformter npub aus dem bech32-Alphabet (1, b, i und o sind darin
 * ausgeschlossen) — genau die Form, die der Endpunkt durchlaesst.
 */
function npub(string $filler): string
{
    return 'npub1'.str_repeat($filler, 58);
}

/**
 * Meetup ohne sichtbaren Ersteller-Leader: Meetup::booted() traegt den Ersteller
 * zwingend als Leader ein (created_by ist NOT NULL), also bekommt er hier kein
 * npub und faellt damit aus der Ausgabe. Der Test bestimmt selbst, wer erscheint.
 */
function leaderlessMeetup(int $cityId, bool $visible = true): Meetup
{
    return Meetup::factory()->create([
        'city_id' => $cityId,
        'created_by' => User::factory()->create(['nostr' => null])->id,
        'visible_on_map' => $visible,
    ]);
}

it('returns leader npubs grouped by meetup without authentication', function () {
    $meetup = leaderlessMeetup($this->city->id);
    $first = User::factory()->create(['nostr' => npub('a')]);
    $second = User::factory()->create(['nostr' => npub('c')]);
    $meetup->promoteLeader($first);
    $meetup->promoteLeader($second);

    $response = $this->getJson('/api/meetup-leaders');

    $response->assertSuccessful()
        ->assertJsonStructure([['meetup_id', 'npubs']]);

    $row = collect($response->json())->firstWhere('meetup_id', $meetup->id);
    expect($row['meetup_id'])->toBeInt()->toBe($meetup->id);
    expect($row['npubs'])->toHaveCount(2)
        ->toContain($first->nostr)
        ->toContain($second->nostr);
});

it('exposes only npubs, never names or avatars', function () {
    $meetup = leaderlessMeetup($this->city->id);
    $meetup->promoteLeader(User::factory()->create([
        'name' => 'Satoshi Nakamoto',
        'nostr' => npub('d'),
    ]));

    $response = $this->getJson('/api/meetup-leaders');

    $row = collect($response->json())->firstWhere('meetup_id', $meetup->id);
    expect(array_keys($row))->toBe(['meetup_id', 'npubs']);
    $response->assertJsonMissing(['name' => 'Satoshi Nakamoto']);
    expect($response->getContent())->not->toContain('Satoshi');
});

it('ignores members who are not leaders', function () {
    $meetup = leaderlessMeetup($this->city->id);
    $leader = User::factory()->create(['nostr' => npub('e')]);
    $member = User::factory()->create(['nostr' => npub('f')]);
    $meetup->promoteLeader($leader);
    $meetup->addMember($member);

    $row = collect($this->getJson('/api/meetup-leaders')->json())
        ->firstWhere('meetup_id', $meetup->id);

    expect($row['npubs'])->toBe([$leader->nostr]);
});

it('drops a demoted leader again', function () {
    $meetup = leaderlessMeetup($this->city->id);
    $stays = User::factory()->create(['nostr' => npub('g')]);
    $goes = User::factory()->create(['nostr' => npub('h')]);
    $meetup->promoteLeader($stays);
    $meetup->promoteLeader($goes);
    $meetup->demoteLeader($goes);

    $row = collect($this->getJson('/api/meetup-leaders')->json())
        ->firstWhere('meetup_id', $meetup->id);

    expect($row['npubs'])->toBe([$stays->nostr]);
});

it('omits meetups that are hidden from the map', function () {
    $hidden = leaderlessMeetup($this->city->id, visible: false);
    $hidden->promoteLeader(User::factory()->create(['nostr' => npub('k')]));

    $ids = collect($this->getJson('/api/meetup-leaders')->json())->pluck('meetup_id');

    expect($ids)->not->toContain($hidden->id);
});

it('omits meetups whose leaders have no npub', function () {
    $meetup = leaderlessMeetup($this->city->id);
    $meetup->promoteLeader(User::factory()->create(['nostr' => null]));

    $ids = collect($this->getJson('/api/meetup-leaders')->json())->pluck('meetup_id');

    expect($ids)->not->toContain($meetup->id);
});

it('stays at a constant query count regardless of the meetup count', function () {
    foreach (range(1, 6) as $index) {
        $meetup = leaderlessMeetup($this->city->id);
        $meetup->promoteLeader(User::factory()->create([
            'nostr' => npub(['q', 'p', 'z', 'r', 'y', 'x'][$index - 1]),
        ]));
    }

    DB::enableQueryLog();
    $this->getJson('/api/meetup-leaders')->assertSuccessful();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Meetups + eager geladene Leader — nicht eine Abfrage je Meetup.
    expect($queries)->toBeLessThanOrEqual(3);
});

it('is reachable but absent from the generated API reference', function () {
    $this->getJson('/api/meetup-leaders')->assertSuccessful();

    $reference = $this->getJson('/docs/api.json');
    $reference->assertSuccessful();
    $paths = array_keys($reference->json('paths') ?? []);

    // Positivkontrolle zuerst: Scramble listet die Pfade OHNE das /api-Praefix.
    // Ohne diese Zeile waere ein leeres Dokument — oder ein Assert auf den
    // falschen Praefix — gruen, und der Ausschluss haette nichts geprueft.
    expect($paths)->toContain('/mobile/meetups');
    expect($paths)->not->toContain('/meetup-leaders');
});

it('drops a malformed npub instead of handing it to the consumer', function () {
    $meetup = leaderlessMeetup($this->city->id);
    $valid = User::factory()->create(['nostr' => npub('m')]);
    $meetup->promoteLeader($valid);
    // Faker- und Altdaten-Form: Grossbuchstaben gibt es in bech32 nicht.
    $meetup->promoteLeader(User::factory()->create([
        'nostr' => 'npub1PEZWBgCuIJU3ehgFoxhRv4qoGaMOxo7nWLbxsZLKwUBRqR45oUJ0RX',
    ]));
    // Zu kurz, aber mit korrektem Praefix.
    $meetup->promoteLeader(User::factory()->create(['nostr' => 'npub1qqqq']));

    $row = collect($this->getJson('/api/meetup-leaders')->json())
        ->firstWhere('meetup_id', $meetup->id);

    expect($row['npubs'])->toBe([$valid->nostr]);
});
