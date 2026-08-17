<?php

use App\Attributes\SeoDataAttribute;
use App\Models\City;
use App\Models\Course;
use App\Models\CourseEvent;
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

    public ?string $fromDate = null;
    public ?string $fromTime = null;
    public ?string $toDate = null;
    public ?string $toTime = null;

    /**
     * The city the event belongs to.
     *
     * Required, even though the column is nullable: an event without a city drops out of
     * every country-filtered listing (courses.index, lecturers.index and the sidebar
     * badges all reach through `courseEvents.city.country`), so it would be invisible.
     */
    #[Validate('required|exists:cities,id')]
    public ?int $city_id = null;

    /**
     * Free text address, the same shape `meetup_events.location` has always had.
     *
     * This is the fallback that always works — "Bürgerhaus, Fischergasse 1" or "wird noch
     * bekannt gegeben". The map place below it is the precise version, when one exists.
     */
    #[Validate('required|string|max:255')]
    public ?string $location = null;

    #[Validate('required|url|max:255')]
    public ?string $link = null;

    /**
     * OpenStreetMap place, or empty. Keys match the course_events columns.
     *
     * @var array<string, mixed>
     */
    public array $osmPlace = [];

    /** @var array<int, int> */
    public array $tagIds = [];

    /**
     * The country of the chosen city, used to narrow the map search.
     *
     * Null until a city is picked, which makes the picker search worldwide — the honest
     * behaviour when nobody has said where to look yet.
     */
    public function getOsmCountryProperty(): ?string
    {
        return $this->city_id === null
            ? null
            : City::with('country')->find($this->city_id)?->country?->code;
    }

    /**
     * Whether this event's country demands at least one tag.
     *
     * Read from the event's own city now that the venue is gone. Bound live, so switching
     * the city updates the requirement while the form is open rather than at save time.
     */
    public function getTagsRequiredProperty(): bool
    {
        $code = $this->osmCountry;

        return $code !== null
            && in_array(mb_strtolower($code), config('einundzwanzig.tags_required_countries', []), true);
    }

    /**
     * Whether to nudge the organiser about a missing map location.
     *
     * Only on an existing event: a new one is being filled in right now, and a warning
     * about something not yet entered is just noise.
     */
    public function getNeedsOsmHintProperty(): bool
    {
        return $this->event !== null && empty($this->osmPlace['osm_id']);
    }

    /**
     * Termine darf nur verwalten, wer den zugehörigen Kurs bearbeiten darf — dieselbe
     * update-Ability wie die Stammdaten, spiegelt meetups.create-edit-events.
     *
     * Fehlte bisher vollständig: die Route lag nur hinter `auth`, und mount/save/delete
     * prüften nichts. Jeder eingeloggte Nutzer konnte damit Termine fremder Kurse anlegen,
     * ändern und löschen.
     */
    protected function authorizeManage(): void
    {
        if (auth()->guest() || auth()->user()->cannot('update', $this->course)) {
            abort(403);
        }
    }

    public function mount(): void
    {
        $this->authorizeManage();
        $this->country = request()->route('country', config('app.domain_country'));
        $timezone = auth()->user()->timezone ?? 'Europe/Berlin';

        if ($this->event) {
            $localFrom = $this->event->from->setTimezone($timezone);
            $localTo = $this->event->to->setTimezone($timezone);

            $this->fromDate = $localFrom->format('Y-m-d');
            $this->fromTime = $localFrom->format('H:i');
            $this->toDate = $localTo->format('Y-m-d');
            $this->toTime = $localTo->format('H:i');
            $this->city_id = $this->event->city_id;
            $this->location = $this->event->location;
            $this->link = $this->event->link;
            $this->tagIds = $this->event->tags->pluck('id')->all();
            $this->osmPlace = $this->event->osm_id ? [
                'osm_type' => $this->event->osm_type,
                'osm_id' => $this->event->osm_id,
                'osm_name' => $this->event->osm_name,
                'osm_address' => $this->event->osm_address,
                'osm_lat' => $this->event->osm_lat,
                'osm_lon' => $this->event->osm_lon,
            ] : [];
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
        $this->authorizeManage();

        $this->validate([
            'fromDate' => 'required|date',
            'fromTime' => 'required',
            'toDate' => 'required|date|after_or_equal:fromDate',
            'toTime' => 'required',
            'city_id' => 'required|exists:cities,id',
            'location' => 'required|string|max:255',
            'link' => 'required|url|max:255',
            ...($this->tagsRequired ? ['tagIds' => 'required|array|min:1'] : []),
        ], [
            'tagIds.required' => __('Bitte wähle mindestens einen Tag.'),
            'tagIds.min' => __('Bitte wähle mindestens einen Tag.'),
        ]);

        $timezone = auth()->user()->timezone ?? 'Europe/Berlin';

        // Combine date and time in user's timezone, then convert to UTC
        $localFrom = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $this->fromDate.' '.$this->fromTime, $timezone);
        $utcFrom = $localFrom->setTimezone('UTC');

        $localTo = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $this->toDate.' '.$this->toTime, $timezone);
        $utcTo = $localTo->setTimezone('UTC');

        // Additional validation: to must be after from
        if ($utcTo->lte($utcFrom)) {
            $this->addError('toTime', __('Die Endzeit muss nach der Startzeit liegen.'));

            return;
        }

        $data = [
            'from' => $utcFrom,
            'to' => $utcTo,
            'city_id' => $this->city_id,
            'location' => $this->location,
            'link' => $this->link,
            ...$this->osmFields(),
        ];

        if ($this->event) {
            // Update existing event
            $this->event->update($data);
            $this->syncTags($this->event);
            session()->flash('status', __('Event erfolgreich aktualisiert!'));
        } else {
            // Create new event
            $event = $this->course->courseEvents()->create([
                ...$data,
                'created_by' => auth()->id(),
            ]);
            $this->syncTags($event);
            session()->flash('status', __('Event erfolgreich erstellt!'));
        }

        $this->redirect(route('courses.landingpage', ['course' => $this->course, 'country' => $this->country]),
            navigate: true);
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
     * Only ids the user was actually offered are accepted — a crafted request must not
     * be able to attach someone else's unapproved suggestion.
     */
    private function syncTags(CourseEvent $event): void
    {
        $allowed = \App\Models\Tag::query()
            ->where('type', 'meetup_event')
            ->selectableBy(auth()->user())
            ->whereIn('id', $this->tagIds)
            ->get();

        $event->syncTagsWithType($allowed->all(), 'meetup_event');
    }

    public function delete(): void
    {
        $this->authorizeManage();

        if ($this->event) {
            $this->event->delete();
            session()->flash('status', __('Event erfolgreich gelöscht!'));
            $this->redirect(route('courses.landingpage', ['course' => $this->course, 'country' => $this->country]),
                navigate: true);
        }
    }

    public function with(): array
    {
        return [
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

            {{-- The city comes before the map search on purpose: it is what narrows that
                 search to a country, so asking for it second would search the wrong place. --}}
            <flux:field>
                <flux:label>{{ __('Stadt') }} <span class="text-red-500">*</span></flux:label>
                <flux:select variant="listbox" searchable wire:model.live="city_id"
                             placeholder="{{ __('Stadt auswählen') }}" required>
                    <x-slot name="search">
                        <flux:select.search class="px-4" placeholder="{{ __('Suche passende Stadt...') }}"/>
                    </x-slot>
                    @foreach($cities as $city)
                        <flux:select.option value="{{ $city->id }}">{{ $city->name }}
                            ({{ $city->country->name }})
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:description>{{ __('In welcher Stadt oder Region findet das Event statt?') }}</flux:description>
                <flux:error name="city_id"/>
            </flux:field>

            @if ($this->needsOsmHint)
                <flux:callout icon="map-pin" data-testid="osm-missing-hint">
                    <flux:callout.heading>{{ __('Dieses Event hat noch keinen Kartenort') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Bitte such den Ort einmal heraus — dann finden Besucher ihn auf der Karte statt nur als Text. Passt nichts, lass das Feld leer und beschreib den Ort unten.') }}
                    </flux:callout.text>
                </flux:callout>
            @endif

            {{-- Keyed on the country, not the city: switching between two German cities keeps
                 a place already found, switching country throws it back to the search. --}}
            <livewire:osm.place-picker
                wire:model="osmPlace"
                :country-code="$this->osmCountry"
                wire:key="osm-picker-{{ $this->osmCountry ?? 'any' }}"
            />

            <flux:field>
                <flux:label>{{ __('Ort') }} <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="location" placeholder="{{ __('z.B. Café Mustermann, Hauptstr. 1') }}"
                            required/>
                <flux:description>{{ __('Wo findet das Event statt?') }}</flux:description>
                <flux:error name="location"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Link') }} <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="link" type="url" placeholder="https://example.com" required/>
                <flux:description>{{ __('Link zu weiteren Informationen oder zur Anmeldung') }}</flux:description>
                <flux:error name="link"/>
            </flux:field>

            <livewire:tags.picker
                wire:model="tagIds"
                type="meetup_event"
                :required="$this->tagsRequired"
            />
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
</div>
