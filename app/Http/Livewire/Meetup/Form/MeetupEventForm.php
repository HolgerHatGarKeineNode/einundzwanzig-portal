<?php

namespace App\Http\Livewire\Meetup\Form;

use App\Models\MeetupEvent;
use App\Support\Carbon;
use Livewire\Component;
use WireUi\Traits\Actions;

class MeetupEventForm extends Component
{
    use Actions;

    public string $country;

    public ?MeetupEvent $meetupEvent = null;

    public bool $recurring = false;

    public int $repetitions = 52;

    public array $series = [];

    public function rules()
    {
        return [
            'meetupEvent.meetup_id' => 'required',
            'meetupEvent.start' => 'required',
            'meetupEvent.location' => 'string|nullable',
            'meetupEvent.description' => 'string|nullable',
            'meetupEvent.link' => 'string|url|nullable',

            'series.*.start' => 'required',

            'recurring' => 'bool',
            'repetitions' => 'numeric|min:1',
        ];
    }

    public function mount()
    {
        if (! $this->meetupEvent) {
            $this->meetupEvent = new MeetupEvent(
                [
                    'start' => now()
                        ->startOfDay()
                        ->addHours(17),
                ]
            );
        } elseif (! auth()
            ->user()
            ->can('update', $this->meetupEvent)) {
            abort(403);
        }
    }

    public function updatedMeetupEventStart($value)
    {
        $this->validate();
        if ($this->recurring) {
            $this->updatedRecurring(true);
        }
    }

    public function updatedRecurring($value)
    {
        $this->validate();
        if ($value && $this->meetupEvent->start) {
            $this->series = [];
            for ($i = 0; $i < $this->repetitions; $i++) {
                $this->series[] = [
                    'start' => $this->meetupEvent->start->addWeeks($i + 1)
                                                        ->toDateTimeString(),
                ];
            }
        }
    }

    public function updatedRepetitions($value)
    {
        $this->validate();
        if ($this->recurring) {
            $this->updatedRecurring(true);
        }
    }

    public function deleteMe()
    {
        $this->dialog()
             ->confirm(
                 [
                     'title' => __('Delete event'),
                     'description' => __('Are you sure you want to delete this event? This action cannot be undone.'),
                     'icon' => 'warning',
                     'accept' => [
                         'label' => __('Yes, delete'),
                         'method' => 'deleteEvent',
                     ],
                     'reject' => [
                         'label' => __('No, cancel'),
                         'method' => 'cancel',
                     ],
                 ]
             );
    }

    public function deleteEvent()
    {
        $this->meetupEvent->delete();

        return to_route('meetup.table.meetupEvent', ['country' => $this->country]);
    }

    public function submit()
    {
        $this->validate();
        if (! $this->meetupEvent->id) {
            $hasAppointmentsOnThisDate = MeetupEvent::query()
                                                    ->where('meetup_id', $this->meetupEvent->meetup_id)
                                                    ->where('start', '>', Carbon::parse($this->meetupEvent->start)
                                                                                ->startOfDay())
                                                    ->where('start', '<', Carbon::parse($this->meetupEvent->start)
                                                                                ->endOfDay())
                                                    ->exists();
            if ($hasAppointmentsOnThisDate) {
                $this->notification()
                     ->warning(__('There is already an event on this date. Please choose another date or delete the existing events.'));

                return;
            }
        }

        $this->meetupEvent->save();

        if (! $this->meetupEvent->id && $this->recurring) {
            foreach ($this->series as $event) {
                $hasAppointmentsOnThisDate = MeetupEvent::query()
                                                        ->where('meetup_id', $this->meetupEvent->meetup_id)
                                                        ->where('start', '>', Carbon::parse($event['start'])
                                                                                    ->startOfDay())
                                                        ->where('start', '<', Carbon::parse($event['start'])
                                                                                    ->endOfDay())
                                                        ->exists();

                if ($hasAppointmentsOnThisDate) {
                    continue;
                }

                $this->meetupEvent->replicate()
                                  ->fill($event)
                                  ->saveQuietly();
            }
        }

        $this->notification()
             ->success(__('Event saved successfully.'));

        return to_route('meetup.table.meetupEvent', ['country' => $this->country]);
    }

    public function render()
    {
        return view('livewire.meetup.form.meetup-event-form');
    }
}
