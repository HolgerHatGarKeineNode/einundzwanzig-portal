@props(['meetup', 'url', 'eventUrl' => null])

<div class="w-72">
    <div class="flex items-center justify-between mb-3 gap-2">
        <flux:heading size="lg">{{ $meetup->name }}</flux:heading>
        @if($meetup->is_active)
            <flux:badge color="green" size="sm">{{ __('Aktiv') }}</flux:badge>
        @else
            <flux:badge color="zinc" size="sm">{{ __('Inaktiv') }}</flux:badge>
        @endif
    </div>

    @if($meetup->last_event_at)
        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 mb-2">
            {{ __('Letztes Event') }}: {{ $meetup->last_event_at->asDate() }}
        </flux:text>
    @endif

    @if($meetup->intro)
        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400 mb-3">
            {{ Str::limit($meetup->intro, 100) }}
        </flux:text>
    @endif

    @if($meetup->nextEvent)
        <flux:separator variant="subtle" class="my-3"/>

        <flux:subheading class="mb-2">{{ __('Nächster Termin') }}</flux:subheading>

        <div class="space-y-1">
            <flux:text class="text-sm flex items-center gap-2">
                <flux:icon.calendar class="w-4 h-4"/>
                {{ $meetup->nextEvent['start']->asDate() }}
            </flux:text>

            <flux:text class="text-sm flex items-center gap-2">
                <flux:icon.clock class="w-4 h-4"/>
                {{ __(':time Uhr', ['time' => $meetup->nextEvent['start']->asTime()]) }}
            </flux:text>

            {{-- x-osm-place erwartet ein Objekt; nextEvent ist ein Array — der Cast
                 reicht, weil alle vier gelesenen Schluessel immer gesetzt sind.
                 showAddress: der Freitext bleibt sichtbar, wenn er etwas anderes
                 sagt als der Kartenort (Raumnummer, Zusatz), und faellt weg, wenn
                 er ihn nur wiederholt. --}}
            @if($meetup->nextEvent['osm_name'] || $meetup->nextEvent['location'])
                <div class="flex items-start gap-2 text-sm">
                    <flux:icon.map-pin class="mt-0.5 size-4 shrink-0" aria-hidden="true"/>
                    <div class="min-w-0 break-words">
                        <x-osm-place :place="(object) $meetup->nextEvent" show-address/>
                    </div>
                </div>
            @endif

            <flux:text class="flex items-center gap-2 mt-2">
                <span class="text-xs text-zinc-600 dark:text-zinc-300">{{ trans_choice(':count Zusage|:count Zusagen', $meetup->nextEvent['attendees']) }}</span>
                <flux:separator vertical/>
                <span class="text-xs text-zinc-600 dark:text-zinc-300">{{ trans_choice(':count Vielleicht|:count Vielleicht', $meetup->nextEvent['might_attendees']) }}</span>
            </flux:text>
        </div>
    @endif

    @if($meetup->telegram_link || $meetup->webpage || $meetup->twitter_username || $meetup->matrix_group || $meetup->nostr || $meetup->simplex || $meetup->signal)
        <flux:separator variant="subtle" class="my-3"/>

        <div class="flex gap-2 flex-wrap text-zinc-600 dark:text-zinc-300">
            @if($meetup->telegram_link)
                <flux:link :href="$meetup->telegram_link" external variant="subtle" title="{{ __('Telegram') }}">
                    <flux:icon.paper-airplane variant="mini"/>
                </flux:link>
            @endif
            @if($meetup->webpage)
                <flux:link :href="$meetup->webpage" external variant="subtle" title="{{ __('Website') }}">
                    <flux:icon.globe-alt variant="mini"/>
                </flux:link>
            @endif
            @if($meetup->twitter_username)
                <flux:link :href="'https://twitter.com/' . $meetup->twitter_username" external variant="subtle" title="{{ __('Twitter') }}">
                    <flux:icon.x-mark variant="mini"/>
                </flux:link>
            @endif
            @if($meetup->matrix_group)
                <flux:link :href="$meetup->matrix_group" external variant="subtle" title="{{ __('Matrix') }}">
                    <flux:icon.chat-bubble-left variant="mini"/>
                </flux:link>
            @endif
            @if($meetup->nostr)
                <flux:link :href="'https://njump.me/'.$meetup->nostr" external variant="subtle" title="{{ __('Nostr') }}">
                    <flux:icon.bolt variant="mini"/>
                </flux:link>
            @endif
            @if($meetup->simplex)
                <flux:link :href="$meetup->simplex" external variant="subtle" title="{{ __('Simplex') }}">
                    <flux:icon.chat-bubble-bottom-center-text variant="mini"/>
                </flux:link>
            @endif
            @if($meetup->signal)
                <flux:link :href="$meetup->signal" external variant="subtle" title="{{ __('Signal') }}">
                    <flux:icon.shield-check variant="mini"/>
                </flux:link>
            @endif
        </div>
    @endif

    <flux:separator variant="subtle" class="my-3"/>

    <div class="flex gap-2">
        <flux:button :href="$url" size="sm" variant="primary">
            {{ __('Details') }}
        </flux:button>

        @if($eventUrl)
            <flux:button :href="$eventUrl" size="sm" variant="primary">
                {{ __('Nächster Termin') }}
            </flux:button>
        @endif
    </div>
</div>
