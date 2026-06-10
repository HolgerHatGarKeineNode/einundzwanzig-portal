<?php

namespace App\Console\Commands\Database;

use App\Models\Meetup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('meetups:snapshot {meetup? : ID eines einzelnen Meetups; ohne Angabe werden alle Meetups gesichert}')]
#[Description('Save the current master data of meetups as a restore point (restore_point JSON column).')]
class SnapshotMeetupRestorePoints extends Command
{
    public function handle(): int
    {
        $meetupId = $this->argument('meetup');

        $count = 0;
        Meetup::query()
            ->when($meetupId, fn ($query) => $query->whereKey($meetupId))
            ->chunkById(200, function ($meetups) use (&$count) {
                foreach ($meetups as $meetup) {
                    $meetup->captureRestorePoint();
                    $count++;
                }
            });

        if ($meetupId && $count === 0) {
            $this->error("Meetup [{$meetupId}] not found.");

            return Command::FAILURE;
        }

        $this->info("Restore points saved for {$count} meetups.");

        return Command::SUCCESS;
    }
}
