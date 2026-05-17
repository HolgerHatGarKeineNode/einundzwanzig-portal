<?php

namespace App\Observers;

use App\Models\MeetupEvent;

class MeetupEventObserver
{
    public function saved(MeetupEvent $meetupEvent): void
    {
        $meetupEvent->meetup?->recalculateActivity();
    }

    public function deleted(MeetupEvent $meetupEvent): void
    {
        $meetupEvent->meetup?->recalculateActivity();
    }
}
