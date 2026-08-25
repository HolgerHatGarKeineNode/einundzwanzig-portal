{{--
    One tag icon, resolved through the whitelist.

    Every screen that shows `tags.icon` goes through here, because the raw value is
    not safe to render: Flux resolves an icon name to a Blade component, and a name
    it does not know throws instead of degrading —

        Illuminate\View\ViewException: Flux component [icon.coin] does not exist.

    Deliberately a whitelist and not a try/catch. A catch would render every typo as
    a fallback and never tell anyone, which is how the Font Awesome names survived in
    the seed vocabulary unnoticed in the first place. An unlisted value falls back to
    `tag` here AND is marked, so the moderation screen can point at it.

    MEASUREMENT: Flux inlines each icon as raw SVG, after which the name appears
    nowhere in the HTML — a test grepping for "microphone" reports "no icon" even
    where one is rendered, and reports it as a pass. `data-tag-icon` is the hook that
    makes the rendered name observable; `data-tag-icon-fallback` carries the stored
    value whenever it had to be substituted, so a test can tell "renders the right
    icon" from "renders the fallback for everything".

    @param string|null $tagIcon              the stored value, unvalidated
    @param string|null $tagIconClass         classes for the svg itself
    @param string|null $tagIconWrapperClass  alignment of the wrapper inside its row;
                                             a flex child stretches to row height by
                                             default, which turns a square icon box
                                             into a tall one, so the caller says where
                                             the icon belongs on its line
--}}
@php
    $tagIconRequested = is_string($tagIcon ?? null) && $tagIcon !== '' ? $tagIcon : null;
    $tagIconResolved = in_array($tagIconRequested, (array) config('einundzwanzig.tag_icons', []), true)
        ? $tagIconRequested
        : 'tag';
@endphp

<span class="{{ $tagIconWrapperClass ?? 'inline-flex shrink-0 self-center' }}"
      aria-hidden="true"
      data-tag-icon="{{ $tagIconResolved }}"
      @if ($tagIconResolved !== $tagIconRequested)
          data-tag-icon-fallback="{{ $tagIconRequested ?? '' }}"
      @endif
>
    <flux:icon :icon="$tagIconResolved"
               variant="mini"
               :class="$tagIconClass ?? 'size-5 text-zinc-600 dark:text-zinc-300'" />
</span>
