{{--
    One tag row, in one of three shapes.

    `$rowVariant`
      'pending'   — a suggestion awaiting review: approve / reject / edit
      'featured'  — part of the picker's resting list: draggable, rank shown
      'other'     — approved but not offered at rest

    All three share the same identity block — icon, name, description — because a
    moderator scanning the page should not have to relearn where the name sits when
    they cross a tab boundary. What differs is only what can be done to the row.

    A featured row leads with the picker's own glyph and the position it holds in the
    resting list. That number is the whole point of `order_column`: the column is
    invisible everywhere else, so without it the ordering controls would move a value
    nobody can see.

    @param App\Models\Tag $tag
    @param string         $rowVariant
    @param int            $rowPosition  1-based, only meaningful for 'featured'
    @param int            $rowTotal     size of the featured group
--}}
@php
    $rowIsFeatured = $rowVariant === 'featured';
    $rowIsPending = $rowVariant === 'pending';
    $rowUnresolvable = filled($tag->icon)
        && ! in_array($tag->icon, (array) config('einundzwanzig.tag_icons', []), true);
    $rowEditing = $this->editingId === $tag->id;
@endphp

<div wire:key="row-{{ $rowVariant }}-{{ $tag->id }}"
     @if ($rowIsFeatured) wire:sort:item="{{ $tag->id }}" @endif
     data-testid="tag-row-{{ $tag->id }}"
     data-row-variant="{{ $rowVariant }}"
     class="flex flex-wrap items-center gap-x-3 gap-y-3 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">

    @if ($rowIsFeatured)
        {{-- Drag handle. Mouse-only on purpose: the keyboard path is the pair of
             buttons on the right, which WCAG 2.5.7 requires anyway. --}}
        <span wire:sort:handle
              aria-hidden="true"
              class="flex size-8 shrink-0 cursor-grab items-center justify-center rounded-md text-zinc-600 active:cursor-grabbing dark:text-zinc-300">
            <flux:icon.bars-2 variant="mini" class="size-4" />
        </span>

        {{-- The picker's own glyph plus the position it holds in the resting list.
             The house typeface is monospaced, so the numbers line up without a
             tabular-figures trick. --}}
        <span class="flex w-8 shrink-0 items-center justify-end gap-1 text-sm text-zinc-600 dark:text-zinc-300">
            <span aria-hidden="true">●</span>
            <span aria-hidden="true">{{ $rowPosition }}</span>
            <span class="sr-only">
                {{ __('Position :position von :total', ['position' => $rowPosition, 'total' => $rowTotal]) }}
            </span>
        </span>
    @elseif (! $rowIsPending)
        {{-- No handle, no number: an unfeatured tag has no place in the resting
             list, and reserving the lanes anyway put ninety-six pixels of nothing
             in front of eighty-four rows. --}}
        <span class="w-4 shrink-0 text-center text-sm text-zinc-600 dark:text-zinc-300">
            <span aria-hidden="true">○</span>
            <span class="sr-only">{{ __('nicht empfohlen') }}</span>
        </span>
    @endif

    @include('livewire.tags.partials.icon', [
        'tagIcon' => $tag->icon,
        'tagIconClass' => 'size-5 text-zinc-600 dark:text-zinc-300',
        'tagIconWrapperClass' => 'inline-flex shrink-0 self-center',
    ])

    {{-- basis-48 keeps this lane from collapsing: `flex-1` alone means basis 0, and
         a neighbour sized `auto` then squeezes the name to nothing without ever
         overflowing, so nothing looks broken. Below that width the action cluster
         wraps to its own line instead. --}}
    <div class="min-w-0 flex-1 basis-48">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <span class="font-medium">{{ $tag->displayName() }}</span>

            {{-- Issue #149: the vocabulary's promise tags, marked read-only. The flag
                 is configured in the seed vocabulary (like `featured`), not edited
                 here — a moderator curates what a tag SAYS, not what the community
                 has agreed a tag MEANS. Badge colours/contrast as in the picker. --}}
            @if ($tag->is_commitment)
                <flux:badge size="sm" icon="hand-raised"
                            data-testid="commitment-{{ $tag->id }}">
                    {{ __('Versprechen an Besucher') }}
                </flux:badge>
            @endif

            @if ($rowUnresolvable)
                {{-- Text plus icon, never colour alone (WCAG 1.4.1). The stored value
                     is shown verbatim: it is the thing that needs fixing. --}}
                <flux:badge size="sm" color="red" icon="exclamation-triangle"
                            data-testid="icon-unresolvable-{{ $tag->id }}">
                    {{ __(':icon — nicht auflösbar', ['icon' => $tag->icon]) }}
                </flux:badge>
            @endif
        </div>

        @if ($rowIsPending)
            <div class="mt-0.5 text-xs text-zinc-600 dark:text-zinc-300">
                {{ $tag->type ?? '—' }}
                &middot;
                {{ $tag->creator?->name ?? __('unbekannt') }}
                &middot;
                {{ $tag->created_at?->diffForHumans() }}
            </div>
        @else
            @php $rowDescribed = $this->describedLocales($tag); @endphp

            {{-- A missing description is deliberately silent. Saying "no description"
                 on every one of ninety-one rows is a lot of noise for one fact; the
                 group heading states it once, as a count. --}}
            @if ($rowDescribed !== [])
                <div class="mt-0.5 line-clamp-1 text-xs text-zinc-600 dark:text-zinc-300">
                    {{ $tag->getTranslation('description', $this->editLocale, false)
                        ?: $tag->getTranslation('description', $rowDescribed[0], false) }}
                </div>

                @unless (in_array($this->editLocale, $rowDescribed, true))
                    {{-- Same provenance marker the picker uses, so both surfaces say
                         "this text is not in your language" the same way. --}}
                    <div class="mt-0.5 flex items-center gap-1 text-xs text-zinc-600 dark:text-zinc-300">
                        <span aria-hidden="true">└</span>
                        <span>{{ __('nur auf :lang vorhanden', ['lang' => mb_strtoupper(implode(', ', $rowDescribed))]) }}</span>
                    </div>
                @endunless
            @endif

            {{--
                Issue #149: the evidence line. A moderator curating the vocabulary
                needs to know whether a tag earns its place, and the two heuristic
                badges say when the number itself is remarkable. Display only —
                the thresholds are documented on App\Support\TagUsageStats and
                deliberately feed no filter, no action, no block.
            --}}
            @php
                $rowUsage = $this->usage->usageCount($tag);
                $rowTooBroad = $this->usage->isTooBroad($tag);
                $rowTooRare = $this->usage->isTooRare($tag);
            @endphp

            @if ($rowUsage > 0 || $rowTooBroad || $rowTooRare)
                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                    @if ($rowUsage > 0)
                        <span class="text-xs text-zinc-600 dark:text-zinc-300"
                              data-testid="usage-{{ $tag->id }}">
                            <span class="sr-only">{{ __(':count-mal verwendet', ['count' => $rowUsage]) }}</span>
                            <span aria-hidden="true">{{ $rowUsage }}×</span>
                        </span>
                    @endif

                    {{-- Amber non-solid, retuned past Flux's own amber-700/200: those
                         measure 4.4:1 on the light wash, under the 4.5:1 of WCAG 1.4.3
                         (12px/500 is not large text; same failure mode as issue #98).
                         Measured pairs on the composited washes:
                           light  amber-800 #92400e on amber-400/25 over white  = #feefc8 → 6.2:1
                           dark   amber-100 #fef3c7 on amber-400/40 over zinc-800 = #7c6428 → 5.1:1
                         The `!` suffixes beat the stub utilities regardless of build
                         order — plain classes would lose to whichever came last. --}}
                    @if ($rowTooBroad)
                        <flux:tooltip :content="__('Klebt auf :percent % aller :count Events — als Filter kaum noch brauchbar.', ['percent' => $this->usage->eventSharePercent($tag), 'count' => $this->usage->totalEvents()])">
                            <flux:badge size="sm" color="amber" icon="arrows-pointing-out"
                                        class="text-amber-800! dark:text-amber-100!"
                                        data-testid="too-broad-{{ $tag->id }}">
                                {{ __('zu breit') }}
                            </flux:badge>
                        </flux:tooltip>
                    @endif

                    @if ($rowTooRare)
                        <flux:tooltip :content="__(':count× genutzt in den letzten 12 Monaten.', ['count' => $this->usage->recentCount($tag)])">
                            <flux:badge size="sm" color="amber" icon="clock"
                                        class="text-amber-800! dark:text-amber-100!"
                                        data-testid="too-rare-{{ $tag->id }}">
                                {{ __('zu selten') }}
                            </flux:badge>
                        </flux:tooltip>
                    @endif
                </div>
            @endif
        @endif
    </div>

    <div class="ms-auto flex shrink-0 items-center gap-3">
        @if ($rowIsPending)
            <flux:button size="sm" variant="ghost"
                         wire:click="edit({{ $tag->id }})"
                         data-testid="edit-{{ $tag->id }}">
                {{ __('Bearbeiten') }}
            </flux:button>
            <flux:button size="sm" variant="primary"
                         wire:click="approve({{ $tag->id }})"
                         data-testid="approve-{{ $tag->id }}">
                {{ __('Freigeben') }}
            </flux:button>
            <flux:button size="sm" variant="danger"
                         wire:click="reject({{ $tag->id }})"
                         data-testid="reject-{{ $tag->id }}">
                {{ __('Ablehnen') }}
            </flux:button>
        @else
            {{-- The switch itself is 32x20. WCAG 2.5.8 is met through the spacing
                 exception, not the size rule: gap-3 puts 44px between the centres of
                 neighbouring targets, well over the 24px the exception asks for.

                 The tooltip is for the pointer, the aria-label for everything else —
                 a bare switch in a row of ninety-one says nothing about what it does,
                 and the group heading is too far away to answer it. --}}
            <flux:tooltip :content="__('Empfohlen')">
                <flux:switch wire:model.live="featured.{{ $tag->id }}"
                             :aria-label="__('Empfohlen: :tag', ['tag' => $tag->displayName()])"
                             data-testid="featured-{{ $tag->id }}" />
            </flux:tooltip>

            @if ($rowIsFeatured)
                {{-- A tooltip is a description, not a name: `aria-label` is what makes
                     an icon-only button announce as something (WCAG 4.1.2). The `id`
                     gives Livewire's morph a stable handle so the focus stays on the
                     button after the row has moved. --}}
                <div class="flex items-center gap-1">
                    <flux:button size="sm" variant="ghost" icon="chevron-up"
                                 id="move-up-{{ $tag->id }}"
                                 :tooltip="__('Nach oben')"
                                 :aria-label="__('Nach oben: :tag', ['tag' => $tag->displayName()])"
                                 :disabled="$rowPosition === 1"
                                 wire:click="moveUp({{ $tag->id }})"
                                 data-testid="move-up-{{ $tag->id }}" />
                    <flux:button size="sm" variant="ghost" icon="chevron-down"
                                 id="move-down-{{ $tag->id }}"
                                 :tooltip="__('Nach unten')"
                                 :aria-label="__('Nach unten: :tag', ['tag' => $tag->displayName()])"
                                 :disabled="$rowPosition === $rowTotal"
                                 wire:click="moveDown({{ $tag->id }})"
                                 data-testid="move-down-{{ $tag->id }}" />
                </div>
            @endif

            <flux:button size="sm" variant="ghost"
                         wire:click="edit({{ $tag->id }})"
                         data-testid="edit-{{ $tag->id }}">
                {{ __('Bearbeiten') }}
            </flux:button>
        @endif
    </div>

    @if ($rowEditing)
        {{-- Editing happens in place rather than in a modal: the row you are fixing
             stays where you found it, and there is no focus trap to get wrong. --}}
        <div class="w-full border-t border-zinc-200 pt-3 dark:border-zinc-700"
             data-testid="tag-editor">
            @include('livewire.tags.partials.editor', ['tag' => $tag])
        </div>
    @endif
</div>
