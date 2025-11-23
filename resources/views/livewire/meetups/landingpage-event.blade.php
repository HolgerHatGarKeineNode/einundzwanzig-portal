<?php

use App\Attributes\SeoDataAttribute;
use App\Models\MeetupEvent;
use App\Models\User;
use App\Traits\SeoTrait;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new
#[SeoDataAttribute(key: 'meetups_landingpage_event')]
class extends Component {
    use SeoTrait;

    public MeetupEvent $event;
    public $country = 'de';

    #[Validate('required|min:2')]
    public string $name = '';

    public bool $willShowUp = false;
    public bool $perhapsShowUp = false;
    public array $attendees = [];
    public array $mightAttendees = [];

    public function mount(): void
    {
        $this->country = request()->route('country', config('app.domain_country'));
        $this->name = auth()->user()->name ?? '';
        $this->loadAttendees();
    }

    public function with(): array
    {
        return [
            'event' => $this->event->load('meetup'),
        ];
    }

    private function getUserIdentifier(): string
    {
        return auth()->check()
            ? 'id_'.auth()->id()
            : 'anon_'.session()->getId();
    }

    private function loadAttendees(): void
    {
        $identifier = $this->getUserIdentifier();
        $attendees = collect($this->event->attendees ?? []);
        $mightAttendees = collect($this->event->might_attendees ?? []);

        // Check if user is in attendees
        $attendeeEntry = $attendees->first(fn($v) => str($v)->startsWith($identifier));
        if ($attendeeEntry) {
            $this->name = str($attendeeEntry)->after('|')->toString();
            $this->willShowUp = true;
        }

        // Check if user is in might_attendees
        $mightAttendeeEntry = $mightAttendees->first(fn($v) => str($v)->startsWith($identifier));
        if ($mightAttendeeEntry) {
            $this->name = str($mightAttendeeEntry)->after('|')->toString();
            $this->perhapsShowUp = true;
        }

        $this->attendees = $this->mapAttendees($attendees);
        $this->mightAttendees = $this->mapAttendees($mightAttendees);
    }

    private function mapAttendees($collection): array
    {
        return $collection->map(function ($value) {
            $isAnon = str($value)->contains('anon_');
            $id = $isAnon ? -1 : str($value)->before('|')->after('id_')->toInteger();

            return [
                'id' => $id,
                'user' => $id > 0 ? User::query()
                    ->select(['id', 'name', 'profile_photo_path'])
                    ->find($id)
                    ?->append('profile_photo_url')
                    ->toArray() : null,
                'name' => str($value)->after('|')->toString(),
            ];
        })->toArray();
    }

    public function attend(): void
    {
        $this->validate();
        $this->removeFromLists();

        $attendees = collect($this->event->attendees ?? []);
        $entry = $this->getUserIdentifier().'|'.$this->name;

        if (!$attendees->contains($entry)) {
            $attendees->push($entry);
            $this->event->update(['attendees' => $attendees->toArray()]);
        }

        $this->loadAttendees();
    }

    public function mightAttend(): void
    {
        $this->validate();
        $this->removeFromLists();

        $mightAttendees = collect($this->event->might_attendees ?? []);
        $entry = $this->getUserIdentifier().'|'.$this->name;

        if (!$mightAttendees->contains($entry)) {
            $mightAttendees->push($entry);
            $this->event->update(['might_attendees' => $mightAttendees->toArray()]);
        }

        $this->loadAttendees();
    }

    public function cannotCome(): void
    {
        $this->removeFromLists();
        $this->loadAttendees();
    }

    private function removeFromLists(): void
    {
        $identifier = $this->getUserIdentifier();

        $attendees = collect($this->event->attendees ?? [])
            ->reject(fn($v) => str($v)->startsWith($identifier));

        $mightAttendees = collect($this->event->might_attendees ?? [])
            ->reject(fn($v) => str($v)->startsWith($identifier));

        $this->event->update([
            'attendees' => $attendees->toArray(),
            'might_attendees' => $mightAttendees->toArray(),
        ]);

        $this->willShowUp = false;
        $this->perhapsShowUp = false;
    }
}; ?>

<div class="container mx-auto px-4 py-8">
    <!-- Breadcrumb -->
    <div class="mb-6">
        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
            <a href="{{ route('meetups.landingpage', ['meetup' => $event->meetup->slug, 'country' => $country]) }}"
               class="hover:underline">
                {{ $event->meetup->name }}
            </a>
            <span class="mx-2">/</span>
            <span>{{ $event->start->format('d.m.Y') }}</span>
        </flux:text>
    </div>

    <div class="flex flex-col md:flex-row gap-8">
        <div class="md:w-2/3">
            <!-- Event Details -->
            <flux:card class="max-w-3xl">
                <flux:heading size="xl" class="mb-4">
                    <flux:icon.calendar class="inline w-6 h-6 mr-2"/>
                    {{ $event->start->asDateTime() }}
                </flux:heading>

                <div class="space-y-4">
                    <!-- Date and Time -->
                    <div class="flex items-center text-zinc-700 dark:text-zinc-300">
                        <flux:icon.clock class="w-5 h-5 mr-3"/>
                        <div>
                            <div class="font-semibold">{{ $event->start->asTime() }} Uhr</div>
                            <div
                                class="text-sm text-zinc-600 dark:text-zinc-400">{{ $event->start->asDate() }}</div>
                        </div>
                    </div>

                    <!-- Location -->
                    @if($event->location)
                        <div class="flex items-center text-zinc-700 dark:text-zinc-300">
                            <flux:icon.map-pin class="w-5 h-5 mr-3"/>
                            <div>
                                <div class="font-semibold">{{ __('Ort') }}</div>
                                <div class="text-sm">{{ $event->location }}</div>
                            </div>
                        </div>
                    @endif

                    <!-- Description -->
                    @if($event->description)
                        <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700">
                            <flux:heading size="lg" class="mb-2">{{ __('Beschreibung') }}</flux:heading>
                            <flux:text class="whitespace-pre-wrap">{{ $event->description }}</flux:text>
                        </div>
                    @endif

                    <!-- Link -->
                    @if($event->link)
                        <div class="pt-4">
                            <flux:button href="{{ $event->link }}" target="_blank" variant="primary">
                                <flux:icon.arrow-top-right-on-square class="w-5 h-5 mr-2"/>
                                {{ __('Mehr Informationen') }}
                            </flux:button>
                        </div>
                    @endif

                    <!-- RSVP Section -->
                    <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700">
                        <flux:heading size="lg" class="mb-4">{{ __('Teilnahme') }}</flux:heading>

                        <div class="space-y-4">

                            @if(!auth()->check())
                                <flux:callout variant="warning" icon="exclamation-triangle" inline>
                                    <flux:callout.heading>{{ __('Du bist nicht eingloggt und musst deshalb den Namen selbst eintippen.') }}</flux:callout.heading>
                                    <x-slot name="actions">
                                        <flux:button :href="route('login')">{{ __('Log in') }}</flux:button>
                                    </x-slot>
                                </flux:callout>
                            @endif

                            <!-- Name Input -->
                            <flux:field>
                                <flux:label>{{ __('Dein Name') }}</flux:label>
                                <flux:input wire:model="name" type="text" placeholder="{{ __('Name eingeben') }}"/>
                                @error('name')
                                <flux:error>{{ $message }}</flux:error>
                                @enderror
                            </flux:field>

                            <!-- Action Buttons -->
                            <div class="flex flex-wrap gap-2">
                                <flux:button
                                    class="cursor-pointer"
                                    icon="check"
                                    wire:click="attend"
                                    variant="{{ $willShowUp ? 'primary' : 'outline' }}"
                                >
                                    {{ __('Ich komme') }}
                                </flux:button>

                                <flux:button
                                    class="cursor-pointer"
                                    icon="question-mark-circle"
                                    wire:click="mightAttend"
                                    variant="{{ $perhapsShowUp ? 'primary' : 'outline' }}"
                                >
                                    {{ __('Vielleicht') }}
                                </flux:button>

                                @if($willShowUp || $perhapsShowUp)
                                    <flux:button
                                        class="cursor-pointer"
                                        icon="x-mark"
                                        wire:click="cannotCome"
                                        variant="ghost"
                                    >
                                        {{ __('Absagen') }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Attendees -->
                    @if(count($attendees) > 0)
                        <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700">
                            <flux:heading size="lg" class="mb-2">
                                {{ __('Zusagen') }} ({{ count($attendees) }})
                            </flux:heading>
                            <div class="flex flex-wrap gap-2">
                                @foreach($attendees as $attendee)
                                    @if($attendee['user'])
                                        <div
                                            class="flex items-center gap-2 px-3 py-1.5 bg-zinc-100 dark:bg-zinc-800 rounded-full">
                                            <flux:avatar size="xs" :src="$attendee['user']['profile_photo_url']"/>
                                            <span class="text-sm">{{ $attendee['name'] }}</span>
                                        </div>
                                    @else
                                        <flux:badge>{{ $attendee['name'] }}</flux:badge>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Might Attend -->
                    @if(count($mightAttendees) > 0)
                        <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700">
                            <flux:heading size="lg" class="mb-2">
                                {{ __('Vielleicht') }} ({{ count($mightAttendees) }})
                            </flux:heading>
                            <div class="flex flex-wrap gap-2">
                                @foreach($mightAttendees as $attendee)
                                    @if($attendee['user'])
                                        <div
                                            class="flex items-center gap-2 px-3 py-1.5 bg-zinc-100 dark:bg-zinc-800 rounded-full">
                                            <flux:avatar size="xs" :src="$attendee['user']['profile_photo_url']"/>
                                            <span class="text-sm">{{ $attendee['name'] }}</span>
                                        </div>
                                    @else
                                        <flux:badge variant="outline">{{ $attendee['name'] }}</flux:badge>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </flux:card>

            <!-- Back Button -->
            <div class="mt-6">
                <flux:button
                    href="{{ route('meetups.landingpage', ['meetup' => $event->meetup->slug, 'country' => $country]) }}"
                    variant="ghost">
                    <flux:icon.arrow-left class="w-5 h-5 mr-2"/>
                    {{ __('Zurück zum Meetup') }}
                </flux:button>
            </div>
        </div>

        <div class="md:w-1/3">
            <div class="flex flex-col sm:flex-row items-center space-x-0 sm:space-x-4 space-y-4 sm:space-y-0 mb-8">
                <flux:avatar class="[:where(&)]:size-32 [:where(&)]:text-base" size="xl"
                             src="{{ $event->meetup->getFirstMediaUrl('logo') }}"/>
                <div class="space-y-2">
                    <flux:heading size="xl" class="mb-4">{{ $event->meetup->name }}</flux:heading>
                    <flux:subheading class="text-gray-600 dark:text-gray-400">
                        {{ $event->meetup->city->name }}, {{ $event->meetup->city->country->name }}
                    </flux:subheading>
                    <flux:button class="cursor-pointer"
                                 x-copy-to-clipboard="'{{ route('ics', ['meetup' => $event->meetup]) }}'"
                                 icon="calendar-date-range">{{ __('Kalender-Stream-URL kopieren') }}</flux:button>
                </div>
            </div>
        </div>
    </div>
</div>
