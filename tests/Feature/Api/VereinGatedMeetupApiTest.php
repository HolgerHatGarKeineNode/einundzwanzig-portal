<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use swentel\nostr\Key\Key as NostrKey;

// Echte, dekodierbare npubs (bech32) — nötig, weil das Matching npub→hex normalisiert.
// randomNpub() liefert nur zu 70 % ein echtes; hier deterministisch fixe Werte.
const REAL_NPUBS = [
    'npub1sg6plzptd64u62a878hep2kev88swjh3tw00gjsfl8f237lmu63q0uf63m',
    'npub1xtscya34g58tk0z605fvr788k263gsu6cy9x0mhnm87echrgufzsevkk5s',
    'npub1qny3tkh0acurzla8x3zy4nhrjz5zd8l9sy9jys09umwng00manysew95gx',
    'npub1u8lnhlw5usp3t9vmpz60ejpyt649z33hu82wc2hpv6m5xdqmuxhs46turz',
];

beforeEach(function () {
    Cache::flush();
    config()->set('services.verein_gate.token', 'test-secret-token');

    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id, 'name' => 'Berlin']);
});

/**
 * Legt ein Meetup an und hängt einen frischen User (mit echtem npub) in die
 * meetup_user-Pivot. Gibt [Meetup, User] zurück.
 *
 * @return array{0: Meetup, 1: User}
 */
function seedMeetupWithMember(City $city, bool $isLeader, string $name, string $npub): array
{
    $user = User::factory()->create(['nostr' => $npub]);
    $meetup = Meetup::factory()->create(['city_id' => $city->id, 'name' => $name]);
    $meetup->users()->attach($user->id, ['is_leader' => $isLeader]);

    return [$meetup, $user];
}

/**
 * Baut eine gefakete verein-Antwort mit den npubs (+ abgeleiteten hex-pubkeys).
 */
function fakeVereinMembers(User ...$members): void
{
    $payload = collect($members)->map(fn (User $user, int $i): array => [
        'id' => $i + 1,
        'npub' => $user->nostr,
        'pubkey' => (new NostrKey)->convertToHex($user->nostr),
        'nip05_handle' => 'member'.$i.'@einundzwanzig.space',
    ])->all();

    Http::fake([
        'verein.einundzwanzig.space/api/members/*' => Http::response($payload, 200),
    ]);
}

it('rejects requests without a bearer token', function () {
    fakeVereinMembers();

    $this->getJson('/api/verein/gated-meetups')->assertUnauthorized();
});

it('rejects requests with a wrong bearer token', function () {
    fakeVereinMembers();

    $this->getJson('/api/verein/gated-meetups', ['Authorization' => 'Bearer wrong-token'])
        ->assertUnauthorized();
});

it('returns the gated meetup shape with a valid token', function () {
    [$meetup, $member] = seedMeetupWithMember($this->city, isLeader: false, name: 'Verein Meetup', npub: REAL_NPUBS[0]);
    fakeVereinMembers($member);

    $response = $this->getJson('/api/verein/gated-meetups', ['Authorization' => 'Bearer test-secret-token']);

    $response->assertSuccessful()
        ->assertJsonStructure([['id', 'slug', 'name', 'country_code', 'logo_url', 'member_npubs']]);

    $entry = collect($response->json())->firstWhere('id', $meetup->id);
    expect($entry)
        ->name->toBe('Verein Meetup')
        ->country_code->toBe('DE')
        ->logo_url->toBe('')
        ->and($entry['member_npubs'])->toBe([$member->nostr]);
});

it('returns only meetups that have a verein member, filtering non-member meetups out', function () {
    [$gated, $member] = seedMeetupWithMember($this->city, isLeader: false, name: 'Mit Vereinsmitglied', npub: REAL_NPUBS[0]);

    // Meetup mit einem User, der KEIN Vereinsmitglied ist → darf nicht erscheinen.
    $outsider = User::factory()->create(['nostr' => REAL_NPUBS[3]]);
    $ungated = Meetup::factory()->create(['city_id' => $this->city->id, 'name' => 'Ohne Vereinsbezug']);
    $ungated->users()->attach($outsider->id, ['is_leader' => false]);

    fakeVereinMembers($member); // nur $member ist im Verein

    $ids = collect($this->getJson('/api/verein/gated-meetups', ['Authorization' => 'Bearer test-secret-token'])->json())
        ->pluck('id');

    expect($ids->all())->toBe([$gated->id]);
});

it('excludes a qualifying meetup that is not visible on the map', function () {
    // Qualifiziert über das Vereinsmitglied, ist aber unsichtbar → darf NICHT kommen.
    [$hidden, $member] = seedMeetupWithMember($this->city, isLeader: false, name: 'Unsichtbar', npub: REAL_NPUBS[0]);
    $hidden->update(['visible_on_map' => false]);

    fakeVereinMembers($member);

    $ids = collect($this->getJson('/api/verein/gated-meetups', ['Authorization' => 'Bearer test-secret-token'])->json())
        ->pluck('id');

    expect($ids->all())->not->toContain($hidden->id)->toBe([]);
});

it('with leaders_only counts only is_leader pivot members', function () {
    // Vereinsmitglied ist NUR normales Pivot-Mitglied (kein Leader).
    [$memberMeetup, $member] = seedMeetupWithMember($this->city, isLeader: false, name: 'Mitglied kein Leader', npub: REAL_NPUBS[0]);

    // Vereinsmitglied ist Leader eines anderen Meetups.
    [$leaderMeetup, $leader] = seedMeetupWithMember($this->city, isLeader: true, name: 'Mitglied ist Leader', npub: REAL_NPUBS[1]);

    fakeVereinMembers($member, $leader);

    $token = ['Authorization' => 'Bearer test-secret-token'];

    // Ohne leaders_only: beide Meetups.
    $all = collect($this->getJson('/api/verein/gated-meetups', $token)->json())->pluck('id');
    expect($all->all())->toContain($memberMeetup->id)->toContain($leaderMeetup->id);

    // Mit leaders_only=1: nur das Leader-Meetup.
    $ledOnly = collect($this->getJson('/api/verein/gated-meetups?leaders_only=1', $token)->json())->pluck('id');
    expect($ledOnly->all())->toBe([$leaderMeetup->id]);
});
