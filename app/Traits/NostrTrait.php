<?php

namespace App\Traits;

use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Illuminate\Support\Facades\Process;

trait NostrTrait
{
    public function publishOnNostr($model, $text): array
    {
        if (app()->environment('local')) {
            return [
                'success' => true,
                'output' => 'local',
                'exitCode' => 0,
                'errorOutput' => '',
            ];
        }

        //noscl publish "Good morning!"
        $result = Process::timeout(60 * 5)
            ->run('noscl publish "'.$text.'"');

        if ($result->successful()) {
            $model->nostr_status = $result->output();
            $model->save();
        }

        return [
            'success' => $result->successful(),
            'output' => $result->output(),
            'exitCode' => $result->exitCode(),
            'errorOutput' => $result->errorOutput(),
        ];
    }

    public function getText($model)
    {
        $from = '';
        if ($model instanceof CourseEvent) {
            if ($model->course->lecturer->nostr) {
                $from .= '@'.$model->course->lecturer->nostr;
            } else {
                $from .= $model->course->lecturer->name;
            }

            return sprintf("Unser Dozent %s hat einen neuen Kurs-Termin eingestellt:\n%s\n%s\n%s\n\n#Bitcoin #Kurs #Education #Einundzwanzig #gesundesgeld #einundzwanzig_portal_lecturer_%s",
                $from,
                $model->course->name,
                str($model->course->description)->toString(),
                url()->route('courses.landingpage',
                    ['country' => str(session('lang_country', 'de'))->after('-')->lower(), 'course' => $model->course]),
                str($model->course->lecturer->slug)->replace('-', '_'),
            );
        }
        if ($model instanceof MeetupEvent) {
            $from = $model->meetup->name;
            if ($model->meetup->nostr) {
                $from .= ' @'.$model->meetup->nostr;
            }

            return sprintf("%s hat einen neuen Termin eingestellt:\n%s\n%s\n%s\n\n#Bitcoin #Meetup #Einundzwanzig #gesundesgeld #einundzwanzig_portal_%s",
                $from,
                $model->start->asDateTime(),
                $model->location,
                url()->route('meetups.landingpage-event',
                    ['country' => str(session('lang_country', 'de'))->after('-')->lower(), 'meetup' => $model, 'event' => $model]),
                str($model->meetup->slug)->replace('-', '_'),
            );
        }
        if ($model instanceof Meetup) {
            $from = $model->name;
            if ($model->nostr) {
                $from .= ' @'.$model->nostr;
            }

            return sprintf("Eine neue Meetup Gruppe wurde hinzugefügt:\n%s\n%s\n\n#Bitcoin #Meetup #Einundzwanzig #gesundesgeld #einundzwanzig_portal_%s",
                $from,
                url()->route('meetups.landingpage', ['country' => $model->city->country->code, 'meetup' => $model]),
                str($model->slug)->replace('-', '_'),
            );
        }
        if ($model instanceof Course) {
            if ($model->lecturer->nostr) {
                $from .= '@'.$model->lecturer->nostr;
            } else {
                $from .= $model->lecturer->name;
            }

            return sprintf("Unser Dozent %s hat einen neuen Kurs eingestellt:\n%s\n%s\n%s\n\n#Bitcoin #Kurs #Education #Einundzwanzig #gesundesgeld #einundzwanzig_portal_lecturer_%s",
                $from,
                $model->name,
                str($model->description)->toString(),
                url()->route('courses.landingpage',
                    ['country' => str(session('lang_country', 'de'))->after('-')->lower(), 'course' => $model]),
                str($model->lecturer->slug)->replace('-', '_'),
            );
        }
    }
}
