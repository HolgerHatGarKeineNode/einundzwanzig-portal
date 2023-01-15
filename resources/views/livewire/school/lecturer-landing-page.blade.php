<div class="bg-21gray flex flex-col h-screen justify-between">
    {{-- MAIN --}}
    <section class="w-full mb-12 mt-8">
        <div class="max-w-screen-2xl mx-auto px-2 sm:px-10 space-y-4 flex flex-col sm:flex-row">
            <div class="flex flex-col sm:flex-row sm:space-x-6">
                <div class="sm:w-2/3 flex flex-col">
                    <h1 class="mb-6 text-5xl font-extrabold leading-none tracking-normal text-gray-200 sm:text-6xl md:text-6xl lg:text-7xl md:tracking-tight">
                        <span
                            class="text-transparent bg-clip-text bg-gradient-to-r from-amber-400 via-amber-500 to-amber-200">{{ $lecturer->name }}</span><br
                            class="lg:block hidden">
                        {{ $lecturer->subtitle }}
                    </h1>
                    <div class="px-0 mb-6 text-lg text-gray-200 md:text-xl">
                        {{ $lecturer->intro }}
                    </div>
                </div>

                <div class="sm:w-1/3 p-4">
                    <img class="max-h-64" src="{{ $lecturer->getFirstMediaUrl('avatar') }}" alt="Avatar">
                </div>
            </div>

        </div>

        <section class="h-auto px-10 py-16">
            <div class="max-w-3xl mx-auto space-y-4 sm:text-center">
                <h2 class="text-4xl sm:text-5xl font-semibold text-white">
                    {{ __('Courses') }}
                </h2>
                <p class="text-gray-400 text-lg sm:text-xl leading-normal">
                    {{ __('All courses of :name', ['name' => $lecturer->name]) }}
                </p>
            </div>
        </section>

        <ul role="list" class="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4">
            @foreach($courseEvents as $courseEvent)
                @php
                    $activeClass = $activeEvent === $courseEvent->id ? 'bg-gradient-to-r from-amber-800 via-amber-600 to-amber-500' : 'bg-amber-500';
                @endphp
                <li id="courseEventId_{{ $courseEvent->id }}" class="{{ $activeClass }} col-span-1 flex flex-col divide-y divide-gray-200 rounded-lg text-center shadow-2xl">
                    <div class="flex flex-1 flex-col p-8">
                        <img class="mx-auto h-32 w-32 object-contain flex-shrink-0 rounded"
                             src="{{ $courseEvent->venue->getFirstMediaUrl('images') }}"
                             alt="{{ $courseEvent->course->name }}">
                        <h3 class="mt-1 text-xl font-medium text-gray-900 truncate">{{ $courseEvent->from->asDate() }}</h3>
                        <h3 class="mt-1 text-xl font-medium text-gray-900 truncate">{{ $courseEvent->venue->city->name }}</h3>
                        <h3 class="mt-1 text-xl font-medium text-gray-900 truncate">{{ $courseEvent->venue->name }}</h3>
                        <dl class="mt-1 flex flex-grow flex-col justify-between">
                            <div x-data="{ active: 2 }" class="mx-auto max-w-3xl w-full space-y-4">
                                <div x-data="{
                                        id: 1,
                                        get expanded() {
                                            return this.active === this.id
                                        },
                                        set expanded(value) {
                                            this.active = value ? this.id : null
                                        },
                                    }"
                                     role="region" class="rounded-lg bg-white shadow">
                                    <h2>
                                        <button
                                            x-on:click="expanded = !expanded"
                                            :aria-expanded="expanded"
                                            class="flex w-full items-center justify-between px-6 py-4 text-xl font-bold"
                                        >
                                            <span>{{ __('Description') }}</span>
                                            <span x-show="expanded" aria-hidden="true" class="ml-4">&minus;</span>
                                            <span x-show="!expanded" aria-hidden="true" class="ml-4">&plus;</span>
                                        </button>
                                    </h2>
                                    <div x-show="expanded" x-collapse>
                                        <div class="px-6 pb-4 text-left">{!! nl2br($courseEvent->course->description) !!}</div>
                                    </div>
                                </div>
                            </div>
                                <dd class="mt-3 flex flex-col space-y-1">
                                @foreach($courseEvent->course->tags as $tag)
                                    <span
                                        class="rounded-full bg-21gray px-2 py-1 text-xs font-medium text-gray-200">{{ $tag->name }}</span>
                                @endforeach
                            </dd>
                        </dl>
                    </div>
                    <div>
                        <div class="-mt-px flex divide-x divide-gray-200">
                            <div class="-ml-px flex w-0 flex-1">
                                <a target="_blank" href="{{ $courseEvent->link }}"
                                   class="relative inline-flex w-0 flex-1 items-center justify-center rounded-br-lg border border-transparent py-4 text-sm font-medium text-gray-700 hover:text-gray-500">
                                    <i class="text-gray-100 text-2xl fa-thin fa-right-to-bracket"></i>
                                    <span class="ml-3 text-gray-100 text-2xl">{{ __('Register') }}</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>

        <section class="h-auto px-10 py-16">
            <div class="max-w-3xl mx-auto space-y-4 sm:text-center">
                <h2 class="text-4xl sm:text-5xl font-semibold text-white">
                    {{ __('Events') }}
                </h2>
                <p class="text-gray-400 text-lg sm:text-xl leading-normal">
                    {{ __('Here you can see all events of :name.', ['name' => $lecturer->name]) }}
                </p>
            </div>
        </section>

        <div class="w-full">

            @php
                $locale = \Illuminate\Support\Facades\Cookie::get('lang', 'de');
            @endphp

            <link rel="stylesheet" type="text/css"
                  href="https://unpkg.com/js-year-calendar@latest/dist/js-year-calendar.min.css"/>
            <script src="https://unpkg.com/js-year-calendar@latest/dist/js-year-calendar.min.js"></script>
            <script src="https://unpkg.com/js-year-calendar@latest/locales/js-year-calendar.{{ $locale }}.js"></script>

            <style>
                .calendar .calendar-header {
                    background-color: #F7931A;
                    color: white;
                    border: 0;
                }

                .calendar table.month th.month-title {
                    color: #F7931A;
                }

                .calendar table.month th.day-header {
                    color: #fff;
                }

                .calendar table.month td.day .day-content {
                    color: #fff;
                }

                .calendar .calendar-header table th:hover {
                    background: #222;
                }
            </style>
            <div
                wire:ignore
                x-data="{
                                calendar: null,
                                init() {
                                    let events = {{ Js::from($events) }};
                                    events = events.map(function(e){
                                        return {id: e.id, startDate: new Date(e.startDate), endDate: new Date(e.endDate), location: e.location, description: e.description}
                                    })

                                    new Calendar(this.$refs.calendar, {
                                        style: 'background',
                                        language: '{{ $locale }}',
                                        startYear: {{ date('Y') }},
                                        dataSource: events,
                                        yearChanged: function(e) {
                                            @this.set('year', e.currentYear);
                                        },
                                        clickDay: function(e) {
                                            if(e.events.length > 0) {
                                                $wire.call('showEvent', e.events[0].id);
                                                document.getElementById('courseEventId_'+e.events[0].id).scrollIntoView();
                                            }
                                        },
                                    });
                                },
                            }"
            >
                <div x-ref="calendar"></div>
            </div>

            <div class="p-4 w-full flex justify-end">
                <x-button :href="route('welcome')" primary lg class="whitespace-nowrap">
                    <i class="fa fa-thin fa-arrow-left mr-2"></i>
                    {{ __('Back') }}
                </x-button>
            </div>
        </div>
    </section>
    {{-- FOOTER --}}
    <livewire:frontend.footer/>
</div>