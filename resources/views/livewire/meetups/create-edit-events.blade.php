<?php

use App\Attributes\SeoDataAttribute;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Traits\SeoTrait;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new
#[SeoDataAttribute(key: 'meetups_create_edit_events')]
class extends Component {
    use SeoTrait;

    public Meetup $meetup;
    public ?MeetupEvent $event = null;

    public $country = 'de';

    public string $startDate = '';
    public string $startTime = '';

    public bool $seriesMode = false;
    public string $endDate = '';
    public string $interval = 'monthly';

    public function getPreviewDatesProperty(): array
    {
        if (!$this->seriesMode || !$this->startDate || !$this->endDate) {
            return [];
        }

        try {
            $timezone = auth()->user()->timezone ?? 'Europe/Berlin';
            $startDate = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $this->startDate . ' ' . $this->startTime, $timezone);
            $endDate = \Carbon\Carbon::createFromFormat('Y-m-d', $this->endDate, $timezone);

            $dates = [];
            $currentDate = $startDate->copy();

            while ($currentDate->lessThanOrEqualTo($endDate) && count($dates) < 100) {
                $dates[] = [
                    'date' => $currentDate->copy(),
                    'formatted' => $currentDate->translatedFormat('l, d.m.Y'),
                    'time' => $currentDate->format('H:i'),
                ];

                if ($this->interval === 'weekly') {
                    $currentDate->addWeek();
                } else {
                    $currentDate->addMonth();
                }
            }

            return $dates;
        } catch (\Exception $e) {
            return [];
        }
    }

    #[Validate('required|string|max:255')]
    public ?string $location = null;

    #[Validate('required|string')]
    public ?string $description = null;

    #[Validate('required|url|max:255')]
    public ?string $link = null;

    public function mount(): void
    {
        $this->country = request()->route('country', config('app.domain_country'));
        $timezone = auth()->user()->timezone ?? 'Europe/Berlin';

        if ($this->event) {
            $localStart = $this->event->start->setTimezone($timezone);
            $this->startDate = $localStart->format('Y-m-d');
            $this->startTime = $localStart->format('H:i');
            $this->location = $this->event->location;
            $this->description = $this->event->description;
            $this->link = $this->event->link;
        } else {
            // Set default start time to next Monday at 19:00 in user's timezone
            $defaultStart = now($timezone)->next('Monday')->setTime(19, 0);
            $this->startDate = $defaultStart->format('Y-m-d');
            $this->startTime = $defaultStart->format('H:i');
            $this->endDate = $defaultStart->copy()->addMonths(6)->format('Y-m-d');
        }
    }

    public function save(): void
    {
        $validationRules = [
            'startDate' => 'required|date',
            'startTime' => 'required',
            'location' => 'required|string|max:255',
            'description' => 'required|string',
            'link' => 'required|url|max:255',
        ];

        if ($this->seriesMode) {
            $validationRules['endDate'] = 'required|date|after:startDate';
            $validationRules['interval'] = 'required|in:weekly,monthly';
        }

        $this->validate($validationRules);

        $timezone = auth()->user()->timezone ?? 'Europe/Berlin';

        if ($this->seriesMode && !$this->event) {
            // Create series of events
            $this->createEventSeries($timezone);
        } else {
            // Create or update single event
            $this->createOrUpdateSingleEvent($timezone);
        }

        $this->redirect(route('meetups.landingpage', ['meetup' => $this->meetup, 'country' => $this->country]),
            navigate: true);
    }

    private function createOrUpdateSingleEvent(string $timezone): void
    {
        // Combine date and time in user's timezone, then convert to UTC
        $localDateTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $this->startDate . ' ' . $this->startTime, $timezone);
        $utcDateTime = $localDateTime->setTimezone('UTC');

        $data = [
            'start' => $utcDateTime,
            'location' => $this->location,
            'description' => $this->description,
            'link' => $this->link,
        ];

        if ($this->event) {
            // Update existing event
            $this->event->update($data);
            session()->flash('status', __('Event erfolgreich aktualisiert!'));
        } else {
            // Create new event
            $this->meetup->meetupEvents()->create([
                ...$data,
                'created_by' => auth()->id(),
                'attendees' => [],
                'might_attendees' => [],
            ]);
            session()->flash('status', __('Event erfolgreich erstellt!'));
        }
    }

    private function createEventSeries(string $timezone): void
    {
        $startDate = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $this->startDate . ' ' . $this->startTime, $timezone);
        $endDate = \Carbon\Carbon::createFromFormat('Y-m-d', $this->endDate, $timezone);

        $currentDate = $startDate->copy();
        $eventsCreated = 0;

        while ($currentDate->lessThanOrEqualTo($endDate)) {
            $utcDateTime = $currentDate->copy()->setTimezone('UTC');

            $this->meetup->meetupEvents()->create([
                'start' => $utcDateTime,
                'location' => $this->location,
                'description' => $this->description,
                'link' => $this->link,
                'created_by' => auth()->id(),
                'attendees' => [],
                'might_attendees' => [],
            ]);

            $eventsCreated++;

            // Move to next date based on interval
            if ($this->interval === 'weekly') {
                $currentDate->addWeek();
            } else {
                $currentDate->addMonth();
            }
        }

        session()->flash('status', __(':count Events erfolgreich erstellt!', ['count' => $eventsCreated]));
    }

    public function delete(): void
    {
        if ($this->event) {
            $this->event->delete();
            session()->flash('status', __('Event erfolgreich gelöscht!'));
            $this->redirect(route('meetups.landingpage', ['meetup' => $this->meetup, 'country' => $this->country]),
                navigate: true);
        }
    }
}; ?>

<div class="max-w-4xl mx-auto p-6">
    <flux:heading size="xl" class="mb-8">
        {{ $event ? __('Event bearbeiten') : __('Neues Event erstellen') }}: {{ $meetup->name }}
    </flux:heading>

    <form wire:submit="save" class="space-y-10">

        <!-- Series Mode Toggle -->
        @if(!$event)
            <flux:field variant="inline">
                <flux:label>{{ __('Serientermine erstellen') }}</flux:label>
                <flux:switch wire:model.live="seriesMode" />
                <flux:description>{{ __('Aktiviere diese Option, um mehrere Events mit regelmäßigen Abständen zu erstellen') }}</flux:description>
                <flux:error name="seriesMode" />
            </flux:field>
        @endif

        <!-- Event Details -->
        <flux:fieldset class="space-y-6">
            <flux:legend>{{ __('Event Details') }}</flux:legend>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ $seriesMode ? __('Startdatum') : __('Datum') }} <span class="text-red-500">*</span></flux:label>
                    <flux:date-picker min="today" wire:model="startDate" required locale="{{ session('lang_country', 'de-DE') }}"/>
                    <flux:description>{{ $seriesMode ? __('Datum des ersten Termins') : __('An welchem Tag findet das Event statt?') }}</flux:description>
                    <flux:error name="startDate"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Uhrzeit') }} <span class="text-red-500">*</span></flux:label>
                    <flux:time-picker wire:model="startTime" required locale="{{ session('lang_country', 'de-DE') }}"/>
                    <flux:description>{{ __('Um wie viel Uhr startet das Event?') }} ({{ auth()->user()->timezone ?? 'Europe/Berlin' }})</flux:description>
                    <flux:error name="startTime"/>
                </flux:field>
            </div>

            @if($seriesMode)
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <flux:field>
                        <flux:label>{{ __('Enddatum') }} <span class="text-red-500">*</span></flux:label>
                        <flux:date-picker min="today" wire:model.live="endDate" required locale="{{ session('lang_country', 'de-DE') }}"/>
                        <flux:description>{{ __('Datum des letzten Termins') }}</flux:description>
                        <flux:error name="endDate"/>
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Intervall') }} <span class="text-red-500">*</span></flux:label>
                        <flux:select wire:model.live="interval" required>
                            <option value="monthly">{{ __('Monatlich') }}</option>
                            <option value="weekly">{{ __('Wöchentlich') }}</option>
                        </flux:select>
                        <flux:description>{{ __('Wie oft soll das Event wiederholt werden?') }}</flux:description>
                        <flux:error name="interval"/>
                    </flux:field>
                </div>
            @endif

            <flux:field>
                <flux:label>{{ __('Ort') }}</flux:label>
                <flux:input wire:model="location" placeholder="{{ __('z.B. Café Mustermann, Hauptstr. 1') }}"/>
                <flux:description>{{ __('Wo findet das Event statt?') }}</flux:description>
                <flux:error name="location"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Beschreibung') }}</flux:label>
                <flux:textarea wire:model="description" rows="6" placeholder="{{ __('Beschreibe das Event...') }}"/>
                <flux:description>{{ __('Details über das Event') }}</flux:description>
                <flux:error name="description"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Link') }}</flux:label>
                <flux:input wire:model="link" type="url" placeholder="https://example.com"/>
                <flux:description>{{ __('Link zu weiteren Informationen') }}</flux:description>
                <flux:error name="link"/>
            </flux:field>
        </flux:fieldset>

        <!-- Series Preview -->
        @if($seriesMode && count($this->previewDates) > 0)
            <flux:card class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Vorschau der Termine') }}</flux:heading>
                    <flux:badge color="zinc" size="lg">{{ count($this->previewDates) }} {{ __('Events') }}</flux:badge>
                </div>

                <flux:separator />

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-96 overflow-y-auto">
                    @foreach($this->previewDates as $index => $dateInfo)
                        <flux:card class="bg-zinc-50 dark:bg-zinc-800/50">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center">
                                    <flux:text class="font-semibold text-sm">{{ $index + 1 }}</flux:text>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <flux:text class="font-semibold text-sm truncate">{{ $dateInfo['formatted'] }}</flux:text>
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $dateInfo['time'] }} {{ __('Uhr') }}</flux:text>
                                </div>
                            </div>
                        </flux:card>
                    @endforeach
                </div>

                @if(count($this->previewDates) >= 100)
                    <flux:text class="text-sm text-amber-600 dark:text-amber-400">
                        {{ __('Vorschau auf 100 Termine begrenzt. Es werden möglicherweise mehr Termine erstellt.') }}
                    </flux:text>
                @endif
            </flux:card>
        @endif

        <!-- Form Actions -->
        <div class="flex items-center justify-between pt-8 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-4">
                <flux:button variant="ghost" type="button"
                             :href="route_with_country('meetups.edit', ['meetup' => $meetup])">
                    {{ __('Abbrechen') }}
                </flux:button>

                @if($event)
                    <flux:button variant="danger" type="button" wire:click="delete"
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

                @if($seriesMode)
                    <flux:modal.trigger name="confirm-series">
                        <flux:button variant="primary" type="button">
                            {{ __('Serientermine erstellen') }}
                        </flux:button>
                    </flux:modal.trigger>
                @else
                    <flux:button variant="primary" type="submit">
                        {{ $event ? __('Event aktualisieren') : __('Event erstellen') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </form>

    <!-- Confirmation Modal for Series -->
    <flux:modal name="confirm-series" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Serientermine erstellen?') }}</flux:heading>

                <flux:text class="mt-2">
                    {{ __('Du bist dabei, mehrere Events zu erstellen.') }}<br>
                    {{ __('Falsch angelegte Termine müssen alle händisch wieder gelöscht werden.') }}<br><br>
                    <strong>{{ __('Bist du sicher, dass die Einstellungen korrekt sind?') }}</strong>
                </flux:text>
            </div>

            <div class="flex gap-2">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Abbrechen') }}</flux:button>
                </flux:modal.close>

                <flux:modal.close>
                    <flux:button type="submit" variant="primary" wire:click="save">{{ __('Jetzt erstellen') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
