{{-- Confirm step of the account-merge wizard. Extracted into a partial so
     the parent Volt view stays under Livewire's morph-compilation regex size
     limit (PCRE 'expression too large'). --}}
            <div class="space-y-6">
                <flux:callout variant="secondary" icon="key">
                    <flux:callout.heading>{{ __('Erkannte Nostr-Identität') }}</flux:callout.heading>
                    <flux:callout.text>
                        <code class="break-all text-xs">{{ $verifiedNpub }}</code>
                    </flux:callout.text>
                </flux:callout>

                <flux:callout variant="warning" icon="exclamation-triangle">
                    <flux:callout.text>
                        {{ __('Stelle sicher, dass dies genau deine npub ist. Nach der Bestätigung meldest du dich mit diesem Schlüssel an.') }}
                    </flux:callout.text>
                </flux:callout>

                {{-- Live Nostr profile (NIP-01 kind-0), loaded from relays after render. --}}
                <div wire:init="loadNostrProfile" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <p class="mb-3 text-sm font-semibold text-zinc-500 dark:text-zinc-400">{{ __('Nostr-Profil (live)') }}</p>

                    <div wire:loading wire:target="loadNostrProfile" class="flex items-center gap-2 text-sm text-zinc-500">
                        <flux:icon.arrow-path class="animate-spin size-4" aria-hidden="true"/>
                        {{ __('Profil wird von Relays geladen…') }}
                    </div>

                    <div wire:loading.remove wire:target="loadNostrProfile">
                        @if ($nostrProfile)
                            <div class="flex items-start gap-4">
                                @if (!empty($nostrProfile['picture']))
                                    <img src="{{ $nostrProfile['picture'] }}" alt="" class="size-16 shrink-0 rounded-full object-cover"
                                         onerror="this.style.display='none'">
                                @endif
                                <div class="min-w-0 space-y-1 text-sm">
                                    @if (!empty($nostrProfile['name']))
                                        <div class="font-medium">{{ $nostrProfile['name'] }}</div>
                                    @endif
                                    @if (!empty($nostrProfile['nip05']))
                                        <div class="text-zinc-500 dark:text-zinc-400">✔ {{ $nostrProfile['nip05'] }}</div>
                                    @endif
                                    @if (!empty($nostrProfile['lud16']))
                                        <div class="text-amber-600 dark:text-amber-400">⚡ {{ $nostrProfile['lud16'] }}</div>
                                    @endif
                                    @if (!empty($nostrProfile['website']))
                                        <div class="truncate text-zinc-500 dark:text-zinc-400">{{ $nostrProfile['website'] }}</div>
                                    @endif
                                    @if (!empty($nostrProfile['about']))
                                        <p class="whitespace-pre-line text-zinc-600 dark:text-zinc-300">{{ \Illuminate\Support\Str::limit($nostrProfile['about'], 280) }}</p>
                                    @endif
                                </div>
                            </div>
                        @elseif ($nostrProfileLoaded)
                            <p class="text-sm text-zinc-500">{{ __('Kein Nostr-Profil gefunden oder Relays nicht erreichbar.') }}</p>
                        @endif
                    </div>
                </div>

                @if ($willMerge)
                    <flux:callout variant="success" icon="shield-check">
                        <flux:callout.heading>{{ __('Alle Leaderships bleiben erhalten') }}</flux:callout.heading>
                        <flux:callout.text>
                            {{ __('Jede Meetup-Leadership und jeder Inhalt des anderen Kontos wird vollständig in dein aktuelles Konto übertragen. Erst danach wird das dann leere Konto gelöscht — du verlierst nichts.') }}
                        </flux:callout.text>
                    </flux:callout>

                    {{-- Die App-Tokens hängen am eingeschmolzenen Konto (MergeUserAccounts::discardLoserTokens)
                         und werden mit ihm gelöscht. Ohne diesen Hinweis liest sich der stille Logout in der
                         Companion-App wie ein Fehler des Merges. --}}
                    <flux:callout variant="warning" icon="device-phone-mobile">
                        <flux:callout.heading>{{ __('Deine App meldet sich einmalig ab') }}</flux:callout.heading>
                        <flux:callout.text>
                            {{ __('Bist du mit dieser npub in der Companion-App angemeldet, wird die App beim Verbinden abgemeldet. Melde dich dort danach einfach neu mit deinem Nostr-Schlüssel an — deine Meetups und Rechte sind unverändert.') }}
                        </flux:callout.text>
                    </flux:callout>
                @else
                    <flux:callout variant="secondary" icon="information-circle">
                        <flux:callout.text>{{ __('Diese npub ist noch keinem Konto zugeordnet und wird einfach mit deinem aktuellen Konto verknüpft.') }}</flux:callout.text>
                    </flux:callout>
                @endif

                @php
                    $accounts = [['title' => __('Dein aktuelles Konto (bleibt)'), 'info' => $survivorInfo, 'absorb' => false]];
                    if ($willMerge) {
                        $accounts[] = ['title' => __('Wird eingeschmolzen & gelöscht'), 'info' => $loserInfo, 'absorb' => true];
                    }
                @endphp

                <div class="grid gap-4 @if ($willMerge) md:grid-cols-2 @endif">
                    @foreach ($accounts as $account)
                        @php($info = $account['info'])
                        <div class="rounded-lg border p-4 text-sm {{ $account['absorb'] ? 'border-amber-300 dark:border-amber-700/60' : 'border-emerald-300 dark:border-emerald-700/60' }}">
                            <div class="mb-3 flex items-center gap-2">
                                <span class="inline-block size-2 rounded-full {{ $account['absorb'] ? 'bg-amber-500' : 'bg-emerald-500' }}"></span>
                                <span class="text-xs font-semibold uppercase tracking-wide {{ $account['absorb'] ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">{{ $account['title'] }}</span>
                            </div>

                            <div class="flex items-center gap-3">
                                @if (!empty($info['photo']))
                                    <img src="{{ $info['photo'] }}" alt="" class="size-10 rounded-full object-cover" onerror="this.style.display='none'">
                                @endif
                                <div class="min-w-0">
                                    <div class="truncate font-medium">{{ $info['name'] ?? '—' }}</div>
                                    <div class="text-xs text-zinc-500">{{ __('Erstellt am') }} {{ $info['created_at'] ?? '—' }} · {{ __('Reputation') }} {{ $info['reputation'] ?? 0 }}</div>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-1.5">
                                <span class="rounded px-2 py-0.5 text-xs {{ ($info['has_lightning'] ?? false) ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-zinc-100 text-zinc-400 dark:bg-zinc-800' }}">⚡ {{ ($info['has_lightning'] ?? false) ? __('Lightning') : __('kein Lightning') }}</span>
                                <span class="rounded px-2 py-0.5 text-xs {{ ($info['has_nostr'] ?? false) ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300' : 'bg-zinc-100 text-zinc-400 dark:bg-zinc-800' }}">🟣 {{ ($info['has_nostr'] ?? false) ? __('Nostr') : __('kein Nostr') }}</span>
                                @foreach (($info['roles'] ?? []) as $role)
                                    <span class="rounded bg-red-100 px-2 py-0.5 text-xs text-red-800 dark:bg-red-900/40 dark:text-red-300">{{ $role }}</span>
                                @endforeach
                            </div>

                            @if (!empty($info['lightning_address']))
                                <div class="mt-2 truncate text-xs text-zinc-500">{{ __('Lightning-Adresse') }}: {{ $info['lightning_address'] }}</div>
                            @endif

                            @if ($account['absorb'] && (!empty($info['leader_meetups']) || !empty($info['member_meetups']) || !empty($info['counts'])))
                                <div class="mt-3 flex items-center gap-1.5 rounded-md bg-emerald-50 px-2 py-1.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                    <flux:icon.arrow-right class="size-4 shrink-0" aria-hidden="true"/>
                                    {{ __('Alles Folgende wird in dein Konto übertragen') }}
                                </div>
                            @endif

                            @if (!empty($info['leader_meetups']))
                                <div class="mt-3">
                                    <div class="text-xs font-semibold text-zinc-500">{{ __('Leiter von') }} ({{ count($info['leader_meetups']) }})</div>
                                    <ul class="mt-1 space-y-0.5 text-zinc-700 dark:text-zinc-300">
                                        @foreach ($info['leader_meetups'] as $name)
                                            <li>👑 {{ $name }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if (!empty($info['member_meetups']))
                                <div class="mt-2">
                                    <div class="text-xs font-semibold text-zinc-500">{{ __('Mitglied bei') }} ({{ count($info['member_meetups']) }})</div>
                                    <ul class="mt-1 space-y-0.5 text-zinc-600 dark:text-zinc-400">
                                        @foreach ($info['member_meetups'] as $name)
                                            <li>· {{ $name }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if (!empty($info['counts']))
                                <div class="mt-3">
                                    <div class="text-xs font-semibold text-zinc-500">{{ __('Erstellte Inhalte') }}</div>
                                    <div class="mt-1 grid grid-cols-2 gap-x-3 gap-y-0.5 text-zinc-600 dark:text-zinc-400">
                                        @foreach ($info['counts'] as $label => $count)
                                            <div class="flex justify-between gap-2"><span class="truncate">{{ $label }}</span><span class="font-medium">{{ $count }}</span></div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if (empty($info['leader_meetups']) && empty($info['member_meetups']) && empty($info['counts']))
                                <div class="mt-3 text-xs text-zinc-400">{{ __('Keine weiteren Verknüpfungen.') }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>

                @include('livewire.settings.partials.profile-picker', ['fields' => $fields])

                <flux:callout variant="warning" icon="key">
                    <flux:callout.heading>{{ __('Wichtig: Lightning wird deaktiviert') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Nach dem Verbinden kannst du dich für dieses Konto NICHT mehr per Lightning anmelden. Dein Nostr-Schlüssel ist dann der einzige Zugang. Sichere dein Schlüssel-Backup (nsec bzw. Amber) besonders gut — verlierst du ihn, verlierst du den Zugang zum Konto.') }}
                    </flux:callout.text>
                </flux:callout>

                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <flux:checkbox wire:model.live="acknowledgedBackup"/>
                    <span class="text-sm text-zinc-700 dark:text-zinc-300">
                        {{ __('Ich habe meinen Nostr-Schlüssel sicher gesichert und verstehe, dass der Lightning-Login danach deaktiviert ist.') }}
                    </span>
                </label>
                @error('acknowledgedBackup')
                    <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                @enderror

                <div class="flex flex-wrap gap-3">
                    <flux:button variant="primary" class="cursor-pointer" wire:click="confirmMerge"
                                 x-bind:disabled="!$wire.acknowledgedBackup">
                        {{ __('Konten jetzt verbinden') }}
                    </flux:button>
                    <flux:button variant="ghost" class="cursor-pointer" x-on:click="window.location.reload()">
                        {{ __('Abbrechen') }}
                    </flux:button>
                </div>

                <div class="text-center">
                    <flux:link :href="$dashboardUrl" wire:navigate class="text-sm">
                        {{ __('Später migrieren – zum Dashboard') }}
                    </flux:link>
                </div>
            </div>
