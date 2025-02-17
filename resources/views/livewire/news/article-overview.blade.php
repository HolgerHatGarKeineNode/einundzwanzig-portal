<div class="bg-21gray flex flex-col h-screen justify-between">
    @push('feeds')
        <x-feed-links />
    @endpush
    <livewire:frontend.header :country="null"/>
    <div class="relative bg-21gray px-6 pt-2 pb-20 lg:px-8 lg:pt-2 lg:pb-2">
        <div class="absolute inset-0">
            <div class="h-1/3 bg-21gray sm:h-2/3"></div>
        </div>
        <div class="relative mx-auto max-w-7xl">
            <div class="flex flex-row justify-center items-center space-x-2">
                <div>
                    <img class="h-32 object-cover" src="{{ asset('img/einundzwanzig-news-colored.png') }}" alt="">
                </div>
                <div>
                    @auth
                        <x-button
                            :href="route('news.form')"
                            primary>
                            <i class="fa fa-thin fa-plus"></i>
                            {{ __('Submit news articles') }}
                        </x-button>
                    @endauth
                </div>
            </div>
            <div class="mx-auto mt-2 grid max-w-lg gap-5 lg:max-w-none lg:grid-cols-3">

                @foreach($libraryItems as $libraryItem)
                    @if($libraryItem->approved || $libraryItem->created_by === auth()->id() || auth()->user()?->hasRole('news-editor'))
                        <div wire:key="library_item_{{ $libraryItem->id }}"
                             class="flex flex-col overflow-hidden rounded-lg  border-2 border-[#F7931A]">
                            <div class="flex-shrink-0 pt-6">
                                <a href="{{ route('article.view', ['libraryItem' => $libraryItem]) }}">
                                    <img class="h-48 w-full object-contain"
                                         src="{{ $libraryItem->getFirstMediaUrl('main') }}"
                                         alt="{{ $libraryItem->name }}">
                                </a>
                            </div>
                            <div class="flex flex-1 flex-col justify-between bg-21gray p-6">
                                <div class="flex-1">
                                    <div class="text-sm font-medium text-amber-600">
                                        <div
                                            class="text-amber-500">{{ $libraryItem->tags->pluck('name')->join(', ') }}</div>
                                    </div>
                                    <a href="{{ route('article.view', ['libraryItem' => $libraryItem]) }}"
                                       class="mt-2 block">
                                        <p class="text-xl font-semibold text-gray-200">{{ $libraryItem->name }}</p>
                                        <p class="mt-3 text-base text-gray-300 line-clamp-6">{{ strip_tags($libraryItem->excerpt) }}</p>
                                    </a>
                                </div>
                                <div class="mt-6 flex items-center w-full">
                                    <div class="flex-shrink-0">
                                        <div>
                                            <span
                                                class="sr-only text-gray-200">{{ $libraryItem->lecturer->name }}</span>
                                            <img class="h-10 w-10 object-cover rounded"
                                                 src="{{ $libraryItem->lecturer->getFirstMediaUrl('avatar') }}"
                                                 alt="{{ $libraryItem->lecturer->name }}">
                                        </div>
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-200">
                                            <div class="text-gray-200">{{ $libraryItem->lecturer->name }}</div>
                                        </div>
                                        <div class="flex space-x-1 text-sm text-gray-500">
                                            <time
                                                datetime="2020-03-16">{{ $libraryItem->created_at->asDateTime() }}</time>
                                            @if($libraryItem->read_time)
                                                <span aria-hidden="true">&middot;</span>
                                                <span>{{ $libraryItem->read_time }} {{ __('min read') }}</span>
                                            @endif
                                        </div>
                                        <div
                                            class="flex space-x-1 text-sm text-gray-500 justify-end items-end">
                                            @if($libraryItem->created_by === auth()->id() || auth()->user()?->hasRole('news-editor'))
                                                <div>
                                                    @if($libraryItem->approved)
                                                        <div>
                                                            <x-badge green>{{ __('approved') }}</x-badge>
                                                        </div>
                                                    @else
                                                        <div>
                                                            <x-badge negative>{{ __('not approved') }}</x-badge>
                                                        </div>
                                                    @endif
                                                </div>
                                                {{--<div>
                                                    @if($libraryItem->approved && auth()->user()?->hasRole('news-editor') && !$libraryItem->tweet)
                                                        <div x-data="{}">
                                                            <x-button xs
                                                                      x-on:click="$wireui.confirmDialog({
                                                                            icon: 'question',
                                                                            title: '{{ __('Are you sure you want to tweet this article?')}}',
                                                                            accept: {label: '{{ __('Yes') }}',
                                                                            execute: () => $wire.tweet({{ $libraryItem->id }})},
                                                                            reject: {label: '{{ __('No, cancel') }}'},
                                                                            })"
                                                            >
                                                                <i class="fa fa-brand fa-twitter"></i>
                                                                {{ __('Tweet') }}
                                                            </x-button>
                                                        </div>
                                                    @endif
                                                </div>--}}
                                                <div>
                                                    @if(!$libraryItem->approved && auth()->user()?->hasRole('news-editor'))
                                                        <x-button
                                                            xs
                                                            wire:click="approve({{ $libraryItem->id }})"
                                                        >
                                                            <i class="fa fa-thin fa-check"></i>
                                                            {{ __('Approve') }}
                                                        </x-button>
                                                    @endif
                                                </div>
                                                <div>
                                                    <x-button xs
                                                              :href="route('news.form', ['libraryItem' => $libraryItem])">
                                                        <i class="fa fa-thin fa-edit"></i>
                                                        {{ __('Edit') }}
                                                    </x-button>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach

            </div>
        </div>
    </div>
    {{-- FOOTER --}}
    <livewire:frontend.footer/>
</div>
