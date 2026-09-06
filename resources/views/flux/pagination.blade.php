@blaze(fold: true)

{{-- Published copy of vendor/livewire/flux/stubs/resources/views/flux/pagination.blade.php.
     One deliberate deviation from the stub: the "Showing … results" line below is a single
     translation key with placeholders instead of four fragment keys (#126). Everything else is
     the stub verbatim — when Flux ships a new pagination view, re-publish it and re-apply that
     one line rather than hand-merging. --}}

<?php
// Workaround: when `scroll-to` is forwarded via `:attributes` (e.g. from the table component
// using `attributesAfter`), Laravel doesn't convert the kebab-case `scroll-to` attribute to
// the camelCase `scrollTo` prop. `forwardedAttributes` handles this conversion manually.
extract(Flux::forwardedAttributes($attributes, ['scrollTo']));
?>

@props([
    'paginator' => null,
    'scrollTo' => $scrollTo ?? null,
])

@php
$simple = ! $paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;

$scrollToSelector = $scrollTo === true ? 'body' : $scrollTo;

$scrollIntoViewJsSnippet = ($scrollTo !== null && $scrollTo !== false)
    ? "(\$el.closest('{$scrollToSelector}') || \$el.closest('body').querySelector('{$scrollToSelector}')).scrollIntoView()"
    : '';
@endphp

@if ($simple)
    <div {{ $attributes->class('pt-3 border-t border-zinc-100 dark:border-zinc-700 flex justify-between items-center') }} data-flux-pagination>
        <div></div>

        @if ($paginator->hasPages())
            <div class="flex items-center bg-white border border-zinc-200 rounded-[8px] p-[1px] dark:bg-white/10 dark:border-white/10">
                @if ($paginator->onFirstPage())
                    <div class="flex justify-center items-center size-8 sm:size-6 rounded-[6px] text-zinc-300 dark:text-zinc-500">
                        <flux:icon.chevron-left variant="micro" class="rtl:hidden" />
                        <flux:icon.chevron-right variant="micro" class="hidden rtl:inline" />
                    </div>
                @else
                    @if(method_exists($paginator,'getCursorName'))
                        {{-- On an empty page the previous cursor is null, so fall back to the current cursor (reloads the same page, mirroring Laravel's null page URL) --}}
                        @php($previousCursor = $paginator->previousCursor() ?? $paginator->cursor())
                        <button type="button" wire:key="cursor-{{ $paginator->getCursorName() }}-{{ $previousCursor?->encode() }}" wire:click="setPage('{{ $previousCursor?->encode() }}','{{ $paginator->getCursorName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" class="flex justify-center items-center size-8 sm:size-6 rounded-[6px] text-zinc-400 dark:text-white hover:bg-zinc-100 dark:hover:bg-white/20 hover:text-zinc-800 dark:hover:text-white">
                            <flux:icon.chevron-left variant="micro" class="rtl:hidden" />
                            <flux:icon.chevron-right variant="micro" class="hidden rtl:inline" />
                        </button>
                    @else
                        <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" class="flex justify-center items-center size-8 sm:size-6 rounded-[6px] text-zinc-400 dark:text-white hover:bg-zinc-100 dark:hover:bg-white/20 hover:text-zinc-800 dark:hover:text-white">
                            <flux:icon.chevron-left variant="micro" class="rtl:hidden" />
                            <flux:icon.chevron-right variant="micro" class="hidden rtl:inline" />
                        </button>
                    @endif
                @endif

                @if ($paginator->hasMorePages())
                    @if(method_exists($paginator,'getCursorName'))
                        {{-- On an empty page the next cursor is null, so fall back to the current cursor (reloads the same page, mirroring Laravel's null page URL) --}}
                        @php($nextCursor = $paginator->nextCursor() ?? $paginator->cursor())
                        <button type="button" wire:key="cursor-{{ $paginator->getCursorName() }}-{{ $nextCursor?->encode() }}" wire:click="setPage('{{ $nextCursor?->encode() }}','{{ $paginator->getCursorName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" class="flex justify-center items-center size-8 sm:size-6 rounded-[6px] text-zinc-400 dark:text-white hover:bg-zinc-100 dark:hover:bg-white/20 hover:text-zinc-800 dark:hover:text-white">
                            <flux:icon.chevron-right variant="micro" class="rtl:hidden" />
                            <flux:icon.chevron-left variant="micro" class="hidden rtl:inline" />
                        </button>
                    @else
                        <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" class="flex justify-center items-center size-8 sm:size-6 rounded-[6px] text-zinc-400 dark:text-white hover:bg-zinc-100 dark:hover:bg-white/20 hover:text-zinc-800 dark:hover:text-white">
                            <flux:icon.chevron-right variant="micro" class="rtl:hidden" />
                            <flux:icon.chevron-left variant="micro" class="hidden rtl:inline" />
                        </button>
                    @endif
                @else
                    <div class="flex justify-center items-center size-8 sm:size-6 rounded-[6px] text-zinc-300 dark:text-zinc-500">
                        <flux:icon.chevron-right variant="micro" class="rtl:hidden" />
                        <flux:icon.chevron-left variant="micro" class="hidden rtl:inline" />
                    </div>
                @endif
            </div>
        @endif
    </div>
@else
    <div {{ $attributes->class('@container pt-3 border-t border-zinc-100 dark:border-zinc-700 flex justify-between items-center gap-3') }} data-flux-pagination>
        @if ($paginator->total() > 0)
            <div class="text-zinc-500 dark:text-zinc-400 text-xs font-medium whitespace-nowrap">
                {{-- The vendor stub assembles this line from four separate keys ("Showing", "to",
                     "of", "results"). Word order and case endings differ per language, so no set of
                     four fragments can be translated into a sentence — Hungarian came out as
                     "Bemutató 1 hogy 15 a 42 eredmények" (#126). One key with placeholders is the
                     only shape a translator can work with, and it lives in the pagination group
                     file, which — unlike a JSON key — falls back to English per key when a locale
                     has not translated it yet. --}}
                {!! __('pagination.showing', [
                    'first' => $paginator->firstItem(),
                    'last' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                ]) !!}
            </div>
        @else
            <div></div>
        @endif

        @if ($paginator->hasPages())
            {{-- Mobile pagination --}}
            <div class="flex @[40rem]:hidden items-center bg-white border border-zinc-200 rounded-[8px] p-[1px] dark:bg-white/10 dark:border-white/10">
                @if ($paginator->onFirstPage())
                    <div aria-disabled="true" aria-label="{{ __('pagination.previous') }}" class="flex justify-center items-center size-8 sm:size-6 rounded-[6px] text-zinc-300 dark:text-zinc-500">
                        <flux:icon.chevron-left variant="micro" class="rtl:hidden" />
                        <flux:icon.chevron-right variant="micro" class="hidden rtl:inline" />
                    </div>
                @else
                    <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" aria-label="{{ __('pagination.previous') }}" class="flex justify-center items-center size-8 sm:size-6 rounded-[6px] text-zinc-400 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-white/20 hover:text-zinc-800 dark:hover:text-white">
                        <flux:icon.chevron-left variant="micro" class="rtl:hidden" />
                        <flux:icon.chevron-right variant="micro" class="hidden rtl:inline" />
                    </button>
                @endif

                @if ($paginator->hasMorePages())
                    <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" aria-label="{{ __('pagination.next') }}" class="flex justify-center items-center size-8 sm:size-6 rounded-[6px] text-zinc-400 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-white/20 hover:text-zinc-800 dark:hover:text-white">
                        <flux:icon.chevron-right variant="micro" class="rtl:hidden" />
                        <flux:icon.chevron-left variant="micro" class="hidden rtl:inline" />
                    </button>
                @else
                    <div aria-label="{{ __('pagination.next') }}" class="flex justify-center items-center size-8 sm:size-6 rounded-[6px] text-zinc-300 dark:text-zinc-500">
                        <flux:icon.chevron-right variant="micro" class="rtl:hidden" />
                        <flux:icon.chevron-left variant="micro" class="hidden rtl:inline" />
                    </div>
                @endif
            </div>

            {{-- Desktop pagination --}}
            <div class="hidden @[40rem]:flex items-center bg-white border border-zinc-200 rounded-[8px] p-[1px] dark:bg-white/10 dark:border-white/10">
                @if ($paginator->onFirstPage())
                    <div aria-disabled="true" aria-label="{{ __('pagination.previous') }}" class="flex justify-center items-center size-6 rounded-[6px] text-zinc-300 dark:text-zinc-500">
                        <flux:icon.chevron-left variant="micro" class="rtl:hidden" />
                        <flux:icon.chevron-right variant="micro" class="hidden rtl:inline" />
                    </div>
                @else
                    <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" aria-label="{{ __('pagination.previous') }}" class="flex justify-center items-center size-6 rounded-[6px] text-zinc-400 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-white/20 hover:text-zinc-800 dark:hover:text-white">
                        <flux:icon.chevron-left variant="micro" class="rtl:hidden" />
                        <flux:icon.chevron-right variant="micro" class="hidden rtl:inline" />
                    </button>
                @endif

                @foreach (\Livewire\invade($paginator)->elements() as $element)
                    {{-- "Three Dots" Separator --}}
                    @if (is_string($element))
                        <div
                            aria-disabled="true"
                            class="cursor-default flex justify-center items-center text-xs size-6 rounded-[6px] font-medium dark:text-zinc-400 text-zinc-400"
                        >{{ $element }}</div>
                    @endif

                    {{-- Array Of Links --}}
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <div
                                    wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}"
                                    aria-current="page"
                                    class="cursor-default flex justify-center items-center text-xs h-6 px-2 rounded-[6px] font-medium dark:text-white text-zinc-800"
                                >{{ $page }}</div>
                            @else
                                <button
                                    wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}"
                                    wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                    x-on:click="{{ $scrollIntoViewJsSnippet }}"
                                    type="button"
                                    class="text-xs h-6 px-2 rounded-[6px] text-zinc-400 font-medium dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-white/20 hover:text-zinc-800 dark:hover:text-white"
                                >{{ $page }}</button>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" aria-label="{{ __('pagination.next') }}" class="flex justify-center items-center size-6 rounded-[6px] text-zinc-400 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-white/20 hover:text-zinc-800 dark:hover:text-white">
                        <flux:icon.chevron-right variant="micro" class="rtl:hidden" />
                        <flux:icon.chevron-left variant="micro" class="hidden rtl:inline" />
                    </button>
                @else
                    <div aria-label="{{ __('pagination.next') }}" class="flex justify-center items-center size-6 rounded-[6px] text-zinc-300 dark:text-zinc-500">
                        <flux:icon.chevron-right variant="micro" class="rtl:hidden" />
                        <flux:icon.chevron-left variant="micro" class="hidden rtl:inline" />
                    </div>
                @endif
            </div>
        @endif
    </div>
@endif
