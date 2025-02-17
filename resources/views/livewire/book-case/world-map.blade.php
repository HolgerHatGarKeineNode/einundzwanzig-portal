<div
    id="matrix"
    class="h-screen justify-between relative">
    {{-- HEADER --}}
    <livewire:frontend.header :country="$country" bgColor="bg-transparent"/>
    {{-- MAIN --}}
    <section class="w-full mb-12">
        <div class="max-w-screen-2xl mx-auto px-2 sm:px-10">

            <h1 class="text-xl font-bold py-4 text-gray-200">
                {{ __('World Map') }}
            </h1>

            <div
                x-data="{
                    data: @js($mapData),
                    init() {

                        var baseLayer = L.tileLayer(
                            'https://{s}.tile.openstreetmap.de/{z}/{x}/{y}.png',{
                                attribution: '&copy; <a href=\'https://www.openstreetmap.org/copyright\'>OpenStreetMap</a> contributors',
                            }
                        );

                        var map = new L.Map($refs.map, {
                            preferCanvas: true,
                            center: new L.LatLng(51.1642,10.4541194),
                            zoom: 6,
                            layers: [baseLayer]
                        });

                        this.data.forEach(element => {
                            if(element.op > 0) {
                                const marker = L.circleMarker([element.lat, element.lng], {color: '#f7931a', radius: 8});
                                marker.url = element.url;
                                marker.addTo(map).on('click', e => window.open(e.target.url, '_self'));
                            } else {
                                const marker = L.circleMarker([element.lat, element.lng], {color: '#111827', radius: 8});
                                marker.url = element.url;
                                marker.addTo(map).on('click', e => window.open(e.target.url, '_self'));
                            }
                        });

                    }
                }"
            >
                <div x-ref="map" style="height: 70vh;"></div>
            </div>

        </div>
    </section>
    {{-- FOOTER --}}
    <livewire:frontend.footer/>
</div>
