<?php

namespace App\Console\Commands\Database;

use App\Models\City;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Removes cities nothing points at any more.
 *
 * This used to clean up venues first and cities second, because a city could only be
 * deleted once its last venue was gone. With the venue removed, events carry their city
 * directly and the two-step dance is over: one query answers whether a city is still in use.
 *
 * ## Warum die Kandidaten namentlich ausgegeben werden
 *
 * Bis `bitcoin_events` fiel, war `whereDoesntHave('bitcoinEvents')` die dritte Sperre.
 * Auf Produktion hingen am 2026-08-25 GENAU ELF Staedte allein an ihr — sie tragen weder
 * Meetup noch Kurstermin und waren nur deshalb geschuetzt. Mit dem Drop sind sie
 * loeschbar geworden, ohne dass sich an ihnen etwas geaendert haette.
 *
 * Dagegen hilft hier keine neue Schutzregel (die waere geraten), sondern Sichtbarkeit:
 * der Trockenlauf nennt jede Stadt mit id, Name und Land, BEVOR irgendetwas passiert.
 * Wer `--force` tippt, hat die Liste dann gesehen — und zwei der elf Zeilen wurden am
 * 2026-08-25 von Hand repariert, was man einer blossen Anzahl nicht ansieht.
 */
#[Signature('places:cleanup {--force : Actually delete instead of doing a dry-run}')]
#[Description('Delete cities without any meetups or course events.')]
class CleanupPlaces extends Command
{
    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $cities = City::query()
            ->with('country')
            ->whereDoesntHave('meetups')
            ->whereDoesntHave('courseEvents')
            ->orderBy('id')
            ->get();

        if ($cities->isEmpty()) {
            $this->info('No unused city entries to clean up.');

            return Command::SUCCESS;
        }

        $this->info(sprintf(
            '%s %d unused city entry/entries.',
            $force ? 'Deleting' : '[DRY-RUN] Would delete',
            $cities->count(),
        ));

        $this->newLine();
        $this->table(
            ['ID', 'City', 'Country'],
            $cities->map(fn (City $city): array => [
                $city->id,
                $city->name,
                $city->country?->name ?? '—',
            ])->all(),
        );

        if (! $force) {
            $this->newLine();
            $this->table(['Type', 'To delete'], [['Cities', $cities->count()]]);
            $this->comment('Dry-run only. Re-run with --force to apply.');

            return Command::SUCCESS;
        }

        /*
         * Die Rueckfrage steht ABSICHTLICH hinter der Liste, nicht davor. Vorher stand sie
         * als erste Anweisung im Kommando — gefragt wurde also, bevor auch nur die Abfrage
         * gelaufen war, und die Antwort „ja" bezog sich auf eine Zahl, die niemand kannte.
         * Jetzt bestaetigt man das, was auf dem Schirm steht.
         */
        if (! $this->confirm('This permanently deletes cities on this database. Continue?', false)) {
            $this->warn('Aborted.');

            return Command::FAILURE;
        }

        // One ->delete() per model rather than a mass delete, so model events fire.
        $this->withProgressBar($cities, fn (City $city) => $city->delete());
        $this->newLine();

        $this->newLine();
        $this->table(['Type', 'Deleted'], [['Cities', $cities->count()]]);

        return Command::SUCCESS;
    }
}
