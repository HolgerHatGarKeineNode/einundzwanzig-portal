<?php

declare(strict_types=1);

namespace App\Console\Commands\Database;

use App\Actions\MergeMeetups;
use App\Models\Meetup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Fuehrt die am 26.08.2026 entschiedene Bereinigung der Meetup-Duplikate aus.
 *
 * Der Befund: auf `meetups.name` liegt seit der ersten Migration ein `unique`,
 * das ein einzelnes Leerzeichen und Grossschreibung aushebeln — so entstanden
 * 'Einundzwanzig Ulm' neben 'Einundzwanzig Ulm ' und 'EINUNDZWANZIG Mannheim'
 * neben 'Einundzwanzig Mannheim'. Die Zuordnung Ueberlebender/Verlierer ist
 * KEINE Heuristik, sondern die Entscheidung des Betreibers, hier fest verdrahtet.
 *
 * Deshalb auch der Namensabgleich vor jedem Schritt: eine id allein ist kein
 * sicherer Bezug, wenn zwischen Entscheidung und Lauf jemand die Daten anfasst.
 * Passt der Name nicht, bricht der Lauf ab, statt das falsche Meetup zu loeschen.
 *
 * Dry-Run ist der Default. Ohne --force wird nichts geschrieben.
 */
#[Signature('meetups:cleanup-duplicates {--force : Wirklich ausfuehren statt nur zu zeigen}')]
#[Description('Fuehrt die entschiedenen Meetup-Duplikate zusammen und loescht den Testeintrag (Dry-Run ohne --force)')]
class CleanupMeetupDuplicates extends Command
{
    /**
     * Zusammenfuehrungen: Ueberlebender <- Verlierer, je mit dem Namen, den beide
     * zum Zeitpunkt der Entscheidung trugen (getrimmt verglichen).
     *
     * @var list<array{survivor: int, survivor_name: string, loser: int, loser_name: string, why: string}>
     */
    private const MERGES = [
        ['survivor' => 45, 'survivor_name' => 'Einundzwanzig Mannheim', 'loser' => 162, 'loser_name' => 'EINUNDZWANZIG Mannheim',
            'why' => '39 Termine und ein naechster Termin gegen einen Eintrag ohne jeden Termin'],
        ['survivor' => 232, 'survivor_name' => 'Einundzwanzig Ulm', 'loser' => 305, 'loser_name' => 'Einundzwanzig Ulm',
            'why' => 'beide ohne Termine; 232 traegt den sauberen Slug, das Intro kommt von 305 herueber'],
        ['survivor' => 232, 'survivor_name' => 'Einundzwanzig Ulm', 'loser' => 63, 'loser_name' => 'Bitcoin Ulm',
            'why' => 'dritter leerer Ulm-Eintrag ohne Termine und ohne Leader-npub'],
        ['survivor' => 313, 'survivor_name' => 'EINUNDZWANZIG LEIPZIG ₿', 'loser' => 27, 'loser_name' => 'Bitcoin Leipzig',
            'why' => '7 Termine und ein gepflegtes Intro gegen einen leeren Eintrag'],
        ['survivor' => 52, 'survivor_name' => 'Einundzwanzig Karlsruhe', 'loser' => 51, 'loser_name' => 'Bitcoin Karlsruhe',
            'why' => '3 Termine gegen keinen; der npub von 51 wandert mit'],
        ['survivor' => 219, 'survivor_name' => '21 Gießen', 'loser' => 173, 'loser_name' => 'Einundzwanzig Gießen',
            'why' => 'Umzug: 173 endet 07/2023, 219 laeuft ab da weiter — die 4 Termine wandern mit'],
        ['survivor' => 14, 'survivor_name' => 'Einundzwanzig Potsdam', 'loser' => 244, 'loser_name' => 'Bitcoin Meetup - Einundzwanzig Potsdam',
            'why' => 'beide ohne Termine; 14 traegt den kurzen Slug, das Intro kommt von 244 herueber'],
    ];

    /**
     * Ersatzlose Loeschung — kein Ueberlebender, weil es nichts zu retten gibt.
     *
     * @var list<array{id: int, name: string, why: string}>
     */
    private const DELETIONS = [
        ['id' => 337, 'name' => 'DELETE', 'why' => 'Testeintrag: keine Termine, keine Leader, keine Links'],
    ];

    /**
     * Was bewusst NICHT automatisch passiert. Steht hier, damit es beim Lauf
     * sichtbar bleibt statt in einem Protokoll zu versanden.
     *
     * @var list<string>
     */
    private const OPEN = [
        'id 322 "Teszt Bitcoin meetup" (Budapest): sieht nach Probelauf aus, hat aber einen stattgefundenen Termin, 2 Leader-npubs und eine Website. Erst bei den Budapester Organisatoren nachfragen.',
        'Die Stadt "03096" ist eine Postleitzahl im Namensfeld und gehoert korrigiert. Sie haengt ausser an id 337 auch an "Zollernalb Balingen" — und 03096 liegt in Brandenburg, Balingen in Baden-Wuerttemberg. Der richtige Name ist damit nicht erschliessbar, nur nachzuschlagen.',
    ];

    public function handle(MergeMeetups $merger): int
    {
        $force = (bool) $this->option('force');

        if (! $this->verifyTargets()) {
            return Command::FAILURE;
        }

        $this->line('');
        $this->info($force ? 'Fuehre die Bereinigung aus.' : '[DRY-RUN] Nichts wird geschrieben. Mit --force ausfuehren.');
        $this->line('');

        foreach (self::MERGES as $merge) {
            $this->line(sprintf(
                '  %s  id %d "%s"  <-  id %d "%s"',
                $force ? '→' : '·',
                $merge['survivor'], $merge['survivor_name'],
                $merge['loser'], $merge['loser_name'],
            ));
            $this->line(sprintf('     %s', $merge['why']));
            $this->line(sprintf('     %s', $this->describeLoser($merge['loser'])));
        }

        foreach (self::DELETIONS as $deletion) {
            $this->line(sprintf('  %s  id %d "%s" ersatzlos loeschen', $force ? '→' : '·', $deletion['id'], $deletion['name']));
            $this->line(sprintf('     %s', $deletion['why']));
        }

        $this->line('');
        $this->warn('Bleibt offen:');
        foreach (self::OPEN as $open) {
            $this->line('  - '.$open);
        }
        $this->line('');

        if (! $force) {
            return Command::SUCCESS;
        }

        /*
         * Ohne Terminal gibt `confirm()` den Default zurueck — hier `false`, der
         * Lauf braeche also stumm ab. Auf einem Server (forge command, Deploy-Hook)
         * gibt es kein Terminal, und dort IST `--force` die bewusste Entscheidung:
         * jemand hat den Schalter getippt, der ohne ihn nichts tut.
         */
        if ($this->input->isInteractive() && ! $this->confirm('Das loescht 8 Meetups unwiderruflich auf DIESER Datenbank. Weiter?', false)) {
            $this->warn('Abgebrochen.');

            return Command::FAILURE;
        }

        return $this->runCleanup($merger);
    }

    /**
     * Jede id gegen den erwarteten Namen pruefen, bevor irgendetwas passiert.
     * Verglichen wird getrimmt und ohne Gross-/Kleinschreibung — die Rand-
     * Leerzeichen sind ja gerade der Grund, warum diese Eintraege existieren.
     */
    private function verifyTargets(): bool
    {
        $expected = [];
        foreach (self::MERGES as $merge) {
            $expected[$merge['survivor']] = $merge['survivor_name'];
            $expected[$merge['loser']] = $merge['loser_name'];
        }
        foreach (self::DELETIONS as $deletion) {
            $expected[$deletion['id']] = $deletion['name'];
        }

        $found = Meetup::query()->whereKey(array_keys($expected))->pluck('name', 'id');
        $problems = [];

        foreach ($expected as $id => $name) {
            $actual = $found[$id] ?? null;

            if ($actual === null) {
                $problems[] = sprintf('id %d ("%s") existiert nicht mehr.', $id, $name);

                continue;
            }

            if (mb_strtolower(trim($actual)) !== mb_strtolower(trim($name))) {
                $problems[] = sprintf('id %d heisst "%s", erwartet war "%s".', $id, $actual, $name);
            }
        }

        if ($problems === []) {
            return true;
        }

        $this->error('Die Daten stimmen nicht mehr mit der Entscheidung vom 26.08.2026 ueberein:');
        foreach ($problems as $problem) {
            $this->line('  - '.$problem);
        }
        $this->line('');
        $this->line('Nichts wurde angefasst. Erst den Befund neu erheben, dann die Liste im Command anpassen.');

        return false;
    }

    private function describeLoser(int $id): string
    {
        $meetup = Meetup::query()->find($id);

        if (! $meetup) {
            return 'nicht gefunden';
        }

        return sprintf(
            'wandern mit: %d Termine, %d Mitglieder (davon %d Leader)',
            $meetup->meetupEvents()->count(),
            $meetup->users()->count(),
            $meetup->users()->wherePivot('is_leader', true)->count(),
        );
    }

    private function runCleanup(MergeMeetups $merger): int
    {
        $protokoll = ['ausgefuehrt_am' => now()->toIso8601String(), 'merges' => [], 'deletions' => []];

        foreach (self::MERGES as $merge) {
            $survivor = Meetup::query()->findOrFail($merge['survivor']);
            $loser = Meetup::query()->findOrFail($merge['loser']);

            $result = $merger->handle($survivor, $loser);
            $protokoll['merges'][] = $result;

            $this->line(sprintf(
                '  ✓ id %d <- id %d   %d Termine, %d Mitglieder verschoben%s',
                $result['survivor'], $result['loser'],
                $result['moved']['meetup_events'], $result['moved']['meetup_user'],
                $result['filled'] === [] ? '' : ', uebernommen: '.implode(', ', $result['filled']),
            ));
        }

        foreach (self::DELETIONS as $deletion) {
            $meetup = Meetup::query()->findOrFail($deletion['id']);
            $protokoll['deletions'][] = $meetup->attributesToArray();
            $meetup->delete();
            $this->line(sprintf('  ✓ id %d geloescht', $deletion['id']));
        }

        // Das Protokoll ist der einzige Weg zurueck: die geloeschten Zeilen sind
        // fort, api_changes meldet nur DASS geloescht wurde, nicht was drinstand.
        $pfad = sprintf('meetup-cleanup/%s.json', now()->format('Y-m-d-His'));
        Storage::disk('local')->put($pfad, json_encode($protokoll, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->line('');
        $this->info('Protokoll mit allen geloeschten Datensaetzen: '.Storage::disk('local')->path($pfad));

        return Command::SUCCESS;
    }
}
