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

    /** Narrows the search; the country of the meetup or venue. */
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

        // Only the stored columns travel onwards; importance and category are ranking
        // aids for the search itself and have no place on the event.
        $this->place = [
            'osm_type' => $hit['osm_type'],
            'osm_id' => $hit['osm_id'],
            'osm_name' => $hit['osm_name'],
            'osm_address' => $hit['osm_address'],
            'osm_lat' => $hit['osm_lat'],
            'osm_lon' => $hit['osm_lon'],
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
            <div class="min-w-0">
                <div class="font-medium">{{ $place['osm_name'] }}</div>
                <div class="mt-0.5 text-xs opacity-60">{{ $place['osm_address'] }}</div>
                <div class="mt-1 text-xs opacity-60">
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
                        <div class="text-xs opacity-60">{{ $hit['osm_address'] }}</div>
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
