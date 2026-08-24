<?php

use App\Attributes\SeoDataAttribute;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Traits\SeoTrait;
use Illuminate\Validation\Rule;
use Livewire\Component;

new
#[SeoDataAttribute(key: 'cities_edit')]
class extends Component {
    use SeoTrait;

    public City $city;
    public string $name = '';
    public ?int $country_id = null;
    public ?int $region_id = null;

    /**
     * Der aus der OSM-Suche gewaehlte Ort, oder ein leeres Array.
     *
     * Der Picker daneben ist derselbe, den die Event-Formulare benutzen — die Suche
     * laeuft serverseitig, weil Nominatims Policy Drosselung und einen echten
     * User-Agent verlangt, und beides kann nur der Server garantieren.
     *
     * @var array<string, mixed>
     */
    public array $osmPlace = [];
    public ?float $latitude = null;
    public ?float $longitude = null;
    public ?int $population = null;
    public ?string $population_date = null;

    /** Der Laenderkontext, mit dem der Nutzer hergekommen ist. */
    public string $country = 'de';

    /**
     * Darf dieser Nutzer die Identitaetsfelder aendern (Name, Land, Region,
     * Einwohnerzahl, Stichjahr)? Steuert die Sperre im Formular — die Durchsetzung
     * steht in updateCity(), nicht hier.
     */
    public bool $canEditIdentity = false;

    public function mount(City $city): void
    {
        /*
         * Issue #30: Anreichern darf jeder angemeldete Nutzer, und das war im Portal
         * immer schon so. Neu ist nur, dass es jetzt auch geprueft wird statt bloss
         * zu gelten — und dass die fuenf Identitaetsfelder daneben eine eigene
         * Ability haben.
         */
        $this->authorize('update', $city);

        // Fail-closed: ohne angemeldeten Nutzer bleibt die Identitaet gesperrt. Der
        // authorize()-Aufruf darueber wirft fuer einen Gast ohnehin, aber diese
        // Property darf sich nicht darauf verlassen, dass die Zeile davor stehen bleibt.
        $this->canEditIdentity = auth()->user()?->can('updateIdentity', $city) ?? false;

        $this->country = request()->route('country', config('app.domain_country'));
        $this->city = $city;
        $this->name = $city->name;
        $this->country_id = $city->country_id;
        $this->region_id = $city->region_id;
        // Vorhandene Referenz zurueck in den Picker, damit sie beim Speichern nicht faellt.
        $this->osmPlace = $city->osm_id ? [
            'osm_type' => $city->osm_type,
            'osm_id' => $city->osm_id,
            'osm_name' => $city->osm_name,
            'osm_address' => $city->osm_address,
            'osm_lat' => $city->osm_lat,
            'osm_lon' => $city->osm_lon,
            'wikidata' => $city->wikidata,
            'wikipedia' => $city->wikipedia,
        ] : [];
        $this->latitude = $city->latitude;
        $this->longitude = $city->longitude;
        $this->population = $city->population;
        $this->population_date = $city->population_date;
    }

    /**
     * Ein Landwechsel macht die gewaehlte Region ungueltig — sonst haenge die Stadt an
     * einem Bundesstaat eines anderen Landes.
     */

    /**
     * Der Laendercode fuer die Suche, damit "Springfield" nicht die halbe Welt trifft.
     */
    public function getPickerCountryCodeProperty(): ?string
    {
        return $this->country_id
            ? Country::query()->whereKey($this->country_id)->value('code')
            : null;
    }

    public function updatedCountryId(): void
    {
        $this->region_id = null;
    }

    public function updateCity(): void
    {
        /*
         * Der Riegel, nicht die Anzeige. Das Formular sperrt die Felder bereits, aber
         * eine gesperrte Eingabe ist nur eine Bitte: `wire:model`-Properties lassen
         * sich aus dem Browser heraus setzen, ohne dass das Formular mitspielt.
         *
         * Geprueft wird gegen den BESTAND, nicht gegen die Anwesenheit eines Feldes —
         * das Formular schickt immer alle Werte mit, auch unveraenderte. Erst ein
         * abweichender Wert ist eine Identitaetsaenderung, und nur die braucht die
         * zweite Ability.
         */
        $identityChanges = $this->city->identityChanges([
            'name' => $this->name,
            'country_id' => $this->country_id,
            'region_id' => $this->region_id,
            'population' => $this->population,
            'population_date' => $this->population_date,
        ]);

        abort_if(
            $identityChanges !== [] && ! (auth()->user()?->can('updateIdentity', $this->city) ?? false),
            403,
            __('Diese Felder darf nur der Ersteller oder ein City-Steward ändern.'),
        );

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', 'unique:cities,name,'.$this->city->id],
            'country_id' => ['required', 'exists:countries,id'],
            // Die Region MUSS zum gewaehlten Land gehoeren; ohne diese Einschraenkung
            // liesse sich jede beliebige Region-ID unterschieben.
            'region_id' => [
                'nullable',
                'integer',
                Rule::exists('regions', 'id')->where('country_id', $this->country_id),
            ],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'population' => ['nullable', 'integer', 'min:0'],
            'population_date' => ['nullable', 'string', 'max:255'],
        ], [], [
            'latitude' => __('Breitengrad'),
            'longitude' => __('Längengrad'),
        ]);

        if ((float) $validated['latitude'] === 0.0 && (float) $validated['longitude'] === 0.0) {
            $this->addError('latitude', __('Breiten- und Längengrad dürfen nicht beide 0 sein.'));

            return;
        }

        // Kein manuelles slug — siehe cities/create. HasSlug erzeugt ihn beim Anlegen
        // und laesst ihn danach stehen.
        $this->city->update($validated + $this->osmFields());

        session()->flash('status', __('City successfully updated!'));

        /*
         * Das Land ausdruecklich mitgeben: dieser Redirect entsteht in einem
         * Livewire-Update, dessen Route `livewire.update` heisst und kein 'country'
         * traegt. Ohne den Parameter landete der Nutzer nach jedem Speichern in der
         * deutschen Liste zurueck (Issue #28).
         */
        $this->redirect(
            route_with_country('cities.index', ['country' => $this->pickerCountryCode ?? $this->country]),
            navigate: true,
        );
    }


    /**
     * Die OSM-Spalten aus dem gewaehlten Ort, immer alle acht.
     *
     * Auch die leeren: beim Bearbeiten muss ein entfernter Ort die alten Werte
     * loeschen, und ein weggelassener Schluessel liesse sie stehen.
     *
     * @return array<string, mixed>
     */
    private function osmFields(): array
    {
        $keys = [
            'osm_type', 'osm_id', 'osm_name', 'osm_address',
            'osm_lat', 'osm_lon', 'wikidata', 'wikipedia',
        ];

        return collect($keys)
            ->mapWithKeys(fn (string $key): array => [$key => $this->osmPlace[$key] ?? null])
            ->all();
    }

    /**
     * Uebernimmt Koordinaten und Einwohnerzahl aus dem OSM-Ort, aber nur in leere Felder.
     *
     * Eine von Hand eingetragene Korrektur zu ueberschreiben waere die unangenehmste Art,
     * hilfsbereit zu sein.
     */
    public function updatedOsmPlace(): void
    {
        if (($this->osmPlace['osm_id'] ?? null) === null) {
            return;
        }

        $this->latitude ??= $this->osmPlace['osm_lat'] ?? null;
        $this->longitude ??= $this->osmPlace['osm_lon'] ?? null;
        $this->population ??= $this->osmPlace['population'] ?? null;
    }

    public function with(): array
    {
        return [
            'countries' => Country::query()->orderBy('name')->get(),
            'regions' => $this->country_id
                ? Region::query()->where('country_id', $this->country_id)->orderBy('name')->get()
                : collect(),
        ];
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Edit City') }}: {{ $city->name }}</flux:heading>
    </div>

    <form wire:submit="updateCity" class="space-y-8">
        <flux:fieldset>
            <flux:legend>{{ __('Basic Information') }}</flux:legend>

            <div class="space-y-6">
                {{-- Issue #30: Anreichern steht jedem offen, die Identitaet nicht. Wer
                     sie nicht aendern darf, sieht die Werte weiterhin — sie sind Kontext
                     fuer die Arbeit daneben — kann sie aber nicht anfassen. Der Riegel
                     sitzt in updateCity(); das hier ist die Anzeige dazu. --}}
                @unless($canEditIdentity)
                    <flux:callout icon="lock-closed" variant="secondary">
                        <flux:callout.heading>{{ __('Name, country, region and population are locked') }}</flux:callout.heading>
                        <flux:callout.text>
                            {{ __('You can enrich this city — OpenStreetMap reference, Wikidata, Wikipedia and coordinates. The fields that identify the city are reserved for its creator and the city stewards, because other records depend on them.') }}
                        </flux:callout.text>
                    </flux:callout>
                @endunless

                <flux:input label="{{ __('Name') }}" wire:model="name" required :disabled="! $canEditIdentity"/>

                {{-- Identisch zu cities/create: rohe <option>-Tags koennen keine Flagge
                     tragen und liefern in einer Liste von ueber 240 Laendern keine Suche.
                     Wer eine Stadt anlegt und dieselbe danach bearbeitet, bekam bisher
                     zwei verschiedene Bedienungen fuer dasselbe Feld. --}}
                <flux:select variant="listbox" searchable label="{{ __('Country') }}" wire:model.live="country_id" required :disabled="! $canEditIdentity">
                    <flux:select.option value="">{{ __('Select a country') }}</flux:select.option>
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

                {{-- Nur Laender mit gepflegten Regionen zeigen das Feld; fuer alle anderen
                     bleibt das Formular unveraendert. --}}
                @if($regions->isNotEmpty())
                    <flux:select variant="listbox" searchable label="{{ __('Region') }}" wire:model="region_id" :disabled="! $canEditIdentity">
                        <flux:select.option value="">{{ __('No region') }}</flux:select.option>
                        @foreach($regions as $region)
                            <flux:select.option :key="$region->id" value="{{ $region->id }}">
                                {{ $region->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                {{-- Derselbe Picker wie in den Event-Formularen. Optional: eine Stadt ohne
                     OSM-Bezug bleibt genauso gueltig wie bisher. --}}
                <flux:field>
                    <flux:label>{{ __('OpenStreetMap') }}</flux:label>
                    <livewire:osm.place-picker
                        wire:model.live="osmPlace"
                        :country-code="$this->pickerCountryCode"
                    />
                    <flux:description>
                        {{ __('Optional. Verknüpft die Stadt mit ihrem OpenStreetMap-Eintrag und füllt leere Koordinaten.') }}
                    </flux:description>
                </flux:field>
            </div>
        </flux:fieldset>

        <flux:fieldset>
            <flux:legend>{{ __('Coordinates') }}</flux:legend>

            <div class="grid grid-cols-2 gap-x-4 gap-y-6">
                <flux:input label="{{ __('Latitude') }}" type="number" step="any" wire:model="latitude" required/>
                <flux:input label="{{ __('Longitude') }}" type="number" step="any" wire:model="longitude" required/>
            </div>

            <div class="my-2">
                <flux:link href="https://www.mappr.co/latitude-longitude-finder/">https://www.mappr.co/latitude-longitude-finder/</flux:link>
            </div>
        </flux:fieldset>

        <flux:fieldset>
            <flux:legend>{{ __('Demographics') }}</flux:legend>

            <div class="grid grid-cols-2 gap-x-4 gap-y-6">
                <flux:input label="{{ __('Population') }}" type="number" wire:model="population" :disabled="! $canEditIdentity"/>
                <flux:input label="{{ __('Population Date') }}" wire:model="population_date" placeholder="e.g. 2024" :disabled="! $canEditIdentity"
                            description:trailing="{{ __('Together with the population figure and the boundary data, this decides whether this city\'s meetups appear in the BTC Map export.') }}"/>
            </div>
        </flux:fieldset>

        <div class="flex gap-4">
            <flux:button type="submit" variant="primary">{{ __('Update City') }}</flux:button>
            <flux:button :href="route_with_country('cities.index')" variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
