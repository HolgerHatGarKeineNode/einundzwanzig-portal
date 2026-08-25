<?php

use App\Models\Meetup;
use Carbon\CarbonInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Traegt `recurrence_group` auf Bestandsserien nach.
 *
 * DAS PROBLEM: bis P5 hat kein Schreibpfad die `recurrence_*`-Spalten gefuellt. Eine
 * Bestandsserie ist deshalb nicht an ihrer Regel zu erkennen — nur an den Spuren, die
 * ihre Anlage hinterlassen hat. Der Serien-Loop im Livewire-Editor legt alle Vorkommen
 * in EINEM Request an und kopiert dabei Titel, Ort, Beschreibung, Link und den
 * Ersteller unveraendert auf jedes.
 *
 * DER FINGERABDRUCK, in dieser Reihenfolge:
 *  1. gleiche `meetup_id`, `created_by`, `title`, `location`, `description`, `link`;
 *  2. innerhalb dieser Menge: Termine, deren `created_at` sich kettenweise um hoechstens
 *     {@see self::BATCH_WINDOW_SECONDS} unterscheiden — das ist der eine Request;
 *  3. mindestens zwei Vorkommen;
 *  4. die Abstaende zwischen aufeinanderfolgenden Startzeitpunkten muessen EIN Muster
 *     ergeben: entweder alle gleich (taeglich, woechentlich, jedes Intervall) oder alle
 *     im Monats- (28–31 Tage) bzw. Jahresfenster (365–366 Tage).
 *
 * WARUM PUNKT 4 TROTZ PUNKT 2: der Kollisionsfall aus dem Plan ist "zwei unabhaengige
 * Serien desselben Meetups mit zufaellig gleicher Konfiguration zu verschiedenen
 * Zeiten". Punkt 2 trennt die beiden bereits, weil sie in zwei Requests entstanden.
 * Punkt 4 faengt den anderen Fall: ein Organisator, der in einem Rutsch drei
 * unregelmaessige Einzeltermine mit identischem Text anlegt. Das ist keine Serie, und im
 * Zweifel wird nicht gruppiert — eine falsche Verschmelzung liesse sich spaeter nicht
 * mehr von einer echten Serie unterscheiden.
 *
 * KEIN RUECKWIRKENDES `recurrence_*`: die Migration schreibt ausschliesslich
 * `recurrence_group`. Ein erfundenes `recurrence_end_date` wuerde ueber
 * {@see Meetup::recalculateActivity()} Meetups reihenweise auf `is_active`
 * kippen und je Meetup eine oeffentliche Aenderungsmeldung ausloesen.
 *
 * GESCHRIEBEN WIRD PER QUERY BUILDER, nicht per Eloquent: sonst feuerte der
 * ApiChangeObserver fuer jeden beruehrten Termin eine `api_changes`-Zeile und einen
 * Reverb-Broadcast. Das Nachtragen eines internen Schluessels ist keine inhaltliche
 * Aenderung; aus demselben Grund bleibt `updated_at` unangetastet.
 */
return new class extends Migration
{
    /**
     * Wie weit zwei `created_at` auseinanderliegen duerfen, um noch als derselbe
     * Anlagevorgang zu gelten. 100 Vorkommen mit je einem INSERT plus Tag-Sync brauchen
     * auf einem langsamen Server durchaus Sekunden; zwei Minuten sind grosszuegig genug
     * und immer noch weit von "ein anderer Tag" entfernt.
     */
    private const BATCH_WINDOW_SECONDS = 120;

    public function up(): void
    {
        if (! Schema::hasColumn('meetup_events', 'recurrence_group')) {
            return;
        }

        $grouped = 0;
        $groups = 0;
        $left = 0;

        $candidates = DB::table('meetup_events')
            ->whereNull('recurrence_group')
            ->orderBy('meetup_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'meetup_id', 'created_by', 'title', 'location', 'description', 'link', 'start', 'created_at']);

        foreach ($candidates->groupBy(fn (object $event): string => $this->fingerprint($event)) as $sameConfiguration) {
            foreach ($this->splitIntoBatches($sameConfiguration) as $batch) {
                if ($batch->count() < 2 || ! $this->isRegularSeries($batch)) {
                    $left += $batch->count();

                    continue;
                }

                DB::table('meetup_events')
                    ->whereIn('id', $batch->pluck('id')->all())
                    ->update(['recurrence_group' => (string) Str::uuid()]);

                $groups++;
                $grouped += $batch->count();
            }
        }

        info('group_existing_meetup_event_series', [
            'geprueft' => $candidates->count(),
            'gruppiert' => $grouped,
            'serien' => $groups,
            'bewusst_ungruppiert' => $left,
        ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('meetup_events', 'recurrence_group')) {
            DB::table('meetup_events')->whereNotNull('recurrence_group')->update(['recurrence_group' => null]);
        }
    }

    private function fingerprint(object $event): string
    {
        return implode("\0", [
            (string) $event->meetup_id,
            (string) ($event->created_by ?? ''),
            (string) ($event->title ?? ''),
            (string) ($event->location ?? ''),
            (string) ($event->description ?? ''),
            (string) ($event->link ?? ''),
        ]);
    }

    /**
     * Zerlegt gleich konfigurierte Termine in die Anlagevorgaenge, aus denen sie stammen.
     *
     * @param  Collection<int, object>  $events
     * @return Collection<int, Collection<int, object>>
     */
    private function splitIntoBatches(Collection $events): Collection
    {
        $batches = collect();
        $current = collect();
        $previous = null;

        foreach ($events->sortBy(fn (object $event): string => (string) $event->created_at)->values() as $event) {
            $createdAt = $this->parse($event->created_at);

            if ($previous !== null && abs($createdAt->diffInSeconds($previous)) > self::BATCH_WINDOW_SECONDS) {
                $batches->push($current);
                $current = collect();
            }

            $current->push($event);
            $previous = $createdAt;
        }

        if ($current->isNotEmpty()) {
            $batches->push($current);
        }

        return $batches;
    }

    /**
     * Ergeben die Startzeitpunkte genau ein Wiederholungsmuster?
     *
     * @param  Collection<int, object>  $batch
     */
    private function isRegularSeries(Collection $batch): bool
    {
        $starts = $batch
            ->map(fn (object $event): CarbonInterface => $this->parse($event->start))
            ->sortBy(fn (CarbonInterface $date): int => $date->getTimestamp())
            ->values();

        $gaps = [];

        for ($i = 1; $i < $starts->count(); $i++) {
            $gaps[] = (int) round(abs($starts[$i]->diffInSeconds($starts[$i - 1])) / 86400);
        }

        if ($gaps === [] || in_array(0, $gaps, true)) {
            return false;
        }

        $unique = array_values(array_unique($gaps));

        // Fester Abstand: taeglich, woechentlich, 14-taegig, was auch immer.
        if (count($unique) === 1) {
            return true;
        }

        // Monats- und Jahresmuster haben von Natur aus ungleiche Tagesabstaende.
        $within = fn (int $low, int $high): bool => collect($gaps)->every(fn (int $gap): bool => $gap >= $low && $gap <= $high);

        return $within(28, 31) || $within(365, 366);
    }

    private function parse(mixed $value): CarbonInterface
    {
        return $value instanceof CarbonInterface ? $value : Carbon::parse((string) $value);
    }
};
