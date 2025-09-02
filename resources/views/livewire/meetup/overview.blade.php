<?php

use App\Models\Country;
use App\Models\Meetup;
use App\Traits\HasMapEmbedCodeTrait;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use RalphJSmit\Laravel\SEO\Support\SEOData;

new
#[\Livewire\Attributes\Layout('components.layouts.app')]
class extends Component {
    use WithPagination;
    use HasMapEmbedCodeTrait;

    public Country $country;

    public string $sortBy = 'next_event_date';
    public string $sortDirection = 'desc';

    public function mount(): void
    {
        $this->country = Country::query()
            ->where('code', 'de')
            ->firstOrFail();
        $this->mountHasMapEmbedCodeTrait();
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function filterByMarker(int $id): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $meetup = Meetup::with(['city.country'])->find($id);

        return to_route('meetup.landing', [
            'country' => $meetup->city->country->code,
            'meetup' => $meetup,
        ]);
    }

    #[\Livewire\Attributes\Computed]
    public function meetups(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        // Adapt "meetups" to represent meetups with useful columns
        $query = Meetup::query()
            ->with(['users', 'city.country', 'meetupEvents' => fn($q) => $q->orderBy('start')])
            ->whereRelation('city.country', 'code', $this->country->code)
            ->withCount([
                'meetupEvents as future_events_count' => fn($q) => $q->where('start', '>=', now()),
            ])
            ->select(['meetups.*']);

        if ($this->sortBy === 'name') {
            $query->orderBy('name', $this->sortDirection);
        } elseif ($this->sortBy === 'next_event_date') {
            $query
                ->leftJoin('meetup_events as me', function ($join) {
                    $join
                        ->on('me.meetup_id', '=', 'meetups.id')
                        ->where('me.start', '>=', now());
                })
                ->orderByRaw('MIN(me.start) '.($this->sortDirection === 'asc' ? 'asc' : 'desc'))
                ->groupBy('meetups.id');
        } else {
            $query->orderBy('meetups.id');
        }

        return $query->paginate(10);
    }

    public function with(): array
    {
        return [
            'SEOData' => new SEOData(
                title: __("Meetups"),
                description: __('Bitcoiner Meetups are a great way to meet other Bitcoiners in your area. You can learn from each other, share ideas, and have fun!'),
                image: asset('img/screenshot.png')
            ),
        ];
    }
};
?>

<div class="bg-21gray flex flex-col h-screen justify-between">
    {{-- HEADER --}}
    <livewire:frontend.header :country="$country"/>

    {{-- MAIN --}}
    <section class="w-full mb-12">
        <div class="max-w-(--breakpoint-2xl) mx-auto px-2 sm:px-10 space-y-4 py-4">
            <div class="w-full flex justify-end">
                <div class="flex flex-col space-y-2">
                    <flux:button
                        x-data="{ textToCopy: '{{ route('meetup.ics', ['country' => $country]) }}' }"
                        @click.prevent="window.navigator.clipboard.writeText(textToCopy);$flux.toast('{{ __('Calendar Stream Url copied!') }}');"
                        variant="primary">
                        <i class="fa fa-thin fa-calendar-arrow-down mr-2"></i>
                        {{ __('Calendar Stream-Url for all meetup events') }}
                    </flux:button>

                    @if(auth()->check() && auth()->user()->meetups->count() > 0)
                        <flux:button
                            x-data="{ textToCopy: '{{ route('meetup.ics', ['country' => $country, 'my' => auth()->user()->meetups->pluck('id')->toArray()]) }}' }"
                            @click.prevent="window.navigator.clipboard.writeText(textToCopy);$flux.toast('{{ __('Calendar Stream Url copied!') }}');"
                            variant="secondary">
                            <i class="fa fa-thin fa-calendar-heart mr-2"></i>
                            {{ __('Calendar Stream-Url for my meetups only') }}
                        </flux:button>
                    @endif

                    <flux:button
                        x-data="{ textToCopy: '{{ $mapEmbedCode }}' }"
                        @click.prevent="window.navigator.clipboard.writeText(textToCopy);$flux.toast('{{ __('Embed code for the map copied!') }}');"
                        variant="primary">
                        <i class="fa fa-thin fa-code mr-2"></i>
                        {{ __('Copy embed code for the map') }}
                        <img class="h-6 rounded ml-2"
                             src="{{ asset('vendor/blade-country-flags/4x3-'. $country->code .'.svg') }}"
                             alt="{{ $country->code }}">
                    </flux:button>
                </div>
            </div>
        </div>

        <div class="max-w-(--breakpoint-2xl) mx-auto px-2 sm:px-10 space-y-4" id="table">
            <div class="md:flex md:items-center md:justify-between">
                <div class="min-w-0 flex-1">
                    <h2 class="text-2xl font-bold leading-7 text-white sm:truncate sm:text-3xl sm:tracking-tight">
                        {{ __('Meetups') }}
                    </h2>
                </div>
                <div class="mt-4 flex md:mt-0 md:ml-4"></div>
            </div>

            <flux:table :paginate="$this->meetups">
                <flux:table.columns>
                    <flux:table.column>{{ __('Meetup') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'next_event_date'" :direction="$sortDirection"
                                       wire:click="sort('next_event_date')">{{ __('Next Event') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Plebs') }}</flux:table.column>
                    <flux:table.column>{{ __('City') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Links') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->meetups as $m)
                        @php
                            $logo = $m->getFirstMediaUrl('logo') ?? $m->logo ?? null;
                            $nextEvent = optional($m->meetupEvents->firstWhere('start', '>=', now()));
                        @endphp
                        <flux:table.row :key="$m->id">
                            <flux:table.cell class="flex items-center gap-3">
                                @if($logo)
                                    <flux:avatar size="xl" src="{{ $logo }}" alt="{{ $m->name }}"/>
                                @else
                                    <flux:avatar size="xl" name="{{ $m->name }}"/>
                                @endif
                                <a href="{{ route('meetup.landing', ['country' => $m->city->country->code, 'meetup' => $m]) }}"
                                   class="text-orange-400 hover:underline">
                                    {{ $m->name }}
                                </a>
                            </flux:table.cell>

                            <flux:table.cell class="whitespace-nowrap">
                                @if($nextEvent)
                                    {{ $nextEvent->start?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                                @else
                                    <span class="text-gray-400">{{ __('No upcoming') }}</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="end" variant="strong">{{ $m->users_count }}</flux:table.cell>

                            <flux:table.cell>
                                {{ $m->city->name }}
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex gap-2 justify-end">
                                    <flux:button size="xs"
                                                 href="{{ route('meetup.ics', ['country' => $country->code, 'meetup' => $m->id]) }}">
                                        {{ __('ICS') }}
                                    </flux:button>
                                    <flux:button size="xs"
                                                 href="{{ route('meetup.event.form', ['country' => $country->code]) }}">
                                        {{ __('Create Event') }}
                                    </flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    </section>

    {{-- FOOTER --}}
    <livewire:frontend.footer/>
</div>
