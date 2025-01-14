<div class="bg-21gray flex flex-col h-screen justify-between">
    {{-- HEADER --}}
    <livewire:frontend.header :c="$country->code"/>
    {{-- MAIN --}}
    <section class="w-full mb-12">
        <div class="max-w-screen-2xl mx-auto px-2 sm:px-10" id="table">
            <div class="flex items-start">
                <div class="w-1/2">
                    <h1 class="mb-6 text-5xl font-extrabold leading-none max-w-5xl mx-auto tracking-normal text-gray-200 sm:text-6xl md:text-6xl lg:text-7xl md:tracking-tight">
                        Bitcoin <span
                            class="w-full text-transparent bg-clip-text bg-gradient-to-r from-amber-400 via-amber-500 to-amber-200 lg:inline">{{ __('Bookcases') }}</span>
                    </h1>
                </div>

                <div class="w-1/2">
                    <p class="px-0 mb-6 text-lg text-gray-600 md:text-xl">
                        {{ __('Search out a public bookcase') }}
                    </p>
                    <div class="rounded" wire:ignore>
                        @if($markers[0] ?? false)
                            <style>
                                .gnw-map-service {
                                    z-index: 0 !important;
                                }
                            </style>
                            <div>
                                @map([
                                    'lat' => $markers[0]['lat'],
                                    'lng' => $markers[0]['lng'],
                                    'zoom' => 12,
                                    'markers' => $markers
                                ])
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <livewire:tables.book-case-table :country="$country->code"/>
        </div>
    </section>
    {{-- FOOTER --}}
    <livewire:frontend.footer/>
</div>
