<?php

use App\Attributes\SeoDataAttribute;
use App\Models\Venue;
use App\Models\City;
use App\Traits\SeoTrait;
use Livewire\Component;

new
#[SeoDataAttribute(key: 'venues_edit')]
class extends Component {
    use SeoTrait;

    public Venue $venue;
    public string $name = '';
    public ?int $city_id = null;
    public string $street = '';

    public function mount(Venue $venue): void
    {
        $this->venue = $venue;
        $this->name = $venue->name;
        $this->city_id = $venue->city_id;
        $this->street = $venue->street;
    }

    public function updateVenue(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', 'unique:venues,name,'.$this->venue->id],
            'city_id' => ['required', 'exists:cities,id'],
            'street' => ['required', 'string', 'max:255'],
        ]);

        $validated['slug'] = str($validated['name'])->slug();

        $this->venue->update($validated);

        session()->flash('status', __('Venue successfully updated!'));

        $this->redirect(route_with_country('venues.index'), navigate: true);
    }

    public function with(): array
    {
        return [
            'cities' => City::query()->with('country')->orderBy('name')->get(),
        ];
    }
}; ?>

<div>
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Edit Venue') }}: {{ $venue->name }}</flux:heading>
    </div>

    <form wire:submit="updateVenue" class="space-y-8">
        <flux:fieldset>
            <flux:legend>{{ __('Venue Information') }}</flux:legend>

            <div class="space-y-6">
                <flux:input label="{{ __('Name') }}" wire:model="name" required/>

                <flux:select label="{{ __('City') }}" wire:model="city_id" required>
                    <option value="">{{ __('Select a city') }}</option>
                    @foreach($cities as $city)
                        <option value="{{ $city->id }}">
                            {{ $city->name }}
                            @if($city->country)
                                ({{ $city->country->name }})
                            @endif
                        </option>
                    @endforeach
                </flux:select>

                <flux:input label="{{ __('Street') }}" wire:model="street" required/>
            </div>
        </flux:fieldset>

        <div class="flex gap-4">
            <flux:button type="submit" variant="primary">{{ __('Update Venue') }}</flux:button>
            <flux:button :href="route_with_country('venues.index')" variant="ghost">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
