<?php

use App\Attributes\SeoDataAttribute;
use App\Models\Meetup;
use App\Traits\SeoTrait;
use Livewire\Volt\Component;

new
#[SeoDataAttribute(key: 'meetups_map')]
class extends Component {
    use SeoTrait;

    public string $country = 'de';
    public float $latitude = 0.0;
    public float $longitude = 0.0;

    public function mount(): void
    {
        $this->country = request()->route('country');
        $geoCountry = \Lwwcas\LaravelCountries\Models\Country::query()
            ->where('iso_alpha_2', str($this->country)->upper())
            ->first()
            ?->coordinates()
            ->first();
        $this->latitude = $geoCountry->latitude ?? 51.165691;
        $this->longitude = $geoCountry->longitude ?? 10.451526;
    }

    public function with(): array
    {
        return [
            'meetups' => Meetup::with(['city:id,longitude,latitude'])
                ->select([
                    'meetups.id',
                    'meetups.city_id',
                    'meetups.name',
                ])
                ->get(),
        ];
    }
}; ?>

<div>
    <style>
        #map {
            height: 90vh;
            z-index: 0 !important;
        }

        #map:focus {
            outline: none;
        }
    </style>
    @php
        $attribution = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
    @endphp
    <div>
        <flux:heading>{{ __('Zoom = STRG+Scroll') }}</flux:heading>
    </div>
    <div x-data="{
            markers: @js($meetups),
            latitude: $wire.entangle('latitude'),
            longitude: $wire.entangle('longitude'),
            initializeMap() {
                const map = L.map($refs.map, {
                    scrollWheelZoom: false
                }).setView([this.latitude, this.longitude], 6);

                L.tileLayer('https://tile.openstreetmap.de/{z}/{x}/{y}.png', {
                    minZoom: 0,
                    maxZoom: 18,
                    attribution: '{{ $attribution }}'
                }).addTo(map);

                // Custom BTC icon
                const btcIcon = L.icon({
                    iconUrl: '/img/btc_marker.png',
                    iconSize: [32, 32],     // Full size of the image
                    iconAnchor: [16, 32],   // Bottom-center of icon (adjust if needed)
                    popupAnchor: [0, -32],  // Popup opens above the icon
                    shadowUrl: null         // No shadow for simplicity
                });

                this.markers.forEach(marker => {
                    L.marker([marker.city.latitude, marker.city.longitude], {
                        icon: btcIcon
                    })
                        .bindPopup(marker.name)
                        .addTo(map);
                });

                // CTRL + scroll wheel zoom
                const container = map.getContainer();
                container.addEventListener('wheel', function (e) {
                    e.preventDefault();
                    if (e.ctrlKey) {
                        const delta = e.deltaY > 0 ? -1 : 1;
                        map.setZoom(map.getZoom() + delta, { animate: true });
                    }
                }, { passive: false });

                // Optional hint (removable)
                const hint = L.control({ position: 'topright' });
                hint.onAdd = function () {
                    const div = L.DomUtil.create('div', 'leaflet-control-zoom-control leaflet-bar');
                    L.DomEvent.disableClickPropagation(div);
                    return div;
                };
                hint.addTo(map);
            }
        }"
         x-init="initializeMap()"
    >
        <div id="map" x-ref="map"></div>
    </div>
</div>
