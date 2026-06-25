{{-- ponytail: ein wiederverwendbarer Disclaimer für Index- und Landingpage --}}
<div role="alert"
     class="mb-8 rounded-2xl border-4 border-amber-500 bg-amber-50 text-amber-950 dark:border-amber-400 dark:bg-amber-950 dark:text-amber-50 shadow-lg">
    <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-start sm:gap-5 sm:p-7">
        <flux:icon.exclamation-triangle
            class="size-12 shrink-0 text-amber-600 dark:text-amber-300 motion-safe:animate-pulse"/>
        <div class="space-y-3">
            <p class="text-2xl font-black uppercase tracking-tight sm:text-3xl">
                {{ __('Keine Empfehlung von EINUNDZWANZIG') }}
            </p>
            <p class="text-base font-semibold leading-relaxed sm:text-lg">
                {{ __('Diese Einträge stammen von Plebs selbst – nicht von EINUNDZWANZIG geprüft, kuratiert oder empfohlen.') }}
            </p>
            <p class="text-base font-bold leading-relaxed sm:text-lg">
                {{ __("Don't trust, verify: Prüfe jeden Service eigenständig auf Qualität und Sicherheit.") }}
            </p>
            <ul class="list-disc space-y-1 pl-5 text-sm font-medium sm:text-base">
                <li>{{ __('Achte darauf, dass der Service stets die neueste Version der Software betreibt.') }}</li>
                <li>{{ __('Validiere den Einsteller über sein npub.') }}</li>
            </ul>
        </div>
    </div>
</div>
