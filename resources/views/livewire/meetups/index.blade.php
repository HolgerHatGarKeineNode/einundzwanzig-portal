<?php

use App\Attributes\SeoDataAttribute;
use App\Models\Meetup;
use App\Traits\SeoTrait;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new
#[SeoDataAttribute(key: 'meetups_index')]
class extends Component {
    use WithPagination;
    use SeoTrait;

    public $country = 'de';
    public $search = '';

    public function mount(): void
    {
        $this->country = request()->route('country');
    }

    public function with(): array
    {
        return [
            'meetups' => Meetup::with(['city.country', 'createdBy'])
                ->withExists([
                    'meetupEvents as has_future_events' => fn($query) => $query->where('start', '>=', now()),
                ])
                ->leftJoin('meetup_events', function ($join) {
                    $join
                        ->on('meetups.id', '=', 'meetup_events.meetup_id')
                        ->where('meetup_events.start', '>=', now());
                })
                ->selectRaw('meetups.*, MIN(meetup_events.start) as next_event_start')
                ->groupBy('meetups.id')
                ->whereHas('city.country', fn($query) => $query->where('countries.code', $this->country))
                ->when($this->search, fn($query)
                    => $query->where('meetups.name', 'ilike', '%'.$this->search.'%'),
                )
                ->orderByDesc('has_future_events')
                ->orderByRaw('next_event_start ASC NULLS LAST')
                ->paginate(15),
        ];
    }
}; ?>

<div>
    <div class="flex items-center justify-between flex-col md:flex-row mb-6">
        <flux:heading size="xl">{{ __('Meetups') }}</flux:heading>
        <div class="flex flex-col md:flex-row items-center gap-4">
            <flux:button class="cursor-pointer" x-copy-to-clipboard="'{{ route('ics') }}'"
                         icon="calendar-date-range">{{ __('Kalender-Stream-URL kopieren') }}</flux:button>
            <flux:input
                wire:model.live="search"
                :placeholder="__('Suche nach Meetups...')"
                clearable
            />
            @auth
                <flux:button class="cursor-pointer" :href="route_with_country('meetups.create')" icon="plus"
                             variant="primary">
                    {{ __('Meetup erstellen') }}
                </flux:button>
            @endauth
        </div>
    </div>

    <flux:table :paginate="$meetups" class="mt-6">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}
            </flux:table.column>
            <flux:table.column>{{ __('Nächster Termin') }}</flux:table.column>
            <flux:table.column>{{ __('Links') }}</flux:table.column>
            <flux:table.column>{{ __('Aktionen') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($meetups as $meetup)
                <flux:table.row :key="$meetup->id">
                    <flux:table.cell variant="strong" class="flex items-center gap-3">
                        <flux:avatar
                            class="[:where(&)]:size-24 [:where(&)]:text-base" size="xl"
                            :href="route('meetups.landingpage', ['meetup' => $meetup, 'country' => $country])"
                            src="{{ $meetup->getFirstMedia('logo') ? $meetup->getFirstMediaUrl('logo', 'thumb') : asset('android-chrome-512x512.png') }}"/>
                        <div>
                            @if($meetup->city)
                                <a href="{{ route('meetups.landingpage', ['meetup' => $meetup, 'country' => $country]) }}">
                                    <span>{{ $meetup->name }}</span>
                                    <div class="text-xs text-zinc-500 flex items-center space-x-2">
                                        <div>{{ $meetup->city->name }}</div>
                                        @if($meetup->city->country)
                                            <flux:separator vertical/>
                                            <div>{{ $meetup->city->country->name }}</div>
                                        @endif
                                    </div>
                                </a>
                            @endif
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if($meetup->nextEvent && $meetup->nextEvent['start']->isFuture())
                            <div class="flex flex-col gap-1">
                                <flux:badge color="green" size="sm">
                                    {{ $meetup->nextEvent['start']->format('d.m.Y H:i') }}
                                </flux:badge>
                                <div class="text-xs text-zinc-500 flex items-center gap-2">
                                    <span>{{ $meetup->nextEvent['attendees'] }} {{ __('Zusagen') }}</span>
                                    <flux:separator vertical/>
                                    <span>{{ $meetup->nextEvent['might_attendees'] }} {{ __('Vielleicht') }}</span>
                                </div>
                            </div>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex gap-2">
                            @if($meetup->telegram_link)
                                <flux:link :href="$meetup->telegram_link" external variant="subtle"
                                           title="{{ __('Telegram') }}">
                                    <flux:icon.paper-airplane variant="mini"/>
                                </flux:link>
                            @endif
                            @if($meetup->webpage)
                                <flux:link :href="$meetup->webpage" external variant="subtle"
                                           title="{{ __('Website') }}">
                                    <flux:icon.globe-alt variant="mini"/>
                                </flux:link>
                            @endif
                            @if($meetup->twitter_username)
                                <flux:link :href="'https://twitter.com/' . $meetup->twitter_username" external
                                           variant="subtle" title="{{ __('Twitter') }}">
                                    <flux:icon.x-mark variant="mini"/>
                                </flux:link>
                            @endif
                            @if($meetup->matrix_group)
                                <flux:link :href="$meetup->matrix_group" external variant="subtle"
                                           title="{{ __('Matrix') }}">
                                    <flux:icon.chat-bubble-left variant="mini"/>
                                </flux:link>
                            @endif
                            @if($meetup->nostr)
                                <flux:link :href="'https://njump.me/'.$meetup->nostr" external variant="subtle"
                                           title="{{ __('Nostr') }}">
                                    <flux:icon.bolt variant="mini"/>
                                </flux:link>
                            @endif
                            @if($meetup->simplex)
                                <flux:link :href="$meetup->simplex" external variant="subtle"
                                           title="{{ __('Simplex') }}">
                                    <flux:icon.chat-bubble-bottom-center-text variant="mini"/>
                                </flux:link>
                            @endif
                            @if($meetup->signal)
                                <flux:link :href="$meetup->signal" external variant="subtle" title="{{ __('Signal') }}">
                                    <flux:icon.shield-check variant="mini"/>
                                </flux:link>
                            @endif
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex flex-col space-y-2">
                            @if(auth()->check() && $meetup->belongsToMe)
                                <div>
                                    <flux:button
                                        :disabled="!$meetup->belongsToMe"
                                        :href="$meetup->belongsToMe ? route_with_country('meetups.edit', ['meetup' => $meetup]) : null"
                                        size="xs"
                                        variant="filled" icon="pencil">
                                        {{ __('Bearbeiten') }}
                                    </flux:button>
                                </div>
                                <div>
                                    <flux:button
                                        :href="route_with_country('meetups.events.create', ['meetup' => $meetup])"
                                        size="xs" variant="filled" icon="calendar">
                                        {{ __('Neues Event erstellen') }}
                                    </flux:button>
                                </div>
                            @elseif(!auth()->check())
                                <flux:link :href="route('login')">{{ __('Log in') }}</flux:link>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
