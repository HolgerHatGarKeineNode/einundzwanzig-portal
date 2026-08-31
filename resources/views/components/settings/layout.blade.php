<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist>
            <flux:navlist.item :href="route('settings.profile', ['country' => str(session('lang_country', 'de'))->after('-')->lower()])" wire:navigate>{{ __('Profile') }}</flux:navlist.item>
            {{--<flux:navlist.item :href="route('settings.password')" wire:navigate>{{ __('Password') }}</flux:navlist.item>--}}
            <flux:navlist.item :href="route('settings.appearance', ['country' => str(session('lang_country', 'de'))->after('-')->lower()])" wire:navigate>{{ __('Appearance') }}</flux:navlist.item>
            <flux:navlist.item :href="route('settings.api-tokens', ['country' => str(session('lang_country', 'de'))->after('-')->lower()])" wire:navigate>{{ __('API Tokens') }}</flux:navlist.item>
            <flux:navlist.item :href="route('settings.webhooks', ['country' => str(session('lang_country', 'de'))->after('-')->lower()])" wire:navigate icon="bolt">{{ __('Webhooks') }}</flux:navlist.item>
            <flux:navlist.item :href="route('settings.link-identity', ['country' => str(session('lang_country', 'de'))->after('-')->lower()])" wire:navigate icon="link">{{ __('Konten verbinden') }}</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
