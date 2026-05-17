<?php

namespace App\Console\Commands\Database;

use App\Models\Meetup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('meetups:update-activity')]
#[Description('Recalculate is_active and last_event_at for every meetup based on its events.')]
class UpdateMeetupActivity extends Command
{
    public function handle(): int
    {
        Meetup::query()->chunkById(200, function ($meetups) {
            foreach ($meetups as $meetup) {
                $meetup->recalculateActivity();
            }
        });

        $this->info('Meetup activity flags updated.');

        return Command::SUCCESS;
    }
}
