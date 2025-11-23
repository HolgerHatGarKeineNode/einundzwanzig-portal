@props(['meetup', 'url', 'eventUrl' => null])

<div class="w-72">
    <flux:heading size="lg" class="mb-3">{{ $meetup->name }}</flux:heading>

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
                {{ $meetup->nextEvent['start']->format('d.m.Y') }}
            </flux:text>

            <flux:text class="text-sm flex items-center gap-2">
                <flux:icon.clock class="w-4 h-4"/>
                {{ $meetup->nextEvent['start']->format('H:i') }} Uhr
            </flux:text>

            @if($meetup->nextEvent['location'])
                <flux:text class="text-sm flex items-center gap-2">
                    <flux:icon.map-pin class="w-4 h-4"/>
                    {{ $meetup->nextEvent['location'] }}
                </flux:text>
            @endif

            <flux:text class="flex items-center gap-2 mt-2">
                <span class="text-xs text-zinc-200">{{ $meetup->nextEvent['attendees'] }} {{ __('Zusagen') }}</span>
                <flux:separator vertical/>
                <span class="text-xs text-zinc-200">{{ $meetup->nextEvent['might_attendees'] }} {{ __('Vielleicht') }}</span>
            </flux:text>
        </div>
    @endif

    @if($meetup->telegram_link || $meetup->webpage || $meetup->twitter_username || $meetup->matrix_group || $meetup->nostr || $meetup->simplex || $meetup->signal)
        <flux:separator variant="subtle" class="my-3"/>

        <div class="flex gap-2 flex-wrap text-white">
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
