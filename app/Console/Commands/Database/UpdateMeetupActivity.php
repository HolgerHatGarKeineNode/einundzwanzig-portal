<?php

namespace App\Console\Commands\Database;

use App\Models\Meetup;
use App\Models\MeetupEvent;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

#[Signature('meetups:update-activity')]
#[Description('Recalculate is_active and last_event_at for every meetup based on its events.')]
class UpdateMeetupActivity extends Command
{
    public function handle(): int
    {
        $threshold = now()->subYear();

        Meetup::query()->chunkById(200, function ($meetups) use ($threshold) {
            foreach ($meetups as $meetup) {
                $this->updateMeetup($meetup, $threshold);
            }
        });

        $this->info('Meetup activity flags updated.');

        return Command::SUCCESS;
    }

    private function updateMeetup(Meetup $meetup, CarbonInterface $threshold): void
    {
        $lastEventAt = MeetupEvent::query()
            ->where('meetup_id', $meetup->id)
            ->where('start', '<=', now())
            ->max('start');

        $lastEventAt = $lastEventAt ? Date::parse($lastEventAt) : null;

        $hasFutureEvent = MeetupEvent::query()
            ->where('meetup_id', $meetup->id)
            ->where('start', '>', now())
            ->exists();

        $hasActiveRecurrence = MeetupEvent::query()
            ->where('meetup_id', $meetup->id)
            ->whereNotNull('recurrence_type')
            ->where(function ($query) {
                $query->whereNull('recurrence_end_date')
                    ->orWhere('recurrence_end_date', '>=', now());
            })
            ->exists();

        $isActive = ($lastEventAt && $lastEventAt->greaterThanOrEqualTo($threshold))
            || $hasFutureEvent
            || $hasActiveRecurrence;

        $meetup->forceFill([
            'is_active' => $isActive,
            'last_event_at' => $lastEventAt,
        ])->saveQuietly();
    }
}
