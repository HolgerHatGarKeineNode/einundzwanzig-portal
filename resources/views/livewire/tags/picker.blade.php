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
    /** Selected tag ids, bound to the parent form via wire:model. */
    #[Modelable]
    public array $tagIds = [];

    #[Locked]
    public string $type = 'meetup_event';

    /** Whether the surrounding form requires at least one tag (per country). */
    #[Locked]
    public bool $required = false;

    public ?string $label = null;

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
    public function createTag(string $name): void
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
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

        <div x-data="{ q: '' }" x-bind:data-searching="q.length > 0 ? 'true' : 'false'">
            <flux:pillbox
                variant="combobox"
                wire:model="tagIds"
                :placeholder="__('Tags wählen')"
                x-on:input="q = $event.target.value ?? ''"
                x-on:change="$nextTick(() => q = '')"
                data-testid="tag-picker"
            >
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
                    <flux:pillbox.option.create
                        x-show="q.trim().length >= 2"
                        x-on:click="$wire.createTag(q)"
                        data-testid="tag-create"
                    >
                        <span x-text="
                            @js($this->canCreateDirectly
                                ? __('als neuen Tag anlegen:')
                                : __('vorschlagen:')) + ' „' + q.trim() + '“'
                        "></span>
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
