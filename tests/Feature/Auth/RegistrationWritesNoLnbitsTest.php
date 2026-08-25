<?php

use App\Jobs\FetchNostrProfileJob;
use App\Models\User;
use Elliptic\EC;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use swentel\nostr\Event\Event as NostrEvent;
use swentel\nostr\Key\Key as NostrKey;
use swentel\nostr\Sign\Sign as NostrSign;

/**
 * Beide Anlagepfade legen ein Konto an, ohne ein `lnbits`-Objekt zu schreiben.
 *
 * Das ist der Nachweis fuer den ERSTEN der beiden Lightning-Commits. `lnbits` wurde bei
 * JEDER Registrierung geschrieben, ueber LNURL wie ueber Nostr, immer als dasselbe
 * Drei-Null-Objekt. Faellt die Spalte, bevor diese zwei Zeilen weg sind, scheitert jede
 * Neuanmeldung ueber BEIDE Wege — nicht einer von beiden. Deshalb misst dieser Test auch
 * beide, in einer Datei.
 *
 * Er ist so geschrieben, dass er vor UND nach dem Spalten-Drop gilt: solange die Spalte
 * steht, darf dort kein Wallet-Objekt liegen; ist sie fort, ist nichts zu lesen.
 * Gelesen wird ueber `DB::table('users')`, nicht ueber das Model — nur so ist sichtbar,
 * was wirklich in der Spalte steht.
 */
function persistedLnbitsPayload(int $userId): ?string
{
    if (! Schema::hasColumn('users', 'lnbits')) {
        return null;
    }

    return DB::table('users')->where('id', $userId)->value('lnbits');
}

/**
 * Leer heisst hier: NULL oder das leere JSON, das CipherSweet fuer ein Feld ohne Wert
 * hinterlegt. Was es NICHT heissen darf, ist das Drei-Null-Objekt aus `read_key`, `url`
 * und `wallet_id` — genau das haben die beiden Anlagepfade bis P6 geschrieben.
 */
function expectNoLnbitsWallet(int $userId): void
{
    $raw = persistedLnbitsPayload($userId);

    expect($raw === null || $raw === '[]' || $raw === '{}')
        ->toBeTrue('lnbits wurde geschrieben: '.var_export($raw, true));
}

/**
 * @return array{0: array<string, mixed>, 1: string}
 */
function signedNostrLoginEventForLnbitsProbe(): array
{
    $keyGen = new NostrKey;
    $privateKey = $keyGen->generatePrivateKey();
    $publicKey = $keyGen->getPublicKey($privateKey);

    $event = new NostrEvent;
    $event->setKind(22242)
        ->setCreatedAt(time())
        ->setContent('')
        ->setTags([['challenge', (string) Session::get('nostr_login_challenge')]]);

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

it('creates an account through LNURL without writing an lnbits wallet', function () {
    $keyPair = (new EC('secp256k1'))->genKeyPair();

    $k1 = bin2hex(random_bytes(32));
    // Komprimiert (66 Hex-Zeichen) — die Validierung des Callbacks laesst 64..66 zu.
    $publicKey = $keyPair->getPublic(true, 'hex');
    $signature = $keyPair->sign($k1)->toDER('hex');

    expect(User::query()->count())->toBe(0);

    $this->getJson('/api/lnurl-auth-callback?'.http_build_query([
        'k1' => $k1,
        'sig' => $signature,
        'key' => $publicKey,
    ]))->assertSuccessful()->assertJson(['status' => 'OK']);

    $user = User::query()->sole();

    expect($user->email)->toEndWith('@portal.einundzwanzig.space')
        ->and((bool) $user->is_lecturer)->toBeTrue();

    expectNoLnbitsWallet($user->id);

    // Der Login ist wirklich zustande gekommen, nicht nur das Konto.
    $this->assertDatabaseHas('login_keys', ['k1' => $k1, 'user_id' => $user->id]);
});

it('creates an account through Nostr without writing an lnbits wallet', function () {
    Queue::fake();

    $component = Livewire::test('auth.login');
    [$signedEvent, $npub] = signedNostrLoginEventForLnbitsProbe();

    $component->dispatch('nostrLoggedIn', signedEvent: $signedEvent)->assertRedirect();

    $user = User::query()->where('nostr', $npub)->sole();

    expect($user->email)->toEndWith('@portal.einundzwanzig.space')
        ->and((bool) $user->is_lecturer)->toBeTrue()
        ->and(auth()->id())->toBe($user->id);

    expectNoLnbitsWallet($user->id);

    Queue::assertPushed(FetchNostrProfileJob::class);
});
