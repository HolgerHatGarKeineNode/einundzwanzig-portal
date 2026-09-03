<?php

use App\Attributes\SeoDataAttribute;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Traits\SeoTrait;
use Livewire\Component;

new
#[SeoDataAttribute(key: 'courses_landingpage')]
class extends Component {
    use SeoTrait;

    public Course $course;

    public $country = 'de';

    public function mount(): void
    {
        $this->country = request()->route('country', config('app.domain_country'));
        $this->course->load([
           'courseEvents.registrations',
        ]);
    }

    public function with(): array
    {
        return [
            'course' => $this->course->load('lecturer'),
            'events' => $this->course
                ->courseEvents()
                ->with([
                    'city',
                    'registrations',
                ])
                ->where('from', '>=', now())
                ->orderBy('from', 'asc')
                ->get(),
        ];
    }
}; ?>

<div class="container mx-auto px-4 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Left Column: Course Details -->
        <div class="space-y-6">
            <div class="flex items-center space-x-4">
                <flux:avatar class="[:where(&)]:size-32 [:where(&)]:text-base" size="xl"
                             src="{{ $course->getFirstMedia('logo') ? $course->getFirstMediaUrl('logo') : asset('android-chrome-512x512.png') }}"/>
                <div class="space-y-2">
                    <flux:heading size="xl" class="mb-4">{{ $course->name }}</flux:heading>
                    @if($course->lecturer)
                        <flux:subheading class="text-gray-600 dark:text-gray-400 flex items-center gap-2">
                            <flux:avatar size="xs"
                                         src="{{ $course->lecturer->getFirstMedia('avatar') ? $course->lecturer->getFirstMediaUrl('avatar', 'thumb') : asset('img/einundzwanzig.png') }}"/>
                            {{ $course->lecturer->name }}
                        </flux:subheading>
                    @endif
                </div>
            </div>

            @if($course->description)
                <div>
                    <flux:heading size="lg" class="mb-2">{{ __('Über den Kurs') }}</flux:heading>
                    <x-markdown class="prose whitespace-pre-wrap">{!! $course->description !!}</x-markdown>
                </div>
            @endif

            @if($course->lecturer)
                <div class="space-y-4">
                    <flux:heading size="lg">{{ __('Über den Dozenten') }}</flux:heading>

                    <div class="flex items-start gap-4 p-4 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                        <flux:avatar size="lg"
                                     src="{{ $course->lecturer->getFirstMedia('avatar') ? $course->lecturer->getFirstMediaUrl('avatar', 'preview') : asset('img/einundzwanzig.png') }}"/>
                        <div class="flex-1">
                            <flux:heading size="md" class="mb-1">{{ $course->lecturer->name }}</flux:heading>
                            @if($course->lecturer->subtitle)
                                <flux:text
                                    class="text-sm text-zinc-600 dark:text-zinc-400 mb-2">{{ $course->lecturer->subtitle }}</flux:text>
                            @endif
                            @if($course->lecturer->intro)
                                <x-markdown
                                    class="prose prose-sm whitespace-pre-wrap">{!! $course->lecturer->intro !!}</x-markdown>
                            @endif

                            <!-- Lecturer Social Links -->
                            <div class="mt-4 flex flex-wrap gap-2">
                                @if($course->lecturer->website)
                                    <flux:button href="{{ $course->lecturer->website }}" target="_blank" rel="noopener noreferrer" variant="ghost"
                                                 size="xs">
                                        <flux:icon.globe-alt class="w-4 h-4 mr-1"/>
                                        Website
                                    </flux:button>
                                @endif

                                @if($course->lecturer->twitter_username)
                                    <flux:button href="https://twitter.com/{{ $course->lecturer->twitter_username }}"
                                                 target="_blank" rel="noopener noreferrer" variant="ghost" size="xs">
                                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                            <path
                                                d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                                        </svg>
                                        Twitter
                                    </flux:button>
                                @endif

                                @if($course->lecturer->nostr)
                                    <flux:button href="https://njump.me/{{ $course->lecturer->nostr }}" target="_blank" rel="noopener noreferrer"
                                                 variant="ghost" size="xs">
                                        <flux:icon.bolt class="w-4 h-4 mr-1"/>
                                        Nostr
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Right Column: Lecturer Avatar/Info -->
        <div>
            @if($course->lecturer && $course->lecturer->getFirstMedia('avatar'))
                <div class="sticky top-8">
                    <flux:heading size="lg" class="mb-4">{{ __('Dozent') }}</flux:heading>
                    <img src="{{ $course->lecturer->getFirstMediaUrl('avatar') }}"
                         alt="{{ $course->lecturer->name }}"
                         class="w-full rounded-lg shadow-lg"/>
                </div>
            @endif
        </div>
    </div>

    <!-- Events Section -->
    @if($events->isNotEmpty())
        <div class="mt-16">
            <flux:heading size="xl" class="mb-6">{{ __('Kommende Veranstaltungen') }}</flux:heading>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($events as $event)
                    <flux:card size="sm" class="h-full flex flex-col">
                        <flux:heading class="flex items-center gap-2">
                            {{ $event->from->format('d.m.Y') }}
                        </flux:heading>

                        <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                            <flux:icon.clock class="inline w-4 h-4"/>
                            {{ __(':from - :to Uhr', ['from' => $event->from->format('H:i'), 'to' => $event->to->format('H:i')]) }}
                        </flux:text>

                        {{-- Die Whitelist fuer osm_type, das 24px-Ziel und der Pfeil stehen
                             jetzt in x-osm-place, damit die naechste Korrektur nicht vier
                             Kopien verfehlt.

                             Die Stadt steht bewusst NEBEN der Komponente, nicht in ihrem Slot:
                             ein Termin ohne Kartenort und ohne Freitext hat trotzdem eine
                             Stadt, und die soll er weiter zeigen. --}}
                        @if($event->osm_name || $event->location || $event->city)
                            <div class="mt-2 flex items-start gap-1.5">
                                <flux:icon.map-pin class="mt-0.5 size-4 shrink-0 text-zinc-600 dark:text-zinc-300"
                                                   aria-hidden="true"/>
                                {{-- Der gemeinsame Wrapper haelt beide Zustaende auf derselben
                                     Hoehe. Gemessen: ohne ihn schob das eigene Padding des
                                     Ankers die Stadt um 4px nach unten, und zwei Karten
                                     nebeneinander liefen ausgefranst. --}}
                                <div class="min-w-0 break-words">
                                    <x-osm-place :place="$event"/>
                                    @if($event->city)
                                        <div class="text-sm text-zinc-600 dark:text-zinc-300">
                                            {{ $event->city->name }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div class="mt-2 flex items-center gap-2 text-xs text-zinc-600 dark:text-zinc-300">
                            <span>{{ trans_choice(':count Anmeldung|:count Anmeldungen', $event->registrations->count()) }}</span>
                        </div>

                        <div class="mt-auto pt-4 flex gap-2">
                            <flux:button
                                target="_blank"
                                rel="noopener noreferrer"
                                :href="$event->link"
                                size="xs"
                                variant="primary"
                                class="flex-1"
                            >
                                {{ __('Details/Anmelden') }}
                            </flux:button>
                            {{--
                                Hier stand `$course->created_by === auth()->id()` — die FALSCHE
                                ENTITAET, nicht bloss eine zu enge Bedingung. Der Knopf fuehrt
                                auf `courses.events.edit`, bearbeitet also den TERMIN; gefragt
                                wurde nach dem Kurs.

                                Nach P1 fallen die beiden auseinander: `CourseEventPolicy::update()`
                                haengt am Ersteller des Termins, und der Kurs-Eigentuemer darf
                                fremde Termine an seinem Kurs ausdruecklich NICHT mehr aendern.
                                Der alte Vergleich zeigte ihm den Knopf trotzdem — und dem
                                Termin-Ersteller, dem die Aktion offensteht, zeigte er keinen.
                                Beides falsch, in beide Richtungen.
                            --}}
                            @can('update', $event)
                                <flux:button
                                    :href="route_with_country('courses.events.edit', ['course' => $course, 'event' => $event])"
                                    size="xs"
                                    variant="ghost"
                                    icon="pencil"
                                >
                                    {{ __('Bearbeiten') }}
                                </flux:button>
                            @endcan
                        </div>
                    </flux:card>
                @endforeach
            </div>
        </div>
    @endif
</div>
