<?php

use App\Attributes\SeoDataAttribute;
use App\Models\MeetupEvent;
use App\Models\User;
use App\Traits\SeoTrait;
use Livewire\Attributes\Validate;
use Livewire\Component;

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

    // Anmeldung für dieses Meetup erlaubt? Teilnehmerliste für den Betrachter sichtbar?
    public bool $rsvpEnabled = true;
    public bool $attendeesPublic = true;
    public bool $canSeeAttendees = true;

    public function mount(): void
    {
        $this->country = request()->route('country', config('app.domain_country'));
        $this->name = auth()->user()->name ?? '';

        $meetup = $this->event->meetup;
        $this->rsvpEnabled = (bool) $meetup->rsvp_enabled;
        $this->attendeesPublic = (bool) $meetup->attendees_public;
        $this->canSeeAttendees = $meetup->attendeesVisibleTo(auth()->user());

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

        // Namen nur für Berechtigte in den (an den Client serialisierten) Zustand
        // legen – bei verborgener Liste bleiben die Arrays leer.
        $this->attendees = $this->canSeeAttendees ? $this->mapAttendees($attendees) : [];
        $this->mightAttendees = $this->canSeeAttendees ? $this->mapAttendees($mightAttendees) : [];
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
        if (! $this->rsvpEnabled) {
            return;
        }

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
        if (! $this->rsvpEnabled) {
            return;
        }

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

@section('meta')
    @php
        $SEOData = SeoDataAttribute::getData('meetups_landingpage');
        $SEOData->title = $this->event->meetup->name;
        $SEOData->description = $this->event->meetup->intro ? str($this->event->meetup->intro)->limit(50) : $SEOData->description;
        $SEOData->image = $this->event->meetup->getFirstMediaUrl('logo');
    @endphp
    {!! seo($SEOData)->render() !!}
@endsection

<div class="container mx-auto px-4 py-8">
    <!-- Breadcrumb -->
    <div class="mb-6">
        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
            <a href="{{ route('meetups.landingpage', ['meetup' => $event->meetup->slug, 'country' => $country]) }}"
               class="hover:underline">
                {{ $event->meetup->name }}
            </a>
            <span class="mx-2">/</span>
            <span>{{ $event->start->asDate() }}</span>
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

                {{-- Series marker (issue #43). `recurrence_group` is the only reliable
                     series identity: the 2026_08_25_194948 migration backfilled that
                     column alone, so events of pre-P5 series carry `recurrence_type = null`
                     and would be missed by it.

                     The note sits between the heading and the data list, pulled up by
                     -mt-2 so it reads as a qualifier of the date rather than as a fourth
                     data field beside Zeit/Ort/Beschreibung. It states the fact and
                     nothing more: no series view exists and a series cannot be edited,
                     so the wording promises neither. --}}
                @if($event->recurrence_group !== null)
                    <div class="-mt-2 mb-4 flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-400"
                         data-testid="series-note">
                        <flux:icon.arrow-path class="mt-0.5 size-4 shrink-0" aria-hidden="true"/>
                        <span>{{ __('Dieser Termin gehört zu einer wiederkehrenden Serie.') }}</span>
                    </div>
                @endif

                <div class="space-y-4">
                    <!-- Date and Time -->
                    <div class="flex items-center text-zinc-700 dark:text-zinc-300">
                        <flux:icon.clock class="w-5 h-5 mr-3"/>
                        <div>
                            <div class="font-semibold">{{ __(':time Uhr', ['time' => $event->start->asTime()]) }}</div>
                            <div
                                class="text-sm text-zinc-600 dark:text-zinc-400">{{ $event->start->asDate() }}</div>
                        </div>
                    </div>

                    <!-- Location -->
                    {{-- Die Bedingung waechst von `location` auf `osm_name || location`:
                         ein Termin mit Kartenort, aber ohne getippten Freitext zeigt jetzt
                         etwas statt nichts. Die alte Bedingung ist eine echte Teilmenge
                         der neuen — kein bestehender Termin verliert seine Ortszeile. --}}
                    @if($event->osm_name || $event->location)
                        <div class="flex items-start text-zinc-700 dark:text-zinc-300">
                            <flux:icon.map-pin class="mt-0.5 w-5 h-5 mr-3 shrink-0" aria-hidden="true"/>
                            <div class="min-w-0">
                                <div class="font-semibold">{{ __('Ort') }}</div>
                                <x-osm-place :place="$event" show-address/>
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

                    {{-- Issue #49: die NIP-52-Adresse des Termins (kind 31923) im
                         Link-Bereich, wie im Report gefordert. Der Schalter kommt vom
                         Meetup — ein Termin hat keinen eigenen (Migration
                         2026_08_29_170904). --}}
                    <div class="pt-4 grid grid-cols-1">
                        <x-nostr-calendar-address :record="$event"
                                                  :publishing-enabled="(bool) $event->meetup->nostr_publishing_enabled"/>
                    </div>

                    <!-- RSVP Section -->
                    @if($rsvpEnabled)
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

                    @endif

                    <!-- Hinweis für Leader, wenn die Liste öffentlich verborgen ist -->
                    @if($canSeeAttendees && !$attendeesPublic)
                        <flux:callout variant="secondary" icon="eye-slash" inline>
                            <flux:callout.heading>{{ __('Teilnehmerliste ist öffentlich verborgen') }}</flux:callout.heading>
                            <flux:callout.text>{{ __('Nur du und weitere Leader sehen, wer sich angemeldet hat.') }}</flux:callout.text>
                        </flux:callout>
                    @endif

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
            {{-- Issue #66: this header used to overflow its own column silently.
                 Measured at a 1280px viewport before the fix: the column ended at
                 x=1232 while its contents ran to x=1290.95 — 58.95px past the column,
                 10.95px past the viewport, with `documentElement.scrollWidth` still
                 at 1280, so no scrollbar ever appeared and the name, the city line
                 and the "Kalender abonnieren" button were cut off.

                 Cause, measured: the text block's min-content width was 213.61px,
                 dictated by the calendar-stream trigger, which carries Flux' default
                 `whitespace-nowrap` (button/index.blade.php:65). Beside the 128px
                 avatar plus a 16px gap that demands 357.61px of column; the column
                 measured 298.66px at 1280. A flex item cannot shrink below its
                 min-content, so the surplus left the column instead.

                 Three changes, all of them making the content shrinkable. The column
                 keeps its `md:w-1/3` and nothing here widens anything:

                 - The trigger overrides `!whitespace-normal` + `!h-auto` + `min-h-11`
                   let the label wrap instead of forcing one line, which drops the
                   text block's min-content from 213.61px to 138px. Measured height
                   stays 44px at every width, wrapped or not, so the touch target
                   holds (WCAG 2.5.8 / Apple HIG). This is verbatim the pattern the
                   picker already uses on its own copy buttons.
                 - `flex-wrap` plus `sm:basis-56 sm:grow` decide WHERE the row breaks
                   instead of leaving it to the name's max-content: the text block
                   asks for 224px, so avatar + gap + text need 368px of column. Above
                   that the two stay side by side, below it the text block moves under
                   the avatar. Measured: side by side at 1920 (column 490.67px) and
                   1536 (373.33px) — the two widths the issue reports as sound, so
                   they are left exactly as they were — wrapped at 1440 (341.33px),
                   1280 (288px), 1024 (202.67px) and 768 (218.67px), where the row
                   did not fit. Below `sm` the container is `flex-col` anyway.
                 - `wrap-anywhere` on the name: a one-word meetup name is one word,
                   and `overflow-wrap: anywhere` is the only value that lowers the
                   min-content width (`break-words` does not). Measured with the name
                   "Bitcoinmeetupfrankfurtammainundumgebung": without it the header
                   runs 262.95px past the column at 1280 and 266.61px at 375 — the
                   mobile width the original defect spared — with it, 0 at both.

                 `space-x-*`/`space-y-*` become `gap-4`: identical 16px spacing, but
                 `space-*` sets margins per sibling and leaves a wrapped row without
                 vertical spacing.

                 Pinned by tests/Browser/Meetups/EventHeaderFitsColumnTest.php, whose
                 negative control strips these utilities back off the live DOM and
                 reproduces the reported 58.95px to the pixel. --}}
            <div class="flex flex-col sm:flex-row flex-wrap items-center gap-4 mb-8">
                <flux:avatar class="[:where(&)]:size-32 [:where(&)]:text-base shrink-0" size="xl"
                             src="{{ $event->meetup->getFirstMediaUrl('logo') }}"/>
                <div class="space-y-2 sm:basis-56 sm:grow [&_[data-testid$='-trigger']]:!whitespace-normal [&_[data-testid$='-trigger']]:!h-auto [&_[data-testid$='-trigger']]:min-h-11 [&_[data-testid$='-trigger']]:w-full [&_[data-testid$='-trigger']]:text-left">
                    <flux:heading size="xl" class="mb-4 wrap-anywhere">{{ $event->meetup->name }}</flux:heading>
                    <flux:subheading class="text-gray-600 dark:text-gray-400">
                        {{ $event->meetup->city->name }}, {{ $event->meetup->city->country->name }}
                    </flux:subheading>
                    <x-calendar-stream-picker :meetup-id="$event->meetup->id"/>
                </div>
            </div>

            {{-- hidden md:block: auf schmalen Geraeten steht die rechte Spalte unter dem
                 kompletten Termin samt Teilnehmerlisten — dort waere die Karte weit weg
                 von der Ortszeile, auf die sie sich bezieht, und laedt trotzdem ihre
                 Kacheln. Die Ortsangabe selbst steht oben, auf jeder Breite. --}}
            @if($event->osm_lat && $event->osm_lon)
                <div class="hidden md:block">
                    <flux:heading size="lg" class="mb-2">{{ __('Anfahrt') }}</flux:heading>
                    <x-venue-map :place="$event"/>
                </div>
            @endif
        </div>
    </div>
</div>
