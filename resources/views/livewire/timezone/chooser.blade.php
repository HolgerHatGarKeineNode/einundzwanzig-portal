<?php

use Livewire\Component;
use Flux\Flux;

new class extends Component {
    public bool $withRedirect = true;
    public $currentRouteName;
    public $currentRouteParams;
    public string $selectedTimezone = 'UTC';

    public function mount(): void
    {
        $this->currentRouteName = request()->route()->getName();
        $this->currentRouteParams = request()->route()->parameters();
        $this->selectedTimezone = config('app.timezone', 'UTC');
    }

    public function updatedSelectedTimezone()
    {
        // Handle timezone change here
        // You can emit an event or update user settings
        auth()->user()->update([
            'timezone' => $this->selectedTimezone,
        ]);
        Flux::toast(text: __('Zeitzone erfolgreich aktualisiert'), heading: __('Zeitzone'), variant: 'success');
        if ($this->withRedirect) {
            $this->redirectRoute($this->currentRouteName, $this->currentRouteParams, navigate: true);
        }
    }

    public function with(): array
    {
        return [
            'timezones' => \DateTimeZone::listIdentifiers(),
        ];
    }
}; ?>

<div>
    <flux:select variant="listbox" searchable placeholder="{{ __('Wähle deine Zeitzone...') }}"
                 wire:model.live.debounce="selectedTimezone">
        <x-slot name="search">
            <flux:select.search class="px-4" placeholder="{{ __('Suche Zeitzone...') }}"/>
        </x-slot>
        @foreach($timezones as $timezone)
            <flux:select.option wire:key="timezone-{{ $timezone }}" value="{{ $timezone }}">
                {{ $timezone }}
            </flux:select.option>
        @endforeach
    </flux:select>
</div>
