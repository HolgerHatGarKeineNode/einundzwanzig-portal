<?php

use App\Models\Tag;
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
new class extends Component {
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
     * Everything selectable: approved tags plus the current user's own pending
     * suggestions, so a suggester can re-select what they just proposed.
     */
    public function getOptionsProperty(): Collection
    {
        return Tag::query()
            ->where('type', $this->type)
            ->selectableBy(auth()->user())
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
     * Create the tag the user typed, or select the existing one it duplicates.
     *
     * An editor's tag is live immediately; anyone else's is stored unapproved but is
     * still selected here and now — otherwise a mandatory-tag country would be a dead
     * end for them.
     *
     * The name is written to every locale rather than only the current one. A tag that
     * exists in one language is invisible to the other eight, and an unfindable tag is
     * the very thing that produced the duplicate sprawl in the existing data. An editor
     * can refine the translations afterwards.
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

        $tag = new Tag(['type' => $this->type]);

        foreach (config('einundzwanzig.tag_locales') as $locale) {
            $tag->setTranslation('name', $locale, $name);
        }

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
             Beispiel der Flux-Dokumentation fuehrt es, hier fehlte es. --}}
        <div x-data="{ q: '' }" x-bind:data-searching="q.length > 0 ? 'true' : 'false'">
            <flux:pillbox
                variant="combobox"
                multiple
                wire:model="tagIds"
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
                                <span aria-hidden="true" class="text-xs opacity-60">{{ $tag->featured ? '●' : '○' }}</span>
                                <span>{{ $tag->displayName() }}</span>
                                @unless ($tag->isApproved())
                                    <span class="text-xs opacity-60">{{ __('in Prüfung') }}</span>
                                @endunless
                            </span>

                            {{--
                                Provenance line. Without it a row carrying a foreign-language
                                label reads as noise — and a user who distrusts the row creates
                                a second tag instead, which is the duplicate sprawl we are
                                trying to end.
                            --}}
                            @if ($tag->isDisplayNameSubstituted())
                                <span class="flex items-center gap-1 ps-5 text-xs opacity-60">
                                    <span aria-hidden="true">└</span>
                                    <span>{{ __('nur auf :lang vorhanden', ['lang' => mb_strtoupper($tag->displayLocale())]) }}</span>
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

        <flux:error name="tagIds" />
    </flux:field>

    {{-- Resting state shows only featured tags; typing reveals the rest. --}}
    <style>
        [data-searching="false"] .tag-option:not(.tag-option--featured) { display: none; }
    </style>
</div>
