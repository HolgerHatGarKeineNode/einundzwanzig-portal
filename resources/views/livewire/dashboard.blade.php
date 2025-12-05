<?php

use App\Attributes\SeoDataAttribute;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Traits\SeoTrait;
use Flux\Flux;
use Livewire\Volt\Component;

new
#[SeoDataAttribute(key: 'dashboard')]
class extends Component {
    use SeoTrait;

    public $selectedMeetupId = null;

    public $country = 'de';

    public function mount(): void
    {
        if (!auth()->check()) {
            $this->redirectRoute('login');
        }
        $this->country = request()->route('country', config('app.domain_country'));
    }

    public function addMeetup()
    {
        if ($this->selectedMeetupId) {
            $user = auth()->user();

            // Prüfen ob bereits zugeordnet
            if (!$user->meetups()->where('meetup_id', $this->selectedMeetupId)->exists()) {
                $user->meetups()->attach($this->selectedMeetupId);
            }

            $this->selectedMeetupId = null;
        }
    }

    public function removeMeetup($meetupId)
    {
        auth()->user()->meetups()->detach($meetupId);
        Flux::modals()->close();
        $this->reset('selectedMeetupId');
    }

    public function with(): array
    {
        $user = auth()->user();

        // Meine Meetups
        $myMeetups = $user
            ->meetups()
            ->with(['city.country'])
            ->get();

        // Alle verfügbaren Meetups (außer die bereits zugeordneten)
        $availableMeetups = Meetup::with(['city.country'])
            ->whereNotIn('id', $myMeetups->pluck('id'))
            ->orderBy('name')
            ->get();

        // Meine nächsten Meetup Termine
        $myUpcomingEvents = MeetupEvent::whereHas('meetup', function ($query) use ($user) {
            $query->whereHas('users', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        })
            ->where('start', '>=', now())
            ->with(['meetup.city.country'])
            ->orderBy('start')
            ->limit(5)
            ->get();

        return [
            'myMeetups' => $myMeetups,
            'availableMeetups' => $availableMeetups,
            'myUpcomingEvents' => $myUpcomingEvents,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <div class="grid auto-rows-min gap-4 grid-cols-1 md:grid-cols-2 2xl:grid-cols-3">
        <div class="relative overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="p-6">
                <flux:heading size="lg" class="mb-4">{{ __('Meine nächsten Meetup Termine') }}</flux:heading>
                @if($myUpcomingEvents->count() > 0)
                    <flux:separator class="my-4"/>
                    <div class="space-y-3">
                        @foreach($myUpcomingEvents as $event)
                            <a href="{{ route('meetups.landingpage-event', ['meetup' => $event->meetup->slug, 'event' => $event->id, 'country' => $event->meetup->city->country->code]) }}"
                               class="block hover:bg-zinc-50 dark:hover:bg-zinc-800 rounded-lg p-3 -m-3 transition-colors">
                                <div class="flex items-start justify-between gap-3">
                                    <flux:avatar size="xl" :src="$event->meetup->getFirstMediaUrl('logo', 'thumb')"/>
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-2">
                                            <div class="font-medium">{{ $event->meetup->name }}</div>
                                            <img
                                                alt="{{ strtolower($event->meetup->city->country->code) }}"
                                                src="{{ asset('vendor/blade-flags/country-'.strtolower($event->meetup->city->country->code).'.svg') }}"
                                                width="24" height="12"
                                            />
                                        </div>
                                        <div class="text-sm text-zinc-500">
                                            {{ $event->meetup->city->name }}, {{ $event->meetup->city->country->name }}
                                        </div>
                                        <flux:badge color="green" size="sm" class="mt-1">
                                            {{ $event->start->asDateTime() }}
                                        </flux:badge>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="text-sm text-zinc-500">{{ __('Keine bevorstehenden Termine') }}</div>
                @endif
            </div>
        </div>
        <div
            class="2xl:col-span-2 relative overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="p-6">
                <flux:heading size="lg" class="mb-4">{{ __('Meine Meetups') }}</flux:heading>

                <flux:select variant="listbox" searchable placeholder="{{ __('Meetup hinzufügen...') }}"
                             wire:model="selectedMeetupId" wire:change="addMeetup">
                    <x-slot name="search">
                        <flux:select.search class="px-4" placeholder="{{ __('Meetup suchen...') }}"/>
                    </x-slot>
                    @foreach($availableMeetups as $meetup)
                        <flux:select.option value="{{ $meetup->id }}">
                            <div class="flex items-center space-x-2">
                                <img alt="{{ $meetup->name }}"
                                     src="{{ $meetup->getFirstMedia('logo') ? $meetup->getFirstMediaUrl('logo', 'thumb') : asset('android-chrome-512x512.png') }}"
                                     width="24" height="24" class="rounded"/>
                                <div>
                                    <span class="font-medium">{{ $meetup->name }}</span>
                                    @if($meetup->city)
                                        <span class="text-xs text-zinc-500">- {{ $meetup->city->name }}</span>
                                    @endif
                                </div>
                            </div>
                        </flux:select.option>
                    @endforeach
                </flux:select>

                @if($myMeetups->count() > 0)
                    <flux:separator class="my-4"/>
                    <div class="space-y-3">
                        @foreach($myMeetups as $meetup)
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 flex-1">
                                    <flux:avatar
                                        :href="route('meetups.landingpage', ['meetup' => $meetup, 'country' => $country])"
                                        size="sm"
                                        src="{{ $meetup->getFirstMedia('logo') ? $meetup->getFirstMediaUrl('logo', 'thumb') : asset('android-chrome-512x512.png') }}"/>
                                    <a href="{{ route('meetups.landingpage', ['meetup' => $meetup, 'country' => $country]) }}">
                                        <div>
                                            <div class="flex items-center space-x-2">
                                                <div class="font-medium">{{ $meetup->name }}</div>
                                                <img
                                                    alt="{{ strtolower($event->meetup->city->country->code) }}"
                                                    src="{{ asset('vendor/blade-flags/country-'.strtolower($event->meetup->city->country->code).'.svg') }}"
                                                    width="24" height="12"
                                                />
                                            </div>
                                            <div class="text-xs text-zinc-500">
                                                {{ $meetup->city->name }}
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <div class="flex flex-col sm:flex-row items-start gap-2">
                                    <flux:button :href="route_with_country('meetups.edit', ['meetup' => $meetup])"
                                                 size="xs" variant="ghost" icon="pencil">
                                        {{ __('Bearbeiten') }}
                                    </flux:button>
                                    <flux:modal.trigger :name="'remove-meetup-' . $meetup->id">
                                        <flux:button class="cursor-pointer" size="xs" variant="danger"
                                                     icon="trash"></flux:button>
                                    </flux:modal.trigger>
                                </div>

                                <flux:modal wire:key="remove-meetup-{{ $meetup->id }}"
                                            :name="'remove-meetup-' . $meetup->id" class="min-w-[22rem]">
                                    <div class="space-y-6">
                                        <div>
                                            <flux:heading size="lg">{{ __('Meetup entfernen?') }}</flux:heading>

                                            <flux:text class="mt-2">
                                                {{ __('Möchtest du') }} "{{ $meetup->name }}
                                                " {{ __('aus deinen Meetups entfernen?') }}<br>
                                                {{ __('Du kannst es jederzeit wieder hinzufügen.') }}
                                            </flux:text>
                                        </div>

                                        <div class="flex gap-2">
                                            <flux:spacer/>

                                            <flux:modal.close>
                                                <flux:button class="cursor-pointer"
                                                             variant="ghost">{{ __('Abbrechen') }}</flux:button>
                                            </flux:modal.close>

                                            <flux:button class="cursor-pointer"
                                                         wire:click="removeMeetup({{ $meetup->id }})"
                                                         variant="danger">{{ __('Entfernen') }}</flux:button>
                                        </div>
                                    </div>
                                </flux:modal>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-sm text-zinc-500 mt-4">{{ __('Keine Meetups zugeordnet') }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Neue Statistiken und Activities (Lazy loaded) --}}
    <div class="grid auto-rows-min gap-4 grid-cols-1 md:grid-cols-2 2xl:grid-cols-3">
        <livewire:dashboard.top-countries lazy/>
        <livewire:dashboard.top-meetups lazy/>
        <livewire:dashboard.activities lazy/>
    </div>
</div>
