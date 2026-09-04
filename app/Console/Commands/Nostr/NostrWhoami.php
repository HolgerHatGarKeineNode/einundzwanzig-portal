<?php

namespace App\Console\Commands\Nostr;

use App\Models\Meetup;
use App\Models\MeetupEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Schema;
use swentel\nostr\Key\Key;
use Throwable;

/**
 * Answers, on the machine that actually publishes: WHO publishes, WHERE to, and
 * WHETHER anything has been published. Read-only — it writes nothing, anywhere.
 *
 * ## Why this command exists
 *
 * Issue #49: an organiser switched Nostr publishing on and then could not find his
 * meetup on any of five relays — two of which are this portal's own defaults. From
 * the outside that failure has half a dozen possible causes (no key, other relays,
 * rejected writes, never ran) and they are indistinguishable without reading the
 * production configuration. Every one of those readings used to require shell access
 * plus knowing which config keys to look at. This is that reading, in one command.
 *
 * ## The output is designed to be pasted in public
 *
 * The answer to "who publishes and where" belongs in a GitHub reply, so every line
 * this command prints must be publishable: a public key, relay URLs, aggregate
 * counts. That is a constraint on the code, not a convention — see the private-key
 * handling below.
 *
 * ## The private key is derived from, never shown
 *
 * `NOSTR_PUBLISHER_NSEC` is read once and only its PUBLIC half leaves this class.
 * Two rules follow, and both matter more than they look:
 *
 *  1. No branch prints the key, a prefix of it, or its length. `keyFormat()` reports
 *     only the ENCODING (`nsec` vs `hex`) — both encode the same secret equally well,
 *     so the label carries no information about the value.
 *  2. A failure prints OUR message, never the library's. `Key::convertToHex()`
 *     re-throws bech32 errors and `Elliptic\EC` raises its own; today none of those
 *     messages embed the input (checked against bitwasp/bech32 1.x, whose fifteen
 *     throw sites all use fixed strings), but "today" is the wrong thing to depend on
 *     when the cost of being wrong is a signing key in a support transcript. The
 *     catch block below discards `$e->getMessage()` on purpose.
 */
#[Signature('nostr:whoami {--json : Output as JSON}')]
#[Description('Show the portal Nostr publishing identity (npub, hex pubkey) and its relays. Read-only, never prints the private key.')]
class NostrWhoami extends Command
{
    public function handle(): int
    {
        $relays = array_values((array) config('services.nostr.relays', []));
        $privateKey = config('services.nostr.publisher_key');

        if (! is_string($privateKey) || $privateKey === '') {
            // Same failure mode and wording as PublishCalendarEvents: an operator who
            // sees this line in either command is looking at the same missing value.
            return $this->report([
                'key_configured' => false,
                'key_format' => null,
                'npub' => null,
                'pubkey_hex' => null,
                'relays' => $relays,
                'error' => 'NOSTR_PUBLISHER_NSEC ist nicht gesetzt.',
            ] + $this->publishingState(), self::FAILURE);
        }

        try {
            $key = new Key;
            $hexPrivate = str_starts_with($privateKey, 'nsec') ? $key->convertToHex($privateKey) : $privateKey;
            $pubkeyHex = $key->getPublicKey($hexPrivate);
            $npub = $key->convertPublicKeyToBech32($pubkeyHex);
        } catch (Throwable) {
            // Deliberately no $e->getMessage(): see the class docblock, rule 2.
            return $this->report([
                'key_configured' => true,
                'key_format' => $this->keyFormat($privateKey),
                'npub' => null,
                'pubkey_hex' => null,
                'relays' => $relays,
                'error' => 'NOSTR_PUBLISHER_NSEC is set but could not be decoded as an nsec or hex private key.',
            ] + $this->publishingState(), self::FAILURE);
        }

        if ($npub === '' || $pubkeyHex === '') {
            return $this->report([
                'key_configured' => true,
                'key_format' => $this->keyFormat($privateKey),
                'npub' => null,
                'pubkey_hex' => null,
                'relays' => $relays,
                'error' => 'NOSTR_PUBLISHER_NSEC is set but did not yield a usable public key.',
            ] + $this->publishingState(), self::FAILURE);
        }

        return $this->report([
            'key_configured' => true,
            'key_format' => $this->keyFormat($privateKey),
            'npub' => $npub,
            'pubkey_hex' => $pubkeyHex,
            'relays' => $relays,
            'error' => null,
        ] + $this->publishingState(), self::SUCCESS);
    }

    /**
     * The encoding the key was supplied in — never any part of its value.
     */
    private function keyFormat(string $privateKey): string
    {
        return str_starts_with($privateKey, 'nsec') ? 'nsec (bech32)' : 'hex';
    }

    /**
     * Whether anything is publishing, and whether anything has been published.
     *
     * `nostr:publish-calendar` publishes nothing on its own — it is a one-record-per-run
     * command that has to be invoked. Reading the registered schedule here is the
     * difference between "the key is fine, so it must be working" and knowing that
     * nothing calls it. That was the actual cause in issue #49.
     *
     * ## Why the column check is not paranoia
     *
     * `DB_CONNECTION` defaults to `sqlite` (config/database.php and .env.example), and
     * SQLite still honours the legacy rule that a double-quoted identifier which
     * matches no column degrades to a STRING LITERAL instead of raising an error.
     * Laravel quotes identifiers with double quotes. So on a database where the
     * 2026_08_29 migrations have not run, `where "nostr_coordinate" is null` compares
     * the constant string 'nostr_coordinate' against NULL — never true. Measured on
     * this repo's own unmigrated dev database: `whereNull` returns 0 of 31 meetups and
     * `whereNotNull` returns all 31.
     *
     * The consequence is worse for the publisher than for this command:
     * PublishCalendarEvents gates on exactly that `whereNull`, so on an unmigrated
     * SQLite it finds nothing to do, prints "No unpublished items" and exits 0 —
     * indistinguishable from a healthy, fully caught-up system. Counting rows without
     * first proving the column exists would reproduce that lie here, which is the one
     * thing a diagnostic command must not do.
     *
     * @return array<string, mixed>
     */
    private function publishingState(): array
    {
        $schemaReady = Schema::hasColumn('meetups', 'nostr_coordinate')
            && Schema::hasColumn('meetups', 'nostr_publishing_enabled')
            && Schema::hasColumn('meetup_events', 'nostr_coordinate');

        if (! $schemaReady) {
            return [
                'publish_command_scheduled' => $this->isPublishCommandScheduled(),
                'schema_ready' => false,
                'meetups_opted_in' => null,
                'meetups_published' => null,
                'events_published' => null,
            ];
        }

        return [
            'publish_command_scheduled' => $this->isPublishCommandScheduled(),
            'schema_ready' => true,
            'meetups_opted_in' => Meetup::query()->where('nostr_publishing_enabled', true)->count(),
            'meetups_published' => Meetup::query()->whereNotNull('nostr_coordinate')->count(),
            'events_published' => MeetupEvent::query()->whereNotNull('nostr_coordinate')->count(),
        ];
    }

    private function isPublishCommandScheduled(): bool
    {
        foreach (app(Schedule::class)->events() as $event) {
            if (str_contains((string) $event->command, 'nostr:publish-calendar')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function report(array $data, int $exitCode): int
    {
        if ($this->option('json')) {
            $this->line((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $exitCode;
        }

        $this->table(['Field', 'Value'], [
            ['Key configured', $data['key_configured'] ? 'yes' : 'no'],
            ['Key format', $data['key_format'] ?? '—'],
            ['npub', $data['npub'] ?? '—'],
            ['Public key (hex)', $data['pubkey_hex'] ?? '—'],
            ['Relays', $data['relays'] === [] ? '—' : implode("\n", $data['relays'])],
            ['Publish command scheduled', $data['publish_command_scheduled'] ? 'yes' : 'no'],
            ['Meetups opted in', $data['meetups_opted_in'] ?? 'unknown'],
            ['Meetups published', $data['meetups_published'] ?? 'unknown'],
            ['Events published', $data['events_published'] ?? 'unknown'],
        ]);

        if ($data['error'] !== null) {
            $this->error($data['error']);
        }

        if (! $data['schema_ready']) {
            $this->error('The nostr_coordinate / nostr_publishing_enabled columns are missing — run php artisan migrate. Until then nostr:publish-calendar silently finds nothing to publish on SQLite.');
        }

        if (! $data['publish_command_scheduled']) {
            $this->warn('nostr:publish-calendar is not registered in the scheduler — nothing publishes automatically.');
        }

        return $exitCode;
    }
}
