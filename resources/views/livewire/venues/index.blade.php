<?php

use App\Attributes\SeoDataAttribute;
use App\Models\Venue;
use App\Traits\SeoTrait;
use Livewire\Component;
use Livewire\WithPagination;

new
#[SeoDataAttribute(key: 'venues_index')]
class extends Component {
    use WithPagination;
    use SeoTrait;

    public $country = 'de';
    public $search = '';

    public function mount(): void
    {
        $this->country = request()->route('country', config('app.domain_country'));
    }

    public function with(): array
    {
        return [
            'venues' => Venue::with(['city.country', 'createdBy'])
                ->when($this->search, fn($query)
                    => $query->whereLike('name', '%'.$this->search.'%'),
                )
                ->whereHas('city.country', fn($query) => $query->where('countries.code', $this->country))
                ->orderBy('name')
                ->paginate(15),
        ];
    }
}; ?>

<div>
    <div class="flex items-center justify-between flex-col md:flex-row mb-6">
        <flux:heading size="xl">{{ __('Venues') }}</flux:heading>
        <div class="flex items-center flex-col md:flex-row gap-4">
            <flux:input
                wire:model.live="search"
                :placeholder="__('Search venues...')"
                clearable
            />
            @auth
                <flux:button class="cursor-pointer" :href="route_with_country('venues.create')" icon="plus"
                             variant="primary">
                    {{ __('Create Venue') }}
                </flux:button>
            @endauth
        </div>
    </div>

    <flux:table :paginate="$venues" class="mt-6">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('City') }}</flux:table.column>
            <flux:table.column>{{ __('Created By') }}</flux:table.column>
            <flux:table.column>{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($venues as $venue)
                <flux:table.row :key="$venue->id">
                    <flux:table.cell variant="strong">
                        {{ $venue->name }}
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($venue->city)
                            {{ $venue->city->name }}
                            @if($venue->city->country)
                                <span class="text-xs text-zinc-500">({{ $venue->city->country->name }})</span>
                            @endif
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($venue->createdBy)
                            {{ Str::limit($venue->createdBy->name, 30) }}
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-2">
                            @if(auth()->check())
                                <flux:button size="xs"
                                             :href="route('venues.edit', ['venue' => $venue, 'country' => $country])"
                                             icon="pencil">
                                    {{ __('Edit') }}
                                </flux:button>
                            @elseif(!auth()->check())
                                <flux:link :href="route('login')">{{ __('Log in') }}</flux:link>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
