<?php

use App\Attributes\SeoDataAttribute;
use App\Models\City;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Venue;
use App\Traits\SeoTrait;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

new
#[SeoDataAttribute(key: 'courses_edit_events')]
class extends Component {
    use SeoTrait;

    public Course $course;
    public ?CourseEvent $event = null;

    #[Locked]
    public $country = 'de';

    public string $fromDate = '';
    public string $fromTime = '';
    public string $toDate = '';
    public string $toTime = '';

    #[Validate('required|exists:venues,id')]
    public ?int $venue_id = null;

    #[Validate('required|url|max:255')]
    public ?string $link = null;

    // New Venue Modal
    public string $newVenueName = '';
    public ?int $newVenueCityId = null;
    public string $newVenueStreet = '';

    public function mount(): void
    {
        $this->country = request()->route('country', config('app.domain_country'));
        $timezone = auth()->user()->timezone ?? 'Europe/Berlin';

        if ($this->event) {
            $localFrom = $this->event->from->setTimezone($timezone);
            $localTo = $this->event->to->setTimezone($timezone);

            $this->fromDate = $localFrom->format('Y-m-d');
            $this->fromTime = $localFrom->format('H:i');
            $this->toDate = $localTo->format('Y-m-d');
            $this->toTime = $localTo->format('H:i');
            $this->venue_id = $this->event->venue_id;
            $this->link = $this->event->link;
        } else {
            // Set default start time to next Monday at 09:00 in user's timezone
            $nextMonday = now($timezone)->next('Monday')->setTime(9, 0);
            $this->fromDate = $nextMonday->format('Y-m-d');
            $this->fromTime = $nextMonday->format('H:i');
            $this->toDate = $nextMonday->format('Y-m-d');
            $this->toTime = $nextMonday->copy()->addHours(3)->format('H:i');
        }
    }

    public function save(): void
    {
        $this->validate([
            'fromDate' => 'required|date',
            'fromTime' => 'required',
            'toDate' => 'required|date|after_or_equal:fromDate',
            'toTime' => 'required',
            'venue_id' => 'required|exists:venues,id',
            'link' => 'required|url|max:255',
        ]);

        $timezone = auth()->user()->timezone ?? 'Europe/Berlin';

        // Combine date and time in user's timezone, then convert to UTC
        $localFrom = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $this->fromDate . ' ' . $this->fromTime, $timezone);
        $utcFrom = $localFrom->setTimezone('UTC');

        $localTo = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $this->toDate . ' ' . $this->toTime, $timezone);
        $utcTo = $localTo->setTimezone('UTC');

        // Additional validation: to must be after from
        if ($utcTo->lte($utcFrom)) {
            $this->addError('toTime', __('Die Endzeit muss nach der Startzeit liegen.'));
            return;
        }

        $data = [
            'from' => $utcFrom,
            'to' => $utcTo,
            'venue_id' => $this->venue_id,
            'link' => $this->link,
        ];

        if ($this->event) {
            // Update existing event
            $this->event->update($data);
            session()->flash('status', __('Event erfolgreich aktualisiert!'));
        } else {
            // Create new event
            $this->course->courseEvents()->create([
                ...$data,
                'created_by' => auth()->id(),
            ]);
            session()->flash('status', __('Event erfolgreich erstellt!'));
        }

        $this->redirect(route('courses.landingpage', ['course' => $this->course, 'country' => $this->country]),
            navigate: true);
    }

    public function delete(): void
    {
        if ($this->event) {
            $this->event->delete();
            session()->flash('status', __('Event erfolgreich gelöscht!'));
            $this->redirect(route('courses.landingpage', ['course' => $this->course, 'country' => $this->country]),
                navigate: true);
        }
    }

    public function createVenue(): void
    {
        $validated = $this->validate([
            'newVenueName' => ['required', 'string', 'max:255', 'unique:venues,name'],
            'newVenueCityId' => ['required', 'exists:cities,id'],
            'newVenueStreet' => ['required', 'string', 'max:255'],
        ]);

        $venue = Venue::create([
            'name' => $validated['newVenueName'],
            'city_id' => $validated['newVenueCityId'],
            'street' => $validated['newVenueStreet'],
            'slug' => str($validated['newVenueName'])->slug(),
            'created_by' => auth()->id(),
        ]);

        $this->venue_id = $venue->id;
        $this->reset(['newVenueName', 'newVenueCityId', 'newVenueStreet']);

        \Flux\Flux::modal('add-venue')->close();
    }

    public function with(): array
    {
        return [
            'venues' => Venue::query()
                ->with([
                    'city',
                ])
                ->orderBy('name')
                ->get(),
            'cities' => City::query()
                ->with([
                    'country',
                ])
                ->orderBy('name')
                ->get(),
        ];
    }
}; ?>

<div class="max-w-4xl mx-auto p-6">
    <flux:heading size="xl" class="mb-8">
        {{ $event ? __('Event bearbeiten') : __('Neues Event erstellen') }}: {{ $course->name }}
    </flux:heading>

    <form wire:submit="save" class="space-y-10">

        <!-- Event Details -->
        <flux:fieldset class="space-y-6">
            <flux:legend>{{ __('Event Details') }}</flux:legend>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('Startdatum') }} <span class="text-red-500">*</span></flux:label>
                    <flux:date-picker wire:model="fromDate" required/>
                    <flux:description>{{ __('An welchem Tag beginnt das Event?') }}</flux:description>
                    <flux:error name="fromDate"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Startzeit') }} <span class="text-red-500">*</span></flux:label>
                    <flux:time-picker wire:model="fromTime" required/>
                    <flux:description>{{ __('Um wie viel Uhr beginnt das Event?') }} ({{ auth()->user()->timezone ?? 'Europe/Berlin' }})</flux:description>
                    <flux:error name="fromTime"/>
                </flux:field>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('Enddatum') }} <span class="text-red-500">*</span></flux:label>
                    <flux:date-picker wire:model="toDate" required/>
                    <flux:description>{{ __('An welchem Tag endet das Event?') }}</flux:description>
                    <flux:error name="toDate"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Endzeit') }} <span class="text-red-500">*</span></flux:label>
                    <flux:time-picker wire:model="toTime" required/>
                    <flux:description>{{ __('Um wie viel Uhr endet das Event?') }} ({{ auth()->user()->timezone ?? 'Europe/Berlin' }})</flux:description>
                    <flux:error name="toTime"/>
                </flux:field>
            </div>

            <flux:field>
                <div class="flex items-center justify-between mb-2">
                    <flux:label>{{ __('Veranstaltungsort') }} <span class="text-red-500">*</span></flux:label>
                    <flux:modal.trigger name="add-venue">
                        <flux:button class="cursor-pointer" size="xs" variant="ghost" icon="plus">
                            {{ __('Ort hinzufügen') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>
                <flux:select variant="listbox" searchable wire:model="venue_id"
                             placeholder="{{ __('Veranstaltungsort auswählen') }}" required>
                    <x-slot name="search">
                        <flux:select.search class="px-4" placeholder="{{ __('Suche nach Ort...') }}"/>
                    </x-slot>
                    @foreach($venues as $venue)
                        <flux:select.option value="{{ $venue->id }}">
                            {{ $venue->name }}
                            @if($venue->city)
                                - {{ $venue->city->name }}
                            @endif
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:description>{{ __('Wo findet das Event statt?') }}</flux:description>
                <flux:error name="venue_id"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Link') }} <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="link" type="url" placeholder="https://example.com" required/>
                <flux:description>{{ __('Link zu weiteren Informationen oder zur Anmeldung') }}</flux:description>
                <flux:error name="link"/>
            </flux:field>
        </flux:fieldset>

        <!-- Form Actions -->
        <div class="flex items-center justify-between pt-8 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-4">
                <flux:button class="cursor-pointer" variant="ghost" type="button"
                             :href="route('courses.landingpage', ['course' => $course, 'country' => $country])">
                    {{ __('Abbrechen') }}
                </flux:button>

                @if($event)
                    <flux:button class="cursor-pointer" variant="danger" type="button" wire:click="delete"
                                 wire:confirm="{{ __('Bist du sicher, dass du dieses Event löschen möchtest?') }}">
                        {{ __('Event löschen') }}
                    </flux:button>
                @endif
            </div>

            <div class="flex items-center gap-4">
                @if (session('status'))
                    <flux:text class="text-green-600 dark:text-green-400 font-medium">
                        {{ session('status') }}
                    </flux:text>
                @endif

                <flux:button class="cursor-pointer" variant="primary" type="submit">
                    {{ $event ? __('Event aktualisieren') : __('Event erstellen') }}
                </flux:button>
            </div>
        </div>
    </form>

    <!-- Add Venue Modal -->
    <flux:modal name="add-venue" variant="flyout" wire:key="add-venue-modal">
        <form wire:submit="createVenue" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Veranstaltungsort hinzufügen') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Füge einen neuen Veranstaltungsort zur Datenbank hinzu.') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Name') }} <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="newVenueName" placeholder="{{ __('z.B. Bitcoin Zentrum München') }}" required/>
                <flux:error name="newVenueName"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Stadt') }} <span class="text-red-500">*</span></flux:label>
                <flux:select variant="listbox" searchable wire:model="newVenueCityId"
                             placeholder="{{ __('Stadt auswählen') }}">
                    <x-slot name="search">
                        <flux:select.search class="px-4" placeholder="{{ __('Suche passende Stadt...') }}"/>
                    </x-slot>
                    @foreach($cities as $city)
                        <flux:select.option value="{{ $city->id }}">{{ $city->name }} ({{ $city->country->name }})
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="newVenueCityId"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Straße') }} <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="newVenueStreet" placeholder="{{ __('z.B. Hauptstraße 1') }}" required/>
                <flux:error name="newVenueStreet"/>
            </flux:field>

            <div class="flex gap-2">
                <flux:spacer/>

                <flux:modal.close>
                    <flux:button class="cursor-pointer" type="button"
                                 variant="ghost">{{ __('Abbrechen') }}</flux:button>
                </flux:modal.close>

                <flux:button class="cursor-pointer" type="submit"
                             variant="primary">{{ __('Ort erstellen') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
