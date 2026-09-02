<?php

use App\Actions\MeetupEvents\ExpandRecurrenceSeries;
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
            // Enddatum kommt aus einem reinen Datums-Picker: bis zum Ende des
            // gewählten Tages (inklusiv). endOfDay() macht das deterministisch —
            // createFromFormat('Y-m-d', …) würde sonst die AKTUELLE Uhrzeit
            // einsetzen und das letzte Vorkommen je nach Laufzeit ein-/ausschließen.
            $endDate = \Carbon\Carbon::createFromFormat('Y-m-d', $this->endDate, $timezone)->endOfDay();

            return array_map(fn (\Carbon\Carbon $date): array => [
                'date' => $date,
                'formatted' => $date->translatedFormat('l, d.m.Y'),
                'time' => $date->format('H:i'),
            ], $this->generateEventDates($startDate, $endDate));
        } catch (\Exception $e) {
            return [];
        }
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

    // A structured OSM place is an equally valid answer to "where" — see save()'s
    // matching 'required_without:osmPlace.osm_id' rule, the one that actually applies.
    #[Validate('nullable|string|max:255|required_without:osmPlace.osm_id')]
    public ?string $location = null;

    #[Validate('required|string')]
    public ?string $description = null;

    #[Validate('nullable|url|max:255')]
    public ?string $link = null;

    public ?string $title = null;

    /**
     * End time of the single event, as HH:MM.
     *
     * Deliberately NOT called endDate — that name is already taken by the end of a
     * recurring series, and the two mean very different things. Only a time is asked
     * for: a meetup runs for hours, not days. If it is earlier than the start time the
     * event is taken to end after midnight.
     */
    public ?string $endTime = null;

    /** @var array<int, int> */
    /**
     * Die gewaehlten Marken.
     *
     * Bewusst untypisiert, wie im Waehler selbst: Flux' Combobox schreibt bei ENTER den
     * getippten Text ins Model, und ueber #[Modelable] landet derselbe Wert hier. Ein
     * `array`-Typ macht daraus einen TypeError in HandleComponents::updateProperty() —
     * also bevor irgendein Hook laufen koennte, und je nach Reihenfolge im Payload
     * trifft es dieses Formular vor dem Waehler. Gemeldet am 2026-08-23 aus dem
     * Termin-Formular: "Cannot assign string to property ... of type array".
     *
     * @var array<int, int>|string
     */
    public $tagIds = [];

    /**
     * Nur Ids behalten.
     *
     * Der Waehler fuehrt seine eigene Normalisierung (dort wird ein getippter Name zu
     * einer Marke); hier geht es allein darum, dass nichts anderes als Ids in die
     * Validierung gegen `array` und in whereIn() geraet.
     */
    public function updatedTagIds(): void
    {
        $this->tagIds = collect(is_array($this->tagIds) ? $this->tagIds : [])
            ->filter(fn ($id): bool => is_numeric($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * OpenStreetMap place, or empty. Keys match the meetup_events columns.
     *
     * @var array<string, mixed>
     */
    public array $osmPlace = [];

    public function getOsmCountryProperty(): ?string
    {
        return $this->meetup->city?->country?->code;
    }

    /**
     * Whether to nudge the organiser about a missing map location.
     *
     * Only on an existing event: a new one is being filled in right now, and a warning
     * about something not yet entered is just noise. On an old event it is the honest
     * message — either the automatic OSM matching never ran, or it was called off
     * because its confidence was too low to trust.
     */
    public function getNeedsOsmHintProperty(): bool
    {
        return $this->event !== null && empty($this->osmPlace['osm_id']);
    }

    /**
     * Whether this event's country demands at least one tag.
     *
     * Read from the meetup's own country rather than the route segment: the route
     * only says which country's pages the visitor is browsing, which is not
     * necessarily where the meetup is.
     */
    public function getTagsRequiredProperty(): bool
    {
        $code = $this->meetup->city?->country?->code;

        return $code !== null
            && in_array(mb_strtolower($code), config('einundzwanzig.tags_required_countries', []), true);
    }

    /**
     * Termine darf nur verwalten, wer das zugehörige Meetup bearbeiten darf
     * (Ersteller/Leader/Super-Admin) — dieselbe update-Ability wie die
     * Stammdaten. Spiegelt meetups.edit::authorizeAccess().
     */
    protected function authorizeManage(): void
    {
        if (auth()->guest() || auth()->user()->cannot('update', $this->meetup)) {
            abort(403);
        }
    }

    public function mount(): void
    {
        $this->authorizeManage();
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
            $this->title = $this->event->title;
            $this->endTime = $this->event->end?->setTimezone($timezone)->format('H:i');
            $this->tagIds = $this->event->tags->pluck('id')->all();
            $this->osmPlace = $this->event->osm_id ? [
                'osm_type' => $this->event->osm_type,
                'osm_id' => $this->event->osm_id,
                'osm_name' => $this->event->osm_name,
                'osm_address' => $this->event->osm_address,
                'osm_lat' => $this->event->osm_lat,
                'osm_lon' => $this->event->osm_lon,
            ] : [];

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
        $this->authorizeManage();

        $validationRules = [
            'startDate' => 'required|date',
            'startTime' => 'required',
            // A structured OSM place is an equally valid answer to "where" — only reject
            // when the event has neither a text location nor a picked map place.
            'location' => ['nullable', 'string', 'max:255', 'required_without:osmPlace.osm_id'],
            'description' => 'required|string',
            'link' => 'nullable|url|max:255',
            // Both optional: existing events have neither, and a meetup event without
            // its own title simply carries the meetup's name.
            'title' => 'nullable|string|max:255',
            'endTime' => 'nullable|date_format:H:i',
        ];

        if ($this->seriesMode) {
            $validationRules['endDate'] = 'required|date|after:startDate';
            $validationRules['recurrenceType'] = 'required';
        }

        if ($this->tagsRequired) {
            $validationRules['tagIds'] = 'required|array|min:1';
        }

        $this->validate($validationRules, [
            'location.required_without' => __('Gib entweder einen Ort als Text ein oder wähle einen Ort auf der Karte.'),
            'tagIds.required' => __('Bitte wähle mindestens einen Tag.'),
            'tagIds.min' => __('Bitte wähle mindestens einen Tag.'),
        ]);

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

    /**
     * The free-text location, or null when it was left blank.
     *
     * Blank rather than an empty string: 'required_without:osmPlace.osm_id' allows an
     * event with only a structured place to clear the text field, and the empty string
     * Livewire binds from a cleared input should not linger in the column afterwards.
     */
    private function normalizedLocation(): ?string
    {
        return blank($this->location) ? null : $this->location;
    }

    /**
     * Same reasoning as {@see self::normalizedLocation()}: the empty string Livewire
     * binds from a cleared input should not linger in the column as a fake value.
     */
    private function normalizedLink(): ?string
    {
        return blank($this->link) ? null : $this->link;
    }

    /**
     * The six OSM columns, all null when no place was picked.
     *
     * Always returns every key so clearing a place actually clears the columns —
     * omitting them would silently keep the old location on an update.
     *
     * @return array<string, mixed>
     */
    private function osmFields(): array
    {
        $keys = ['osm_type', 'osm_id', 'osm_name', 'osm_address', 'osm_lat', 'osm_lon'];

        return collect($keys)
            ->mapWithKeys(fn (string $key): array => [$key => $this->osmPlace[$key] ?? null])
            ->all();
    }

    /**
     * Turn the entered end time into a UTC timestamp on the event's own day.
     *
     * A time earlier than or equal to the start means the event runs past midnight —
     * a 20:00 meetup ending at 01:00 ends the next day, not five minutes into the past.
     */
    private function resolveEnd(\Carbon\Carbon $localStart): ?\Carbon\Carbon
    {
        if (blank($this->endTime)) {
            return null;
        }

        [$hour, $minute] = array_pad(explode(':', $this->endTime), 2, '0');

        $end = $localStart->copy()->setTime((int) $hour, (int) $minute);

        if ($end->lessThanOrEqualTo($localStart)) {
            $end->addDay();
        }

        return $end->setTimezone('UTC');
    }

    /**
     * Attach the picked tags, scoped to the event type so nothing else is disturbed.
     *
     * Only ids the user was actually offered are accepted — a crafted request must not
     * be able to attach someone else's unapproved suggestion.
     */
    private function syncTags(MeetupEvent $event): void
    {
        $allowed = \App\Models\Tag::query()
            ->where('type', 'meetup_event')
            ->selectableBy(auth()->user())
            ->whereIn('id', $this->tagIds)
            ->get();

        $event->syncTagsWithType($allowed->all(), 'meetup_event');
    }

    private function createOrUpdateSingleEvent(string $timezone): void
    {
        // Combine date and time in user's timezone, then convert to UTC
        $localDateTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $this->startDate . ' ' . $this->startTime, $timezone);
        // copy() matters: setTimezone() mutates in place, and resolveEnd() below needs
        // the start still expressed in the user's own timezone to compare against.
        $utcDateTime = $localDateTime->copy()->setTimezone('UTC');

        $data = [
            'start' => $utcDateTime,
            'end' => $this->resolveEnd($localDateTime),
            'title' => $this->title,
            'location' => $this->normalizedLocation(),
            'description' => $this->description,
            'link' => $this->normalizedLink(),
            ...$this->osmFields(),
        ];

        if ($this->event) {
            // Update existing event
            $this->event->update($data);
            $this->syncTags($this->event);
            session()->flash('status', __('Event erfolgreich aktualisiert!'));
        } else {
            // Create new event
            $event = $this->meetup->meetupEvents()->create([
                ...$data,
                'created_by' => auth()->id(),
                'attendees' => [],
                'might_attendees' => [],
            ]);
            $this->syncTags($event);
            session()->flash('status', __('Event erfolgreich erstellt!'));
        }
    }

    private function createEventSeries(string $timezone): void
    {
        $startDate = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $this->startDate . ' ' . $this->startTime, $timezone);
        // Inklusiv bis zum Ende des gewählten Tages, deterministisch (siehe
        // getPreviewDatesProperty) — Vorschau und Anlegen erzeugen so dieselbe Liste.
        $endDate = \Carbon\Carbon::createFromFormat('Y-m-d', $this->endDate, $timezone)->endOfDay();

        $eventsCreated = 0;

        $dates = $this->generateEventDates($startDate, $endDate);

        /*
         * Die Serien-Identitaet (P5): ein UUID fuer alle Vorkommen, dazu die fuenf
         * `recurrence_*`-Werte auf JEDEM Termin. Der MeetupEventResource verspricht sie
         * seit jeher pro Termin und lieferte fuer jede je angelegte Serie null.
         *
         * Die Expansion bleibt hier und wandert NICHT in CreateMeetupEventSeries: dort
         * laufen Start und Ende in UTC, hier in der Zeitzone des Nutzers. Ueber eine
         * Zeitumstellung hinweg ist das der Unterschied zwischen "immer 18:00 Ortszeit"
         * und "ab Ende Oktober 17:00".
         */
        $seriesFields = [
            'recurrence_type' => $this->recurrenceType?->value,
            'recurrence_day_of_week' => $this->recurrenceDayOfWeek ?: null,
            'recurrence_day_position' => $this->recurrenceDayPosition ?: null,
            'recurrence_interval' => 1,
            'recurrence_end_date' => $endDate->copy()->setTimezone('UTC'),
            'recurrence_group' => (string) \Illuminate\Support\Str::uuid(),
        ];

        // Ein Nachlauf statt einer Neuberechnung je Termin, siehe MeetupEventObserver::batched().
        \App\Observers\MeetupEventObserver::batched(function () use ($dates, $seriesFields, &$eventsCreated): void {
            foreach ($dates as $date) {
                $utcDateTime = $date->copy()->setTimezone('UTC');

                $event = $this->meetup->meetupEvents()->create([
                    'start' => $utcDateTime,
                    'end' => $this->resolveEnd($date),
                    'title' => $this->title,
                    'location' => $this->normalizedLocation(),
                    'description' => $this->description,
                    'link' => $this->normalizedLink(),
                    'created_by' => auth()->id(),
                    'attendees' => [],
                    'might_attendees' => [],
                    ...$this->osmFields(),
                    ...$seriesFields,
                ]);

                // Every occurrence of a series carries the same tags.
                $this->syncTags($event);

                $eventsCreated++;
            }
        });

        session()->flash('status', __(':count Events erfolgreich erstellt!', ['count' => $eventsCreated]));
    }

    /**
     * @return array<int, \Carbon\Carbon>
     */
    private function generateEventDates(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate): array
    {
        return app(ExpandRecurrenceSeries::class)->handle(
            $startDate,
            $endDate,
            $this->recurrenceType,
            $this->recurrenceDayOfWeek ?: null,
            $this->recurrenceDayPosition ?: null,
        );
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
                        <flux:description>{{ __('Welcher Wochentag im Monat? (z.B. „letzter Freitag“)') }}</flux:description>
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
                <flux:label>{{ __('Titel') }}</flux:label>
                <flux:input wire:model="title" placeholder="{{ __('z.B. Einsteigerabend: Wallets einrichten') }}"/>
                <flux:description>{{ __('Optional — ohne Titel erscheint der Name des Meetups.') }}</flux:description>
                <flux:error name="title"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Ende') }}</flux:label>
                <flux:time-picker wire:model="endTime" locale="{{ session('lang_country', 'de-DE') }}"/>
                <flux:description>{{ __('Optional. Eine Zeit vor dem Beginn bedeutet: das Event endet nach Mitternacht.') }}</flux:description>
                <flux:error name="endTime"/>
            </flux:field>

            @if ($this->needsOsmHint)
                <flux:callout icon="map-pin" data-testid="osm-missing-hint">
                    <flux:callout.heading>{{ __('Dieses Event hat noch keinen Kartenort') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Bitte such den Ort einmal heraus — dann finden Besucher ihn auf der Karte statt nur als Text. Passt nichts, lass das Feld leer und beschreib den Ort unten.') }}
                    </flux:callout.text>
                </flux:callout>
            @endif

            <livewire:osm.place-picker
                wire:model="osmPlace"
                :country-code="$this->osmCountry"
            />

            <flux:field>
                <flux:label>{{ __('Ort') }}</flux:label>
                <flux:input wire:model="location" placeholder="{{ __('z.B. Café Mustermann, Hauptstr. 1') }}"/>
                <flux:description>{{ __('Wo findet das Event statt?') }}</flux:description>
                <flux:error name="location"/>
            </flux:field>

            <livewire:tags.picker
                wire:model="tagIds"
                type="meetup_event"
                :required="$this->tagsRequired"
            />

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
                    <flux:badge color="zinc" size="lg">{{ trans_choice(':count Event|:count Events', count($this->previewDates)) }}</flux:badge>
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
                        {{ __('Eine Serie umfasst höchstens 100 Termine. Spätere Termine dieser Serie werden nicht erstellt.') }}
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
