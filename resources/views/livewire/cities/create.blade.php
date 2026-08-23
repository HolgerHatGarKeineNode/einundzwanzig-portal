<?php

use App\Attributes\SeoDataAttribute;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Traits\SeoTrait;
use Illuminate\Validation\Rule;
use Livewire\Component;

new
#[SeoDataAttribute(key: 'cities_create')]
class extends Component {
    use SeoTrait;

    public $country = 'de';
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

    public function mount(): void
    {
        $this->country = request()->route('country', config('app.domain_country'));
        $this->country_id = Country::query()
            ->where('code', $this->country)
            ->value('id');
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

    public function createCity(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', 'unique:cities,name'],
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

        // Kein manuelles slug: HasSlug auf City ist dafuer zustaendig und erzeugt
        // 'laendercode-name'. Zwei Regeln nebeneinander liessen den Wert bei jedem
        // Speichern springen.
        $validated['created_by'] = auth()->id();
        $validated += $this->osmFields();

        $city = City::create($validated);

        session()->flash('status', __('City successfully created!'));

        // Siehe cities/edit: der Redirect entsteht im Livewire-Update, das kein
        // 'country' in der Route hat (Issue #28).
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
        <flux:heading size="xl">{{ __('Create City') }}</flux:heading>
    </div>

    <form wire:submit="createCity" class="space-y-8">
        <flux:fieldset>
            <flux:legend>{{ __('Basic Information') }}</flux:legend>

            <div class="space-y-6">
                <flux:input label="{{ __('Name') }}" wire:model="name" required/>

                <flux:select variant="listbox" searchable label="{{ __('Country') }}" wire:model.live="country_id" required>
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
                    <flux:select variant="listbox" searchable label="{{ __('Region') }}" wire:model="region_id">
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
                <flux:input label="{{ __('Population') }}" type="number" wire:model="population"/>
                <flux:input label="{{ __('Population Date') }}" wire:model="population_date" placeholder="e.g. 2024"/>
            </div>
        </flux:fieldset>

        <div class="flex gap-4">
            <flux:button type="submit" variant="primary">{{ __('Create City') }}</flux:button>
            <flux:button :href="route_with_country('cities.index')" variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
