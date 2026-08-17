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
                        <span class="flex items-center gap-2">
                            <span aria-hidden="true" class="text-xs opacity-60">{{ $tag->featured ? '●' : '○' }}</span>
                            <span>{{ $tag->displayName() }}</span>
                            @if ($tag->isDisplayNameSubstituted())
                                <span class="text-xs opacity-60">[{{ $tag->displayLocale() }}]</span>
                            @endif
                            @unless ($tag->isApproved())
                                <span class="text-xs opacity-60">{{ __('in Prüfung') }}</span>
                            @endunless
                        </span>

                        {{-- Searchable in every language; Flux matches on textContent. --}}
                        <span hidden>{{ implode(' ', $this->aliasesFor($tag)) }}</span>
                    </flux:pillbox.option>
                @endforeach
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
