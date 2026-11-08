<?php

use App\Models\City;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\MeetupEventNostrRsvp;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\Fixtures\NostrTestEvents;

/*
|--------------------------------------------------------------------------
| nostr:ingest-rsvps against real relays on localhost (plan risk R11)
|--------------------------------------------------------------------------
|
| Everything else about the ingest is tested with a scripted reader. This file is the
| one place where swentel/nostr-php talks to a real relay, because the verdict
| "complete or unknown" depends on what swentel does with real frames — and that was
| measured, not guessed, before these tests were written:
|
| 1. `nak serve` (khatru, in memory, no AUTH): swentel returns every EVENT and the EOSE
|    of its own subscription. The RSVP is counted.
| 2. zooid demands NIP-42 AUTH for EVERY read, whatever `public_read` says
|    (zooid/instance.go, OnRequest: `if !ok { return true, "auth-required: …" }`).
|    swentel then authenticates with its constant key 0x…01 and keeps exactly one
|    further frame — measured with two stored RSVPs: AUTH, AUTH, CLOSED(auth-required),
|    OK, EVENT, and no EOSE. The ingest must call that UNKNOWN: no row, no cursor move.
|    A positive round trip through zooid is therefore not possible with swentel as the
|    client; the relay does hold the event, which the `nak req --auth` requery proves.
|
| Both relays are started on a free port for the duration of one test and killed after
| it. The binaries are looked up, never installed: `nak` on PATH or in ~/go/bin, zooid
| at ZOOID_BINARY or in the sibling checkout `../zooid/bin/zooid` (built by the group
| repo's E2E setup with `CGO_ENABLED=1 go build -o bin/zooid cmd/relay/main.go`). A
| missing binary SKIPS the test with the reason — it is reported, not silently green.
*/

function relayIntegrationBinary(string $name): ?string
{
    if ($name === 'zooid') {
        $path = getenv('ZOOID_BINARY') ?: base_path('../zooid/bin/zooid');

        return is_executable($path) ? $path : null;
    }

    return getenv('NAK_BINARY') ?: (new ExecutableFinder)->find('nak', null, [getenv('HOME').'/go/bin']);
}

function relayIntegrationFreePort(): int
{
    $server = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
    fclose($server);

    return $port;
}

function relayIntegrationWaitForPort(int $port, Process $process): void
{
    for ($attempt = 0; $attempt < 80; $attempt++) {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);

        if ($socket !== false) {
            fclose($socket);

            return;
        }

        if (! $process->isRunning()) {
            throw new RuntimeException('Relay exited early: '.$process->getErrorOutput().$process->getOutput());
        }

        usleep(100_000);
    }

    throw new RuntimeException("Relay did not open port {$port}.");
}

/**
 * Runs `nak` with a closed stdin unless input is given: without it `nak req` waits on
 * stdin and returns nothing, which reads exactly like an empty relay.
 *
 * @param  list<string>  $arguments
 */
function relayIntegrationNak(string $nak, array $arguments, string $input = ''): string
{
    $process = new Process([$nak, ...$arguments]);
    $process->setInput($input);
    $process->setTimeout(30);
    $process->run();

    return $process->getOutput().$process->getErrorOutput();
}

function relayIntegrationEvent(): MeetupEvent
{
    $event = MeetupEvent::factory()->create([
        'meetup_id' => Meetup::factory()->create([
            'city_id' => City::factory()->create()->id,
            'rsvp_enabled' => true,
            'attendees_public' => true,
        ])->id,
        'start' => now()->addDays(2),
        'attendees' => [],
        'might_attendees' => [],
    ]);

    $event->forceFill(['nostr_coordinate' => NostrTestEvents::coordinate($event->id)])->save();

    return $event;
}

beforeEach(function () {
    config()->set('services.nostr.publisher_key', NostrTestEvents::PUBLISHER_SECRET);
    $this->relayProcess = null;
    $this->relayDirectory = null;
});

afterEach(function () {
    $this->relayProcess?->stop(2);

    if ($this->relayDirectory !== null) {
        Illuminate\Support\Facades\File::deleteDirectory($this->relayDirectory);
    }
});

it('counts an RSVP that a relay without AUTH returns to swentel', function () {
    $nak = relayIntegrationBinary('nak') ?? $this->markTestSkipped('nak not found (PATH, ~/go/bin or NAK_BINARY).');
    $port = relayIntegrationFreePort();
    $relayUrl = "ws://127.0.0.1:{$port}";

    $this->relayProcess = new Process([$nak, 'serve', '--hostname', '127.0.0.1', '--port', (string) $port]);
    $this->relayProcess->setInput('');
    $this->relayProcess->start();
    relayIntegrationWaitForPort($port, $this->relayProcess);

    $event = relayIntegrationEvent();
    $rsvp = NostrTestEvents::rsvp($event->id, 'accepted', now()->subMinute()->timestamp);

    relayIntegrationNak($nak, ['event', $relayUrl], json_encode($rsvp));
    // nak prints the event and exits 0 even when a relay rejects it — only a requery tells.
    expect(relayIntegrationNak($nak, ['req', '-k', '31925', $relayUrl]))->toContain($rsvp['id']);

    config()->set('services.nostr.relays', [$relayUrl]);

    $this->artisan('nostr:ingest-rsvps')->assertSuccessful();

    expect(MeetupEventNostrRsvp::query()->sole())
        ->meetup_event_id->toBe($event->id)
        ->nostr_event_id->toBe($rsvp['id'])
        ->and($event->fresh()->nostrAttendeesCount())->toBe(1)
        ->and(Cache::get('nostr:ingest-rsvps:cursor:'.sha1($relayUrl)))->toHaveKey('last_ok');
});

it('treats a zooid that demands AUTH as unknown: no count, no cursor', function () {
    $nak = relayIntegrationBinary('nak') ?? $this->markTestSkipped('nak not found (PATH, ~/go/bin or NAK_BINARY).');
    $zooid = relayIntegrationBinary('zooid') ?? $this->markTestSkipped('zooid binary not found (ZOOID_BINARY or ../zooid/bin/zooid).');
    $port = relayIntegrationFreePort();
    $relayUrl = "ws://localhost:{$port}";

    $directory = $this->relayDirectory = sys_get_temp_dir().'/portal-rsvp-zooid-'.bin2hex(random_bytes(4));
    mkdir("{$directory}/config", 0700, true);
    mkdir("{$directory}/data", 0700, true);
    $relaySecret = bin2hex(random_bytes(32));
    file_put_contents("{$directory}/config/rsvp.toml", implode("\n", [
        "host = \"localhost:{$port}\"",
        'schema = "rsvp_ingest_test"',
        "secret = \"{$relaySecret}\"",
        '[info]',
        'name = "portal rsvp ingest test"',
        'pubkey = "'.NostrTestEvents::pubkey($relaySecret).'"',
        '[policy]',
        'public_read = true',
        'public_write = true',
        'public_join = true',
        'strip_signatures = false',
        '',
    ]));

    $this->relayProcess = new Process([$zooid], dirname($zooid, 2), ['PORT' => (string) $port, 'DATA' => "{$directory}/data", 'CONFIG' => "{$directory}/config"]);
    $this->relayProcess->setInput('');
    $this->relayProcess->start();
    relayIntegrationWaitForPort($port, $this->relayProcess);

    $event = relayIntegrationEvent();
    $rsvp = NostrTestEvents::rsvp($event->id, 'accepted', now()->subMinute()->timestamp);
    $second = NostrTestEvents::rsvp($event->id, 'tentative', now()->subMinute()->timestamp, NostrTestEvents::OTHER_ATTENDEE_SECRET);

    relayIntegrationNak($nak, ['event', '--auth', '--sec', NostrTestEvents::ATTENDEE_SECRET, $relayUrl], json_encode($rsvp));
    relayIntegrationNak($nak, ['event', '--auth', '--sec', NostrTestEvents::OTHER_ATTENDEE_SECRET, $relayUrl], json_encode($second));

    // The relay holds both — so an empty result below is the client's blindness, not an empty relay.
    $requery = relayIntegrationNak($nak, ['req', '--auth', '--sec', NostrTestEvents::ATTENDEE_SECRET, '-k', '31925', $relayUrl]);
    expect($requery)->toContain($rsvp['id'])->toContain($second['id']);

    config()->set('services.nostr.relays', [$relayUrl]);

    $this->artisan('nostr:ingest-rsvps')->assertFailed();

    expect(MeetupEventNostrRsvp::query()->count())->toBe(0)
        ->and(Cache::get('nostr:ingest-rsvps:cursor:'.sha1($relayUrl)))->toBeNull();
});
