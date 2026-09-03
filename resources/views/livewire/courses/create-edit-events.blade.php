<?php

use App\Attributes\SeoDataAttribute;
use App\Models\City;
use App\Models\Country;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Tag;
use App\Services\Osm\NominatimClient;
use App\Traits\SeoTrait;
use Carbon\Carbon;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

new
#[SeoDataAttribute(key: 'courses_edit_events')]
class extends Component
{
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

    /** Scratch state for the "city is missing" flyout. */
    public ?int $newCityCountryId = null;

    public string $newCityQuery = '';

    /** @var array<int, array<string, mixed>> */
    public array $newCityResults = [];

    public bool $newCitySearched = false;

    /**
     * Free text address, the same shape `meetup_events.location` has always had.
     *
     * This is the fallback that always works — "Bürgerhaus, Fischergasse 1" or "wird noch
     * bekannt gegeben". The map place below it is the precise version, when one exists.
     *
     * A structured OSM place is an equally valid answer to "where" — see save()'s
     * matching 'required_without:osmPlace.osm_id' rule, the one that actually applies.
     */
    #[Validate('nullable|string|max:255|required_without:osmPlace.osm_id')]
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
     * Die Termin-Berechtigung entscheidet, nicht die des Kurses — und sie ist eine andere,
     * je nachdem ob hier ein Termin entsteht oder einer geändert wird.
     *
     * Bisher fragte beides `update` auf dem KURS ab. Das war zu grob in beide Richtungen:
     * es sperrte den Ersteller eines Termins aus, sobald ihm der Kurs nicht gehörte, und
     * es band das Anlegen an eine Ability, die mit dem Termin nichts zu tun hat.
     *
     * - Neuer Termin → `createForCourse` auf CourseEventPolicy: wem der Kurs gehört
     *   (oder Super-Admin). Bewusst NICHT die schrankenlose `create`-Ability, die die
     *   REST-API ohne Kurs-Kontext benutzt — die Route liegt nur hinter `auth`, und mit
     *   `create` allein stünde das kürzlich geschlossene Loch wieder offen.
     * - Bestehender Termin → `update` auf dem Termin: sein Ersteller oder ein
     *   Super-Admin, dieselbe Regel wie in REST-API und MCP-Werkzeug.
     */
    protected function authorizeManage(): void
    {
        $user = auth()->user();

        abort_unless(
            $this->event !== null
                ? (bool) $user?->can('update', $this->event)
                : (bool) $user?->can('createForCourse', [CourseEvent::class, $this->course]),
            403
        );
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
            // A structured OSM place is an equally valid answer to "where" — only reject
            // when the event has neither a text location nor a picked map place.
            'location' => ['nullable', 'string', 'max:255', 'required_without:osmPlace.osm_id'],
            'link' => 'required|url|max:255',
            ...($this->tagsRequired ? ['tagIds' => 'required|array|min:1'] : []),
        ], [
            'location.required_without' => __('Gib entweder einen Ort als Text ein oder wähle einen Ort auf der Karte.'),
            'tagIds.required' => __('Bitte wähle mindestens einen Tag.'),
            'tagIds.min' => __('Bitte wähle mindestens einen Tag.'),
        ]);

        $timezone = auth()->user()->timezone ?? 'Europe/Berlin';

        // Combine date and time in user's timezone, then convert to UTC
        $localFrom = Carbon::createFromFormat('Y-m-d H:i', $this->fromDate.' '.$this->fromTime, $timezone);
        $utcFrom = $localFrom->setTimezone('UTC');

        $localTo = Carbon::createFromFormat('Y-m-d H:i', $this->toDate.' '.$this->toTime, $timezone);
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
            'location' => $this->normalizedLocation(),
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
        $allowed = Tag::query()
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

    /**
     * Searches OpenStreetMap for a town, so a missing city can be added without leaving
     * the form.
     *
     * The city table requires latitude and longitude — they are NOT NULL, and all 304 rows
     * have them. Asking a lecturer to type coordinates would be absurd, so they come from
     * the same geocoder the map field already uses. That is the whole reason this is a
     * search and not three input boxes.
     */
    public function searchCity(): void
    {
        $this->validate([
            'newCityCountryId' => ['required', 'exists:countries,id'],
            'newCityQuery' => ['required', 'string', 'min:2'],
        ]);

        $this->newCitySearched = true;

        $code = Country::find($this->newCityCountryId)?->code;

        $this->newCityResults = app(NominatimClient::class)
            ->search($this->newCityQuery, $code)
            // Only populated places. Without this a search for "Bern" also offers streets
            // and buildings named Bern, and one of them would become a "city".
            ->filter(fn (array $hit): bool => ($hit['category'] ?? null) === 'place')
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * Takes one search result and makes it the selected city.
     */
    public function useCity(int $index): void
    {
        $hit = $this->newCityResults[$index] ?? null;

        if ($hit === null || ! auth()->user()->can('create', City::class)) {
            return;
        }

        $name = trim((string) ($hit['osm_name'] ?? ''));

        if ($name === '') {
            return;
        }

        /*
         * An existing city is selected rather than duplicated. Two rows for the same town
         * would split its events across both and neither list would be complete — the same
         * failure the tag vocabulary already suffered from.
         */
        $city = City::query()
            ->where('country_id', $this->newCityCountryId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        $city ??= City::create([
            'country_id' => $this->newCityCountryId,
            'name' => $name,
            'latitude' => $hit['osm_lat'],
            'longitude' => $hit['osm_lon'],
            /*
             * Die Referenz gleich mitschreiben: der Treffer kommt aus derselben Suche,
             * die sie liefert, und eine Stadt ohne sie muesste spaeter von Hand
             * nachgezogen werden. Bestehende Staedte werden nicht angefasst — dieser
             * Zweig laeuft nur, wenn keine gefunden wurde.
             */
            'osm_type' => $hit['osm_type'] ?? null,
            'osm_id' => $hit['osm_id'] ?? null,
            'osm_name' => $hit['osm_name'] ?? null,
            'osm_address' => $hit['osm_address'] ?? null,
            'osm_lat' => $hit['osm_lat'] ?? null,
            'osm_lon' => $hit['osm_lon'] ?? null,
            'wikidata' => $hit['wikidata'] ?? null,
            'wikipedia' => $hit['wikipedia'] ?? null,
            'population' => $hit['population'] ?? null,
        ]);

        $this->city_id = $city->id;
        $this->reset(['newCityQuery', 'newCityResults', 'newCitySearched']);

        Flux::modal('add-city')->close();
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
            'countries' => Country::query()->orderBy('name')->get(),
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

            {{-- The `locale` attribute is not decoration: without it Flux falls back to
                 navigator.language, so these four pickers followed the BROWSER instead of
                 the language the user picked in the portal — a German organiser on an
                 English-language laptop got an English calendar here while the meetup form
                 next door showed a German one. session('lang_country') is the portal's own
                 selection and what meetups/create-edit-events.blade.php has always passed.

                 Deliberately NOT pinned to an ISO locale. Issue #48 put the portal on ISO
                 8601, but that rule governs data display, not an input widget — the picker
                 is a control the organiser operates and its calendar may speak their
                 language. The measurement behind that decision, and the one locale that
                 would have produced an ISO date, are recorded at the matching block in
                 meetups/create-edit-events.blade.php.

                 Display only: wire:model carries Y-m-d and H:i, and save() converts those
                 from the user's timezone to UTC. --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('Startdatum') }} <span class="text-red-500">*</span></flux:label>
                    <flux:date-picker wire:model="fromDate" required locale="{{ session('lang_country', 'de-DE') }}"/>
                    <flux:description>{{ __('An welchem Tag beginnt das Event?') }}</flux:description>
                    <flux:error name="fromDate"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Startzeit') }} <span class="text-red-500">*</span></flux:label>
                    <flux:time-picker wire:model="fromTime" required locale="{{ session('lang_country', 'de-DE') }}"/>
                    <flux:description>{{ __('Um wie viel Uhr beginnt das Event?') }} ({{ auth()->user()->timezone ?? 'Europe/Berlin' }})</flux:description>
                    <flux:error name="fromTime"/>
                </flux:field>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('Enddatum') }} <span class="text-red-500">*</span></flux:label>
                    <flux:date-picker wire:model="toDate" required locale="{{ session('lang_country', 'de-DE') }}"/>
                    <flux:description>{{ __('An welchem Tag endet das Event?') }}</flux:description>
                    <flux:error name="toDate"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Endzeit') }} <span class="text-red-500">*</span></flux:label>
                    <flux:time-picker wire:model="toTime" required locale="{{ session('lang_country', 'de-DE') }}"/>
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
                <flux:description>
                    {{ __('In welcher Stadt oder Region findet das Event statt?') }}
                    <flux:modal.trigger name="add-city">
                        <button type="button" class="underline underline-offset-2 hover:no-underline"
                                data-testid="add-city-trigger">
                            {{ __('Stadt nicht dabei?') }}
                        </button>
                    </flux:modal.trigger>
                </flux:description>
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

            {{-- Same pair, same fix as the meetup event form (issue #48). The reporter
                 only saw that form, but leaving its twin with the old "Ort" next to an
                 identically-hinted "Ort auf der Karte" would have been half a fix. --}}
            <flux:field>
                <flux:label>{{ __('Ort als Text') }} <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="location" placeholder="{{ __('z.B. Hinterzimmer im Café Mustermann') }}"
                            required/>
                <flux:description>{{ __('Freitext für Besucher. Auch Details, die ein Kartenpunkt nicht zeigt.') }}</flux:description>
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

    {{-- Sits outside the form: a nested <form> is invalid HTML, and pressing Enter in the
         search box would otherwise submit the event instead of searching. --}}
    <flux:modal name="add-city" variant="flyout" wire:key="add-city-modal">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Stadt hinzufügen') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Such die Stadt auf OpenStreetMap — Lage und Schreibweise kommen von dort. Danach ist sie oben auswählbar.') }}
                </flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Land') }}</flux:label>
                <flux:select variant="listbox" searchable wire:model="newCityCountryId"
                             placeholder="{{ __('Land auswählen') }}" data-testid="add-city-country">
                    <x-slot name="search">
                        <flux:select.search class="px-4" placeholder="{{ __('Land suchen...') }}"/>
                    </x-slot>
                    @foreach($countries as $country)
                        <flux:select.option value="{{ $country->id }}">{{ $country->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="newCityCountryId"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Stadt') }}</flux:label>
                <div class="flex gap-2">
                    <flux:input wire:model="newCityQuery" wire:keydown.enter.prevent="searchCity"
                                placeholder="{{ __('z.B. Lippstadt') }}" data-testid="add-city-query"/>
                    <flux:button wire:click="searchCity" data-testid="add-city-search">
                        {{ __('Suchen') }}
                    </flux:button>
                </div>
                <flux:error name="newCityQuery"/>
            </flux:field>

            @if ($newCityResults)
                <div class="flex flex-col gap-1" data-testid="add-city-results">
                    @foreach($newCityResults as $index => $hit)
                        <button type="button" wire:click="useCity({{ $index }})"
                                wire:key="city-hit-{{ $hit['osm_type'] }}-{{ $hit['osm_id'] }}"
                                data-testid="add-city-result-{{ $index }}"
                                class="rounded-md border border-zinc-200 p-2 text-start hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                            <div class="text-sm font-medium">{{ $hit['osm_name'] }}</div>
                            <div class="text-xs opacity-60">{{ $hit['osm_address'] }}</div>
                        </button>
                    @endforeach
                </div>
            @elseif ($newCitySearched)
                <flux:callout data-testid="add-city-empty">
                    {{ __('Keine Stadt gefunden. Prüf die Schreibweise oder das Land.') }}
                </flux:callout>
            @endif
        </div>
    </flux:modal>
</div>
