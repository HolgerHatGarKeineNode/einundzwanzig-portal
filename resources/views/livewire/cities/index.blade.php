<?php

use App\Models\City;
use App\Traits\SeoTrait;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;
    use SeoTrait;

    public $country = 'de';
    public $search = '';

    public function mount(): void
    {
        $this->country = request()->route('country');
    }

    public function with(): array
    {
        return [
            'cities' => City::with(['country', 'createdBy'])
                ->when($this->search, fn($query)
                    => $query->where('name', 'ilike', '%'.$this->search.'%'),
                )
                ->orderBy('name')
                ->paginate(15),
        ];
    }
}; ?>

<div>
    <div class="flex items-center justify-between flex-col md:flex-row mb-6">
        <flux:heading size="xl">{{ __('Cities') }}</flux:heading>
        <div class="flex items-center flex-col md:flex-row gap-4">
            <flux:input
                wire:model.live="search"
                :placeholder="__('Search cities...')"
                clearable
            />
            @auth
                <flux:button class="cursor-pointer" :href="route_with_country('cities.create')" icon="plus"
                             variant="primary">
                    {{ __('Create City') }}
                </flux:button>
            @endauth
        </div>
    </div>

    <flux:table :paginate="$cities" class="mt-6">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Country') }}</flux:table.column>
            <flux:table.column>{{ __('Population') }}</flux:table.column>
            <flux:table.column>{{ __('Created By') }}</flux:table.column>
            <flux:table.column>{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($cities as $city)
                <flux:table.row :key="$city->id">
                    <flux:table.cell variant="strong">
                        {{ $city->name }}
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($city->country)
                            {{ $city->country->name }}
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($city->population)
                            {{ number_format($city->population) }}
                            @if($city->population_date)
                                <span class="text-xs text-zinc-500">({{ $city->population_date }})</span>
                            @endif
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if($city->createdBy)
                            {{ Str::limit($city->createdBy->name, 30) }}
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-2">
                            @auth
                                <flux:button size="xs"
                                             :href="route('cities.edit',['city' => $city, 'country' => $country])"
                                             icon="pencil">
                                    {{ __('Edit') }}
                                </flux:button>
                            @endauth
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
