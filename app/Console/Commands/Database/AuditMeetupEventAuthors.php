<?php

namespace App\Console\Commands\Database;

use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Findet Meetup-Organisatoren, die faktisch Termine pflegen, sie aber nach dem
 * Wechsel auf das Leader-Modell nicht mehr anlegen dürfen.
 *
 * Betroffen ist, wer für ein Meetup schon einmal einen Termin erstellt hat
 * (meetup_events.created_by), heute aber weder Leader (meetup_user.is_leader)
 * noch Ersteller des Meetups (meetups.created_by) ist. Solche Nutzer laufen bei
 * POST /api/meetup-events in einen 403 („Du darfst diesen Termin nicht
 * bearbeiten."), ohne dass sich für sie sichtbar etwas geändert hat.
 *
 * Zwei bekannte Wege in diesen Zustand:
 *  - Das Meetup stammt aus dem GitHub-Import (created_by = Admin), der
 *    Organisator war nur „Meine Meetups"-Mitglied. PromoteExistingLeaders hat
 *    nur den Ist-Zustand der Pivot fixiert — wer damals nicht in der Pivot
 *    stand, blieb außen vor.
 *  - Meetup::addMember() hat bis zum Fix per syncWithoutDetaching auch
 *    bestehende Zeilen auf is_leader = false zurückgeschrieben; ein Leader, der
 *    „zu meinen Meetups hinzufügen" antippte, degradierte sich damit selbst.
 *
 * Ohne --fix schreibt der Befehl nichts (reiner Report).
 */
#[Signature('meetups:audit-event-authors {--fix : Betroffene Nutzer zu Leadern ihres Meetups befördern}')]
#[Description('Listet Termin-Autoren, die ihr Meetup heute nicht mehr bearbeiten dürfen; mit --fix werden sie zu Leadern befördert.')]
class AuditMeetupEventAuthors extends Command
{
    public function handle(): int
    {
        $affected = $this->findAffected();

        if ($affected === []) {
            $this->info('Keine betroffenen Termin-Autoren gefunden.');

            return Command::SUCCESS;
        }

        $this->table(
            ['Meetup', 'Meetup-ID', 'Nutzer', 'User-ID', 'npub', 'Termine'],
            array_map(fn (array $row): array => [
                $row['meetup_name'],
                $row['meetup_id'],
                $row['user_name'],
                $row['user_id'],
                $row['npub'] ?? '—',
                $row['event_count'],
            ], $affected),
        );

        $count = count($affected);

        if (! $this->option('fix')) {
            $this->warn("{$count} Nutzer betroffen. Zum Beheben erneut mit --fix ausführen.");

            return Command::SUCCESS;
        }

        foreach ($affected as $row) {
            $meetup = Meetup::find($row['meetup_id']);
            $user = User::find($row['user_id']);

            if ($meetup === null || $user === null) {
                continue;
            }

            $meetup->promoteLeader($user);
        }

        $this->info("{$count} Nutzer zu Leadern ihres Meetups befördert.");

        return Command::SUCCESS;
    }

    /**
     * Termin-Autoren ohne heutige Bearbeitungsberechtigung, absteigend nach der
     * Zahl der von ihnen angelegten Termine.
     *
     * @return list<array{meetup_id:int, meetup_name:string, user_id:int, user_name:string, npub:?string, event_count:int}>
     */
    private function findAffected(): array
    {
        $rows = MeetupEvent::query()
            ->join('meetups', 'meetups.id', '=', 'meetup_events.meetup_id')
            ->join('users', 'users.id', '=', 'meetup_events.created_by')
            ->whereNotNull('meetup_events.created_by')
            // Ersteller des Meetups darf immer — ChecksCreatorOwnership::owns().
            ->whereColumn('meetup_events.created_by', '!=', 'meetups.created_by')
            // Leader darf immer — MeetupPolicy::update() via Meetup::isLeader().
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('meetup_user')
                    ->whereColumn('meetup_user.meetup_id', 'meetup_events.meetup_id')
                    ->whereColumn('meetup_user.user_id', 'meetup_events.created_by')
                    ->where('meetup_user.is_leader', true);
            })
            ->groupBy('meetup_events.meetup_id', 'meetups.name', 'meetup_events.created_by', 'users.name', 'users.nostr')
            ->orderByDesc(DB::raw('count(*)'))
            ->get([
                'meetup_events.meetup_id',
                'meetups.name as meetup_name',
                'meetup_events.created_by as user_id',
                'users.name as user_name',
                'users.nostr as npub',
                DB::raw('count(*) as event_count'),
            ]);

        return $rows->map(fn ($row): array => [
            'meetup_id' => (int) $row->meetup_id,
            'meetup_name' => (string) $row->meetup_name,
            'user_id' => (int) $row->user_id,
            'user_name' => (string) $row->user_name,
            'npub' => $row->npub,
            'event_count' => (int) $row->event_count,
        ])->all();
    }
}
