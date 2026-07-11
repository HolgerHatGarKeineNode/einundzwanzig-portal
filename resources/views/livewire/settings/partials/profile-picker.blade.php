{{-- Profile field picker for the account-merge wizard.
     Isolated into a partial: nested loops compiled directly inside the Volt
     component's own loop-key compiler produced a broken template. --}}
@php($sourceLabels = ['survivor' => __('Aktuell'), 'loser' => __('Anderes Konto'), 'nostr' => __('Nostr')])
<div class="rounded-xl border border-zinc-200 bg-gradient-to-b from-zinc-50 to-transparent p-4 dark:border-zinc-700 dark:from-zinc-800/40">
    <div class="flex items-center gap-2">
        <span aria-hidden="true">✨</span>
        <h3 class="font-semibold">{{ __('Profil zusammenstellen') }}</h3>
    </div>
    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
        {{ __('Wähle je Feld, welche Daten dein verbundenes Konto übernimmt. Nostr bringt oft ein Bild oder eine Lightning-Adresse mit, die du noch nicht hast.') }}
    </p>

    <div class="mt-4 space-y-5">
        @foreach ($fields as $field => $def)
            <div>
                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $def['label'] }}</div>

                <div class="{{ $def['type'] === 'photo' ? 'flex flex-wrap gap-3' : 'grid gap-2 sm:grid-cols-3' }}">
                    @foreach ($def['options'] as $opt)
                        <label class="relative cursor-pointer">
                            <input type="radio" class="peer sr-only" wire:model.live="profileChoices.{{ $field }}" value="{{ $opt['src'] }}">
                            @if ($def['type'] === 'photo')
                                <span class="absolute right-1 top-1 z-10 flex size-5 items-center justify-center rounded-full bg-emerald-500 text-xs font-bold text-white opacity-0 transition peer-checked:opacity-100" aria-hidden="true">&check;</span>
                                <div class="flex w-24 flex-col items-center gap-1.5 rounded-xl border-2 border-transparent p-2 transition hover:bg-zinc-100 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 dark:hover:bg-zinc-800 dark:peer-checked:bg-emerald-900/20">
                                    <img src="{{ $opt['preview'] }}" alt="" class="size-16 rounded-full object-cover ring-1 ring-zinc-200 dark:ring-zinc-700" onerror="this.style.visibility='hidden'">
                                    <span class="text-xs text-zinc-600 dark:text-zinc-300">{{ $sourceLabels[$opt['src']] ?? $opt['src'] }}</span>
                                    <span class="rounded-full bg-emerald-500 px-1.5 py-0.5 text-[10px] font-bold text-white {{ $opt['is_new'] ? '' : 'hidden' }}">✨ {{ __('Neu') }}</span>
                                </div>
                            @else
                                <div class="flex h-full items-start gap-2 rounded-lg border-2 border-zinc-200 p-3 text-sm transition peer-checked:border-emerald-500 peer-checked:bg-emerald-50 dark:border-zinc-700 dark:peer-checked:bg-emerald-900/20">
                                    <span class="mt-0.5 shrink-0 font-bold text-emerald-500 opacity-30 transition peer-checked:opacity-100" aria-hidden="true">&check;</span>
                                    <div class="min-w-0">
                                        <div class="text-[11px] uppercase tracking-wide text-zinc-400">
                                            {{ $sourceLabels[$opt['src']] ?? $opt['src'] }}
                                            <span class="text-emerald-500 {{ $opt['is_new'] ? '' : 'hidden' }}">✨</span>
                                        </div>
                                        <div class="break-words font-medium">{{ $opt['preview'] }}</div>
                                    </div>
                                </div>
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
