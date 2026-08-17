<?php

namespace App\Console\Commands;

use App\Models\BitcoinEvent;
use App\Models\CourseEvent;
use App\Models\Venue;
use App\Services\Osm\NominatimClient;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Tries to resolve every Venue to an OpenStreetMap place and writes the result onto the
 * events that use it (issue #4, point 6).
 *
 * This is a spike with a stated exit condition, not a migration that must succeed. A
 * venue knows only a name, a street and a city — no house-number guarantee, no
 * coordinates. A wrong match sends visitors to the wrong address, which is worse than
 * an empty field: an empty field is visibly missing, a wrong one is silently trusted.
 *
 * So every match is classified, and the command reports a confidence rate. Below the
 * threshold the honest answer is to drop the attempt and ask organisers to enter the
 * location themselves.
 *
 * Rate limiting is not optional here: Nominatim's policy allows bulk scripts 4 requests
 * per MINUTE. A hundred venues therefore take about 25 minutes. Point NOMINATIM_URL at
 * a self-hosted instance to go faster.
 */
class MatchVenuesToOsm extends Command
{
    protected $signature = 'venues:match-osm
                            {--dry-run : Classify and report without writing anything}
                            {--threshold=70 : Percent of confident matches below which the attempt is called off}
                            {--fast : Ignore the bulk rate limit — only legitimate against a self-hosted instance}
                            {--limit= : Only process the first N venues}';

    protected $description = 'Match venues to OpenStreetMap places and copy the result onto their events';

    /**
     * Names that describe an arrangement rather than a place. Geocoding them is
     * meaningless and would only produce confident-looking nonsense.
     *
     * @var array<int, string>
     */
    private array $nonPlaces = ['tba', 'tbd', 'toandounce', 'online', 'virtuell', 'virtual', 'wird bekannt gegeben'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $threshold = (int) $this->option('threshold');

        $client = $this->option('fast')
            ? new NominatimClient(minIntervalMs: 0)
            : NominatimClient::forBulk();

        $venues = Venue::query()->with('city.country')->orderBy('id');

        if ($limit = $this->option('limit')) {
            $venues->limit((int) $limit);
        }

        $venues = $venues->get();

        if ($venues->isEmpty()) {
            $this->info('Keine Venues vorhanden — nichts zu tun.');

            return self::SUCCESS;
        }

        if (! $dryRun && ! $this->option('fast')) {
            $minutes = (int) ceil($venues->count() / 4);
            $this->warn("Rate-Limit: {$venues->count()} Venues brauchen etwa {$minutes} Minuten.");
        }

        $results = collect();
        $bar = $this->output->createProgressBar($venues->count());
        $bar->start();

        foreach ($venues as $venue) {
            $results->push($this->classify($venue, $client));
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->report($results, $threshold, $dryRun);

        $confident = $results->where('verdict', 'confident');
        $rate = $this->confidenceRate($results);

        if ($rate === null) {
            $this->info('Keine geocodierbaren Venues — alle beschreiben eine Absprache statt eines Ortes.');

            return self::SUCCESS;
        }

        if ($rate < $threshold) {
            $this->error("Trefferquote {$rate}% liegt unter der Schwelle von {$threshold}%.");
            $this->line('Nichts geschrieben. Empfehlung: Zuordnung verwerfen und die Ortsangabe von den');
            $this->line('Organisatoren neu erfassen lassen — ein falscher Ort ist schlimmer als ein leerer.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info("Trefferquote {$rate}% — über der Schwelle. Ohne --dry-run würden ".
                $confident->count().' Venues übernommen.');

            return self::SUCCESS;
        }

        $written = $this->write($confident);

        $this->info("Trefferquote {$rate}%. {$written} Event(s) mit OSM-Ort versehen.");

        return self::SUCCESS;
    }

    /**
     * Look one venue up and judge how much the result can be trusted.
     *
     * @return array<string, mixed>
     */
    private function classify(Venue $venue, NominatimClient $client): array
    {
        $base = ['venue' => $venue, 'match' => null];

        if ($this->isNonPlace($venue->name)) {
            return [...$base, 'verdict' => 'skipped', 'reason' => 'kein echter Ort'];
        }

        $hits = $client->search(
            $this->queryFor($venue),
            $venue->city?->country?->code,
        );

        if ($hits->isEmpty()) {
            return [...$base, 'verdict' => 'none', 'reason' => 'kein Treffer'];
        }

        $best = $hits->first();
        $similarity = $this->similarity($venue->name, (string) ($best['osm_name'] ?? ''));

        /*
         * Two conditions, both necessary. A single result is not by itself convincing —
         * Nominatim happily returns one unrelated place for a misspelt query. And a good
         * name match among several candidates is only trustworthy if the runner-up is
         * clearly worse, otherwise we are picking one of two plausible addresses at random.
         */
        $runnerUp = $hits->skip(1)->first();
        $runnerUpSimilarity = $runnerUp
            ? $this->similarity($venue->name, (string) ($runnerUp['osm_name'] ?? ''))
            : 0.0;

        $clearWinner = $similarity - $runnerUpSimilarity >= 0.25 || $hits->count() === 1;

        if ($similarity >= 0.6 && $clearWinner) {
            return [...$base, 'match' => $best, 'verdict' => 'confident', 'similarity' => $similarity];
        }

        return [
            ...$base,
            'match' => $best,
            'verdict' => 'uncertain',
            'similarity' => $similarity,
            'reason' => $similarity < 0.6 ? 'Name weicht ab' : 'zweiter Treffer fast gleich gut',
        ];
    }

    /**
     * Share of confident matches among the venues that could be matched at all.
     *
     * Skipped entries are excluded on purpose. "TBA" is not a failed match, it is not a
     * candidate — counting it as a miss would let a handful of placeholder rows drag a
     * perfectly good run below the threshold and call off a migration that worked.
     *
     * Returns null when nothing was geocodable, which is a distinct outcome from 0 %.
     *
     * @param  Collection<int, array<string, mixed>>  $results
     */
    private function confidenceRate(Collection $results): ?int
    {
        $matchable = $results->where('verdict', '!=', 'skipped');

        if ($matchable->isEmpty()) {
            return null;
        }

        return (int) round($matchable->where('verdict', 'confident')->count() / $matchable->count() * 100);
    }

    private function queryFor(Venue $venue): string
    {
        return collect([$venue->name, $venue->street, $venue->city?->name])
            ->filter()
            ->implode(', ');
    }

    private function isNonPlace(?string $name): bool
    {
        $needle = mb_strtolower(trim((string) $name));

        return $needle === '' || in_array($needle, $this->nonPlaces, true);
    }

    /**
     * 0.0 to 1.0. Case- and whitespace-insensitive.
     */
    private function similarity(string $a, string $b): float
    {
        $a = mb_strtolower(trim($a));
        $b = mb_strtolower(trim($b));

        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 1.0;
        }

        similar_text($a, $b, $percent);

        return round($percent / 100, 3);
    }

    /**
     * Copy a confident match onto every event that uses the venue.
     *
     * @param  Collection<int, array<string, mixed>>  $confident
     */
    private function write(Collection $confident): int
    {
        $written = 0;

        foreach ($confident as $row) {
            $fields = [
                'osm_type' => $row['match']['osm_type'],
                'osm_id' => $row['match']['osm_id'],
                'osm_name' => $row['match']['osm_name'],
                'osm_address' => $row['match']['osm_address'],
                'osm_lat' => $row['match']['osm_lat'],
                'osm_lon' => $row['match']['osm_lon'],
            ];

            $written += CourseEvent::query()->where('venue_id', $row['venue']->id)->update($fields);
            $written += BitcoinEvent::query()->where('venue_id', $row['venue']->id)->update($fields);
        }

        return $written;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $results
     */
    private function report(Collection $results, int $threshold, bool $dryRun): void
    {
        $byVerdict = $results->countBy('verdict');

        $this->table(
            ['Urteil', 'Anzahl'],
            collect(['confident', 'uncertain', 'none', 'skipped'])
                ->map(fn (string $v): array => [$v, $byVerdict->get($v, 0)])
                ->all()
        );

        foreach ($results->whereIn('verdict', ['uncertain', 'none']) as $row) {
            $this->line(sprintf(
                '  %-30s %s%s',
                mb_substr((string) $row['venue']->name, 0, 30),
                $row['reason'] ?? '',
                isset($row['match']['osm_name']) ? ' → '.$row['match']['osm_name'] : ''
            ));
        }

        $path = base_path('docs/plans/2026-08-17T1505-events-modell-tags-osm/osm-match-report.md');

        if (is_dir(dirname($path))) {
            file_put_contents($path, $this->markdown($results, $threshold, $dryRun));
            $this->newLine();
            $this->line("Report: {$path}");
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $results
     */
    private function markdown(Collection $results, int $threshold, bool $dryRun): string
    {
        $byVerdict = $results->countBy('verdict');
        $rate = $this->confidenceRate($results);

        $lines = [
            '# OSM-Matching — Report',
            '',
            $dryRun ? '**Trockenlauf**, nichts geschrieben.' : '**Echter Lauf.**',
            '',
            "- Venues geprüft: **{$results->count()}**",
            '- Eindeutig: **'.$byVerdict->get('confident', 0).'**',
            '- Unsicher: '.$byVerdict->get('uncertain', 0),
            '- Kein Treffer: '.$byVerdict->get('none', 0),
            '- Übersprungen (kein echter Ort): '.$byVerdict->get('skipped', 0),
            '',
            $rate === null
                ? '**Keine geocodierbaren Venues.**'
                : "**Trefferquote: {$rate} %** (bezogen auf die matchbaren Venues, Übersprungene zählen nicht mit) — Schwelle {$threshold} %.",
            '',
            $rate !== null && $rate < $threshold
                ? '> Unter der Schwelle. Empfehlung: Zuordnung verwerfen, Ortsangabe neu erfassen lassen.'
                : '> Über der Schwelle.',
            '',
            '## Nicht übernommen',
            '',
            '| Venue | Urteil | Grund | bester Treffer |',
            '|---|---|---|---|',
        ];

        foreach ($results->whereIn('verdict', ['uncertain', 'none', 'skipped']) as $row) {
            $lines[] = sprintf(
                '| %s | %s | %s | %s |',
                $row['venue']->name,
                $row['verdict'],
                $row['reason'] ?? '',
                $row['match']['osm_name'] ?? '—'
            );
        }

        return implode("\n", $lines)."\n";
    }
}
