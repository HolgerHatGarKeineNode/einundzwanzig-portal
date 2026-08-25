<div
    {{-- Die dunkle Flaeche sitzt jetzt am SVG-Zweig in `app-logo-icon`, wo sie
         hingehoert: sie ist die Unterlage fuer das hart weisse de-DE-SVG. An der
         Huelle lag sie unter JEDEM Motiv und stand dem `twenty-one.png` als
         Eckkeil um die eigene Kachel. --}}
    class="flex aspect-square size-8 items-center justify-center rounded-xs text-accent-foreground">
    <x-app-logo-icon class="size-5 fill-current text-white dark:text-black"/>
</div>
<div class="ms-1 grid flex-1 text-start text-sm">
    <span class="mb-0.5 truncate leading-tight font-semibold">{{ __('EINUNDZWANZIG Portal') }}</span>
</div>
