<?php

use App\Attributes\SeoDataAttribute;
use App\Traits\SeoTrait;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('components.layouts.auth')]
#[SeoDataAttribute(key: 'ki_assistent')]
class extends Component {
    use SeoTrait;

    #[Computed]
    public function mcpUrl(): string
    {
        return url('/mcp');
    }

    /**
     * Setup-Schritte mit zugehörigem Screenshot (inkl. intrinsischer Maße gegen Layout-Shift),
     * Titel- und Beschreibungs-Übersetzungsschlüssel.
     *
     * @return list<array{number:int, image:string, width:int, height:int, title:string, description:string}>
     */
    #[Computed]
    public function steps(): array
    {
        return [
            [
                'number' => 1,
                'image' => 'step-1.png',
                'width' => 2863,
                'height' => 1548,
                'title' => 'Connectors öffnen',
                'description' => 'Öffne claude.ai und gehe zu deinem Profil. Wähle »Customize« und dann »Connectors«. Klicke oben auf das Plus-Symbol und anschließend auf »Add custom connector«.',
            ],
            [
                'number' => 2,
                'image' => 'step-2.png',
                'width' => 918,
                'height' => 873,
                'title' => 'Connector hinzufügen',
                'description' => 'Trage als Name »EINUNDZWANZIG PORTAL« ein und als URL die oben gezeigte Adresse. Die Felder unter »Advanced settings« lässt du leer. Klicke dann auf »Add«.',
            ],
            [
                'number' => 3,
                'image' => 'step-3.png',
                'width' => 2858,
                'height' => 1547,
                'title' => 'Verbindung herstellen',
                'description' => 'Der Connector erscheint nun in deiner Liste. Klicke auf »Connect«, um die Verbindung zu starten.',
            ],
            [
                'number' => 4,
                'image' => 'step-4.png',
                'width' => 833,
                'height' => 757,
                'title' => 'Anmelden & Zugriff erlauben',
                'description' => 'Du wirst zum EINUNDZWANZIG-Portal weitergeleitet. Melde dich an (zum Beispiel per Lightning) und klicke auf »Authorize«, um Claude den Zugriff zu erlauben.',
            ],
            [
                'number' => 5,
                'image' => 'step-5.png',
                'width' => 2406,
                'height' => 1502,
                'title' => 'Berechtigungen festlegen',
                'description' => 'Du kannst wählen, welche Aktionen Claude automatisch ausführen darf. Für lesende Werkzeuge ist »Always allow« praktisch. Vor schreibenden Aktionen wirst du sicherheitshalber gefragt.',
            ],
            [
                'number' => 6,
                'image' => 'step-6.png',
                'width' => 2792,
                'height' => 1543,
                'title' => 'Fertig – einfach loslegen!',
                'description' => 'Schreibe Claude jetzt in normaler Sprache, was du möchtest – zum Beispiel: »Ich möchte Dortmund zu meinen Meetups hinzufügen.« Claude sucht, prüft und legt alles für dich im Portal an.',
            ],
        ];
    }

    /**
     * Beispielsätze, die ein Nutzer an Claude richten kann.
     *
     * @return list<string>
     */
    #[Computed]
    public function examples(): array
    {
        return [
            'Füge mein Meetup in meiner Stadt hinzu.',
            'Lege einen neuen Termin für mein Meetup an.',
            'Zeige mir meine Veranstaltungsorte.',
            'Erstelle einen neuen Kurs mit Referent.',
        ];
    }
}; ?>

<div class="relative min-h-screen overflow-hidden text-zinc-900 dark:text-white"
     x-data="{ lightboxSrc: null, lightboxAlt: '' }"
     x-effect="document.body.style.overflow = lightboxSrc ? 'hidden' : ''"
     @keydown.escape.window="lightboxSrc = null">
    {{-- Dekorativer Hintergrund-Glow --}}
    <div aria-hidden="true"
         class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[40rem] bg-[radial-gradient(60rem_30rem_at_50%_-10%,rgba(249,115,22,0.18),transparent)]"></div>

    {{-- Kopfleiste --}}
    <header class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-6">
        <a href="{{ route('welcome') }}" wire:navigate
           class="flex items-center gap-3"
           aria-label="{{ __('Zur Startseite') }}">
            <div class="size-10">
                <x-app-logo-icon/>
            </div>
            <span class="hidden text-sm font-semibold tracking-tight sm:block">{{ __('EINUNDZWANZIG') }}</span>
        </a>

        <div class="flex items-center gap-3">
            <div class="w-44 max-w-[11rem]">
                <livewire:language.selector/>
            </div>
            <flux:button :href="route('welcome')" wire:navigate variant="ghost" size="sm" class="max-sm:hidden">
                {{ __('Zurück zum Portal') }}
            </flux:button>
        </div>
    </header>

    {{-- Hero --}}
    <section class="mx-auto max-w-6xl px-6 pt-10 pb-16 text-center sm:pt-16">
        <flux:badge color="orange" size="sm" icon="sparkles" class="mb-6">{{ __('KI-Assistent') }}</flux:badge>

        <h1 class="mx-auto max-w-3xl text-balance text-4xl font-bold tracking-tight sm:text-6xl">
            {{ __('EINUNDZWANZIG mit Claude verbinden') }}
        </h1>

        <p class="mx-auto mt-6 max-w-2xl text-pretty text-lg text-zinc-600 dark:text-zinc-300">
            {{ __('Verwalte deine Meetups, Termine und Kurse ganz einfach per Chat – mit der KI von claude.ai. Ganz ohne Technikwissen.') }}
        </p>

        <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
            <flux:button href="#einrichtung" variant="primary" icon="rocket-launch" size="base">
                {{ __('Einrichtung starten') }}
            </flux:button>
            <flux:button :href="route('scramble.docs.ui')" target="_blank" variant="ghost" icon="book-open-text" size="base">
                {{ __('Zur API-Dokumentation') }}
            </flux:button>
        </div>
    </section>

    {{-- Was ist das? --}}
    <section class="mx-auto max-w-4xl px-6 pb-16">
        <div class="rounded-2xl border border-zinc-200 bg-white/60 p-8 shadow-sm backdrop-blur dark:border-white/10 dark:bg-white/[0.03] sm:p-10">
            <div class="flex items-start gap-5">
                <div class="hidden shrink-0 rounded-xl bg-orange-500/10 p-3 text-orange-500 sm:block">
                    <flux:icon name="puzzle-piece" class="size-7"/>
                </div>
                <div>
                    <flux:heading size="xl" class="mb-3">{{ __('Was ist der EINUNDZWANZIG-Connector?') }}</flux:heading>
                    <p class="text-pretty leading-relaxed text-zinc-600 dark:text-zinc-300">
                        {{ __('Ein Connector verbindet die KI claude.ai mit dem EINUNDZWANZIG-Portal. Du schreibst Claude einfach in normaler Sprache, was du möchtest – zum Beispiel »Füge mein Meetup hinzu« – und Claude erledigt es für dich im Portal. Alles geschieht in deinem Namen und nur mit deiner ausdrücklichen Erlaubnis.') }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- Voraussetzungen --}}
    <section class="mx-auto max-w-5xl px-6 pb-16">
        <flux:heading size="lg" class="mb-6 text-center">{{ __('Das brauchst du') }}</flux:heading>
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['icon' => 'user-circle', 'text' => __('Ein kostenloses Konto bei claude.ai')],
                ['icon' => 'identification', 'text' => __('Ein Konto im EINUNDZWANZIG-Portal')],
                ['icon' => 'clock', 'text' => __('Etwa 5 Minuten Zeit')],
            ] as $req)
                <div wire:key="req-{{ $loop->index }}"
                     class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white/60 p-5 dark:border-white/10 dark:bg-white/[0.03]">
                    <div class="shrink-0 rounded-lg bg-orange-500/10 p-2.5 text-orange-500">
                        <flux:icon name="{{ $req['icon'] }}" class="size-6"/>
                    </div>
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ $req['text'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Connector-Adresse --}}
    <section class="mx-auto max-w-3xl px-6 pb-16">
        <div class="rounded-2xl border border-orange-500/30 bg-orange-500/[0.06] p-6 sm:p-8">
            <div class="flex items-center gap-3">
                <flux:icon name="link" class="size-5 text-orange-500"/>
                <flux:heading size="lg">{{ __('Die Adresse des Connectors') }}</flux:heading>
            </div>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('Diese Adresse brauchst du in Schritt 2. Du kannst sie hier kopieren:') }}
            </p>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <code class="flex-1 truncate rounded-lg border border-zinc-200 bg-white px-4 py-3 font-mono text-sm text-zinc-900 dark:border-white/10 dark:bg-zinc-900 dark:text-white">{{ $this->mcpUrl }}</code>
                <flux:button x-data x-copy-to-clipboard="'{{ $this->mcpUrl }}'" variant="primary" icon="clipboard-document" class="shrink-0 cursor-pointer">
                    {{ __('Adresse kopieren') }}
                </flux:button>
            </div>
        </div>
    </section>

    {{-- Schritt-für-Schritt --}}
    <section id="einrichtung" class="mx-auto max-w-5xl scroll-mt-8 px-6 pb-20">
        <flux:heading size="xl" class="mb-12 text-center">{{ __('In 6 Schritten eingerichtet') }}</flux:heading>

        <div class="space-y-16">
            @foreach ($this->steps as $step)
                <div wire:key="step-{{ $step['number'] }}"
                     class="grid items-center gap-8 lg:grid-cols-2 @if ($loop->even) lg:[&>figure]:order-first @endif">
                    {{-- Text --}}
                    <div class="@if ($loop->even) lg:order-last @endif">
                        <div class="flex items-center gap-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-orange-500 text-base font-bold text-white">
                                {{ $step['number'] }}
                            </span>
                            <span class="text-xs font-semibold uppercase tracking-wider text-orange-500">
                                {{ __('Schritt :number', ['number' => $step['number']]) }}
                            </span>
                        </div>
                        <flux:heading size="lg" class="mt-4">{{ __($step['title']) }}</flux:heading>
                        <p class="mt-3 text-pretty leading-relaxed text-zinc-600 dark:text-zinc-300">
                            {{ __($step['description']) }}
                        </p>
                    </div>

                    {{-- Screenshot --}}
                    <figure class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-lg ring-1 ring-black/5 dark:border-white/10 dark:bg-zinc-900 dark:ring-white/10">
                        <div class="flex items-center gap-1.5 border-b border-zinc-200 bg-zinc-100 px-3 py-2 dark:border-white/10 dark:bg-zinc-800">
                            <span class="size-2.5 rounded-full bg-red-400"></span>
                            <span class="size-2.5 rounded-full bg-yellow-400"></span>
                            <span class="size-2.5 rounded-full bg-green-400"></span>
                        </div>
                        <button type="button"
                                class="block w-full cursor-zoom-in"
                                @click="lightboxSrc = '{{ asset('img/ki-assistent/'.$step['image']) }}'; lightboxAlt = @js(__($step['title']))"
                                aria-label="{{ __('Screenshot zu Schritt :number vergrößern', ['number' => $step['number']]) }}">
                            <img src="{{ asset('img/ki-assistent/'.$step['image']) }}"
                                 alt="{{ __($step['title']) }}"
                                 loading="lazy"
                                 width="{{ $step['width'] }}"
                                 height="{{ $step['height'] }}"
                                 class="h-auto w-full transition hover:opacity-90"/>
                        </button>
                    </figure>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Beispiele --}}
    <section class="mx-auto max-w-4xl px-6 pb-16">
        <div class="rounded-2xl border border-zinc-200 bg-white/60 p-8 dark:border-white/10 dark:bg-white/[0.03] sm:p-10">
            <flux:heading size="xl" class="text-center">{{ __('Das kannst du Claude sagen') }}</flux:heading>
            <p class="mx-auto mt-3 max-w-xl text-center text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('Sprich oder schreibe mit Claude wie mit einem Menschen. Hier ein paar Beispiele:') }}
            </p>
            <div class="mt-8 grid gap-4 sm:grid-cols-2">
                @foreach ($this->examples as $example)
                    <div wire:key="example-{{ $loop->index }}"
                         class="flex items-start gap-3 rounded-xl bg-zinc-100 px-5 py-4 dark:bg-zinc-800/60">
                        <flux:icon name="chat-bubble-left-right" class="mt-0.5 size-5 shrink-0 text-orange-500"/>
                        <span class="text-sm italic text-zinc-700 dark:text-zinc-200">„{{ __($example) }}"</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Sicherheit --}}
    <section class="mx-auto max-w-4xl px-6 pb-16">
        <flux:callout icon="shield-check" variant="secondary">
            <flux:callout.heading>{{ __('Sicher und in deinem Namen') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Claude handelt ausschließlich mit deiner Erlaubnis und im Kontext deines eigenen Kontos. Du kannst die Verbindung jederzeit in claude.ai unter »Connectors« wieder trennen.') }}
            </flux:callout.text>
        </flux:callout>
    </section>

    {{-- Für Entwickler --}}
    <section class="mx-auto max-w-4xl px-6 pb-24">
        <div class="flex flex-col items-start gap-5 rounded-2xl border border-zinc-200 bg-white/60 p-8 dark:border-white/10 dark:bg-white/[0.03] sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-4">
                <div class="shrink-0 rounded-xl bg-zinc-200/70 p-3 text-zinc-700 dark:bg-white/10 dark:text-zinc-200">
                    <flux:icon name="code-bracket" class="size-6"/>
                </div>
                <div>
                    <flux:heading size="lg">{{ __('Du bist Entwickler?') }}</flux:heading>
                    <p class="mt-1 max-w-md text-sm text-zinc-600 dark:text-zinc-300">
                        {{ __('Du möchtest die Daten direkt über die Schnittstelle nutzen? In der API-Dokumentation findest du alle Endpunkte – inklusive Authentifizierung per persönlichem Token.') }}
                    </p>
                </div>
            </div>
            <flux:button :href="route('scramble.docs.ui')" target="_blank" variant="primary" icon="arrow-up-right" class="shrink-0">
                {{ __('API-Dokumentation öffnen') }}
            </flux:button>
        </div>
    </section>

    {{-- Lightbox --}}
    <div x-show="lightboxSrc"
         x-transition.opacity
         @click="lightboxSrc = null"
         role="dialog"
         aria-modal="true"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm sm:p-8"
         style="display: none">
        <button type="button"
                @click="lightboxSrc = null"
                aria-label="{{ __('Schließen') }}"
                class="absolute right-4 top-4 flex size-10 cursor-pointer items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20">
            <flux:icon name="x-mark" class="size-6"/>
        </button>
        <img :src="lightboxSrc"
             :alt="lightboxAlt"
             @click.stop
             class="max-h-[90vh] max-w-[90vw] rounded-lg object-contain shadow-2xl"/>
    </div>
</div>
