<?php

use App\Models\Tag;
use App\Support\TagLocales;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Modelable;
use Livewire\Component;

/**
 * Multilingual tag picker.
 *
 * The cross-language search needs no JavaScript of our own: Flux's FilterableGroup
 * matches on `el.textContent`, NFD-normalised and diacritic-insensitive, and that
 * includes hidden spans. So every option carries one hidden alias per locale, and a
 * Czech organiser typing "zaklady" finds a tag that only exists in German.
 *
 * The visible label is set through `selected-label`; without it Flux would put all
 * nine translations into the chip.
 */
new class extends Component
{
    /**
     * Selected tag ids, bound to the parent form via wire:model.
     *
     * Deliberately untyped. Flux's combobox writes the *typed text* into the model
     * when the user hits ENTER instead of clicking the create row, and a typed
     * `array` turns that into a 500 before any hook can run:
     * "Cannot assign string to property ... of type array". The normalisation lives
     * in updatedTagIds(), which needs the value to arrive at all.
     *
     * @var array<int, int>|string
     */
    #[Modelable]
    public $tagIds = [];

    /**
     * Der getippte Suchtext.
     *
     * Flux' Combobox erwartet dafuer eine eigene Property (x-slot "input" mit
     * flux:pillbox.input). Ohne sie schrieb Flux den Text bei ENTER in `tagIds` —
     * das war der 500er "Cannot assign string to property ... of type array".
     */
    public string $search = '';

    #[Locked]
    public string $type = 'meetup_event';

    /** Whether the surrounding form requires at least one tag (per country). */
    #[Locked]
    public bool $required = false;

    public ?string $label = null;

    /**
     * Nur Ids behalten.
     *
     * Mit dem Input-Slot kann Flux hier nichts anderes mehr hineinschreiben; die
     * Pruefung bleibt trotzdem, weil das Elternformular den Wert ungeprueft in
     * whereIn() gibt und ein manipulierter Snapshot sonst dort landet.
     */
    public function updatedTagIds(): void
    {
        $this->tagIds = collect(is_array($this->tagIds) ? $this->tagIds : [])
            ->filter(fn ($id): bool => is_numeric($id))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Everything selectable, as {@see Tag::scopeSelectableBy()} defines it: every tag
     * of this type while the approval gate is off, and approved tags plus the current
     * user's own pending suggestions while it is on.
     *
     * `ordered()` is the moderation screen's sort order (tags.order_column). Without
     * it the resting list came out in whatever order the database returned and the
     * ordering controls over in tags.moderation would have moved a number nobody
     * ever sees. sortByDesc() only lifts the featured block to the top; PHP's sort
     * has been stable since 8.0, so the order_column sequence survives inside each
     * block.
     */
    public function getOptionsProperty(): Collection
    {
        return Tag::query()
            ->where('type', $this->type)
            ->selectableBy(auth()->user())
            ->ordered()
            ->get()
            ->sortByDesc('featured')
            ->values();
    }

    public function getFeaturedCountProperty(): int
    {
        return $this->options->where('featured', true)->count();
    }

    /** Whether this user creates tags outright rather than only suggesting them. */
    public function getCanCreateDirectlyProperty(): bool
    {
        return auth()->user()?->can('create', Tag::class) ?? false;
    }

    public function getCanAddProperty(): bool
    {
        return auth()->check();
    }

    /**
     * The tags behind the current selection, in the vocabulary's own order.
     *
     * Filtered from `options` rather than queried by id: the options are already
     * on the component, a second query would grow with the size of the selection
     * (AdministrationFormPerformanceTest pins the form's query shape against
     * exactly that), and a tag the user cannot see in the picker has no business
     * growing an explanation under the field either — its chip is equally absent.
     *
     * @return Collection<int, Tag>
     */
    public function getSelectedTagsProperty(): Collection
    {
        $ids = collect(is_array($this->tagIds) ? $this->tagIds : [])
            ->filter(fn ($id): bool => is_numeric($id))
            ->map(fn ($id): int => (int) $id)
            ->flip();

        return $this->options->filter(fn (Tag $tag): bool => $ids->has($tag->id))->values();
    }

    /**
     * Whether the current selection includes the Families tag, which carries its
     * own inline hint beyond the definition.
     *
     * Issue #149 asked for one specific nudge: choosing "Familien" is a promise
     * about physical reality (space, play corner), and the one place that promise
     * is kept or broken is the event text — so the hint points there. Identified
     * by the German source name, the same identity the seeder matches on; the
     * name exists in all nine locales, the flag in the database.
     */
    public function getShowsFamilyHintProperty(): bool
    {
        return $this->selectedTags
            ->contains(fn (Tag $tag): bool => $tag->is_commitment
                && $tag->getTranslation('name', 'de', false) === 'Familien');
    }

    /**
     * Groups the selection holds two or more members of — the soft contradiction
     * hint of issue #149.
     *
     * Members are matched by German name, the vocabulary's source language (see
     * config('einundzwanzig.tag_groups')). The message is chosen by group key so
     * the phrasing can say WHAT is odd about this particular combination; a group
     * the config knows but no text does gets the generic phrasing rather than
     * silence — a hint that only fires for some groups would look broken, not
     * careful.
     *
     * @return Collection<int, array{group: string, tags: Collection<int, Tag>, message: string}>
     */
    public function getGroupConflictsProperty(): Collection
    {
        return collect((array) config('einundzwanzig.tag_groups', []))
            ->map(fn (array $members, string $group): array => [
                'group' => $group,
                'tags' => $this->selectedTags->filter(
                    fn (Tag $tag): bool => in_array(
                        (string) $tag->getTranslation('name', 'de', false),
                        $members,
                        true,
                    ),
                )->values(),
            ])
            ->filter(fn (array $conflict): bool => $conflict['tags']->count() >= 2)
            ->values()
            ->map(function (array $conflict): array {
                $names = $conflict['tags']
                    ->map(fn (Tag $tag): string => $tag->displayName())
                    ->join(' + ');

                $conflict['message'] = match ($conflict['group']) {
                    /*
                     * "beides" is safe in the format text: the group has exactly two
                     * members. The niveau text stays count-neutral because all three
                     * levels can be picked at once.
                     */
                    'format' => __('„:names“ gleichzeitig? Das passt nur, wenn das Programm beides bewusst anbietet — prüfe die Format-Tags.', ['names' => $names]),
                    'niveau' => __('„:names“ gleichzeitig? Wähle eine Zielgruppen-Stufe oder beschreibe getrennte Programmteile im Eventtext.', ['names' => $names]),
                    default => __('„:names“ gleichzeitig gewählt — prüfe, ob das beabsichtigt ist.', ['names' => $names]),
                };

                return $conflict;
            });
    }

    /**
     * Create the tag the user typed, or select the existing one it duplicates.
     *
     * `approved_at` follows the `create` ability, which follows the approval gate: with
     * the gate off every signed-in user passes `can('create')`, so every tag is stamped
     * `now()` and is live at once. With the gate on, an editor's tag is live immediately
     * and anyone else's is stored unapproved but still selected here and now — otherwise
     * a mandatory-tag country would be a dead end for them.
     *
     * ONE LOCALE, NOT NINE. This used to copy the typed name into all nine tag locales
     * so the other eight could find it. The copy was not a translation, and it disabled
     * the very mechanism built to make that visible: with a name present in every
     * language, `Tag::isDisplayNameSubstituted()` is false for every reader, so the
     * picker's "only available in :lang" line could never fire. Measured on production
     * on 2026-09-05: three tags in that state, among them the Czech `Rodiny s dětmi`
     * stored as the German, English, Spanish, Hungarian, Latvian, Dutch, Polish and
     * Portuguese name.
     *
     * `source_locale` records what is actually known — the tag locale in force while the
     * name was typed. It is a statement about the interface the author was using, not a
     * detected language of the string; nothing here inspects the text.
     *
     * Cross-language findability is unchanged for tags that carry real translations
     * (aliasesFor() still feeds every locale to Flux's matcher), and a moderator fills
     * the remaining eight in tags.moderation. Until then the tag reads as what it is: a
     * label in one language, marked as such.
     */
    public function createTag(?string $name = null): void
    {
        // Flux ruft die Aktion ohne Argument auf und liefert den Suchtext ueber die
        // gebundene Property; der Parameter bleibt fuer direkte Aufrufe erhalten.
        $name = trim(preg_replace('/\s+/u', ' ', $name ?? $this->search) ?? '');
        $user = auth()->user();

        abort_unless($user !== null, 403);

        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            return;
        }

        $mayCreate = $user->can('create', Tag::class);

        abort_unless($mayCreate || $user->can('suggest', Tag::class), 403);

        // Duplicate guard across ALL locales, not just the current one.
        $existing = $this->findByAnyLocale($name);

        if ($existing !== null) {
            $this->select($existing->id);
            $this->search = '';

            return;
        }

        $locale = TagLocales::current();

        $tag = new Tag(['type' => $this->type]);
        $tag->source_locale = $locale;
        $tag->setTranslation('name', $locale, $name);

        $tag->icon = 'tag';
        $tag->featured = false;
        $tag->approved_at = $mayCreate ? now() : null;
        $tag->save();

        $this->select($tag->id);

        $this->search = '';
    }

    private function select(int $id): void
    {
        if (! in_array($id, $this->tagIds, true)) {
            $this->tagIds[] = $id;
        }
    }

    /**
     * Case-insensitive match against every locale of every tag of this type.
     */
    private function findByAnyLocale(string $name): ?Tag
    {
        $needle = mb_strtolower($name);
        $locales = config('einundzwanzig.tag_locales');

        return Tag::query()
            ->where('type', $this->type)
            ->get()
            ->first(function (Tag $tag) use ($needle, $locales): bool {
                foreach ($locales as $locale) {
                    if (mb_strtolower((string) $tag->getTranslation('name', $locale, false)) === $needle) {
                        return true;
                    }
                }

                return false;
            });
    }

    /**
     * Aliases fed to Flux's text matcher: every locale's name plus the slugs.
     * Rendered hidden, so they widen the search without cluttering the row.
     *
     * @return array<int, string>
     */
    public function aliasesFor(Tag $tag): array
    {
        $locales = config('einundzwanzig.tag_locales');

        return collect($locales)
            ->flatMap(fn (string $locale): array => [
                $tag->getTranslation('name', $locale, false),
                $tag->getTranslation('slug', $locale, false),
            ])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}; ?>

<div>
    <flux:field>
        <flux:label :badge="$required ? __('Pflicht') : null">
            {{ $label ?? __('Tags') }}
        </flux:label>

        {{-- `multiple` ist der Unterschied zwischen Mehrfach- und Einfachauswahl.
             Ohne das Attribut tauschte die Pillbox die Wahl bei jedem Klick aus, statt
             sie zu ergaenzen — gemeldet am 2026-08-23 mit Bildschirmfoto. Jedes
             Beispiel der Flux-Dokumentation fuehrt es, hier fehlte es.

             `.live` (issue #149): ein plaines wire:model ist deferred — die Auswahl
             geht NICHT zum Server, bis irgendeine andere Aktion committet. Gemessen
             im Browser: null Fetches nach dem Klick, der Chip erschien rein
             clientseitig. Die Antwortzone (Definitionen, Hinweise) muesste bis zum
             Speichern stumm bleiben. Eine Auswahl ist ein seltener, bewusster Klick;
             ein Roundtrip pro Wahl ist der Preis fuer eine Oberflaeche, die auf die
             Wahl antwortet — dasselbe Argument, das der Featured-Switch in
             tags.moderation schon fuer sich entschieden hat. --}}
        <div x-data="{ q: '' }" x-bind:data-searching="q.length > 0 ? 'true' : 'false'">
            <flux:pillbox
                variant="combobox"
                multiple
                wire:model.live="tagIds"
                :placeholder="__('Tags wählen')"
                data-testid="tag-picker"
            >
                {{-- Der Suchtext gehoert in eine eigene Property. Vorher hing er nur an
                     einer Alpine-Variablen, und Flux schrieb ihn bei ENTER in `tagIds` —
                     ein 500er, weil dort ein Array erwartet wird. Das Spiegeln nach
                     Alpine bleibt: das CSS unten blendet nicht hervorgehobene Marken
                     aus, solange nichts getippt ist. --}}
                <x-slot name="input">
                    <flux:pillbox.input
                        wire:model="search"
                        :placeholder="__('Tags wählen')"
                        x-on:input="q = $event.target.value ?? ''"
                        x-on:change="$nextTick(() => q = '')"
                    />
                </x-slot>

                @foreach ($this->options as $tag)
                    <flux:pillbox.option
                        :value="$tag->id"
                        :selected-label="$tag->displayName()"
                        @class(['tag-option', 'tag-option--featured' => $tag->featured])
                        data-featured="{{ $tag->featured ? 'true' : 'false' }}"
                        data-testid="tag-option-{{ $tag->id }}"
                    >
                        <span class="flex flex-col">
                            <span class="flex items-center gap-2">
                                {{-- Glyph, not colour: the house palette is monochrome. --}}
                                <span aria-hidden="true" class="text-xs text-zinc-600 dark:text-zinc-300">{{ $tag->featured ? '●' : '○' }}</span>

                                {{-- Never `$tag->icon` directly: an unresolvable name throws
                                     and takes the whole form down. --}}
                                @include('livewire.tags.partials.icon', [
                                    'tagIcon' => $tag->icon,
                                    'tagIconClass' => 'size-4 text-zinc-600 dark:text-zinc-300',
                                    'tagIconWrapperClass' => 'inline-flex shrink-0 self-center',
                                ])

                                <span>{{ $tag->displayName() }}</span>

                                {{-- Issue #149: the commitment badge. Flux's default (zinc)
                                     badge measured on the composited pairs — 12px/500 is NOT
                                     WCAG large text, so 4.5:1 applies:
                                     light  zinc-700 #3f3f46 on zinc-400/15 over white  = #f1f1f2 → 9.3:1
                                     dark   zinc-200 #e4e4e7 on zinc-400/40 over zinc-800 = #58585d → 5.6:1
                                     Monochrome on purpose: a promise is information, not a
                                     warning — amber here read as caution and measured 4.4:1
                                     light, under the limit (same failure mode as issue #98). --}}
                                @if ($tag->is_commitment)
                                    <flux:badge size="sm" icon="hand-raised"
                                                data-testid="commitment-badge-{{ $tag->id }}">
                                        {{ __('Versprechen an Besucher') }}
                                    </flux:badge>
                                @endif
                                {{-- Only while the approval gate is on (issue #143). With it off a
                                     NULL approved_at is provenance — "arrived as a suggestion" — and
                                     no longer says anything about whether the tag may be used, so
                                     the marker would announce a review that will never happen. --}}
                                @if (config('einundzwanzig.tags.require_approval', true) && ! $tag->isApproved())
                                    <span class="text-xs text-zinc-600 dark:text-zinc-300">{{ __('in Prüfung') }}</span>
                                @endif
                            </span>

                            {{--
                                Provenance line. Without it a row carrying a foreign-language
                                label reads as noise — and a user who distrusts the row creates
                                a second tag instead, which is the duplicate sprawl we are
                                trying to end.
                            --}}
                            @if ($tag->isDisplayNameSubstituted())
                                {{-- opacity-60 on zinc-800 composites to #7d7d7d — 4.13:1 on
                                     white, under the 4.5:1 of WCAG 1.4.3. Named colours
                                     instead: 7.8:1 light, 12.5:1 dark. --}}
                                <span class="flex items-center gap-1 ps-5 text-xs text-zinc-600 dark:text-zinc-300">
                                    <span aria-hidden="true">└</span>
                                    <span>{{ __('nur auf :lang vorhanden', ['lang' => mb_strtoupper($tag->displayLocale())]) }}</span>
                                </span>
                            @endif

                            @php $optionDescription = $tag->displayDescription(); @endphp
                            @if ($optionDescription !== '')
                                {{-- Issue #149: the meaning travels with the choice, not with a
                                     manual. Two lines are enough to decide "is this my tag?";
                                     the full text waits below the field once it is picked.

                                     Deliberately visible, not a hidden alias span: Flux's
                                     matcher reads textContent, so the words of a description
                                     now also find their tag - typing "kinder" finds "Familien"
                                     even before the name itself matches. That widens the
                                     search, and for a guidance feature that is the point. --}}
                                <span class="line-clamp-2 ps-5 text-xs text-zinc-600 dark:text-zinc-300">
                                    {{ $optionDescription }}
                                </span>
                            @endif
                        </span>

                        {{-- Searchable in every language; Flux matches on textContent. --}}
                        <span hidden>{{ implode(' ', $this->aliasesFor($tag)) }}</span>
                    </flux:pillbox.option>
                @endforeach

                @if ($this->canAdd)
                    {{--
                        Present for both roles — it just leads somewhere different. Editors
                        create, everyone else suggests. Hiding it from non-editors would
                        leave them at a dead end when nothing fits.

                        x-show rather than Flux's own min-length: display:none is what
                        filterAwareWalker checks, so the row stays keyboard-consistent.
                    --}}
                    {{-- min-length und wire:click sind die vorgesehenen Anschluesse:
                         Flux blendet die Zeile selbst aus, solange zu wenig getippt ist,
                         versteckt sie bei einem Treffer in der Liste und sperrt sie
                         waehrend des Requests gegen Doppelanlagen. Das taten vorher
                         x-show und ein Alpine-Aufruf, der den Text am Server vorbei
                         uebergab. --}}
                    <flux:pillbox.option.create
                        wire:click="createTag"
                        min-length="2"
                        data-testid="tag-create"
                    >
                        {{ $this->canCreateDirectly ? __('als neuen Tag anlegen:') : __('vorschlagen:') }}
                        „<span wire:text="search"></span>“
                    </flux:pillbox.option.create>
                @endif
            </flux:pillbox>
        </div>

        <flux:description>
            @if ($required)
                {{ __('Mindestens 1 Tag — in diesem Land erforderlich.') }}
            @else
                {{ __('Optional — hilft Besuchern, dein Event zu finden.') }}
            @endif
        </flux:description>

        {{--
            Issue #149: the answer zone. The static line above explains what the field
            is for; everything here is a REACTION to what the organiser just picked —
            the meaning of every selected tag, plus the two soft hints (families,
            contradicting groups). It appears below the static hint so the hint itself
            never jumps when a tag is added or removed; changing content grows downward,
            stable content stays put.

            A tag without a description in ANY language gets no row at all: a term with
            nothing under it is exactly the blank line the fallback chain exists to
            prevent, and it would read as "this tag was refused an explanation".
        --}}
        {{-- Pre-filtered, not @continue'd per row: with every chosen tag
             unexplained (fresh community tags) the loop would otherwise leave
             an empty <dl> behind — a container that announces a list and then
             lists nothing. --}}
        @php $explainedTags = $this->selectedTags->filter(fn (Tag $tag): bool => $tag->displayDescription() !== ''); @endphp
        @if ($explainedTags->isNotEmpty())
            <dl class="flex flex-col gap-2" data-testid="tag-definitions">
                @foreach ($explainedTags as $tag)
                    <div class="flex flex-col gap-0.5" data-testid="tag-definition-{{ $tag->id }}">
                        <dt class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm font-medium">
                            {{ $tag->displayName() }}

                            {{-- Same badge, same measured pairs, as on the option row above. --}}
                            @if ($tag->is_commitment)
                                <flux:badge size="sm" icon="hand-raised"
                                            data-testid="definition-commitment-{{ $tag->id }}">
                                    {{ __('Versprechen an Besucher') }}
                                </flux:badge>
                            @endif
                        </dt>
                        <dd class="text-xs leading-relaxed text-zinc-600 dark:text-zinc-300">
                            @if ($tag->isDisplayDescriptionSubstituted())
                                {{-- Same provenance vocabulary the option rows use, so both
                                     surfaces say "this text is not in your language" the same
                                     way. One line: marker, then the text itself. --}}
                                <span class="me-1" aria-hidden="true">└</span>{{ __('nur auf :lang vorhanden', ['lang' => mb_strtoupper($tag->displayDescriptionLocale())]) }}:
                            @endif
                            {{ $tag->displayDescription() }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif

        {{--
            The advisory part of the answer zone, announced politely: these two
            hints can appear without anything else on the page changing, and a
            screen-reader organiser should hear that their selection carries a
            caveat — not discover it after the visitor did. The definitions
            above are deliberately not live: they are reference for an action
            the user just took, and re-announcing them on every pick would be
            noise.
        --}}
        <div aria-live="polite">
            @if ($this->showsFamilyHint)
                {{--
                    Soft by design: it names where the promise is kept (the event text),
                    it never blocks, and it repeats on every render while the tag stays
                    selected — the organiser may edit the description long after picking.
                --}}
                <p class="flex items-start gap-1 text-xs text-zinc-600 dark:text-zinc-300"
                   data-testid="family-hint">
                    <span aria-hidden="true">└</span>
                    <span>{{ __('Beschreibe im Eventtext, was für Kinder da ist — Spielplatz, Spielecke, Platz.') }}</span>
                </p>
            @endif

            @foreach ($this->groupConflicts as $conflict)
                {{--
                    Issue #149: two tags of one group are usually a mistake, not a
                    plan — but sometimes they are a plan, so this says what to check,
                    never that saving is refused.
                --}}
                <p class="flex items-start gap-1 text-xs text-zinc-600 dark:text-zinc-300"
                   data-testid="group-hint-{{ $conflict['group'] }}">
                    <span aria-hidden="true">└</span>
                    <span>{{ $conflict['message'] }}</span>
                </p>
            @endforeach
        </div>

        <flux:error name="tagIds" />
    </flux:field>

    {{-- Resting state shows only featured tags; typing reveals the rest. --}}
    <style>
        [data-searching="false"] .tag-option:not(.tag-option--featured) { display: none; }
    </style>
</div>
