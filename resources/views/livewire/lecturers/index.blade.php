<?php

use App\Attributes\SeoDataAttribute;
use App\Models\Lecturer;
use App\Traits\SeoTrait;
use Livewire\Component;
use Livewire\WithPagination;

new
#[SeoDataAttribute(key: 'lecturers_index')]
class extends Component {
    use WithPagination;
    use SeoTrait;

    public $country = 'de';
    public $search = '';

    public function mount(): void
    {
        $this->country = request()->route('country', config('app.domain_country'));
    }

    public function with(): array
    {
        return [
            'lecturers' => Lecturer::query()
                ->with([
                    'createdBy',
                    'coursesEvents' => fn($query) => $query->where('from', '>=', now())->orderBy('from', 'asc'),
                    'coursesEvents.course',
                ])
                ->withExists([
                    'coursesEvents as has_future_events' => fn($query) => $query->where('from', '>=', now()),
                ])
                ->withCount([
                    'coursesEvents as future_events_count' => fn($query) => $query->where('from', '>=', now()),
                ])
                ->whereHas('coursesEvents')
                ->whereHas('coursesEvents.city.country',
                    fn($query) => $query->where('countries.code', $this->country))
                ->when($this->search, fn($query)
                    => $query
                    ->whereLike('name', '%'.$this->search.'%')
                    ->orWhereLike('description', '%'.$this->search.'%')
                    ->orWhereLike('subtitle', '%'.$this->search.'%'),
                )
                ->orderByDesc('has_future_events')
                ->orderBy('name', 'asc')
                ->paginate(15),
        ];
    }
}; ?>

<div>
    <div class="flex items-center justify-between flex-col md:flex-row mb-6">
        <flux:heading size="xl">{{ __('Dozenten') }}</flux:heading>
        <div class="flex items-center flex-col md:flex-row gap-4">
            <flux:input
                wire:model.live="search"
                :placeholder="__('Suche nach Dozenten...')"
                clearable
            />
            @auth
                <flux:button class="cursor-pointer" :href="route_with_country('lecturers.create')" icon="plus"
                             variant="primary">
                    {{ __('Dozenten anlegen') }}
                </flux:button>
            @endauth
        </div>
    </div>

    <flux:table :paginate="$lecturers" class="mt-6">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}
            </flux:table.column>
            <flux:table.column>{{ __('Nächster Termin') }}</flux:table.column>
            <flux:table.column>{{ __('Kurse') }}</flux:table.column>
            <flux:table.column>{{ __('Links') }}</flux:table.column>
            <flux:table.column>{{ __('Aktionen') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($lecturers as $lecturer)
                <flux:table.row :key="$lecturer->id">
                    <flux:table.cell variant="strong" class="flex items-center gap-3">
                        <div class="flex items-center gap-3">
                            <flux:avatar size="lg"
                                         src="{{ $lecturer->getFirstMedia('avatar') ? $lecturer->getFirstMediaUrl('avatar', 'thumb') : asset('img/einundzwanzig.png') }}"/>
                            <div>
                                <div class="font-semibold">{{ $lecturer->name }}</div>
                                @if($lecturer->active)
                                    <flux:badge size="sm" color="green">{{ __('Aktiv') }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">{{ __('Inaktiv') }}</flux:badge>
                                @endif
                            </div>
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        @php
                            $nextEvent = $lecturer->coursesEvents->first();
                        @endphp
                        @if($nextEvent)
                            <div class="flex flex-col gap-1">
                                <a href="{{ route('courses.landingpage', ['course' => $nextEvent->course, 'country' => $country]) }}">
                                    <flux:badge color="green" size="sm">
                                        {{ $nextEvent->from->format('d.m.Y H:i') }}
                                    </flux:badge>
                                </a>
                                @if($lecturer->future_events_count > 1)
                                    <div class="text-xs text-zinc-500">
                                        +{{ trans_choice(':count weiterer Termin|:count weitere Termine', $lecturer->future_events_count - 1) }}
                                    </div>
                                @endif
                            </div>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm">{{ $lecturer->courses()->count() }} {{ __('Kurse') }}</flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex gap-2">
                            @if($lecturer->website)
                                <flux:link :href="$lecturer->website" external variant="subtle"
                                           title="{{ __('Website') }}">
                                    <flux:icon.globe-alt variant="mini"/>
                                </flux:link>
                            @endif
                            @if($lecturer->twitter_username)
                                <flux:link :href="'https://twitter.com/' . $lecturer->twitter_username" external
                                           variant="subtle" title="{{ __('Twitter') }}">
                                    <flux:icon.x-mark variant="mini"/>
                                </flux:link>
                            @endif
                            @if($lecturer->nostr)
                                <flux:link :href="'https://njump.me/'.$lecturer->nostr" external variant="subtle"
                                           title="{{ __('Nostr') }}">
                                    <flux:icon.bolt variant="mini"/>
                                </flux:link>
                            @endif
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if(auth()->check() && $lecturer->created_by === auth()->id())
                            <flux:button
                                :disabled="$lecturer->created_by !== auth()->id()"
                                :href="$lecturer->created_by === auth()->id() ? route_with_country('lecturers.edit', ['lecturer' => $lecturer]) : null"
                                size="xs"
                                variant="filled"
                                icon="pencil">
                                {{ __('Bearbeiten') }}
                            </flux:button>
                        @elseif(!auth()->check())
                            <flux:link :href="route('login')">{{ __('Log in') }}</flux:link>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
