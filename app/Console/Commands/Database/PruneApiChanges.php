<?php

namespace App\Console\Commands\Database;

use App\Models\ApiChange;
use Illuminate\Console\Command;

/**
 * Haelt das Aenderungs-Log (Issue #29) auf seiner Aufbewahrungsfrist.
 *
 * Die Frist ist zugleich das Versprechen an den Konsumenten: laenger als sie kann
 * niemand per /api/changes nachziehen. Wer laenger offline war, braucht einen
 * vollstaendigen Abgleich — deshalb steht die Zahl in der Config und nicht hier fest
 * verdrahtet.
 */
class PruneApiChanges extends Command
{
    /**
     * @var string
     */
    protected $signature = 'api-changes:prune {--days= : Aufbewahrungsfrist in Tagen; Default aus einundzwanzig.change_log.prune_days}';

    /**
     * @var string
     */
    protected $description = 'Loescht Eintraege des API-Aenderungs-Logs, die aelter als die Aufbewahrungsfrist sind';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('einundzwanzig.change_log.prune_days', 30));

        if ($days < 1) {
            $this->error('Die Aufbewahrungsfrist muss mindestens einen Tag betragen.');

            return Command::FAILURE;
        }

        $cutoff = now()->subDays($days);

        $deleted = ApiChange::query()
            ->where('occurred_at', '<', $cutoff)
            ->delete();

        $this->info("{$deleted} Aenderungen vor {$cutoff->toDateTimeString()} geloescht.");

        return Command::SUCCESS;
    }
}
