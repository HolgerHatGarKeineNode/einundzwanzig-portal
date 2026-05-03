<?php

use App\Jobs\FetchNostrProfileJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use swentel\nostr\Event\Event as NostrEvent;
use swentel\nostr\Key\Key as NostrKey;
use swentel\nostr\Sign\Sign as NostrSign;

/**
 * Build a NIP-42-style signed login event using the challenge that the
 * login component placed in the session during mount(), and return
 * [signedEventArray, npubBech32].
 *
 * @return array{0: array<string, mixed>, 1: string}
 */
function makeSignedNostrLoginEvent(): array
{
    $keyGen = new NostrKey;
    $privateKey = $keyGen->generatePrivateKey();
    $publicKey = $keyGen->getPublicKey($privateKey);

    $challenge = Session::get('nostr_login_challenge');

    $event = new NostrEvent;
    $event->setKind(22242)
        ->setCreatedAt(time())
        ->setContent('')
        ->setTags([['challenge', (string) $challenge]]);

    (new NostrSign)->signEvent($event, $privateKey);

    $signed = [
        'id' => $event->getId(),
        'pubkey' => $event->getPublicKey(),
        'created_at' => $event->getCreatedAt(),
        'kind' => $event->getKind(),
        'tags' => $event->getTags(),
        'content' => $event->getContent(),
        'sig' => $event->getSignature(),
    ];

    $npub = $keyGen->convertPublicKeyToBech32($publicKey);

    return [$signed, $npub];
}

it('creates a new user and dispatches FetchNostrProfileJob when an unknown pubkey logs in', function () {
    Queue::fake();

    $component = Livewire::test('auth.login');
    [$signedEvent, $npub] = makeSignedNostrLoginEvent();

    $component
        ->dispatch('nostrLoggedIn', signedEvent: $signedEvent)
        ->assertRedirect();

    $user = User::query()->where('nostr', $npub)->first();
    expect($user)->not->toBeNull()
        ->and((bool) $user->is_lecturer)->toBeTrue()
        ->and($user->email)->toEndWith('@portal.einundzwanzig.space');

    Queue::assertPushed(FetchNostrProfileJob::class);
    expect(auth()->id())->toBe($user->id);
});

it('logs in an existing user without creating a duplicate when their pubkey is already known', function () {
    Queue::fake();

    $component = Livewire::test('auth.login');
    [$signedEvent, $npub] = makeSignedNostrLoginEvent();

    $existing = User::factory()->create(['nostr' => $npub]);

    $component
        ->dispatch('nostrLoggedIn', signedEvent: $signedEvent)
        ->assertRedirect();

    expect(User::query()->where('nostr', $npub)->count())->toBe(1);
    expect(auth()->id())->toBe($existing->id);
    Queue::assertPushed(FetchNostrProfileJob::class);
});
