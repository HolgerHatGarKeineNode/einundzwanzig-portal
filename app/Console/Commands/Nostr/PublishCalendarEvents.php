<?php

namespace App\Console\Commands\Nostr;

use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\NostrCalendarEventFactory;
use App\Support\NostrEventTransmitter;
use Illuminate\Console\Command;
use swentel\nostr\Key\Key;
use swentel\nostr\Sign\Sign;

/**
 * NIP-52-Gegenstueck zu {@see PublishUnpublishedItems}:
 * dasselbe Muster (ein Datensatz pro Lauf, per Cron wiederholt aufgerufen), aber
 * eigene Gating-Spalte (`nostr_coordinate` statt `nostr_status`) und eigener
 * Signierweg (swentel/nostr-php statt `noscl`), siehe Migration
 * 2026_08_29_170000_add_nostr_coordinate... fuer die Begruendung der Trennung.
 */
class PublishCalendarEvents extends Command
{
    protected $signature = 'nostr:publish-calendar {--model=}';

    protected $description = 'Publish unpublished meetups/events to Nostr as NIP-52 calendar events';

    public function __construct(private readonly NostrEventTransmitter $transmitter)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $privateKey = config('services.nostr.publisher_key');

        if (! $privateKey) {
            $this->error('NOSTR_PUBLISHER_NSEC ist nicht gesetzt.');

            return self::FAILURE;
        }

        $modelName = $this->option('model');

        $query = match ($modelName) {
            'Meetup' => Meetup::query()
                ->with('city.country')
                ->whereNull('nostr_coordinate')
                ->where('nostr_publishing_enabled', true)
                ->orderByDesc('created_at'),
            'MeetupEvent' => MeetupEvent::query()
                ->with('meetup.city.country')
                ->whereNull('nostr_coordinate')
                ->where('start', '>', now())
                ->whereHas('meetup', fn ($meetup) => $meetup->where('nostr_publishing_enabled', true))
                ->orderByDesc('created_at'),
            default => null,
        };

        if (! $query) {
            $this->error("Unsupported model: {$modelName}");

            return self::FAILURE;
        }

        $model = $query->first();

        if (! $model) {
            $this->info("No unpublished items for model: {$modelName}");

            return self::SUCCESS;
        }

        $key = new Key;
        $hexKey = str_starts_with($privateKey, 'nsec') ? $key->convertToHex($privateKey) : $privateKey;
        $pubkeyHex = $key->getPublicKey($hexKey);

        $event = match (true) {
            $model instanceof Meetup => NostrCalendarEventFactory::forMeetup($model),
            $model instanceof MeetupEvent => NostrCalendarEventFactory::forMeetupEvent($model, $pubkeyHex),
        };

        $dTag = $model instanceof Meetup
            ? NostrCalendarEventFactory::calendarDTag($model)
            : NostrCalendarEventFactory::eventDTag($model);

        $signer = new Sign;
        $signer->signEvent($event, $hexKey);

        $accepted = $this->transmitter->transmit($event, config('services.nostr.relays', []));

        if (! $accepted) {
            $this->error("Failed to publish calendar event for {$modelName} #{$model->id}");

            return self::FAILURE;
        }

        $model->nostr_coordinate = NostrCalendarEventFactory::coordinate($event->getKind(), $pubkeyHex, $dTag);
        $model->save();

        $this->info("Published calendar event for {$modelName} #{$model->id}");

        return self::SUCCESS;
    }
}
