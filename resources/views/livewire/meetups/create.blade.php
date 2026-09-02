<?php

use App\Attributes\SeoDataAttribute;
use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Traits\SeoTrait;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[SeoDataAttribute(key: 'meetups_create')]
class extends Component {
    use WithFileUploads;
    use SeoTrait;

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
    public string $newCityName = '';
    public ?int $newCityCountryId = null;
    public ?float $newCityLatitude = null;
    public ?float $newCityLongitude = null;

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


    /**
     * Vorhandene Orte gleichen Namens im gewaehlten Land.
     *
     * Sucht ueber LOWER(TRIM(name)) wie City::resolveOrCreate(), damit dieses Formular
     * denselben Bestand sieht wie die API.
     *
     * @return array<int, array{id: int, latitude: float, longitude: float}>
     */
    protected function duplicateCityCandidates(): array
    {
        if (trim($this->newCityName) === '' || $this->newCityCountryId === null) {
            return [];
        }

        return City::matchingName($this->newCityName, $this->newCityCountryId)
            ->map(fn (City $city): array => [
                'id' => $city->getKey(),
                'latitude' => (float) $city->latitude,
                'longitude' => (float) $city->longitude,
            ])
            ->all();
    }

    public function createCity(): void
    {
        /*
         * Trimmen VOR der Validierung, nicht danach. Die unique-Regel unten prueft den
         * Wert, den sie bekommt — steht der Trim erst beim Speichern, laesst sie
         * "Offenburg " an einem vorhandenen "Offenburg" vorbei und erzeugt genau die
         * Dublette, die sie verhindern soll. Belegt: 12 der 305 Staedte in Produktion
         * tragen ein nachgestelltes Leerzeichen, "Offenburg " steht dort seit 2023
         * neben "Offenburg".
         */
        $this->newCityName = trim($this->newCityName);
        $this->duplicateCityCandidates = $this->duplicateCityCandidates();

        $validated = $this->validate([
            // Landesbezogen, nicht global (Issue #33): Paris in Frankreich und Paris in
            // Texas sind kein Konflikt. Innerhalb eines Landes bleibt die Bremse.
            'newCityName' => [
                'required', 'string', 'max:255',
                ...($this->confirmDuplicateCity
                    ? []
                    : [Rule::unique('cities', 'name')->where('country_id', $this->newCityCountryId)]),
            ],
            'newCityCountryId' => ['required', 'exists:countries,id'],
            'newCityLatitude' => ['required', 'numeric', 'between:-90,90'],
            'newCityLongitude' => ['required', 'numeric', 'between:-180,180'],
        ], [], [
            'newCityLatitude' => __('Breitengrad'),
            'newCityLongitude' => __('Längengrad'),
        ]);

        if ((float) $validated['newCityLatitude'] === 0.0 && (float) $validated['newCityLongitude'] === 0.0) {
            $this->addError('newCityLatitude', __('Breiten- und Längengrad dürfen nicht beide 0 sein.'));

            return;
        }

        $city = City::create([
            'name' => $validated['newCityName'],
            'country_id' => $validated['newCityCountryId'],
            'latitude' => $validated['newCityLatitude'],
            'longitude' => $validated['newCityLongitude'],
            // slug uebernimmt HasSlug auf City.
            'created_by' => auth()->id(),
        ]);

        $this->city_id = $city->id;
        $this->reset(['newCityName', 'newCityCountryId', 'newCityLatitude', 'newCityLongitude', 'confirmDuplicateCity', 'duplicateCityCandidates']);

        \Flux\Flux::modal('add-city')->close();
    }

    public function createMeetup(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', new \App\Rules\UniqueMeetupName],
            'city_id' => ['required', 'exists:cities,id'],
            'intro' => ['nullable', 'string'],
            'telegram_link' => ['nullable', 'url', 'max:255'],
            'webpage' => ['nullable', 'url', 'max:255'],
            'twitter_username' => ['nullable', 'string', 'max:255'],
            'matrix_group' => ['nullable', 'string', 'max:255'],
            'nostr' => ['nullable', 'string', 'max:255'],
            'simplex' => ['nullable', 'string',],
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

            <flux:button class="cursor-pointer" variant="primary" type="submit">
                {{ __('Meetup erstellen') }}
            </flux:button>
        </div>
    </form>

    <!-- Add City Modal -->
    <flux:modal name="add-city" variant="flyout" wire:key="add-city-modal">
        <form wire:submit="createCity" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Stadt hinzufügen') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Füge eine neue Stadt zur Datenbank hinzu.') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Stadtname') }} <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="newCityName" placeholder="{{ __('z.B. Berlin') }}" required/>
                <flux:error name="newCityName"/>
                {{-- Rueckfrage aus Issue #33: gleichnamige Orte existieren wirklich.
                     Nicht verbieten, sondern zur Entscheidung machen. --}}
                @if ($duplicateCityCandidates !== [])
                    <div class="mt-3 rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
                        <p class="font-semibold">
                            {{ trans_choice('Es gibt in diesem Land bereits :count Ort dieses Namens.|Es gibt in diesem Land bereits :count Orte dieses Namens.', count($duplicateCityCandidates), ['count' => count($duplicateCityCandidates)]) }}
                        </p>
                        <ul class="mt-2 space-y-1 opacity-90">
                            @foreach ($duplicateCityCandidates as $candidate)
                                <li>#{{ $candidate['id'] }} · {{ number_format($candidate['latitude'], 4) }} / {{ number_format($candidate['longitude'], 4) }}</li>
                            @endforeach
                        </ul>
                        <flux:checkbox class="mt-3" wire:model.live="confirmDuplicateCity"
                                       label="{{ __('Trotzdem als weiteren Ort gleichen Namens anlegen') }}"/>
                    </div>
                @endif
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Land') }} <span class="text-red-500">*</span></flux:label>
                <flux:select variant="listbox" searchable wire:model="newCityCountryId"
                             placeholder="{{ __('Land auswählen') }}">
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

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Breitengrad') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="newCityLatitude" type="number" step="0.000001" placeholder="52.520008"
                                required/>
                    <flux:error name="newCityLatitude"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Längengrad') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="newCityLongitude" type="number" step="0.000001" placeholder="13.404954"
                                required/>
                    <flux:error name="newCityLongitude"/>
                </flux:field>
            </div>

            <div class="flex gap-2">
                <flux:spacer/>

                <flux:modal.close>
                    <flux:button class="cursor-pointer" type="button"
                                 variant="ghost">{{ __('Abbrechen') }}</flux:button>
                </flux:modal.close>

                <flux:button class="cursor-pointer" type="submit"
                             variant="primary">{{ __('Stadt erstellen') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
