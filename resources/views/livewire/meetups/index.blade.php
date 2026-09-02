<?php

use App\Attributes\SeoDataAttribute;
use App\Models\Meetup;
use App\Models\Region;
use App\Traits\SeoTrait;
use Livewire\Component;
use Livewire\WithPagination;

new
#[SeoDataAttribute(key: 'meetups_index')]
class extends Component {
    use WithPagination;
    use SeoTrait;

    public $country = 'de';
    public $search = '';
    public string $currentRouteName = '';

    /**
     * Gesetzt nur auf der Regions-Route (/us/in/meetups); sonst null und damit wirkungslos.
     */
    public ?int $regionId = null;

    public ?string $regionName = null;

    public function mount(): void
    {
        $this->currentRouteName = request()->route()->getName();
        $this->country = request()->route('country', config('app.domain_country'));

        $region = Region::fromRouteOrFail($this->country);
        $this->regionId = $region?->id;
        $this->regionName = $region?->name;
    }

    public function with(): array
    {
        return [
            'meetups' => Meetup::with(['city.country', 'city.region', 'createdBy'])
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
                ->when(in_array($this->currentRouteName, ['meetups.index', 'meetups.index-region'], true), fn($query) =>
                    $query->whereHas('city.country', fn($query) => $query->where('countries.code', $this->country))
                )
                ->when($this->regionId, fn($query) =>
                    $query->whereHas('city', fn($query) => $query->where('cities.region_id', $this->regionId))
                )
                ->when($this->search, fn($query)
                    => $query->whereLike('meetups.name', '%'.$this->search.'%'),
                )
                ->orderByDesc('has_future_events')
                ->orderByRaw('next_event_start ASC NULLS LAST')
                ->paginate(15),
        ];
    }
}; ?>

<div>
    <div class="flex items-center justify-between flex-col md:flex-row mb-6">
        <flux:heading size="xl">
            {{ $regionName ? __('Meetups in :region', ['region' => $regionName]) : __('Meetups') }}
        </flux:heading>
        <div class="flex flex-col md:flex-row items-center gap-4">
            {{-- Der Einstieg in die Regions-Ansicht. Zeigt sich nur, wenn das Land
                 Regionen hat — sonst bleibt die Leiste wie bisher. --}}
            <livewire:region.chooser/>
            <x-calendar-stream-picker/>
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
            <flux:table.column>{{ __('Aktivität') }}</flux:table.column>
            <flux:table.column>{{ __('Nächster Termin') }}</flux:table.column>
            <flux:table.column>{{ __('Links') }}</flux:table.column>
            <flux:table.column>{{ __('Aktionen') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($meetups as $meetup)
                <flux:table.row :key="$meetup->id" :class="$meetup->is_active ? '' : 'opacity-60'">
                    <flux:table.cell variant="strong" class="flex items-center gap-3">
                        <flux:avatar
                            class="[:where(&)]:size-24 [:where(&)]:text-base {{ $meetup->is_active ? '' : 'grayscale' }}" size="xl"
                            :href="route('meetups.landingpage', ['meetup' => $meetup, 'country' => $country])"
                            src="{{ $meetup->getFirstMedia('logo') ? $meetup->getFirstMediaUrl('logo', 'thumb') : asset('android-chrome-512x512.png') }}"/>
                        <div>
                            @if($meetup->city)
                                <a href="{{ route('meetups.landingpage', ['meetup' => $meetup, 'country' => $country]) }}">
                                    <span>{{ $meetup->name }}</span>
                                    {{-- zinc-600/300 statt zinc-500: letzteres misst auf dem
                                         dunklen Body 3,19:1 und reisst damit WCAG 1.4.3.
                                         Der Fehler ist Bestand, sitzt aber genau da, wo
                                         jetzt Text dazukommt. --}}
                                    <div class="text-xs text-zinc-600 dark:text-zinc-300 flex items-center space-x-2">
                                        <div>{{ $meetup->city->name }}</div>
                                        @if($meetup->city->country)
                                            <flux:separator vertical/>
                                            <div>{{ $meetup->city->country->name }}</div>
                                        @endif
                                        @if($meetup->city->region)
                                            <flux:separator vertical/>
                                            <div>{{ $meetup->city->region->name }}</div>
                                        @endif
                                    </div>
                                </a>
                            @endif
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex flex-col gap-1">
                            @if($meetup->is_active)
                                <flux:badge color="green" size="sm">{{ __('Aktiv') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('Inaktiv') }}</flux:badge>
                            @endif
                            @if($meetup->last_event_at)
                                <span class="text-xs text-zinc-500">
                                    {{ __('Letztes Event') }}: {{ $meetup->last_event_at->asDate() }}
                                </span>
                            @else
                                <span class="text-xs text-zinc-500">{{ __('Noch kein Event') }}</span>
                            @endif
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if($meetup->nextEvent && $meetup->nextEvent['start']->isFuture())
                            <a href="{{ route('meetups.landingpage-event', ['meetup' => $meetup, 'event' => $meetup->nextEvent['id'], 'country' => $country]) }}">
                                <div class="flex flex-col gap-1">
                                    <flux:badge color="green" size="sm">
                                        {{ $meetup->nextEvent['start']->asDateTime() }}
                                    </flux:badge>
                                    <div class="text-xs text-zinc-500 flex items-center gap-2">
                                        <span>{{ trans_choice(':count Zusage|:count Zusagen', $meetup->nextEvent['attendees']) }}</span>
                                        <flux:separator vertical/>
                                        <span>{{ trans_choice(':count Vielleicht|:count Vielleicht', $meetup->nextEvent['might_attendees']) }}</span>
                                    </div>
                                </div>
                            </a>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        @php
                            $socialLinks = [
                                ['value' => $meetup->telegram_link, 'href' => $meetup->telegram_link, 'icon' => 'paper-airplane', 'title' => __('Telegram')],
                                ['value' => $meetup->webpage, 'href' => $meetup->webpage, 'icon' => 'globe-alt', 'title' => __('Website')],
                                ['value' => $meetup->twitter_username, 'href' => 'https://twitter.com/'.$meetup->twitter_username, 'icon' => 'x-mark', 'title' => __('Twitter')],
                                ['value' => $meetup->matrix_group, 'href' => $meetup->matrix_group, 'icon' => 'chat-bubble-left', 'title' => __('Matrix')],
                                ['value' => $meetup->nostr, 'href' => 'https://njump.me/'.$meetup->nostr, 'icon' => 'bolt', 'title' => __('Nostr')],
                                ['value' => $meetup->simplex, 'href' => $meetup->simplex, 'icon' => 'chat-bubble-bottom-center-text', 'title' => __('Simplex')],
                                ['value' => $meetup->signal, 'href' => $meetup->signal, 'icon' => 'shield-check', 'title' => __('Signal')],
                            ];
                        @endphp
                        <div class="flex gap-2">
                            @foreach($socialLinks as $socialLink)
                                @if($socialLink['value'])
                                    <flux:link :href="$socialLink['href']" external variant="subtle"
                                               title="{{ $socialLink['title'] }}">
                                        <flux:icon name="{{ $socialLink['icon'] }}" variant="mini"/>
                                    </flux:link>
                                @endif
                            @endforeach
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex flex-col space-y-2">
                            @if(auth()->check())
                                {{-- Bearbeiten (inkl. Leader-Verwaltung) folgt der update-Ability:
                                     Leader, Ersteller, Super-Admin und Meetup-Steward. --}}
                                @if($meetup->leadByMe || auth()->user()->can('update', $meetup))
                                    <div>
                                        <flux:button
                                            :href="route_with_country('meetups.edit', ['meetup' => $meetup])"
                                            size="xs"
                                            variant="filled" icon="pencil">
                                            {{ __('Bearbeiten') }}
                                        </flux:button>
                                    </div>
                                @endif
                                {{-- Termin-Schaltfläche bleibt an der Pivot-Leaderschaft: sie ist
                                     die Affordance für die eigenen Meetups, nicht die Rechtegrenze. --}}
                                @if($meetup->leadByMe)
                                    <div>
                                        <flux:button
                                            :href="route_with_country('meetups.events.create', ['meetup' => $meetup])"
                                            size="xs" variant="filled" icon="calendar">
                                            {{ __('Neues Event erstellen') }}
                                        </flux:button>
                                    </div>
                                @endif
                            @elseif(!auth()->check())
                                <flux:link :href="route('login')">{{ __('Log in') }}</flux:link>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    {{-- Eine Region ohne Treffer ist der Normalfall, solange erst wenige Städte eine
         Region tragen — ohne diesen Hinweis sieht die Seite aus wie ein Fehler. --}}
    @if($regionName && $meetups->isEmpty())
        <div class="mt-8 text-center">
            <flux:text>{{ __('No meetups in :region yet.', ['region' => $regionName]) }}</flux:text>
            <div class="mt-4">
                <flux:button :href="route('meetups.index', ['country' => $country])" variant="primary">
                    {{ __('Show all meetups in the country') }}
                </flux:button>
            </div>
        </div>
    @endif
</div>
