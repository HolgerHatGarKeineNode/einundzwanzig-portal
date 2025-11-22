<?php

use App\Attributes\SeoDataAttribute;
use App\Models\City;
use App\Models\Country;
use App\Traits\SeoTrait;
use Livewire\Volt\Component;

new
#[SeoDataAttribute(key: 'cities_edit')]
class extends Component {
    use SeoTrait;

    public City $city;
    public string $name = '';
    public ?int $country_id = null;
    public float $latitude = 0;
    public float $longitude = 0;
    public ?int $population = null;
    public ?string $population_date = null;

    public function mount(City $city): void
    {
        $this->city = $city;
        $this->name = $city->name;
        $this->country_id = $city->country_id;
        $this->latitude = $city->latitude;
        $this->longitude = $city->longitude;
        $this->population = $city->population;
        $this->population_date = $city->population_date;
    }

    public function updateCity(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', 'unique:cities,name,'.$this->city->id],
            'country_id' => ['required', 'exists:countries,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'population' => ['nullable', 'integer', 'min:0'],
            'population_date' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['slug'] = str($validated['name'])->slug();

        $this->city->update($validated);

        session()->flash('status', __('City successfully updated!'));

        $this->redirect(route_with_country('cities.index'), navigate: true);
    }

    public function with(): array
    {
        return [
            'countries' => Country::query()->orderBy('name')->get(),
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
                <flux:input label="{{ __('Name') }}" wire:model="name" required/>

                <flux:select label="{{ __('Country') }}" wire:model="country_id" required>
                    <option value="">{{ __('Select a country') }}</option>
                    @foreach($countries as $country)
                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                    @endforeach
                </flux:select>
            </div>
        </flux:fieldset>

        <flux:fieldset>
            <flux:legend>{{ __('Coordinates') }}</flux:legend>

            <div class="grid grid-cols-2 gap-x-4 gap-y-6">
                <flux:input label="{{ __('Latitude') }}" type="number" step="any" wire:model="latitude" required/>
                <flux:input label="{{ __('Longitude') }}" type="number" step="any" wire:model="longitude" required/>
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
            <flux:button type="submit" variant="primary">{{ __('Update City') }}</flux:button>
            <flux:button :href="route_with_country('cities.index')" variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
