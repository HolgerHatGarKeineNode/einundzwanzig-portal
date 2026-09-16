<?php

use App\Attributes\SeoDataAttribute;
use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Rules\UniqueMeetupName;
use App\Services\Osm\NominatimClient;
use App\Traits\SeoTrait;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[SeoDataAttribute(key: 'meetups_create')]
class extends Component
{
    use SeoTrait;
    use WithFileUploads;

    #[Validate('image|mimes:jpeg,png,webp,avif|max:5120|dimensions:max_width=4000,max_height=4000')]
    public $logo;

    // Basic Information
    public string $name = '';

    public ?int $city_id = null;

    public ?string $intro = null;

    // Links and Social Media
    public ?string $telegram_link = null;

    public ?string $webpage = null;

    public ?string $twitter_username = null;

    public ?string $matrix_group = null;

    public ?string $nostr = null;

    public ?string $simplex = null;

    public ?string $signal = null;

    // Additional Information
    public ?string $community = null;

    public bool $visible_on_map = true;

    // New City Modal
    public ?int $newCityCountryId = null;

    public string $newCityQuery = '';

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $newCityResults = [];

    #[Locked]
    public bool $newCitySearched = false;

    #[Locked]
    public bool $newCityFailed = false;

    /**
     * Bestaetigung, dass hier bewusst ein weiterer Ort gleichen Namens entsteht —
     * siehe cities/create. Gleichnamige Orte gibt es wirklich (acht Neuenkirchen in
     * Niedersachsen); wir verbieten sie nicht, wir verlangen eine Entscheidung.
     */
    public bool $confirmDuplicateCity = false;

    /**
     * Vorhandene Orte gleichen Namens im gewaehlten Land, nur zum Anzeigen.
     *
     * @var array<int, array{id: int, latitude: float, longitude: float}>
     */
    public array $duplicateCityCandidates = [];

    public ?int $pendingCityHitIndex = null;

    /**
     * Vorhandene Orte gleichen Namens im gewaehlten Land.
     *
     * Sucht ueber LOWER(TRIM(name)) wie City::resolveOrCreate(), damit dieses Formular
     * denselben Bestand sieht wie die API.
     *
     * @return array<int, array{id: int, latitude: float, longitude: float}>
     */
    protected function duplicateCityCandidatesFor(string $name): array
    {
        if (trim($name) === '' || $this->newCityCountryId === null) {
            return [];
        }

        return City::matchingName($name, $this->newCityCountryId)
            ->map(fn (City $city): array => [
                'id' => $city->getKey(),
                'latitude' => (float) $city->latitude,
                'longitude' => (float) $city->longitude,
            ])
            ->all();
    }

    /**
     * Searches OpenStreetMap for a town, so a missing city can be added without leaving
     * the form. Coordinates come from the hit — organisers do not type WGS84.
     */
    public function searchCity(): void
    {
        $this->validate([
            'newCityCountryId' => ['required', 'exists:countries,id'],
            'newCityQuery' => ['required', 'string', 'min:3'],
        ]);

        $this->newCitySearched = true;
        $this->newCityFailed = false;
        $this->confirmDuplicateCity = false;
        $this->duplicateCityCandidates = [];
        $this->pendingCityHitIndex = null;

        $code = Country::find($this->newCityCountryId)?->code;

        $result = app(NominatimClient::class)->trySearch($this->newCityQuery, $code, featureType: 'settlement');

        if ($result['failed']) {
            $this->newCityResults = [];
            $this->newCityFailed = true;

            return;
        }

        $this->newCityResults = $result['hits']
            ->filter(fn (array $hit): bool => in_array($hit['category'] ?? null, ['place', 'boundary'], true))
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * Takes one search result and makes it the selected city.
     *
     * Order: same osm_type+osm_id → select; same name in country → confirmDuplicateCity;
     * otherwise create from the hit. Not the course useCity path (silent LOWER(name)).
     */
    public function chooseCity(int $index): void
    {
        $hit = $this->newCityResults[$index] ?? null;

        if ($hit === null) {
            return;
        }

        if (! auth()->user()?->can('create', City::class)) {
            $this->addError('newCityQuery', __('Du darfst keine Stadt anlegen.'));

            return;
        }

        $name = trim((string) ($hit['osm_name'] ?? ''));

        if ($name === '') {
            return;
        }

        $osmType = $hit['osm_type'] ?? null;
        $osmId = $hit['osm_id'] ?? null;

        if (! blank($osmType) && ! blank($osmId)) {
            $existing = City::query()
                ->where('osm_type', $osmType)
                ->where('osm_id', $osmId)
                ->first();

            if ($existing !== null) {
                $this->selectAddedCity($existing);

                return;
            }
        }

        if ($this->pendingCityHitIndex !== $index) {
            $this->confirmDuplicateCity = false;
        }

        $this->pendingCityHitIndex = $index;
        $this->duplicateCityCandidates = $this->duplicateCityCandidatesFor($name);

        if ($this->duplicateCityCandidates !== [] && ! $this->confirmDuplicateCity) {
            return;
        }

        $latitude = $hit['osm_lat'] ?? null;
        $longitude = $hit['osm_lon'] ?? null;

        if ($latitude === null || $longitude === null) {
            return;
        }

        $city = City::create([
            'country_id' => $this->newCityCountryId,
            'name' => $name,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'osm_type' => $osmType,
            'osm_id' => $osmId,
            'osm_name' => $hit['osm_name'] ?? null,
            'osm_address' => $hit['osm_address'] ?? null,
            'osm_lat' => $hit['osm_lat'] ?? null,
            'osm_lon' => $hit['osm_lon'] ?? null,
            'wikidata' => $hit['wikidata'] ?? null,
            'wikipedia' => $hit['wikipedia'] ?? null,
            'population' => $hit['population'] ?? null,
            'created_by' => auth()->id(),
        ]);

        $this->selectAddedCity($city);
    }

    protected function selectAddedCity(City $city): void
    {
        $this->city_id = $city->id;
        $this->reset([
            'newCityQuery',
            'newCityResults',
            'newCitySearched',
            'newCityFailed',
            'confirmDuplicateCity',
            'duplicateCityCandidates',
            'pendingCityHitIndex',
        ]);

        Flux::modal('add-city')->close();
    }

    public function createMeetup(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', new UniqueMeetupName],
            'city_id' => ['required', 'exists:cities,id'],
            'intro' => ['nullable', 'string'],
            'telegram_link' => ['nullable', 'url', 'max:255'],
            'webpage' => ['nullable', 'url', 'max:255'],
            'twitter_username' => ['nullable', 'string', 'max:255'],
            'matrix_group' => ['nullable', 'string', 'max:255'],
            'nostr' => ['nullable', 'string', 'max:255'],
            'simplex' => ['nullable', 'string'],
            'signal' => ['nullable', 'string', 'max:510'],
            'community' => ['required', 'string', 'max:255'],
            'visible_on_map' => ['boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,webp,avif', 'max:5120', 'dimensions:max_width=4000,max_height=4000'],
        ]);

        $meetup = Meetup::create($validated + ['created_by' => auth()->id()]);

        // Der Ersteller wird über das Meetup::created-Model-Event automatisch als Leiter
        // in die meetup_user-Pivot eingetragen (einheitlich mit MCP und REST-API).

        if ($this->logo) {
            $meetup
                ->addMedia($this->logo->getRealPath())
                ->usingName($meetup->name)
                ->toMediaCollection('logo');
        }

        session()->flash('status', __('Meetup erfolgreich erstellt!'));

        $this->redirect(route_with_country('meetups.edit', ['meetup' => $meetup]), navigate: true);
    }

    public function with(): array
    {
        return [
            // Column-limited: this Livewire request re-runs on every re-render (any
            // validation error, any wire:model.live change), and City carries
            // osm_relation/simplified_geojson — JSON blobs the picker never reads but
            // would otherwise hydrate for every row on every one of those round trips.
            'cities' => City::query()
                ->select(['id', 'name', 'country_id'])
                ->with(['country:id,name'])
                ->orderBy('name')
                ->get(),
            'countries' => Country::query()
                ->select(['id', 'name', 'code'])
                ->orderBy('countries.name')
                ->get(),
        ];
    }
}; ?>

<div class="max-w-4xl mx-auto p-6">
    <flux:heading size="xl" class="mb-8">{{ __('Neues Meetup erstellen') }}</flux:heading>

    <form wire:submit="createMeetup" class="space-y-10">

        <!-- Basic Information -->
        <flux:fieldset class="space-y-6">
            <flux:legend>{{ __('Grundlegende Informationen') }}</flux:legend>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <flux:file-upload wire:model="logo">
                    <!-- Custom logo uploader -->
                    <div class="
                            relative flex items-center justify-center size-20 rounded transition-colors cursor-pointer
                            border border-zinc-200 dark:border-white/10 hover:border-zinc-300 dark:hover:border-white/10
                            bg-zinc-100 hover:bg-zinc-200 dark:bg-white/10 hover:dark:bg-white/15 in-data-dragging:dark:bg-white/15
                        ">
                        @if($logo?->isPreviewable())
                            <img src="{{ $logo->temporaryUrl() }}" alt="Logo"
                                 class="size-full object-cover rounded"/>
                        @else
                            <!-- Show the default icon if no file is uploaded -->
                            <flux:icon name="user-group" variant="solid" class="text-zinc-500 dark:text-zinc-400"/>
                        @endif

                        <!-- Corner upload icon -->
                        <div class="absolute bottom-0 right-0 bg-white dark:bg-zinc-800 rounded">
                            <flux:icon name="arrow-up-circle" variant="solid" class="text-zinc-500 dark:text-zinc-400"/>
                        </div>
                    </div>

                    <flux:error name="logo"/>
                </flux:file-upload>

                <flux:field>
                    <flux:label>{{ __('Name') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="name" required/>
                    <flux:description>{{ __('Der Anzeigename für dieses Meetup') }}</flux:description>
                    <flux:error name="name"/>
                </flux:field>

                <flux:field>
                    <div class="flex items-center justify-between mb-2">
                        <flux:label>{{ __('Stadt') }} <span class="text-red-500">*</span></flux:label>
                        <flux:modal.trigger name="add-city">
                            <flux:button class="cursor-pointer" size="xs" variant="ghost" icon="plus">
                                {{ __('Stadt hinzufügen') }}
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                    <flux:select variant="listbox" searchable wire:model="city_id"
                                 placeholder="{{ __('Stadt auswählen') }}" required>
                        <x-slot name="search">
                            <flux:select.search class="px-4" placeholder="{{ __('Suche passende Stadt...') }}"/>
                        </x-slot>
                        @foreach($cities as $city)
                            <flux:select.option value="{{ $city->id }}">{{ $city->name }} ({{ $city->country->name }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:description>{{ __('Die nächstgrößte Stadt oder Ort') }}</flux:description>
                    <flux:error name="city_id"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Auf Karte sichtbar') }}</flux:label>
                    <flux:switch wire:model="visible_on_map"/>
                    <flux:description>{{ __('Soll dieses Meetup auf der Karte angezeigt werden?') }}</flux:description>
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Einführung') }}</flux:label>
                <flux:textarea wire:model="intro" rows="4"/>
                <flux:description>{{ __('Kurze Beschreibung des Meetups') }}</flux:description>
                <flux:error name="intro"/>
            </flux:field>
        </flux:fieldset>

        <!-- Links and Social Media -->
        <flux:fieldset class="space-y-6">
            <flux:legend>{{ __('Links & Soziale Medien') }}</flux:legend>

            <!-- Primary Links -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('Webseite') }}</flux:label>
                    <flux:input wire:model="webpage" type="url" placeholder="https://example.com"/>
                    <flux:description>{{ __('Offizielle Webseite oder Landingpage') }}</flux:description>
                    <flux:error name="webpage"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Telegram Link') }}</flux:label>
                    <flux:input wire:model="telegram_link" type="url" placeholder="https://t.me/gruppenname"/>
                    <flux:description>{{ __('Link zur Telegram-Gruppe oder zum Kanal') }}</flux:description>
                    <flux:error name="telegram_link"/>
                </flux:field>
            </div>

            <!-- Social Media -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('Twitter Benutzername') }}</flux:label>
                    <flux:input wire:model="twitter_username" placeholder="benutzername"/>
                    <flux:description>{{ __('Twitter-Handle ohne @ Symbol') }}</flux:description>
                    <flux:error name="twitter_username"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Matrix Gruppe') }}</flux:label>
                    <flux:input wire:model="matrix_group" placeholder="#gruppe:matrix.org"/>
                    <flux:description>{{ __('Matrix-Raum Bezeichner oder Link') }}</flux:description>
                    <flux:error name="matrix_group"/>
                </flux:field>
            </div>

            <!-- Decentralized Platforms -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('Nostr') }}</flux:label>
                    <flux:input wire:model="nostr" placeholder="npub..."/>
                    <flux:description>{{ __('Nostr öffentlicher Schlüssel oder Bezeichner') }}</flux:description>
                    <flux:error name="nostr"/>
                </flux:field>
            </div>

            <!-- Messaging Apps -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('SimpleX') }}</flux:label>
                    <flux:input wire:model="simplex"/>
                    <flux:description>{{ __('SimpleX Chat Kontaktinformationen') }}</flux:description>
                    <flux:error name="simplex"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Signal') }}</flux:label>
                    <flux:input wire:model="signal"/>
                    <flux:description>{{ __('Signal Kontakt- oder Gruppeninformationen') }}</flux:description>
                    <flux:error name="signal"/>
                </flux:field>
            </div>
        </flux:fieldset>

        <!-- Additional Information -->
        <flux:fieldset class="space-y-6">
            <flux:legend>{{ __('Zusätzliche Informationen') }}</flux:legend>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('Gemeinschaft') }}</flux:label>
                    <flux:select wire:model="community">
                        <flux:select.option value="">{{ __('Keine') }}</flux:select.option>
                        <flux:select.option value="einundzwanzig">{{ __('EINUNDZWANZIG Community') }}</flux:select.option>
                        <flux:select.option value="bitcoin">{{ __('Allgemeine Bitcoin Community') }}</flux:select.option>
                    </flux:select>
                    <flux:description>{{ __('Gemeinschafts- oder Organisationsname') }}</flux:description>
                    <flux:error name="community"/>
                </flux:field>
            </div>
        </flux:fieldset>

        <!-- Form Actions -->
        <div class="flex items-center justify-between pt-8 border-t border-gray-200 dark:border-gray-700">
            <flux:button class="cursor-pointer" variant="ghost" type="button" onclick="history.back()">
                {{ __('Abbrechen') }}
            </flux:button>

            <div class="flex items-center gap-4">
                {{-- Visible acknowledgement only, no wire:loading.attr="disabled":
                     the button is a button[type=submit] INSIDE this <form wire:submit>,
                     and Livewire v4's supportDisablingFormsDuringRequest already
                     disables every such button for the duration of the request
                     (vendor/livewire/livewire/dist/livewire.esm.js, disableForm()/
                     shouldMarkDisabled()), so the double-submit guard is covered.
                     Plain span, not <flux:text> — Flux renders `wire:loading` as
                     `wire:loading=""` instead of the bare attribute Livewire expects. --}}
                <span wire:loading wire:target="createMeetup"
                      class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Wird gespeichert…') }}
                </span>

                <flux:button class="cursor-pointer" variant="primary" type="submit">
                    {{ __('Meetup erstellen') }}
                </flux:button>
            </div>
        </div>
    </form>

    {{-- Sits outside the form: a nested <form> is invalid HTML, and pressing Enter in the
         search box would otherwise submit the meetup instead of searching. --}}
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
                        <flux:select.option value="{{ $country->id }}">
                            <div class="flex items-center space-x-2">
                                <img alt="{{ str($country->code)->lower() }}"
                                     src="{{ asset('vendor/blade-flags/country-'.str($country->code)->lower().'.svg') }}"
                                     width="24" height="12"/>
                                <span>{{ $country->name }}</span>
                            </div>
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="newCityCountryId"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Stadt') }}</flux:label>
                <div class="flex gap-2">
                    <flux:input wire:model="newCityQuery" wire:keydown.enter.prevent="searchCity"
                                placeholder="{{ __('z.B. Berlin') }}" data-testid="add-city-query"/>
                    <flux:button type="button" class="cursor-pointer" wire:click="searchCity" data-testid="add-city-search">
                        {{ __('Suchen') }}
                    </flux:button>
                </div>
                <flux:error name="newCityQuery"/>
            </flux:field>

            @if ($newCityFailed)
                <flux:callout variant="danger" icon="exclamation-triangle" data-testid="add-city-unavailable">
                    {{ __('OpenStreetMap ist gerade nicht erreichbar. Versuch es später noch einmal.') }}
                </flux:callout>
            @elseif ($newCityResults)
                <div class="flex flex-col gap-1" data-testid="add-city-results">
                    @foreach($newCityResults as $index => $hit)
                        <button type="button" wire:click="chooseCity({{ $index }})"
                                wire:key="city-hit-{{ $hit['osm_type'] }}-{{ $hit['osm_id'] }}"
                                data-testid="add-city-result-{{ $index }}"
                                class="min-h-11 rounded-md border border-zinc-200 p-2 text-start hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                            <div class="text-sm font-medium">{{ $hit['osm_name'] }}</div>
                            {{-- Issue #123: was `text-xs opacity-60`. Measured 5.742:1 light and
                                 6.364:1 dark, so it did not breach 1.4.3 — but it is the same
                                 line as the OSM picker's, and one picker dimming its result
                                 rows two different ways is a coin toss for the next editor.
                                 Named colour: 7.814:1 light, 10.210:1 dark. --}}
                            <div class="text-xs text-zinc-600 dark:text-zinc-300">{{ $hit['osm_address'] }}</div>
                        </button>
                    @endforeach
                </div>

                {{-- Rueckfrage aus Issue #33: gleichnamige Orte existieren wirklich.
                     Nicht verbieten, sondern zur Entscheidung machen. --}}
                @if ($duplicateCityCandidates !== [])
                    <div class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
                        <p class="font-semibold">
                            {{ trans_choice('Es gibt in diesem Land bereits :count Ort dieses Namens.|Es gibt in diesem Land bereits :count Orte dieses Namens.', count($duplicateCityCandidates), ['count' => count($duplicateCityCandidates)]) }}
                        </p>
                        {{-- Issue #123: measured 16.442:1 light / 10.603:1 dark. Stays. --}}
                        <ul class="mt-2 space-y-1 opacity-90">
                            @foreach ($duplicateCityCandidates as $candidate)
                                <li wire:key="dup-city-{{ $candidate['id'] }}">#{{ $candidate['id'] }} · {{ number_format($candidate['latitude'], 4) }} / {{ number_format($candidate['longitude'], 4) }}</li>
                            @endforeach
                        </ul>
                        <flux:checkbox class="mt-3" wire:model.live="confirmDuplicateCity"
                                       label="{{ __('Trotzdem als weiteren Ort gleichen Namens anlegen') }}"/>
                        <flux:button type="button" class="mt-3 cursor-pointer" variant="primary"
                                     wire:click="chooseCity({{ $pendingCityHitIndex }})"
                                     :disabled="! $confirmDuplicateCity">
                            {{ __('Stadt erstellen') }}
                        </flux:button>
                    </div>
                @endif
            @elseif ($newCitySearched)
                <flux:callout data-testid="add-city-empty">
                    {{ __('Keine Stadt gefunden. Prüf die Schreibweise oder das Land.') }}
                </flux:callout>
            @endif
        </div>
    </flux:modal>
</div>
