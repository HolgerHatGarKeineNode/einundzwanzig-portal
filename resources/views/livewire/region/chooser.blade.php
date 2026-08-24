<?php

use App\Models\Region;
use App\Support\RegionRoutes;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Der Einstieg in die Regions-Ansichten.
 *
 * Die Routen `meetups.index-region`, `meetups.map-region` und `cities.index-region`
 * existieren seit dem 2026-08-22 und funktionieren — aber nichts im Portal verwies auf
 * sie. 67 gepflegte Regionen, drei Routen, null Einstiegspunkte: gebaut, erreichbar und
 * unauffindbar. Diese Komponente ist die fehlende Tuer.
 *
 * Sie zeigt sich nur, wenn das Land ueberhaupt Regionen hat. Fuer die uebrigen 247
 * Laender ist die Seite unveraendert — eine leere Auswahl waere die schlechtere Antwort
 * als gar keine.
 */
new class extends Component {
    #[Locked]
    public string $country = 'de';

    /** Der Code der aktuell gewaehlten Region, oder leer fuer "alle im Land". */
    public string $region = '';

    /**
     * Der Routenname der TRAGENDEN Seite, festgehalten beim ersten Rendern.
     *
     * Nicht bei jedem Aufruf neu aus `request()->route()` lesen: bei einem
     * Livewire-Update heisst die Route `livewire.update`, und die steht in keiner
     * Zuordnung — der Waehler waere nach der ersten Interaktion verschwunden.
     */
    #[Locked]
    public string $pageRoute = '';

    /**
     * Land und Seitenroute kommen normalerweise aus der Route; beide sind trotzdem
     * uebergebbar, damit die Komponente pruefbar bleibt, ohne dass ein Test eine
     * gesperrte Property beschreiben muesste (was sie zu Recht nicht darf).
     */
    public function mount(?string $country = null, ?string $pageRoute = null, ?string $region = null): void
    {
        $this->country = $country ?? (string) request()->route('country', config('app.domain_country'));
        $this->region = $region ?? (string) (request()->route('region') ?? '');
        $this->pageRoute = $pageRoute ?? (string) (request()->route()?->getName() ?? '');
    }

    public function updatedRegion(string $value): void
    {
        [$plain, $withRegion] = RegionRoutes::pair($this->pageRoute) ?? [null, null];

        if ($plain === null) {
            return;
        }

        $this->redirectRoute(
            $value === '' ? $plain : $withRegion,
            $value === ''
                ? ['country' => $this->country]
                : ['country' => $this->country, 'region' => $value],
            navigate: true,
        );
    }

    public function with(): array
    {
        return [
            'regions' => Region::query()
                ->whereHas('country', fn ($query) => $query->whereRaw('LOWER(code) = ?', [mb_strtolower($this->country)]))
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'supported' => RegionRoutes::supports($this->pageRoute),
        ];
    }
}; ?>

<div>
    @if($supported && $regions->isNotEmpty())
        <flux:select variant="listbox" searchable wire:model.live="region"
                     placeholder="{{ __('Alle Regionen') }}" class="min-w-48">
            <x-slot name="search">
                <flux:select.search class="px-4" placeholder="{{ __('Region suchen...') }}"/>
            </x-slot>
            <flux:select.option value="">{{ __('Alle Regionen') }}</flux:select.option>
            {{-- `$item`, nicht `$region`: die Schleifenvariable wuerde sonst die
                 gleichnamige Property ueberschreiben und die Vorauswahl zerstoeren. --}}
            @foreach($regions as $item)
                <flux:select.option :key="$item->id" value="{{ $item->code }}">
                    {{ $item->name }}
                </flux:select.option>
            @endforeach
        </flux:select>
    @endif
</div>
