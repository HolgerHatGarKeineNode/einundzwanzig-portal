<?php

use Livewire\Component;

new class extends Component {
    public $currentRouteName;
    public $currentRouteParams;
    public string $currentCountry = 'de';

    public function mount(): void
    {
        $this->currentCountry = request()->route('country', config('app.domain_country'));
        $this->currentRouteName = request()->route()->getName();
        $this->currentRouteParams = request()->route()->parameters();
    }

    public function updatedCurrentCountry()
    {
        $this->currentRouteParams['country'] = $this->currentCountry;
        $this->redirectRoute($this->currentRouteName, $this->currentRouteParams);
    }
}; ?>

<div>
    <flux:select variant="listbox" searchable placeholder="{{ __('Wähle dein Land...') }}"
                 wire:model.live.debounce="currentCountry">
        <x-slot name="search">
            <flux:select.search class="px-4" placeholder="{{ __('Suche dein Land...') }}"/>
        </x-slot>
        @foreach(\WW\Countries\Models\Country::all() as $country)
            <flux:select.option value="{{ str($country->iso_code)->lower() }}">
                <div class="flex items-center space-x-2">
                    <img alt="{{ str($country->iso_code)->lower() }}"
                         src="{{ asset('vendor/blade-flags/country-'.str($country->iso_code)->lower().'.svg') }}"
                         width="24" height="12"/>
                    <span>{{ $country->name }}</span>
                </div>
            </flux:select.option>
        @endforeach
    </flux:select>
</div>
