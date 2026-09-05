{{--
    The editable parts of one tag: its names, its icon and its description.

    All of it sits in the row itself rather than in a modal. A modal would put the tag
    you are fixing behind an overlay and hand you a focus trap to get right; editing in
    place keeps the neighbours visible, which is what tells you whether a description
    is worth writing at all.

    The names come first and come in all nine languages, unlike the description, which
    is edited only in the reader's own language. A name is what the picker matches on
    in every language; leaving eight of them unreachable is what made a Czech tag read
    as noise to a Dutch organiser.

    @param App\Models\Tag $tag
--}}
<div class="mb-4">
    <flux:text class="text-xs">
        {{ __('Leer lassen heißt: in dieser Sprache nicht vorhanden.') }}
    </flux:text>

    @if ($this->looksLikeCopiedNames($tag))
        {{-- The fingerprint of the picker before #117: one typed word copied into all
             nine languages, which also switched off the "only available in" marker.
             Named here because the language the word is really in is not in the
             database — only the person reading it knows. --}}
        <flux:callout variant="warning" icon="exclamation-triangle" class="mt-2"
                      data-testid="copied-names-{{ $tag->id }}">
            {{ __('Alle Sprachen tragen denselben Namen — vermutlich vom alten Picker kopiert. Lösche die Sprachen, in denen er nicht steht.') }}
        </flux:callout>
    @endif

    @if ($this->missingNameLocales !== [])
        <flux:text class="mt-1 text-xs" data-testid="missing-name-locales">
            {{ __('Fehlt in: :langs', ['langs' => mb_strtoupper(implode(', ', $this->missingNameLocales))]) }}
        </flux:text>
    @endif

    <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {{-- The same list the component reads back on save; a second source here
             would silently give the form a different set of fields. --}}
        @foreach (\App\Support\TagLocales::all() as $nameLocale)
            <flux:field>
                <flux:label>{{ __('Name (:lang)', ['lang' => mb_strtoupper($nameLocale)]) }}</flux:label>

                <flux:input wire:model="editNames.{{ $nameLocale }}"
                            maxlength="60"
                            data-testid="name-input-{{ $nameLocale }}" />

                <flux:error name="editNames.{{ $nameLocale }}" />
            </flux:field>
        @endforeach
    </div>

    <flux:error name="editNames" />
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <flux:field>
        <flux:label>{{ __('Symbol') }}</flux:label>

        <flux:select variant="listbox"
                     searchable
                     wire:model="editIcon"
                     :placeholder="__('Symbol wählen')"
                     data-testid="icon-select">
            {{-- Flux's own search field says "Search..." — its default placeholder is
                 an untranslated English string, which on a German page is simply the
                 wrong word. --}}
            <x-slot name="search">
                <flux:select.search :placeholder="__('Suchen')" />
            </x-slot>

            @foreach ((array) config('einundzwanzig.tag_icons', []) as $iconName)
                <flux:select.option :value="$iconName"
                                    :icon="$iconName"
                                    icon:class="size-5 text-zinc-600 dark:text-zinc-300"
                                    @class([
                                        'icon-option',
                                        'icon-option--common' => in_array($iconName, (array) config('einundzwanzig.tag_icons_common', []), true),
                                    ])>
                    {{ $iconName }}
                </flux:select.option>
            @endforeach
        </flux:select>

        <flux:description>
            {{ __('Der Name steht neben dem Symbol — 52 Symbole, die verbreiteten zuerst.') }}
        </flux:description>

        <flux:error name="editIcon" />
    </flux:field>

    <flux:field>
        <flux:label>{{ __('Beschreibung (:lang)', ['lang' => mb_strtoupper($this->editLocale)]) }}</flux:label>

        <flux:textarea wire:model="editDescription"
                       rows="3"
                       maxlength="280"
                       data-testid="description-input" />

        <flux:description>
            {{ __('Gespeichert wird nur diese Sprache; die anderen acht bleiben unverändert.') }}
        </flux:description>

        <flux:error name="editDescription" />
    </flux:field>
</div>

<div class="mt-3 flex items-center justify-end gap-2">
    <flux:button size="sm" variant="ghost" wire:click="cancelEdit" data-testid="edit-cancel">
        {{ __('Abbrechen') }}
    </flux:button>
    <flux:button size="sm" variant="primary" wire:click="save" data-testid="edit-save">
        {{ __('Speichern') }}
    </flux:button>
</div>

{{--
    Resting state shows the sixteen names the vocabulary already uses; typing reveals
    all fifty-two. The signal is Flux's own — the search input is `:placeholder-shown`
    exactly while nothing has been typed, which is what Flux uses to hide its own
    clear button. No second source of truth, no Alpine state to keep in step.

    `display: none` rather than `visibility` or `opacity`: Flux's filter walker reads
    computed display, so a hidden option also drops out of keyboard navigation instead
    of becoming an invisible stop.
--}}
<style>
    [data-flux-options]:has(input:placeholder-shown) .icon-option:not(.icon-option--common) { display: none; }
</style>
