<?php

use App\Support\RegionRoutes;
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

    public function updatingCurrentCountry(mixed $value): void
    {
        abort_if(! is_string($value), 422);
    }

    /**
     * Beim Landwechsel wird die Region verlassen — sie gehoert zu genau einem Land.
     *
     * Vorher gingen ALLE gemerkten Routenparameter unveraendert mit, `region`
     * eingeschlossen: auf `/us/in/meetups` fuehrte ein Wechsel nach Deutschland damit auf
     * `/de/in/meetups`, und das ist ein 404 — Indiana ist kein deutsches Bundesland, und
     * ein unbekannter Regionscode antwortet ausdruecklich mit 404 statt mit einer leeren
     * Liste. Der Laenderwaehler war damit auf jeder Regionsseite eine Sackgasse.
     *
     * Umgebogen wird nur, was {@see RegionRoutes} kennt. Liefert `plain()` null, hat die
     * Route kein Regionspaar und bleibt unangetastet — dann ist auch kein `region`-
     * Parameter da, den man entfernen muesste.
     */
    public function updatedCurrentCountry()
    {
        $params = $this->currentRouteParams;
        $params['country'] = $this->currentCountry;

        $route = $this->currentRouteName;

        if (($plain = RegionRoutes::plain((string) $route)) !== null) {
            $route = $plain;
            unset($params['region']);
        }

        $this->redirectRoute($route, $params);
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
