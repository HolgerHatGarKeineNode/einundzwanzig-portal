<x-layouts.app.sidebar :title="$title ?? null">
    <flux:main>
        <livewire:meetup-privacy-hint-banner/>
        {{ $slot }}
    </flux:main>
</x-layouts.app.sidebar>
