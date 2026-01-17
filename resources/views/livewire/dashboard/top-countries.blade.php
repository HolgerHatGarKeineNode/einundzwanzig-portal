<?php

use Livewire\Attributes\Lazy;
use Livewire\Component;

new
#[Lazy]
class extends Component {
    public function with(): array
    {
        // Top Countries - Länder mit den meisten Usern
        $topCountries = \App\Models\Country::select('countries.*')
            ->join('cities', 'cities.country_id', '=', 'countries.id')
            ->join('meetups', 'meetups.city_id', '=', 'cities.id')
            ->join('meetup_user', 'meetup_user.meetup_id', '=', 'meetups.id')
            ->groupBy('countries.id')
            ->selectRaw('COUNT(DISTINCT meetup_user.user_id) as user_count')
            ->orderBy('user_count', 'desc')
            ->limit(15)
            ->get()
            ->map(function ($country) {
                // Optimierte Query: Hole alle User-Erstellungsdaten für dieses Land auf einmal
                $userCreationDates = \DB::table('users')
                    ->join('meetup_user', 'users.id', '=', 'meetup_user.user_id')
                    ->join('meetups', 'meetup_user.meetup_id', '=', 'meetups.id')
                    ->join('cities', 'meetups.city_id', '=', 'cities.id')
                    ->where('cities.country_id', $country->id)
                    ->whereNotNull('users.created_at')
                    ->orderBy('users.created_at')
                    ->pluck('users.created_at')
                    ->unique()
                    ->values();

                if ($userCreationDates->isEmpty()) {
                    $country->sparkline = [0];
                    return $country;
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

                $country->sparkline = $sparklineData;
                return $country;
            });

        return [
            'topCountries' => $topCountries,
        ];
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="relative overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="p-6">
                <flux:heading size="lg" class="mb-4">{{ __('Top Länder') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500 mb-4">{{ __('Länder mit den meisten Usern') }}</flux:text>
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
        <flux:heading size="lg" class="mb-4">{{ __('Top Länder') }}</flux:heading>
        <flux:text class="text-sm text-zinc-500 mb-4">{{ __('Länder mit den meisten Usern') }}</flux:text>

        @if($topCountries->count() > 0)
            <flux:separator class="my-4"/>
            <div class="space-y-3">
                @foreach($topCountries as $country)
                    <div
                        class="flex items-center justify-between gap-3 p-2 hover:bg-zinc-50 dark:hover:bg-zinc-800 rounded-lg transition-colors">
                        <a href="{{ route('meetups.map', ['country' => $country->code]) }}">
                            <div class="flex items-center gap-3 flex-1">
                                <img alt="{{ $country->code }}"
                                     src="{{ asset('vendor/blade-flags/country-'.$country->code.'.svg') }}"
                                     width="24" height="12"/>
                                <div class="flex-1">
                                    <div class="font-medium">{{ $country->name }}</div>
                                    <div class="text-xs text-zinc-500">{{ $country->user_count }} {{ __('User') }}</div>
                                </div>
                            </div>
                        </a>
                        @if(count($country->sparkline) > 1)
                            <flux:chart :value="$country->sparkline" class="w-[5rem] aspect-[3/1]">
                                <flux:chart.svg gutter="0">
                                    <flux:chart.line class="text-green-500 dark:text-green-400"/>
                                </flux:chart.svg>
                            </flux:chart>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-sm text-zinc-500">{{ __('Keine Daten verfügbar') }}</div>
        @endif
    </div>
</div>
