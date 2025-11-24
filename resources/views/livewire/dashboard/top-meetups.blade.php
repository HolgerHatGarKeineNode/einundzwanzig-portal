<?php

use App\Models\Meetup;
use Livewire\Attributes\Lazy;
use Livewire\Volt\Component;

new
#[Lazy]
class extends Component {
    public function with(): array
    {
        // Top Meetups - Meetups mit den meisten Usern
        $topMeetups = Meetup::withCount('users')
            ->with(['city.country'])
            ->orderBy('users_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($meetup) {
                // Optimierte Query: Hole alle User-Erstellungsdaten für dieses Meetup auf einmal
                $userCreationDates = \DB::table('users')
                    ->join('meetup_user', 'users.id', '=', 'meetup_user.user_id')
                    ->where('meetup_user.meetup_id', $meetup->id)
                    ->whereNotNull('users.created_at')
                    ->orderBy('users.created_at')
                    ->pluck('users.created_at')
                    ->unique()
                    ->values();

                if ($userCreationDates->isEmpty()) {
                    $meetup->sparkline = [0];
                    return $meetup;
                }

                // Berechne monatliche Buckets für kumulative Zählung
                $startDate = \Carbon\Carbon::parse($userCreationDates->first())->startOfMonth();
                $endDate = now()->endOfMonth();
                $monthsDiff = max(1, $startDate->diffInMonths($endDate));
                $interval = max(1, ceil($monthsDiff / 12));

                // Generiere 12 Zeitpunkte
                $sparklineData = [];
                $currentDate = $startDate->copy();

                for ($i = 0; $i < 12 && $currentDate <= $endDate; $i++) {
                    // Zähle kumulative User bis zu diesem Zeitpunkt
                    $count = $userCreationDates->filter(function ($date) use ($currentDate) {
                        return \Carbon\Carbon::parse($date) <= $currentDate;
                    })->count();

                    $sparklineData[] = $count;
                    $currentDate->addMonths($interval);
                }

                $meetup->sparkline = $sparklineData;
                return $meetup;
            });

        return [
            'topMeetups' => $topMeetups,
        ];
    }

    public function placeholder(): string
    {
        return <<<'HTML'
            <div class="relative overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <div class="p-6">
                    <flux:heading size="lg" class="mb-4">{{ __('Top Meetups') }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500 mb-4">{{ __('Meetups mit den meisten Usern') }}</flux:text>
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
        <flux:heading size="lg" class="mb-4">{{ __('Top Meetups') }}</flux:heading>
        <flux:text class="text-sm text-zinc-500 mb-4">{{ __('Meetups mit den meisten Usern') }}</flux:text>

        @if($topMeetups->count() > 0)
            <flux:separator class="my-4"/>
            <div class="space-y-3">
                @foreach($topMeetups as $meetup)
                    <a href="{{ route('meetups.landingpage', ['meetup' => $meetup, 'country' => $meetup->city->country->code]) }}"
                       class="flex items-center justify-between gap-3 p-2 hover:bg-zinc-50 dark:hover:bg-zinc-800 rounded-lg transition-colors block">
                        <div class="flex items-center gap-3 flex-1">
                            <flux:avatar
                                size="sm"
                                src="{{ $meetup->getFirstMedia('logo') ? $meetup->getFirstMediaUrl('logo', 'thumb') : asset('android-chrome-512x512.png') }}"/>
                            <div class="flex-1">
                                <div class="font-medium">{{ $meetup->name }}</div>
                                <div class="text-xs text-zinc-500">{{ $meetup->users_count }} {{ __('User') }}</div>
                            </div>
                        </div>
                        <flux:chart :value="$meetup->sparkline" class="w-[5rem] aspect-[3/1]">
                            <flux:chart.svg gutter="0">
                                <flux:chart.line class="text-green-500 dark:text-green-400"/>
                            </flux:chart.svg>
                        </flux:chart>
                    </a>
                @endforeach
            </div>
        @else
            <div class="text-sm text-zinc-500">{{ __('Keine Daten verfügbar') }}</div>
        @endif
    </div>
</div>
