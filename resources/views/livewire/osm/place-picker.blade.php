<?php

use App\Services\Osm\NominatimClient;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Modelable;
use Livewire\Component;

/**
 * Picks a place from OpenStreetMap and hands the parent form the fields the events
 * table stores.
 *
 * The search runs server-side on purpose. Nominatim's policy caps requests at one per
 * second and forbids stock user agents, so the call cannot happen in the browser: only
 * the server can throttle it, cache it and identify itself properly.
 *
 * Nothing here is required. The free-text location field beside it stays the right
 * answer for "TBA" or "follow the Signal group", which is exactly how the issue framed it.
 */
new class extends Component {
    /**
     * The chosen place, or an empty array. Keys match the event columns.
     *
     * @var array<string, mixed>
     */
    #[Modelable]
    public array $place = [];

    public string $query = '';

    /** Narrows the search to the country the event belongs to. */
    #[Locked]
    public ?string $countryCode = null;

    /** @var array<int, array<string, mixed>> */
    public array $results = [];

    public bool $searched = false;

    public function search(): void
    {
        $this->searched = true;

        if (mb_strlen(trim($this->query)) < 3) {
            $this->results = [];

            return;
        }

        $this->results = app(NominatimClient::class)
            ->search($this->query, $this->countryCode)
            ->take(5)
            ->values()
            ->all();
    }

    public function choose(int $index): void
    {
        $hit = $this->results[$index] ?? null;

        if ($hit === null) {
            return;
        }

        /*
         * Only the stored columns travel onwards; importance and category are ranking
         * aids for the search itself and have no place on the event.
         *
         * wikidata, wikipedia und population kommen seit dem extratags-Einbau dazu. Sie
         * sind fuer Staedte und Laender gedacht; die Event-Formulare filtern in
         * osmFields() ohnehin auf ihre sechs Spalten und sehen sie nie.
         */
        $this->place = [
            'osm_type' => $hit['osm_type'],
            'osm_id' => $hit['osm_id'],
            'osm_name' => $hit['osm_name'],
            'osm_address' => $hit['osm_address'],
            'osm_lat' => $hit['osm_lat'],
            'osm_lon' => $hit['osm_lon'],
            'wikidata' => $hit['wikidata'] ?? null,
            'wikipedia' => $hit['wikipedia'] ?? null,
            'population' => $hit['population'] ?? null,
        ];

        $this->results = [];
        $this->query = '';
        $this->searched = false;
    }

    public function clearPlace(): void
    {
        $this->place = [];
    }

    public function getChosenProperty(): bool
    {
        return ! empty($this->place['osm_id']);
    }
}; ?>

<flux:field>
    <flux:label>{{ __('Ort auf der Karte') }}</flux:label>

    @if ($this->chosen)
        <div class="flex items-start justify-between gap-4 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700"
             data-testid="osm-chosen">
            {{-- Issue #123: these two lines were `text-xs opacity-60`, and the issue
                 called them its worst case. Measured at the pixel, they were not:
                 the box inherits the page's plain black, and black at 60% over
                 white is 5.742:1 (6.364:1 on the dark page) against a 4.5:1 bar.
                 12px does not raise that bar — WCAG 1.4.3 only ever LOWERS it, at
                 24px or 18.66px bold.

                 The class went anyway. `opacity` fades the glyph and the ground
                 it sits on by the same factor, so it was spending three quarters
                 of the pair's reserve (21:1 down to 5.742:1) to say "secondary",
                 which a named colour says for nothing — and it left the lines one
                 token change away from failing without any warning. Same two
                 tokens the tag picker uses for its provenance line: 7.814:1 light,
                 10.210:1 dark. --}}
            <div class="min-w-0">
                <div class="font-medium">{{ $place['osm_name'] }}</div>
                <div class="mt-0.5 text-xs text-zinc-600 dark:text-zinc-300">{{ $place['osm_address'] }}</div>
                <div class="mt-1 text-xs text-zinc-600 dark:text-zinc-300">
                    OSM {{ $place['osm_type'] }}/{{ $place['osm_id'] }}
                </div>
            </div>

            <flux:button size="sm" variant="ghost" wire:click="clearPlace" data-testid="osm-clear">
                {{ __('Entfernen') }}
            </flux:button>
        </div>
    @else
        <div class="flex gap-2">
            <flux:input
                wire:model="query"
                wire:keydown.enter.prevent="search"
                placeholder="{{ __('z.B. Café Mustermann, Hauptstr. 1') }}"
                data-testid="osm-query"
            />
            <flux:button wire:click="search" data-testid="osm-search">
                {{ __('Suchen') }}
            </flux:button>
        </div>

        @if ($results)
            <div class="mt-2 flex flex-col gap-1" data-testid="osm-results">
                @foreach ($results as $index => $hit)
                    <button type="button"
                            wire:click="choose({{ $index }})"
                            wire:key="osm-hit-{{ $hit['osm_type'] }}-{{ $hit['osm_id'] }}"
                            data-testid="osm-result-{{ $index }}"
                            class="rounded-md border border-zinc-200 p-2 text-start hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                        <div class="text-sm font-medium">{{ $hit['osm_name'] }}</div>
                        <div class="text-xs text-zinc-600 dark:text-zinc-300">{{ $hit['osm_address'] }}</div>
                    </button>
                @endforeach
            </div>
        @elseif ($searched)
            <flux:callout class="mt-2" data-testid="osm-empty">
                {{ __('Nichts gefunden. Trag den Ort einfach als Text ein — das Feld darunter genügt.') }}
            </flux:callout>
        @endif
    @endif

    <flux:description>
        {{ __('Optional. Ein Kartenort macht das Event auffindbar; für „wird noch bekannt gegeben" reicht das Textfeld.') }}
    </flux:description>
</flux:field>
