<?php

namespace App\Console\Commands\Database;

use App\Models\Meetup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Einmalige Fixierung des Ist-Zustands beim Wechsel auf das Leader-Modell:
 * Bisher durfte jedes „Meine Meetups"-Mitglied (meetup_user) ein Meetup über
 * das Portal bearbeiten. Dieser Kreis gilt als historisch legitimiert und wird
 * zu echten Leadern (meetup_user.is_leader = true) befördert. Zusätzlich wird
 * sichergestellt, dass jeder Ersteller Leader seines Meetups ist (auch bei
 * Alt-Meetups, die vor dem created-Hook angelegt wurden).
 *
 * Nach diesem Lauf berechtigt nur noch is_leader = true zum Bearbeiten; frisch
 * über addToMine hinzugefügte Mitglieder bleiben is_leader = false.
 */
#[Signature('meetups:promote-existing-leaders {--dry-run : Nur anzeigen, nichts schreiben}')]
#[Description('Befördert alle bestehenden meetup_user-Mitglieder zu Leadern (Ist-Zustand fixieren) und sichert die Ersteller-Leaderschaft.')]
class PromoteExistingLeaders extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $memberRows = DB::table('meetup_user')->where('is_leader', false)->count();

        $missingCreators = Meetup::query()
            ->whereNotNull('created_by')
            ->whereDoesntHave('users', function ($query): void {
                $query->whereColumn('users.id', 'meetups.created_by');
            })
            ->count();

        if ($dryRun) {
            $this->info("Dry-Run: {$memberRows} Mitglieder würden zu Leadern befördert.");
            $this->info("Dry-Run: {$missingCreators} fehlende Ersteller-Mitgliedschaften würden ergänzt (als Leader).");

            return Command::SUCCESS;
        }

        DB::table('meetup_user')->where('is_leader', false)->update(['is_leader' => true]);

        $ensured = 0;
        Meetup::query()
            ->whereNotNull('created_by')
            ->chunkById(200, function ($meetups) use (&$ensured): void {
                foreach ($meetups as $meetup) {
                    $meetup->users()->syncWithoutDetaching([
                        $meetup->created_by => ['is_leader' => true],
                    ]);
                    $ensured++;
                }
            });

        $this->info("{$memberRows} Mitglieder zu Leadern befördert.");
        $this->info("Ersteller-Leaderschaft für {$ensured} Meetups sichergestellt.");

        return Command::SUCCESS;
    }
}
