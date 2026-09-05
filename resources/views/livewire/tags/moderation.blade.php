<?php

use App\Models\Tag;
use App\Support\TagEditorGate;
use App\Support\TagLocales;
use App\Traits\SeoTrait;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The tag editor's workbench: review queue and vocabulary in one place.
 *
 * It began as a review queue only. That was a dead end for the job it exists to do:
 * with zero suggestions outstanding — the normal state — the screen said "nothing to
 * do" while ninety-one approved tags carried no description, an unrenderable icon
 * name, and an order nobody could see. Approving is the rare event; keeping the
 * vocabulary usable is the standing one.
 *
 * Hence two tabs, and hence the split of the four fields between them:
 *   - `icon` and `description` describe a tag, so they help when deciding whether to
 *     approve one. They are editable in both tabs.
 *   - `featured` and `order_column` govern the picker's RESTING list, and an
 *     unapproved tag is in nobody's resting list. Setting them on a suggestion would
 *     do exactly nothing, so they only appear in the vocabulary tab.
 *
 * Ordering is written by one code path (applySequence) whichever way it was asked
 * for — dragging, the arrow buttons, or turning `featured` on — so the two input
 * methods cannot drift apart.
 */
new class extends Component
{
    /**
     * Shares $SEOData with the layout.
     *
     * Without it this route answered 500 on every real page load: partials/head
     * renders `seo($SEOData)`, the variable is only ever shared by this trait, and
     * this component never used it. It went unnoticed because the only tests were
     * Livewire::test(), which renders the component and not the layout around it —
     * the same blind spot the plan warns about for the P1 and P2 forms. Found while
     * writing this phase's browser test, and reproduced on the unchanged commit.
     */
    use SeoTrait;

    /** Which tab is open. Bound to flux:tabs so the server renders the same one. */
    public string $tab = 'pending';

    /** Free-text narrowing of the vocabulary list; 91 rows is more than one screen. */
    public string $filter = '';

    /**
     * tag id => featured, one entry per approved tag, bound to one switch per row.
     *
     * Not #[Locked]: the switches write to it. Every write is re-checked against the
     * policy in updatedFeatured(), and an id that is not a tag fails findOrFail().
     *
     * @var array<int|string, bool>
     */
    public array $featured = [];

    /** The tag whose editor is open, or null. */
    #[Locked]
    public ?int $editingId = null;

    public string $editIcon = 'tag';

    public string $editDescription = '';

    /**
     * locale => name, one entry per tag locale, empty where the tag has no name.
     *
     * All nine at once, unlike `editDescription`, which edits only the request's
     * language. A description is prose somebody has to write; a name is the thing the
     * picker matches on in every language, and filling the eight missing ones is the
     * job this screen exists for — the owner's "Übersetzungen sollen ausfüllbar sein,
     * für all unsere Sprachen".
     *
     * Not #[Locked]: the inputs write to it. Only keys from TagLocales::all() are ever
     * read back out, so an extra key in a crafted snapshot reaches no translation.
     *
     * @var array<string, string>
     */
    public array $editNames = [];

    /**
     * What just happened to the order, announced politely.
     *
     * The ordering controls move a number that is otherwise invisible; without a
     * status line a keyboard user pressing "up" gets no confirmation that anything
     * happened at all (WCAG 4.1.3, and Nielsen's first heuristic).
     */
    public string $reorderStatus = '';

    public function mount(): void
    {
        abort_unless(TagEditorGate::allows(auth()->user()), 403);

        $this->featured = Tag::query()
            ->approved()
            ->get()
            ->mapWithKeys(fn (Tag $tag): array => [$tag->id => (bool) $tag->featured])
            ->all();

        // Open where the work is. With an empty queue the review tab is a dead end.
        $this->tab = Tag::query()->pending()->exists() ? 'pending' : 'vocabulary';
    }

    /*
    |--------------------------------------------------------------------------
    | Reading
    |--------------------------------------------------------------------------
    */

    public function getPendingProperty(): Collection
    {
        return Tag::query()
            ->pending()
            ->with('creator')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * The approved vocabulary, grouped by type.
     *
     * @return Collection<string, Collection<int, Tag>>
     */
    public function getVocabularyProperty(): Collection
    {
        $needle = mb_strtolower(trim($this->filter));

        return Tag::query()
            ->approved()
            ->ordered()
            ->get()
            ->when($needle !== '', fn (Collection $tags): Collection => $tags->filter(
                fn (Tag $tag): bool => $this->matchesFilter($tag, $needle)
            ))
            ->groupBy(fn (Tag $tag): string => (string) ($tag->type ?? ''))
            ->sortBy(fn (Collection $group, string $type): int => $this->typeRank($type), SORT_NUMERIC);
    }

    /**
     * Group order, chosen rather than alphabetical.
     *
     * Sorting by the raw type string puts `course` (one tag) first and
     * `meetup_event` last — and meetup_event is the only type with a resting list,
     * so the one group that actually needs ordering sat below eighty-four rows that
     * do not. Types this list does not know fall in behind, in their own order.
     */
    private function typeRank(string $type): int
    {
        return match ($type) {
            'meetup_event' => 0,
            'course' => 1,
            'library_item' => 2,
            default => 3,
        };
    }

    /**
     * How many tags of this group already carry a description.
     *
     * @param  Collection<int, Tag>  $group
     */
    public function describedCount(Collection $group): int
    {
        return $group->filter(fn (Tag $tag): bool => $this->describedLocales($tag) !== [])->count();
    }

    public function getApprovedCountProperty(): int
    {
        return Tag::query()->approved()->count();
    }

    public function getFeaturedCountProperty(): int
    {
        return Tag::query()->approved()->where('featured', true)->count();
    }

    /**
     * The language a description is written in on this screen.
     *
     * `description` carries all nine tag locales. Editing nine textareas per row
     * would be absurd, and guessing a locale that the tag vocabulary does not even
     * use would write into a language the picker never reads — so the request's
     * locale is used when it is one of the nine, and the app's fallback otherwise.
     */
    public function getEditLocaleProperty(): string
    {
        return TagLocales::current();
    }

    /**
     * The languages the open editor has no name for — what is left to fill.
     *
     * Read from the form, not from the stored tag, so it answers for what the
     * moderator is looking at right now.
     *
     * @return array<int, string>
     */
    public function getMissingNameLocalesProperty(): array
    {
        return collect(TagLocales::all())
            ->filter(fn (string $locale): bool => trim((string) ($this->editNames[$locale] ?? '')) === '')
            ->values()
            ->all();
    }

    /**
     * Whether the open editor shows the fingerprint of the old picker: one string
     * copied into every language, and no source language recorded.
     *
     * Three production rows were in that state on 2026-09-05 (`Poker`, `Pubkvíz`,
     * `Rodiny s dětmi`). No migration repairs them, because which of the nine
     * identical strings is the original is recorded nowhere — the missing fact lives
     * with the person reading the word, and this is where that person works. Seeded
     * tags are excluded by `created_by`: their nine identical names are a deliberate
     * proper noun (Bitcoin, Nostr, Lightning), not damage.
     */
    public function looksLikeCopiedNames(Tag $tag): bool
    {
        if ($tag->created_by === null || $tag->source_locale !== null) {
            return false;
        }

        $names = collect(TagLocales::all())
            ->map(fn (string $locale): string => (string) $tag->getTranslation('name', $locale, false))
            ->filter();

        return $names->count() === count(TagLocales::all()) && $names->unique()->count() === 1;
    }

    /**
     * Which locales this tag already carries a description in.
     *
     * @return array<int, string>
     */
    public function describedLocales(Tag $tag): array
    {
        return collect(config('einundzwanzig.tag_locales', []))
            ->filter(fn (string $locale): bool => filled($tag->getTranslation('description', $locale, false)))
            ->values()
            ->all();
    }

    public function typeLabel(?string $type): string
    {
        return match ($type) {
            'meetup_event' => __('Veranstaltungen'),
            'course' => __('Kurse'),
            'library_item' => __('Bibliothek'),
            default => __('Sonstige'),
        };
    }

    /*
    |--------------------------------------------------------------------------
    | The queue
    |--------------------------------------------------------------------------
    */

    public function approve(int $id): void
    {
        $tag = Tag::findOrFail($id);

        $this->authorize('approve', $tag);

        $tag->approve();

        $this->featured[$tag->id] = (bool) $tag->featured;

        session()->flash('status', __('Tag freigegeben.'));
    }

    /**
     * Rejecting deletes the tag, which also detaches it from the event it was proposed
     * on. That is deliberate: an unapproved tag only ever hung on its author's own
     * event, so nothing shared is lost.
     */
    public function reject(int $id): void
    {
        $tag = Tag::findOrFail($id);

        $this->authorize('delete', $tag);

        $tag->delete();

        if ($this->editingId === $id) {
            $this->cancelEdit();
        }

        session()->flash('status', __('Tag abgelehnt und entfernt.'));
    }

    /*
    |--------------------------------------------------------------------------
    | Describing a tag
    |--------------------------------------------------------------------------
    */

    public function edit(int $id): void
    {
        $tag = Tag::findOrFail($id);

        $this->authorize('update', $tag);

        $this->editingId = $tag->id;

        // A stored name outside the whitelist cannot be preselected — there is no
        // option for it. The row shows it verbatim in a badge; this field offers the
        // replacement, which is the only way out of the broken state.
        $this->editIcon = in_array($tag->icon, (array) config('einundzwanzig.tag_icons', []), true)
            ? (string) $tag->icon
            : 'tag';

        $this->editDescription = (string) $tag->getTranslation('description', $this->editLocale, false);

        $this->editNames = collect(TagLocales::all())
            ->mapWithKeys(fn (string $locale): array => [
                $locale => (string) $tag->getTranslation('name', $locale, false),
            ])
            ->all();

        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editIcon = 'tag';
        $this->editDescription = '';
        $this->editNames = [];
        $this->resetValidation();
    }

    /**
     * Collapse runs of whitespace and trim, exactly as the picker does before it
     * stores a typed name. Without it "Vortrag " and "Vortrag" are two different
     * names to every comparison in the codebase, including the duplicate guard.
     *
     * @return array<string, string>
     */
    private function normalisedEditNames(): array
    {
        return collect(TagLocales::all())
            ->mapWithKeys(fn (string $locale): array => [
                $locale => trim(preg_replace('/\s+/u', ' ', (string) ($this->editNames[$locale] ?? '')) ?? ''),
            ])
            ->all();
    }

    public function save(): void
    {
        $tag = Tag::findOrFail($this->editingId);

        $this->authorize('update', $tag);

        $names = $this->normalisedEditNames();
        $this->editNames = $names;

        $this->validate([
            'editIcon' => ['required', 'string', Rule::in((array) config('einundzwanzig.tag_icons', []))],
            'editDescription' => ['nullable', 'string', 'max:280'],
            /*
             * A tag with no name at all cannot be saved — `tags.slug` is NOT NULL and
             * a slug is derived from a name — and it would read as a blank row in every
             * picker. The bound rule is on the array rather than the fields, because the
             * requirement is about the set: any one of the nine will do.
             */
            'editNames' => ['array', function (string $attribute, mixed $value, Closure $fail) use ($names): void {
                if (collect($names)->filter()->isEmpty()) {
                    $fail(__('Mindestens eine Sprache muss einen Namen tragen.'));
                }
            }],
            'editNames.*' => ['nullable', 'string', 'min:2', 'max:60'],
        ]);

        /*
         * One locale at a time, and an emptied field forgets that language rather than
         * storing "" — the same rule the description follows. Measured 2026-09-05:
         * Spatie's readers (getTranslations, getTranslatedLocales, hasTranslation, and
         * therefore displayLocale) already filter an empty value out, so this is about
         * the stored row, not about behaviour: without it the JSON column collects a
         * dead `"en":""` for every language a moderator ever cleared.
         *
         * The SLUG is deliberately untouched here. Tag::bootHasSlug() adds one for a
         * locale that has a name and no slug yet and never rewrites an existing one
         * (commit fd48fa7), so renaming keeps the public URL and a forgotten name
         * leaves its slug behind. Dropping that slug would let a later, different name
         * in the same language take over a URL that is already in the wild.
         */
        foreach ($names as $locale => $name) {
            if ($name === '') {
                $tag->forgetTranslation('name', $locale);

                continue;
            }

            $tag->setTranslation('name', $locale, $name);
        }

        $tag->icon = $this->editIcon;

        // Touch one locale and one only. setTranslation() merges into the existing
        // JSON, and an emptied field drops that language instead of storing "".
        $locale = $this->editLocale;
        $description = trim($this->editDescription);

        if ($description === '') {
            $tag->forgetTranslation('description', $locale);
        } else {
            $tag->setTranslation('description', $locale, $description);
        }

        $tag->save();

        $this->cancelEdit();

        session()->flash('status', __('Gespeichert.'));
    }

    /*
    |--------------------------------------------------------------------------
    | The resting list
    |--------------------------------------------------------------------------
    */

    /**
     * A switch was flipped.
     *
     * @param  string  $key  the array key, i.e. the tag id
     */
    public function updatedFeatured(mixed $value, string $key): void
    {
        $tag = Tag::findOrFail((int) $key);

        $this->authorize('update', $tag);

        $tag->featured = (bool) $value;

        // A tag joining the resting list joins it at the end. Without this it would
        // slot in wherever its old order_column happened to fall — for a library tag
        // that is a number from a completely different range.
        if ($tag->featured) {
            $tag->order_column = ((int) Tag::query()->max('order_column')) + 1;
        }

        $tag->save();

        $this->applySequence($this->featuredSequence($tag->type)->pluck('id'));

        $this->featured[$tag->id] = (bool) $tag->featured;

        $this->reorderStatus = $tag->featured
            ? __(':tag ist jetzt empfohlen.', ['tag' => $tag->displayName()])
            : __(':tag ist nicht mehr empfohlen.', ['tag' => $tag->displayName()]);

        // The row leaves one list and reappears in the other, so the switch that was
        // just operated is a different DOM node afterwards and focus would land on
        // <body>. Put it back where the user left it (WCAG 2.4.3).
        $this->focusAfterMorph(sprintf('document.querySelector(\'[data-testid="featured-%d"]\')', $tag->id));
    }

    /**
     * Keep the keyboard on the row that was just moved.
     *
     * A row at either end has one disabled button, and focusing a disabled element
     * silently does nothing — so aim at the one that is still there.
     */
    private function keepFocusOnRow(int $id): void
    {
        $this->focusAfterMorph(sprintf(
            '(document.getElementById("move-up-%1$d")?.disabled'
            .' ? document.getElementById("move-down-%1$d")'
            .' : document.getElementById("move-up-%1$d"))',
            $id
        ));
    }

    /**
     * Focus the element the expression returns, once the DOM has been replaced.
     *
     * The rAF wrapper is not decoration. Livewire runs `js()` expressions from an
     * "effect" hook that is registered before the morph's own hook, so the plain
     * expression focuses the OLD node and the morph then throws it away — measured:
     * document.activeElement came back as <body>. Waiting one frame puts the call
     * after the replacement.
     */
    private function focusAfterMorph(string $elementExpression): void
    {
        $this->js('requestAnimationFrame(() => '.$elementExpression.'?.focus())');
    }

    /**
     * Dragging: put this tag at this index within its type's featured group.
     */
    public function reorder(int $id, int $position): void
    {
        $tag = Tag::findOrFail($id);

        $this->authorize('update', $tag);

        if (! $tag->featured) {
            return;
        }

        $ids = $this->featuredSequence($tag->type)
            ->pluck('id')
            ->reject(fn (int $each): bool => $each === $tag->id)
            ->values();

        $position = max(0, min($position, $ids->count()));

        $ids->splice($position, 0, [$tag->id]);

        $this->applySequence($ids);

        $this->reorderStatus = __(':tag steht jetzt an Position :position von :total.', [
            'tag' => $tag->displayName(),
            'position' => $position + 1,
            'total' => $ids->count(),
        ]);

        $this->keepFocusOnRow($tag->id);
    }

    /**
     * The keyboard and single-pointer alternative WCAG 2.5.7 and 2.1.1 require.
     *
     * Routed through reorder() rather than Spatie's moveOrderUp(): that helper swaps
     * with the neighbour in buildSortQuery(), which is unscoped — a featured event tag
     * would trade places with whatever library tag happens to hold the next lower
     * number. Going through the same sequence the drag path uses also makes the two
     * produce identical order_column values by construction, not by coincidence.
     */
    public function moveUp(int $id): void
    {
        $this->shift($id, -1);
    }

    public function moveDown(int $id): void
    {
        $this->shift($id, 1);
    }

    private function shift(int $id, int $delta): void
    {
        $tag = Tag::findOrFail($id);

        $this->authorize('update', $tag);

        if (! $tag->featured) {
            return;
        }

        $ids = $this->featuredSequence($tag->type)->pluck('id');
        $index = $ids->search($tag->id);

        if ($index === false) {
            return;
        }

        $target = $index + $delta;

        if ($target < 0 || $target >= $ids->count()) {
            return;
        }

        $this->reorder($id, $target);
    }

    /**
     * The featured tags of one type, in their current order.
     *
     * @return Collection<int, Tag>
     */
    private function featuredSequence(?string $type): Collection
    {
        return Tag::query()
            ->approved()
            ->where('featured', true)
            ->when(
                $type === null,
                fn ($query) => $query->whereNull('type'),
                fn ($query) => $query->where('type', $type),
            )
            ->ordered()
            ->get();
    }

    /**
     * Number one group 1..n and leave every other row alone.
     *
     * The picker lifts the whole featured block above the rest regardless of the
     * numbers, so a featured group and a non-featured one may hold the same values
     * without anyone seeing a difference. Renumbering only the group that moved keeps
     * the write small and the sequence readable in the interface.
     *
     * @param  Collection<int, int>  $ids
     */
    private function applySequence(Collection $ids): void
    {
        if ($ids->isEmpty()) {
            return;
        }

        Tag::setNewOrder($ids->all());
    }

    private function matchesFilter(Tag $tag, string $needle): bool
    {
        foreach ((array) config('einundzwanzig.tag_locales', []) as $locale) {
            if (str_contains(mb_strtolower((string) $tag->getTranslation('name', $locale, false)), $needle)) {
                return true;
            }
        }

        return str_contains(mb_strtolower((string) $tag->icon), $needle);
    }
}; ?>

<div class="mx-auto w-full max-w-4xl p-4">
    <flux:heading size="xl">{{ __('Tags verwalten') }}</flux:heading>

    <flux:text class="mt-2 max-w-prose">
        {{ __('Vorschläge freigeben, Symbole und Beschreibungen pflegen und festlegen, was der Picker im Ruhezustand anbietet.') }}
    </flux:text>

    @if (session('status'))
        <flux:callout variant="success" class="mt-4">{{ session('status') }}</flux:callout>
    @endif

    <flux:tab.group class="mt-6">
        {{-- Flux's own unselected tab colour is zinc-400 — 2.6:1 on the white page
             ground, under the 4.5:1 of WCAG 1.4.3. `not-data-selected:` overrides
             exactly the resting state and leaves the selected one to Flux. --}}
        {{-- `scrollable` because both labels carry a count: at 320px the two tabs
             already end exactly on the viewport edge, and a four-digit vocabulary
             would clip. A scrolling tab strip is the accepted answer; a clipped one
             is a 1.4.10 failure. --}}
        <flux:tabs wire:model="tab" scrollable scrollable:scrollbar="hide">
            <flux:tab name="pending"
                      :selected="$tab === 'pending'"
                      class="not-data-selected:text-zinc-600 dark:not-data-selected:text-zinc-300"
                      data-testid="tab-pending">
                {{ __('Vorschläge (:count)', ['count' => $this->pending->count()]) }}
            </flux:tab>

            <flux:tab name="vocabulary"
                      :selected="$tab === 'vocabulary'"
                      class="not-data-selected:text-zinc-600 dark:not-data-selected:text-zinc-300"
                      data-testid="tab-vocabulary">
                {{ __('Vokabular (:count)', ['count' => $this->approvedCount]) }}
            </flux:tab>
        </flux:tabs>

        <flux:tab.panel name="pending" :selected="$tab === 'pending'">
            <flux:text class="max-w-prose">
                {{ __('Vorschläge von Nutzern ohne Redaktionsrecht. Bis zur Freigabe sind sie nur am eigenen Event des Vorschlagenden sichtbar.') }}
            </flux:text>

            @if ($this->pending->isEmpty())
                <flux:callout class="mt-4" data-testid="moderation-empty">
                    {{ __('Keine offenen Vorschläge.') }}
                </flux:callout>
            @else
                <div class="mt-4 flex flex-col gap-3" data-testid="moderation-list">
                    @foreach ($this->pending as $tag)
                        @include('livewire.tags.partials.row', [
                            'tag' => $tag,
                            'rowVariant' => 'pending',
                            'rowPosition' => 0,
                            'rowTotal' => 0,
                        ])
                    @endforeach
                </div>
            @endif
        </flux:tab.panel>

        <flux:tab.panel name="vocabulary" :selected="$tab === 'vocabulary'">
            @if ($this->featuredCount === 0)
                {{-- The tipping point worth naming: with nothing featured the picker's
                     resting list is empty and every organiser has to type before they
                     see a single tag. --}}
                <flux:callout variant="warning" icon="exclamation-triangle" class="mb-4"
                              data-testid="no-featured-warning">
                    {{ __('Kein Tag ist empfohlen. Der Picker zeigt im Ruhezustand nichts an, bis jemand tippt.') }}
                </flux:callout>
            @endif

            <flux:input wire:model.live.debounce.300ms="filter"
                        icon="magnifying-glass"
                        :label="__('Vokabular durchsuchen')"
                        :placeholder="__('Name oder Symbolname')"
                        data-testid="vocabulary-filter" />

            {{-- Kept in the DOM across renders so a change of its text is announced
                 rather than replaced silently. --}}
            <div wire:key="reorder-status"
                 aria-live="polite"
                 class="mt-3 min-h-5 text-xs text-zinc-600 dark:text-zinc-300"
                 data-testid="reorder-status">
                {{ $reorderStatus }}
            </div>

            @forelse ($this->vocabulary as $type => $group)
                @php
                    $groupFeatured = $group->where('featured', true)->sortBy('order_column')->values();
                    $groupRest = $group->where('featured', false)
                        ->sortBy(fn (\App\Models\Tag $tag): string => mb_strtolower($tag->displayName()))
                        ->values();
                @endphp

                <section class="mt-8" wire:key="group-{{ $type ?: 'none' }}">
                    <flux:heading size="lg">{{ $this->typeLabel($type ?: null) }}</flux:heading>

                    {{-- The work still to be done, stated once per group instead of
                         "no description" on every row. --}}
                    <flux:text class="mt-1 text-xs" data-testid="described-count-{{ $type ?: 'none' }}">
                        {{ __(':described von :count mit Beschreibung', [
                            'described' => $this->describedCount($group),
                            'count' => $group->count(),
                        ]) }}
                    </flux:text>

                    @if ($groupFeatured->isNotEmpty())
                        <flux:text class="mt-1 text-xs">
                            {{ __('Empfohlen — in dieser Reihenfolge im Ruhezustand des Pickers.') }}
                        </flux:text>

                        {{-- Dragging is the convenience; the arrow buttons on each row
                             are the requirement (WCAG 2.5.7 / 2.1.1). Both call the
                             same action. --}}
                        <div class="mt-2 flex flex-col gap-2"
                             wire:sort="reorder($item, $position)"
                             data-testid="featured-list-{{ $type ?: 'none' }}">
                            @foreach ($groupFeatured as $index => $tag)
                                @include('livewire.tags.partials.row', [
                                    'tag' => $tag,
                                    'rowVariant' => 'featured',
                                    'rowPosition' => $index + 1,
                                    'rowTotal' => $groupFeatured->count(),
                                ])
                            @endforeach
                        </div>
                    @endif

                    @if ($groupRest->isNotEmpty())
                        <flux:text class="mt-4 text-xs">{{ __('Weitere') }}</flux:text>

                        <div class="mt-2 flex flex-col gap-2"
                             data-testid="rest-list-{{ $type ?: 'none' }}">
                            @foreach ($groupRest as $tag)
                                @include('livewire.tags.partials.row', [
                                    'tag' => $tag,
                                    'rowVariant' => 'other',
                                    'rowPosition' => 0,
                                    'rowTotal' => 0,
                                ])
                            @endforeach
                        </div>
                    @endif
                </section>
            @empty
                <flux:callout class="mt-6" data-testid="vocabulary-empty">
                    {{ __('Keine Treffer.') }}
                </flux:callout>
            @endforelse
        </flux:tab.panel>
    </flux:tab.group>
</div>
