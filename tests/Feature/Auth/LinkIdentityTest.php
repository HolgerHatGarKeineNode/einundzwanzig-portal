<?php

declare(strict_types=1);

use App\Models\Meetup;
use App\Models\User;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use swentel\nostr\Event\Event as NostrEvent;
use swentel\nostr\Key\Key as NostrKey;
use swentel\nostr\Sign\Sign as NostrSign;

/**
 * Sign a kind-22242 event against the challenge the wizard put in the session.
 *
 * @return array{0: array<string, mixed>, 1: string}
 */
function makeSignedMergeEvent(?string $content = null): array
{
    $keyGen = new NostrKey;
    $privateKey = $keyGen->generatePrivateKey();
    $publicKey = $keyGen->getPublicKey($privateKey);

    // Must match the wizard's required merge intent, or proveNostr rejects it.
    $content ??= 'einundzwanzig.space: Nostr-Konto mit Portal-Konto verbinden';

    $event = new NostrEvent;
    $event->setKind(22242)
        ->setCreatedAt(time())
        ->setContent($content)
        ->setTags([['challenge', (string) Session::get('merge_nostr_challenge')]]);

    (new NostrSign)->signEvent($event, $privateKey);

    return [[
        'id' => $event->getId(),
        'pubkey' => $event->getPublicKey(),
        'created_at' => $event->getCreatedAt(),
        'kind' => $event->getKind(),
        'tags' => $event->getTags(),
        'content' => $event->getContent(),
        'sig' => $event->getSignature(),
    ], $keyGen->convertPublicKeyToBech32($publicKey)];
}

it('merges a separate Nostr account (with its leadership) into the current Lightning account', function () {
    $lightningUser = User::factory()->create(['nostr' => null, 'public_key' => 'ab'.str_repeat('0', 62)]);

    $this->actingAs($lightningUser);
    $component = Livewire::test('settings.link-identity')->assertSet('mode', 'prove_nostr');

    [$signed, $npub] = makeSignedMergeEvent();

    // A separate, app-created Nostr account that leads a meetup.
    $nostrAccount = User::factory()->create(['nostr' => $npub]);
    $meetup = Meetup::factory()->create(['created_by' => $nostrAccount->id]);

    $component->call('proveNostr', $signed)
        ->assertSet('proofReady', true)
        ->assertSet('willMerge', true);

    // The confirm step previews the incoming account's relations by name.
    expect($component->get('loserInfo')['leader_meetups'])->toContain($meetup->name);

    $component->set('acknowledgedBackup', true)->call('confirmMerge');

    $lightningUser->refresh();
    $meetup->refresh();

    expect($lightningUser->nostr)->toBe($npub)
        ->and($meetup->created_by)->toBe($lightningUser->id)
        ->and($meetup->isLeader($lightningUser))->toBeTrue()
        ->and($lightningUser->lightning_retired_at)->not->toBeNull()
        ->and(User::whereKey($nostrAccount->id)->exists())->toBeFalse();
});

it('just links the npub when no separate account exists', function () {
    $lightningUser = User::factory()->create(['nostr' => null, 'public_key' => 'cd'.str_repeat('0', 62)]);

    $this->actingAs($lightningUser);
    $component = Livewire::test('settings.link-identity');

    [$signed, $npub] = makeSignedMergeEvent();

    $component->call('proveNostr', $signed)
        ->assertSet('willMerge', false)
        ->set('acknowledgedBackup', true)
        ->call('confirmMerge');

    expect($lightningUser->fresh()->nostr)->toBe($npub);
});

it('requires the backup acknowledgement before merging', function () {
    $lightningUser = User::factory()->create(['nostr' => null, 'public_key' => 'ba'.str_repeat('0', 62)]);
    $this->actingAs($lightningUser);

    $component = Livewire::test('settings.link-identity');
    [$signed, $npub] = makeSignedMergeEvent();
    User::factory()->create(['nostr' => $npub]);

    $component->call('proveNostr', $signed)
        ->call('confirmMerge') // acknowledgedBackup is false
        ->assertHasErrors('acknowledgedBackup');

    expect($lightningUser->fresh()->nostr)->toBeNull();
});

it('retires the Lightning credential after a merge', function () {
    $lightningUser = User::factory()->create(['nostr' => null, 'public_key' => 'be'.str_repeat('0', 62)]);
    $this->actingAs($lightningUser);

    $component = Livewire::test('settings.link-identity');
    [$signed, $npub] = makeSignedMergeEvent();
    User::factory()->create(['nostr' => $npub]);

    $component->call('proveNostr', $signed)
        ->set('acknowledgedBackup', true)
        ->call('confirmMerge');

    expect($lightningUser->fresh()->lightning_retired_at)->not->toBeNull();
});

it('refuses to merge without a verified signature in the session (anti-theft)', function () {
    $victimNostr = 'npub1victim'.str_repeat('0', 20);
    $victim = User::factory()->create(['nostr' => $victimNostr]);
    Meetup::factory()->create(['created_by' => $victim->id]);

    $attacker = User::factory()->create(['nostr' => null, 'public_key' => 'ef'.str_repeat('0', 62)]);
    $this->actingAs($attacker);

    // No proveNostr call -> no session npub. confirmMerge must not absorb the victim.
    Livewire::test('settings.link-identity')
        ->call('confirmMerge')
        ->assertHasErrors('nostr');

    expect($victim->fresh())->not->toBeNull()
        ->and($attacker->fresh()->nostr)->toBeNull();
});

it('rejects a signed event carrying the wrong intent (phishing relay)', function () {
    $lightningUser = User::factory()->create(['nostr' => null, 'public_key' => '99'.str_repeat('0', 62)]);
    $this->actingAs($lightningUser);

    $component = Livewire::test('settings.link-identity');
    [$signed] = makeSignedMergeEvent('malicious: sign in to evil.example');

    $component->call('proveNostr', $signed)
        ->assertHasErrors('nostr')
        ->assertSet('proofReady', false);

    expect($lightningUser->fresh()->nostr)->toBeNull();
});

it('renders the full wizard page through the app layout (SEO data present)', function () {
    $this->actingAs(User::factory()->create(['nostr' => null, 'public_key' => 'aa'.str_repeat('0', 62)]));

    $this->get('/de/settings/link-identity')->assertOk();
});

it('defaults the photo to the incoming account when the current one has none', function () {
    $survivor = User::factory()->create(['nostr' => null, 'public_key' => 'cd'.str_repeat('0', 62), 'profile_photo_path' => null]);
    $this->actingAs($survivor);

    $component = Livewire::test('settings.link-identity');
    [$signed, $npub] = makeSignedMergeEvent();
    User::factory()->create(['nostr' => $npub, 'profile_photo_path' => 'profile-photos/incoming.jpg']);

    $component->call('proveNostr', $signed)
        ->assertSet('profileChoices.photo', 'loser');
});

it('applies the incoming account name and photo when picked', function () {
    $survivor = User::factory()->create(['nostr' => null, 'public_key' => 'ab'.str_repeat('0', 62), 'name' => 'RandomWallet']);
    $this->actingAs($survivor);

    $component = Livewire::test('settings.link-identity');
    [$signed, $npub] = makeSignedMergeEvent();
    User::factory()->create(['nostr' => $npub, 'name' => 'SeppTheBitcoiner', 'profile_photo_path' => 'profile-photos/sepp.jpg']);

    $component->call('proveNostr', $signed)
        ->set('profileChoices.name', 'loser')
        ->set('profileChoices.photo', 'loser')
        ->set('acknowledgedBackup', true)
        ->call('confirmMerge');

    $survivor->refresh();

    expect($survivor->name)->toBe('SeppTheBitcoiner')
        ->and($survivor->profile_photo_path)->toBe('profile-photos/sepp.jpg');
});

it('re-derives a tampered profile source server-side and keeps the survivor value', function () {
    $survivor = User::factory()->create(['nostr' => null, 'public_key' => 'ef'.str_repeat('0', 62), 'name' => 'KeepMe']);
    $this->actingAs($survivor);

    $component = Livewire::test('settings.link-identity');
    [$signed, $npub] = makeSignedMergeEvent();
    User::factory()->create(['nostr' => $npub, 'name' => 'LoserName']);

    // A tampered, non-existent source must not crash and must not wipe the name.
    $component->call('proveNostr', $signed)
        ->set('profileChoices.name', 'evil-injection')
        ->set('acknowledgedBackup', true)
        ->call('confirmMerge');

    expect($survivor->fresh()->name)->toBe('KeepMe');
});

it('shows the lightning hint when signed in via Nostr only', function () {
    $nostrUser = User::factory()->create(['nostr' => 'npub1abc'.str_repeat('0', 20), 'public_key' => null]);
    $this->actingAs($nostrUser);

    Livewire::test('settings.link-identity')->assertSet('mode', 'lightning_hint');
});

it('shows linked when both identities are present', function () {
    $both = User::factory()->create(['nostr' => 'npub1both'.str_repeat('0', 20), 'public_key' => '11'.str_repeat('0', 62)]);
    $this->actingAs($both);

    Livewire::test('settings.link-identity')->assertSet('mode', 'linked');
});
