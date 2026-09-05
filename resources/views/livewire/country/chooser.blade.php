<?php

use App\Support\RegionRoutes;
use Illuminate\Support\Collection;
use Livewire\Component;
use WW\Countries\Models\Country;

new class extends Component
{
    public $currentRouteName;

    public $currentRouteParams;

    public ?string $currentCountry = null;

    /**
     * The option values this select offers — lowercased ISO codes, in the order the
     * list renders.
     *
     * Read by BOTH mount() and the view, deliberately. Issue #105 was the two sides
     * disagreeing: the options were lowercased here while the bound value came raw
     * off the route segment, so `/DE/meetups` bound `DE` against a list holding only
     * `de` and Flux threw `Could not find option for value "DE"` while rendering.
     * One source cannot drift from itself.
     *
     * @return Collection<int, Country>
     */
    public function getCountryOptionsProperty(): Collection
    {
        return Country::all();
    }

    /**
     * The offered spelling of a route segment, or null when nothing is offered.
     *
     * Normalised on the VALUE side rather than by widening the options, for the same
     * reason #73 chose that side: the option list is the canonical set — it is what
     * every link this component builds points at — while the route segment is
     * whatever a visitor typed or a link happened to carry. Since #78 made country
     * codes case-insensitive, an uppercase segment resolves instead of 404ing, so
     * this is now reachable rather than theoretical.
     *
     * null renders Flux's placeholder. updatedCurrentCountry() only fires on a real
     * selection, so it can never redirect on the null.
     */
    private function offeredCountry(mixed $segment): ?string
    {
        if (! is_string($segment) || $segment === '') {
            return null;
        }

        $wanted = mb_strtolower($segment);

        foreach ($this->countryOptions as $country) {
            $value = mb_strtolower((string) $country->iso_code);

            if ($value === $wanted) {
                return $value;
            }
        }

        return null;
    }

    public function mount(): void
    {
        $this->currentCountry = $this->offeredCountry(
            request()->route('country', config('app.domain_country'))
        );
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
        @foreach($this->countryOptions as $country)
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
