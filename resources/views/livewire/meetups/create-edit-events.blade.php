<?php

use App\Attributes\SeoDataAttribute;
use App\Enums\RecurrenceType;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Traits\SeoTrait;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

new
#[SeoDataAttribute(key: 'meetups_create_edit_events')]
class extends Component {
    use SeoTrait;

    public Meetup $meetup;
    public ?MeetupEvent $event = null;

    #[Locked]
    public $country = 'de';

    public ?string $startDate = null;
    public ?string $startTime = null;

    // Explicitly track timezone for reactivity
    public string $userTimezone = '';

    public bool $seriesMode = false;
    public ?string $endDate = null;

    public ?RecurrenceType $recurrenceType = null;
    public ?string $recurrenceDayOfWeek = null;
    public ?string $recurrenceDayPosition = null;

    public function getPreviewDatesProperty(): array
    {
        if (!$this->seriesMode || !$this->startDate || !$this->endDate) {
            return [];
        }

        try {
            // Ensure timezone is always set - use fallback if not initialized yet
            $timezone = $this->userTimezone ?: (auth()->user()->timezone ?? 'Europe/Berlin');
            $startDate = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $this->startDate . ' ' . $this->startTime, $timezone);
            $endDate = \Carbon\Carbon::createFromFormat('Y-m-d', $this->endDate, $timezone);

            // Use custom recurrence when dayOfWeek and dayPosition are set (e.g., "last Friday of month")
            if ($this->recurrenceDayOfWeek && $this->recurrenceDayPosition) {
                return $this->generateCustomRecurrenceDates($startDate, $endDate, $timezone, true);
            }

            // For weekly recurrence with a specific day of week (no position),
            // shift start date to the next occurrence of that weekday
            if ($this->recurrenceType === RecurrenceType::Weekly && $this->recurrenceDayOfWeek) {
                $dayOfWeekNumber = $this->getDayOfWeekNumber($this->recurrenceDayOfWeek);
                if ($dayOfWeekNumber !== null) {
                    $adjustedStartDate = $startDate->copy();
                    // Find the next occurrence of the specified weekday
                    while ($adjustedStartDate->dayOfWeek !== $dayOfWeekNumber) {
                        $adjustedStartDate->addDay();
                    }
                    // Generate weekly dates from the adjusted start
                    $dates = [];
                    $currentDate = $adjustedStartDate->copy();
                    while ($currentDate->lessThanOrEqualTo($endDate) && count($dates) < 100) {
                        $dates[] = [
                            'date' => $currentDate->copy(),
                            'formatted' => $currentDate->translatedFormat('l, d.m.Y'),
                            'time' => $currentDate->format('H:i'),
                        ];
                        $currentDate->addWeek();
                    }
                    return $dates;
                }
            }

            // Default: generate dates based on recurrence type
            $currentDate = $startDate->copy();
            $dates = [];

            while ($currentDate->lessThanOrEqualTo($endDate) && count($dates) < 100) {
                $dates[] = [
                    'date' => $currentDate->copy(),
                    'formatted' => $currentDate->translatedFormat('l, d.m.Y'),
                    'time' => $currentDate->format('H:i'),
                ];

                if ($this->recurrenceType === RecurrenceType::Weekly) {
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

    private function generateCustomRecurrenceDates(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate, string $timezone, bool $formatted = false): array
    {
        $dates = [];

        // Start from the beginning of the month containing startDate
        $currentDate = $startDate->copy()->startOfMonth();
        // Preserve the time from startDate for the occurrences
        $time = $startDate->format('H:i:s');

        while ($currentDate->lessThanOrEqualTo($endDate) && count($dates) < 100) {
            $occurrenceDate = $this->findNextOccurrence($currentDate, $timezone);

            if ($occurrenceDate && $occurrenceDate->lessThanOrEqualTo($endDate)) {
                // Set the time from startDate, preserving the date
                $occurrenceWithTime = $occurrenceDate->copy()->setTimeFrom($startDate);

                // Only add if this is after or on the start date
                if ($occurrenceWithTime->gte($startDate)) {
                    if ($formatted) {
                        $dates[] = [
                            'date' => $occurrenceWithTime,
                            'formatted' => $occurrenceWithTime->translatedFormat('l, d.m.Y'),
                            'time' => $time,
                        ];
                    } else {
                        $dates[] = $occurrenceWithTime;
                    }
                }

                // Move to the next month
                $currentDate = $currentDate->copy()->addMonth();
            } else {
                break;
            }
        }

        return $dates;
    }

    private function findNextOccurrence(\Carbon\Carbon $currentDate, string $timezone): ?\Carbon\Carbon
    {
        if (!$this->recurrenceDayOfWeek || !$this->recurrenceDayPosition) {
            return $currentDate;
        }

        $dayOfWeek = $this->getDayOfWeekNumber($this->recurrenceDayOfWeek);
        $dayPosition = $this->getDayPositionNumber($this->recurrenceDayPosition);

        if ($dayOfWeek === null || $dayPosition === null) {
            return $currentDate;
        }

        // Find the Nth dayOfWeek in the current month
        $date = $currentDate->copy()->startOfMonth();

        if ($dayPosition === -1) {
            return $date->lastOfMonth($dayOfWeek)->setTime($currentDate->hour, $currentDate->minute, $currentDate->second);
        }

        $count = 0;
        while ($date->month === $currentDate->month) {
            if ($date->dayOfWeek === $dayOfWeek) {
                $count++;
                if ($count === $dayPosition) {
                    return $date->copy()->setTime($currentDate->hour, $currentDate->minute, $currentDate->second);
                }
            }
            $date->addDay();
        }

        // If we didn't find enough occurrences in this month, return null
        return null;
    }

    private function getDayOfWeekNumber(string $day): ?int
    {
        return match (strtolower($day)) {
            'monday', 'montag' => \Carbon\Carbon::MONDAY,
            'tuesday', 'dienstag' => \Carbon\Carbon::TUESDAY,
            'wednesday', 'mittwoch' => \Carbon\Carbon::WEDNESDAY,
            'thursday', 'donnerstag' => \Carbon\Carbon::THURSDAY,
            'friday', 'freitag' => \Carbon\Carbon::FRIDAY,
            'saturday', 'samstag' => \Carbon\Carbon::SATURDAY,
            'sunday', 'sonntag' => \Carbon\Carbon::SUNDAY,
            default => null,
        };
    }

    private function getDayPositionNumber(string $position): ?int
    {
        return match (strtolower($position)) {
            'first', 'erster' => 1,
            'second', 'zweiter' => 2,
            'third', 'dritter' => 3,
            'fourth', 'vierter' => 4,
            'last', 'letzter' => -1,
            default => null,
        };
    }

    public function getRecurrenceTypesProperty(): array
    {
        return [
            RecurrenceType::Weekly,
            RecurrenceType::Monthly,
        ];
    }

    public function getDaysOfWeekProperty(): array
    {
        return [
            'monday' => __('Montag'),
            'tuesday' => __('Dienstag'),
            'wednesday' => __('Mittwoch'),
            'thursday' => __('Donnerstag'),
            'friday' => __('Freitag'),
            'saturday' => __('Samstag'),
            'sunday' => __('Sonntag'),
        ];
    }

    public function getDayPositionsProperty(): array
    {
        return [
            'first' => __('Erster'),
            'second' => __('Zweiter'),
            'third' => __('Dritter'),
            'fourth' => __('Vierter'),
            'last' => __('Letzter'),
        ];
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
        $this->userTimezone = auth()->user()->timezone ?? 'Europe/Berlin';
        $timezone = $this->userTimezone;

        if ($this->event) {
            $localStart = $this->event->start->setTimezone($timezone);
            $this->startDate = $localStart->format('Y-m-d');
            $this->startTime = $localStart->format('H:i');
            $this->location = $this->event->location;
            $this->description = $this->event->description;
            $this->link = $this->event->link;

            if ($this->event->recurrence_type) {
                $this->seriesMode = true;
                $this->recurrenceType = $this->event->recurrence_type;
                $this->recurrenceDayOfWeek = $this->event->recurrence_day_of_week;
                $this->recurrenceDayPosition = $this->event->recurrence_day_position;
                $this->endDate = $this->event->recurrence_end_date ? $this->event->recurrence_end_date->format('Y-m-d') : '';
            }
        } else {
            // Set default start time to next Monday at 19:00 in user's timezone
            $defaultStart = now($timezone)->next('Monday')->setTime(19, 0);
            $this->startDate = $defaultStart->format('Y-m-d');
            $this->startTime = $defaultStart->format('H:i');
            $this->endDate = $defaultStart->copy()->addMonths(6)->format('Y-m-d');
            $this->recurrenceType = RecurrenceType::Weekly;
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
            $validationRules['recurrenceType'] = 'required';
        }

        $this->validate($validationRules);

        $timezone = $this->userTimezone;

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

        $eventsCreated = 0;

        $dates = $this->generateEventDates($startDate, $endDate, $timezone);

        foreach ($dates as $date) {
            $utcDateTime = $date->copy()->setTimezone('UTC');

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
        }

        session()->flash('status', __(':count Events erfolgreich erstellt!', ['count' => $eventsCreated]));
    }

    private function generateEventDates(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate, string $timezone): array
    {
        // Use custom recurrence when dayOfWeek and dayPosition are set (e.g., "last Friday of month")
        if ($this->recurrenceDayOfWeek && $this->recurrenceDayPosition) {
            return $this->generateCustomRecurrenceDates($startDate, $endDate, $timezone);
        }

        // For weekly recurrence with a specific day of week (no position),
        // shift start date to the next occurrence of that weekday
        if ($this->recurrenceType === RecurrenceType::Weekly && $this->recurrenceDayOfWeek) {
            $dayOfWeekNumber = $this->getDayOfWeekNumber($this->recurrenceDayOfWeek);
            if ($dayOfWeekNumber !== null) {
                $adjustedStartDate = $startDate->copy();
                while ($adjustedStartDate->dayOfWeek !== $dayOfWeekNumber) {
                    $adjustedStartDate->addDay();
                }
                $dates = [];
                $currentDate = $adjustedStartDate->copy();
                while ($currentDate->lessThanOrEqualTo($endDate)) {
                    $dates[] = $currentDate->copy();
                    $currentDate->addWeek();
                }
                return $dates;
            }
        }

        // Default: generate dates based on recurrence type
        $dates = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lessThanOrEqualTo($endDate)) {
            $dates[] = $currentDate->copy();

            if ($this->recurrenceType === RecurrenceType::Weekly) {
                $currentDate->addWeek();
            } else {
                $currentDate->addMonth();
            }
        }

        return $dates;
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
                    <flux:date-picker :clearable="false" min="today" wire:model.live="startDate" required locale="{{ session('lang_country', 'de-DE') }}"/>
                    <flux:description>{{ $seriesMode ? __('Datum des ersten Termins') : __('An welchem Tag findet das Event statt?') }}</flux:description>
                    <flux:error name="startDate"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Uhrzeit') }} <span class="text-red-500">*</span></flux:label>
                    <flux:time-picker :clearable="false" wire:model="startTime" required locale="{{ session('lang_country', 'de-DE') }}"/>
                    <flux:description>{{ __('Um wie viel Uhr startet das Event?') }} ({{ $this->userTimezone }})</flux:description>
                    <flux:error name="startTime"/>
                </flux:field>
            </div>

            @if($seriesMode)
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <flux:field>
                        <flux:label>{{ __('Enddatum') }} <span class="text-red-500">*</span></flux:label>
                        <flux:date-picker :clearable="false" min="today" wire:model.live="endDate" required locale="{{ session('lang_country', 'de-DE') }}"/>
                        <flux:description>{{ __('Datum des letzten Termins') }}</flux:description>
                        <flux:error name="endDate"/>
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Wiederholungstyp') }} <span class="text-red-500">*</span></flux:label>
                        <flux:select wire:model.live="recurrenceType" required>
                            @foreach($this->recurrenceTypes as $type)
                                <flux:select.option value="{{ $type->value }}">{{ $type->getLabel() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:description>{{ __('Wie oft soll das Event wiederholt werden?') }}</flux:description>
                        <flux:error name="recurrenceType"/>
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <flux:field>
                        <flux:label>{{ __('Wochentag') }}</flux:label>
                        <flux:select wire:model.live="recurrenceDayOfWeek">
                            <flux:select.option value="">{{ __('Automatisch (wie Startdatum)') }}</flux:select.option>
                            @foreach($this->daysOfWeek as $key => $label)
                                <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:description>{{ __('An welchem Wochentag soll das Event stattfinden?') }}</flux:description>
                        <flux:error name="recurrenceDayOfWeek"/>
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Position im Monat') }}</flux:label>
                        <flux:select wire:model.live="recurrenceDayPosition">
                            <flux:select.option value="">{{ __('Automatisch (gleiches Datum)') }}</flux:select.option>
                            @foreach($this->dayPositions as $key => $label)
                                <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:description>{{ __('Welcher Wochentag im Monat? (z.B. "letzter Freitag")') }}</flux:description>
                        <flux:error name="recurrenceDayPosition"/>
                    </flux:field>
                </div>

                <flux:field>
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Für regelmäßige Termine wie "immer am letzten Freitag des Monats":') }}<br>
                        • {{ __('Wiederholungstyp: Monatlich') }}<br>
                        • {{ __('Wochentag: Freitag') }}<br>
                        • {{ __('Position im Monat: Letzter') }}
                    </flux:text>
                </flux:field>
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
                                <div class="shrink-0 w-10 h-10 rounded-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center">
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
    <flux:modal name="confirm-series" class="min-w-88">
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
