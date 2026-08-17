@blaze(fold: true)

@props([
    'placeholder' => null,
    'suffix' => null,
    'size' => null,
    'max' => null,
    'input' => null
])

@php
    $classes = Flux::classes()
        ->add('overflow-hidden flex gap-2 text-start flex-1 text-zinc-700')
        ->add('[[disabled]_&]:text-zinc-500 dark:text-zinc-300 dark:[[disabled]_&]:text-zinc-400');

    $optionClasses = Flux::classes()
        ->add('px-2 flex max-w-full text-zinc-700 dark:text-zinc-200 bg-zinc-400/15 dark:bg-zinc-400/40')
        ->add('cursor-default') // Combobox trigger sets cursor-text, so we need to reset it here...
        ->add(match($size) {
            default => 'rounded-md py-1 text-base sm:text-sm leading-4',
            'sm' => 'rounded-sm py-[calc(0.125rem+1px)] text-sm leading-4',
        });

    /*
     * Published from the Flux stub to fix two accessibility defects in the remove button.
     * Everything else on this page is the stub verbatim.
     *
     * Target size (WCAG 2.5.8): the stub's px-1 with a 12px micro icon yields roughly
     * 20x18px, under the required 24x24. px-1.5/py-1.5 makes it exactly 24x24, and the
     * matching negative margins keep the chip the same height as before.
     */
    $removeClasses = Flux::classes()
        ->add('shrink-0 px-1.5 -me-2 text-zinc-400 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200')
        ->add('cursor-pointer rounded-sm focus-visible:outline-2 focus-visible:outline-offset-1')
        ->add(match($size) {
            default => 'py-1.5 -my-1.5',
            'sm' => 'py-1 -my-1',
        });
@endphp

<ui-selected {{ $attributes->class($classes) }}>
    <?php if ($placeholder): ?>
        <div class="contents" wire:ignore x-ignore>
            <template name="placeholder">
                <span class="ms-1 text-zinc-400 [[disabled]_&]:text-zinc-400/70 dark:text-zinc-400 dark:[[disabled]_&]:text-zinc-500" data-flux-pillbox-placeholder>
                    {{ $placeholder }}
                </span>
            </template>
        </div>
    <?php endif; ?>

    <template name="option">
        <div {{ $attributes->class($optionClasses) }}>
            <div class="font-medium min-w-0"><slot name="text"></slot></div>

            {{--
                Keyboard reachability (WCAG 2.1.1): ui-selected-remove is a custom element
                that listens for `click` only. Without tabindex it never receives focus, so
                it never receives a keyboard-generated click either — the third chip of five
                could not be removed without destroying the ones behind it via Backspace.

                The handler is an inline onkeydown rather than Alpine: this markup lives in
                a <template> that Flux instantiates with cloneNode, and neighbouring
                templates on this page are explicitly marked x-ignore, so Alpine bindings
                are not dependable here. A plain DOM attribute survives cloning.
            --}}
            <ui-selected-remove
                {{ $attributes->class($removeClasses) }}
                tabindex="0"
                role="button"
                aria-label="{{ __('Entfernen') }}"
                onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); this.click(); }"
            >
                <flux:icon.x-mark variant="micro" :class="$size === 'xs' ? 'size-3' : ''" />
            </ui-selected-remove>
        </div>
    </template>

    <div class="flex flex-wrap gap-1 grow">
        <div class="contents" wire:ignore x-ignore>
            <template name="options">
                <div class="contents">
                    <slot></slot>
                </div>
            </template>
        </div>
        
        {{ $input }}
    </div>
</ui-selected>