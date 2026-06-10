<?php

namespace App\Console\Commands\Database;

use App\Models\Meetup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('meetups:restore {meetup : ID des wiederherzustellenden Meetups}')]
#[Description('Restore the master data of a meetup from its saved restore point.')]
class RestoreMeetupFromRestorePoint extends Command
{
    public function handle(): int
    {
        $meetupId = $this->argument('meetup');
        $meetup = Meetup::query()->find($meetupId);

        if ($meetup === null) {
            $this->error("Meetup [{$meetupId}] not found.");

            return Command::FAILURE;
        }

        if (! $meetup->restoreFromRestorePoint()) {
            $this->error("Meetup [{$meetup->id}] {$meetup->name} has no restore point. Run meetups:snapshot first.");

            return Command::FAILURE;
        }

        $capturedAt = $meetup->restore_point['captured_at'] ?? 'unknown';
        $this->info("Meetup [{$meetup->id}] {$meetup->name} restored from restore point (captured at {$capturedAt}).");

        return Command::SUCCESS;
    }
}
