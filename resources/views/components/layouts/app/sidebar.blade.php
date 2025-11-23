<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800">
<flux:toast.group>
    <flux:toast/>
</flux:toast.group>
<flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
    <flux:sidebar.toggle class="lg:hidden" icon="x-mark"/>

    <a href="{{ route('welcome') }}" class="me-5 flex items-center space-x-2 rtl:space-x-reverse"
       wire:navigate>
        <x-app-logo/>
    </a>

    <flux:navlist variant="outline">
        <flux:navlist.group :heading="__('App')" class="grid">
            <flux:navlist.item icon="home" :href="route('dashboard', request()->route('country', 'de'))"
                               :current="request()->routeIs('dashboard')"
                               wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
        </flux:navlist.group>
        <flux:navlist.group :heading="__('Meetups')" class="grid">
            <flux:navlist.item icon="user-group" :href="route_with_country('meetups.index')"
                               :current="request()->routeIs('meetups.index')"
                               wire:navigate
                               badge="{{ \App\Models\Meetup::query()->whereHas('city.country', fn($query) => $query->where('countries.code', request()->route('country')))->count() }}">
                {{ __('Meetups') }}
            </flux:navlist.item>
            <flux:navlist.item icon="map" :href="route_with_country('meetups.map')"
                               :current="request()->routeIs('meetups.map')"
                               wire:navigate>
                <div class="flex items-center space-x-2">
                    <span>{{ __('Karte') }}</span>
                    <img alt="{{ request()->route('country') }}"
                         src="{{ asset('vendor/blade-flags/country-'.request()->route('country').'.svg') }}"
                         width="24" height="12"/>
                </div>
            </flux:navlist.item>
            <flux:navlist.item icon="map" :href="route_with_country('meetups.map-world')"
                               :current="request()->routeIs('meetups.map-world')"
                               wire:navigate badge="{{ \App\Models\Meetup::query()->count() }}">
                <div class="flex items-center space-x-2">
                    <span>{{ __('Welt-Karte') }}</span>
                    <flux:icon name="globe-europe-africa"/>
                </div>
            </flux:navlist.item>
        </flux:navlist.group>

        <flux:navlist.group :heading="__('Kurse')" class="grid">
            <flux:navlist.item icon="academic-cap" :href="route_with_country('courses.index')"
                               :current="request()->routeIs('courses.index')"
                               wire:navigate
                               badge="{{ \App\Models\Course::query()->count() }}">
                {{ __('Kurse') }}
            </flux:navlist.item>
            <flux:navlist.item icon="user" :href="route_with_country('lecturers.index')"
                               :current="request()->routeIs('lecturers.index')"
                               wire:navigate
                               badge="{{ \App\Models\Lecturer::query()->count() }}">
                {{ __('Dozenten') }}
            </flux:navlist.item>
        </flux:navlist.group>

        <flux:navlist.group :heading="__('Diverses')" class="grid">
            <flux:navlist.group :heading="__('Orte/Gebiete')" expandable
                                :expanded="request()->routeIs('cities.*') || request()->routeIs('venues.*')">
                <flux:navlist.item icon="building-office-2" :href="route_with_country('cities.index')"
                                   :current="request()->routeIs('cities.index')"
                                   wire:navigate
                                   badge="{{ \App\Models\City::query()->count() }}">
                    {{ __('Städte/Gebiete') }}
                </flux:navlist.item>
                <flux:navlist.item icon="map-pin" :href="route_with_country('venues.index')"
                                   :current="request()->routeIs('venues.index')"
                                   wire:navigate
                                   badge="{{ \App\Models\Venue::query()->count() }}">
                    {{ __('Veranstaltungsorte') }}
                </flux:navlist.item>
            </flux:navlist.group>
        </flux:navlist.group>
    </flux:navlist>

    <flux:spacer/>

    <flux:navlist variant="outline">
        <flux:navlist.item icon="language"
                           :href="route('settings.profile', ['country' => str(session('lang_country', 'de'))->after('-')->lower()])"
                           :current="request()->routeIs('settings.profile')"
                           wire:navigate>
            {{ __('Sprache wechseln') }}
        </flux:navlist.item>
        <flux:navlist.item icon="folder-git-2"
                           href="https://gitworkshop.dev/holgerhatgarkeinenode@einundzwanzig.space/einundzwanzig-app"
                           target="_blank">
            {{ __('Repository') }}
        </flux:navlist.item>
    </flux:navlist>

    <flux:navlist variant="outline">
        <flux:navlist.group>
            <div class="grid gap-4">
                <div>
                    <livewire:country.chooser/>
                </div>
                <div>
                    <livewire:timezone.chooser/>
                </div>
            </div>
        </flux:navlist.group>
    </flux:navlist>

    <!-- Desktop User Menu -->
    @auth
        <flux:dropdown class="hidden lg:block" position="bottom" align="start">
            <flux:profile
                :name="auth()->user()->name"
                :avatar="auth()->user()->profile_photo_url"
                :initials="auth()->user()->initials()"
                icon:trailing="chevrons-up-down"
            />

            <flux:menu class="w-[220px]">
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar :src="auth()->user()->profile_photo_url" size="sm" class="shrink-0"/>

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <span class="truncate font-semibold">{{ auth()->user()?->name }}</span>
                                <span class="truncate text-xs">
                                        @if(strlen(auth()->user()?->name) > 12)
                                        {{ Str::substr(auth()->user()?->name, 0, 4) }}
                                        ...{{ Str::substr(auth()->user()?->name, -4) }}
                                    @else
                                        {{ auth()->user()?->name }}
                                    @endif
                                    </span>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator/>

                <flux:menu.radio.group>
                    <flux:menu.item
                        :href="route('settings.profile', ['country' => str(session('lang_country', 'de'))->after('-')->lower()])"
                        icon="cog"
                        wire:navigate>{{ __('Settings') }}</flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator/>

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                        {{ __('Log Out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    @endauth
</flux:sidebar>

<!-- Mobile User Menu -->
@auth
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left"/>

        <flux:spacer/>

        <flux:navlist variant="outline" class="mr-6">
            <flux:navlist.group class="grid">
                <livewire:country.chooser/>
            </flux:navlist.group>
        </flux:navlist>

        <flux:dropdown position="top" align="end">
            <flux:profile
                :avatar="auth()->user()->profile_photo_url"
                :initials="auth()->user()->initials()"
                icon-trailing="chevron-down"
            />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar :src="auth()->user()->profile_photo_url" size="sm" class="shrink-0"/>

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <span class="truncate font-semibold">{{ auth()->user()?->name }}</span>
                                <span class="truncate text-xs">
                                        @if(strlen(auth()->user()?->name) > 12)
                                        {{ Str::substr(auth()->user()?->name, 0, 4) }}
                                        ...{{ Str::substr(auth()->user()?->name, -4) }}
                                    @else
                                        {{ auth()->user()?->name }}
                                    @endif
                                    </span>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator/>

                <flux:menu.radio.group>
                    <flux:menu.item
                        :href="route('settings.profile', ['country' => str(session('lang_country', 'de'))->after('-')->lower()])"
                        icon="cog"
                        wire:navigate>{{ __('Settings') }}</flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator/>

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                        {{ __('Log Out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>
@endauth

{{ $slot }}

@fluxScripts

<script>
    if (!localStorage.getItem('flux.appearance')) {
        localStorage.setItem('flux.appearance', 'dark');
    }
    document.addEventListener('alpine:init', () => {
        Alpine.directive('copy-to-clipboard', (el, {expression}, {evaluate}) => {
            el.addEventListener('click', () => {
                const text = evaluate(expression);
                console.log(text);

                navigator.clipboard.writeText(text).then(() => {
                    Flux.toast({
                        heading: '{{ __('Success!') }}',
                        text: '{{ __('Copied into clipboard') }}',
                        variant: 'success',
                        duration: 3000
                    });
                }).catch(err => console.error(err));
            });
        });
    });
</script>
<script>
    window.wnjParams = {
        position: 'bottom',
        // The only accepted value is 'bottom', default is top
        accent: 'orange',
        // Supported values: cyan (default), green, purple, red, orange, neutral, stone
        startHidden: false,
        // If the host page has a button that call `getPublicKey` to start a
        // login procedure, the minimized widget can be hidden until connected
        compactMode: false,
        // Show the minimized widget in a compact form
        disableOverflowFix: false,
        // If the host page on mobile has an horizontal scrolling, the floating
        // element/modal are pushed to the extreme right/bottom and exit the
        // viewport. A style is injected in the html/body elements fix this.
        // This option permit to disable this default behavior
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/window.nostr.js/dist/window.nostr.min.js"></script>

</body>
</html>
