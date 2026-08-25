{{--
    The two describing fields of one tag: its icon and its description.

    Both sit in the row itself rather than in a modal. A modal would put the tag you
    are fixing behind an overlay and hand you a focus trap to get right; editing in
    place keeps the neighbours visible, which is what tells you whether a description
    is worth writing at all.

    @param App\Models\Tag $tag
--}}
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
