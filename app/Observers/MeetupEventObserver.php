<?php

namespace App\Observers;

use App\Models\Meetup;
use App\Models\MeetupEvent;
use Closure;

class MeetupEventObserver
{
    /**
     * Sammelbetrieb an? Siehe {@see self::batched()}.
     */
    private static bool $batching = false;

    /**
     * Meetup-IDs, deren Aktivitaet nach dem Block genau EINMAL neu zu berechnen ist.
     *
     * @var array<int, int>
     */
    private static array $pending = [];

    public function saved(MeetupEvent $meetupEvent): void
    {
        $this->recalculate($meetupEvent);
    }

    public function deleted(MeetupEvent $meetupEvent): void
    {
        $this->recalculate($meetupEvent);
    }

    /**
     * Fuehrt einen Block aus, in dem jedes betroffene Meetup nur EINEN Nachlauf bekommt.
     *
     * Ohne das kostet eine Serienanlage ueber 40 Termine 40 Neuberechnungen desselben
     * Meetups — je zwei Aggregat-Queries plus ein `saveQuietly()`. Teurer als die
     * Queries ist die Nebenwirkung: {@see Meetup::recalculateActivity()} meldet jeden
     * Wechsel von `is_active`/`last_event_at` von Hand an den ChangeRecorder. Bei einer
     * Serie in der Vergangenheit waechst `last_event_at` mit JEDEM eingefuegten Termin,
     * also entsteht pro Termin eine `api_changes`-Zeile und ein Reverb-Broadcast. Ein
     * Konsument sieht dann 40 Meldungen ueber einen Vorgang, der genau eine Aenderung
     * am Meetup ist.
     *
     * `finally` ist Pflicht: wirft der Block, muss der Nachlauf trotzdem laufen — sonst
     * bliebe ein Meetup mit halb angelegter Serie auf einem veralteten `is_active`
     * stehen, ohne Fehler und ohne dass es jemandem auffiele. Verschachtelung ist
     * erlaubt; nur der aeusserste Block leert die Liste.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function batched(Closure $callback): mixed
    {
        if (self::$batching) {
            return $callback();
        }

        self::$batching = true;

        try {
            return $callback();
        } finally {
            self::$batching = false;

            $pending = self::$pending;
            self::$pending = [];

            foreach (Meetup::query()->whereIn('id', $pending)->get() as $meetup) {
                $meetup->recalculateActivity();
            }
        }
    }

    private function recalculate(MeetupEvent $meetupEvent): void
    {
        if (self::$batching) {
            if ($meetupEvent->meetup_id !== null) {
                self::$pending[(int) $meetupEvent->meetup_id] = (int) $meetupEvent->meetup_id;
            }

            return;
        }

        $meetupEvent->meetup?->recalculateActivity();
    }
}
