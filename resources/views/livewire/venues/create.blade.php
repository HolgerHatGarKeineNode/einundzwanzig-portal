<?php

use App\Attributes\SeoDataAttribute;
use App\Models\Venue;
use App\Models\City;
use App\Traits\SeoTrait;
use Livewire\Component;

new
#[SeoDataAttribute(key: 'venues_create')]
class extends Component {
    use SeoTrait;

    public $country = 'de';
    public string $name = '';
    public ?int $city_id = null;
    public string $street = '';

    public function mount(): void
    {
        $this->country = request()->route('country', config('app.domain_country'));
    }

    public function createVenue(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', 'unique:venues,name'],
            'city_id' => ['required', 'exists:cities,id'],
            'street' => ['required', 'string', 'max:255'],
        ]);

        $validated['slug'] = str($validated['name'])->slug();
        $validated['created_by'] = auth()->id();

        $venue = Venue::create($validated);

        session()->flash('status', __('Venue successfully created!'));

        $this->redirect(route_with_country('venues.index'), navigate: true);
    }

    public function with(): array
    {
        return [
            'cities' => City::query()
                ->with('country')
                ->whereHas('country', fn($query) => $query->where('countries.code', $this->country))
                ->orderBy('name')->get(),
        ];
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Create Venue') }}</flux:heading>
    </div>

    <form wire:submit="createVenue" class="space-y-8">
        <flux:fieldset>
            <flux:legend>{{ __('Venue Information') }}</flux:legend>

            <div class="space-y-6">
                <flux:input label="{{ __('Name') }}" wire:model="name" required/>

                <flux:select placeholder="{{ __('Wähle die Stadt aus...') }}" variant="listbox" searchable
                             label="{{ __('City') }}" wire:model="city_id" required>
                    @foreach($cities as $city)
                        <flux:select.option value="{{ $city->id }}">
                            {{ $city->name }}
                            @if($city->country)
                                ({{ $city->country->name }})
                            @endif
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input label="{{ __('Street') }}" wire:model="street" required/>
            </div>
        </flux:fieldset>

        <div class="flex gap-4">
            <flux:button type="submit" variant="primary">{{ __('Create Venue') }}</flux:button>
            <flux:button :href="route_with_country('venues.index')" variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
