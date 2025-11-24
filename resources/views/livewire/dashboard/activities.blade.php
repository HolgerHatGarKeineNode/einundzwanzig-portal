<?php

use App\Models\Meetup;
use App\Models\MeetupEvent;
use Livewire\Attributes\Lazy;
use Livewire\Volt\Component;

new
#[Lazy]
class extends Component {
    public function with(): array
    {
        // Recent Activities - Neue Meetups und Events
        $recentMeetups = Meetup::with(['city.country'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentEvents = MeetupEvent::with(['meetup.city.country'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Kombiniere und sortiere Activities
        $activities = collect($recentMeetups->map(fn($m) => ['type' => 'meetup', 'data' => $m, 'created_at' => $m->created_at]))
            ->merge($recentEvents->map(fn($e) => ['type' => 'event', 'data' => $e, 'created_at' => $e->created_at]))
            ->sortByDesc('created_at')
            ->take(10);

        return [
            'activities' => $activities,
        ];
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="relative overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="p-6">
                <flux:heading size="lg" class="mb-4">{{ __('Aktivitäten') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500 mb-4">{{ __('Neue Meetups und Termine') }}</flux:text>
                <flux:separator class="my-4"/>
                <div class="flex items-center justify-center py-8">
                    <flux:icon.arrow-path class="animate-spin size-6 text-zinc-400" />
                </div>
            </div>
        </div>
        HTML;
    }
}; ?>

<div class="relative overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
    <div class="p-6">
        <flux:heading size="lg" class="mb-4">{{ __('Aktivitäten') }}</flux:heading>
        <flux:text class="text-sm text-zinc-500 mb-4">{{ __('Neue Meetups und Termine') }}</flux:text>

        @if($activities->count() > 0)
            <flux:separator class="my-4"/>
            <div class="space-y-3">
                @foreach($activities as $activity)
                    @if($activity['type'] === 'meetup')
                        @php $meetup = $activity['data']; @endphp
                        <a href="{{ route('meetups.landingpage', ['meetup' => $meetup, 'country' => $meetup->city->country->code]) }}"
                           class="block p-2 hover:bg-zinc-50 dark:hover:bg-zinc-800 rounded-lg transition-colors">
                            <div class="flex items-start gap-3">
                                <flux:avatar
                                    size="sm"
                                    src="{{ $meetup->getFirstMedia('logo') ? $meetup->getFirstMediaUrl('logo', 'thumb') : asset('android-chrome-512x512.png') }}"/>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <flux:badge color="green" size="sm">{{ __('Neues Meetup') }}</flux:badge>
                                        <span class="text-2xl">{{ $meetup->city->country->emoji }}</span>
                                    </div>
                                    <div class="font-medium mt-1">{{ $meetup->name }}</div>
                                    <div class="text-xs text-zinc-500">
                                        {{ $meetup->city->name }}, {{ $meetup->city->country->name }}
                                    </div>
                                    <div class="text-xs text-zinc-400 mt-1">
                                        {{ $activity['created_at']->diffForHumans() }}
                                    </div>
                                </div>
                            </div>
                        </a>
                    @else
                        @php $event = $activity['data']; @endphp
                        <a href="{{ route('meetups.landingpage-event', ['meetup' => $event->meetup->slug, 'event' => $event->id, 'country' => $event->meetup->city->country->code]) }}"
                           class="block p-2 hover:bg-zinc-50 dark:hover:bg-zinc-800 rounded-lg transition-colors">
                            <div class="flex items-start gap-3">
                                <flux:avatar
                                    size="sm"
                                    src="{{ $event->meetup->getFirstMedia('logo') ? $event->meetup->getFirstMediaUrl('logo', 'thumb') : asset('android-chrome-512x512.png') }}"/>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <flux:badge color="blue" size="sm">{{ __('Neuer Termin') }}</flux:badge>
                                        <span class="text-2xl">{{ $event->meetup->city->country->emoji }}</span>
                                    </div>
                                    <div class="font-medium mt-1">{{ $event->meetup->name }}</div>
                                    <div class="text-xs text-zinc-500">
                                        {{ $event->start->asDateTime() }}
                                    </div>
                                    <div class="text-xs text-zinc-400 mt-1">
                                        {{ $activity['created_at']->diffForHumans() }}
                                    </div>
                                </div>
                            </div>
                        </a>
                    @endif
                @endforeach
            </div>
        @else
            <div class="text-sm text-zinc-500">{{ __('Keine Aktivitäten') }}</div>
        @endif
    </div>
</div>
