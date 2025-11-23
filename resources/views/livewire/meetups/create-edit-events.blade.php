<?php

use App\Attributes\SeoDataAttribute;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Traits\SeoTrait;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new
#[SeoDataAttribute(key: 'meetups_create_edit_events')]
class extends Component {
    use SeoTrait;

    public Meetup $meetup;
    public ?MeetupEvent $event = null;

    public $country = 'de';

    public string $startDate = '';
    public string $startTime = '';

    #[Validate('required|string|max:255')]
    public ?string $location = null;

    #[Validate('required|string')]
    public ?string $description = null;

    #[Validate('required|url|max:255')]
    public ?string $link = null;

    public function mount(): void
    {
        $this->country = request()->route('country');
        $timezone = auth()->user()->timezone ?? 'Europe/Berlin';

        if ($this->event) {
            $localStart = $this->event->start->setTimezone($timezone);
            $this->startDate = $localStart->format('Y-m-d');
            $this->startTime = $localStart->format('H:i');
            $this->location = $this->event->location;
            $this->description = $this->event->description;
            $this->link = $this->event->link;
        } else {
            // Set default start time to next Monday at 19:00 in user's timezone
            $defaultStart = now($timezone)->next('Monday')->setTime(19, 0);
            $this->startDate = $defaultStart->format('Y-m-d');
            $this->startTime = $defaultStart->format('H:i');
        }
    }

    public function save(): void
    {
        $this->validate([
            'startDate' => 'required|date',
            'startTime' => 'required',
            'location' => 'required|string|max:255',
            'description' => 'required|string',
            'link' => 'required|url|max:255',
        ]);

        $timezone = auth()->user()->timezone ?? 'Europe/Berlin';

        // Combine date and time in user's timezone, then convert to UTC
        $localDateTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $this->startDate . ' ' . $this->startTime, $timezone);
        $utcDateTime = $localDateTime->setTimezone('UTC');

        $data = [
            'start' => $utcDateTime,
            'location' => $this->location,
            'description' => $this->description,
            'link' => $this->link,
        ];

        if ($this->event) {
            // Update existing event
            $this->event->update($data);
            session()->flash('status', __('Event erfolgreich aktualisiert!'));
        } else {
            // Create new event
            $this->meetup->meetupEvents()->create([
                ...$data,
                'created_by' => auth()->id(),
                'attendees' => [],
                'might_attendees' => [],
            ]);
            session()->flash('status', __('Event erfolgreich erstellt!'));
        }

        $this->redirect(route('meetups.landingpage', ['meetup' => $this->meetup, 'country' => $this->country]),
            navigate: true);
    }

    public function delete(): void
    {
        if ($this->event) {
            $this->event->delete();
            session()->flash('status', __('Event erfolgreich gelöscht!'));
            $this->redirect(route('meetups.landingpage', ['meetup' => $this->meetup, 'country' => $this->country]),
                navigate: true);
        }
    }
}; ?>

<div class="max-w-4xl mx-auto p-6">
    <flux:heading size="xl" class="mb-8">
        {{ $event ? __('Event bearbeiten') : __('Neues Event erstellen') }}: {{ $meetup->name }}
    </flux:heading>

    <form wire:submit="save" class="space-y-10">

        <!-- Event Details -->
        <flux:fieldset class="space-y-6">
            <flux:legend>{{ __('Event Details') }}</flux:legend>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('Datum') }} <span class="text-red-500">*</span></flux:label>
                    <flux:date-picker min="today" wire:model="startDate" required locale="{{ session('lang_country', 'de-DE') }}"/>
                    <flux:description>{{ __('An welchem Tag findet das Event statt?') }}</flux:description>
                    <flux:error name="startDate"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Uhrzeit') }} <span class="text-red-500">*</span></flux:label>
                    <flux:time-picker wire:model="startTime" required locale="{{ session('lang_country', 'de-DE') }}"/>
                    <flux:description>{{ __('Um wie viel Uhr startet das Event?') }} ({{ auth()->user()->timezone ?? 'Europe/Berlin' }})</flux:description>
                    <flux:error name="startTime"/>
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Ort') }}</flux:label>
                <flux:input wire:model="location" placeholder="{{ __('z.B. Café Mustermann, Hauptstr. 1') }}"/>
                <flux:description>{{ __('Wo findet das Event statt?') }}</flux:description>
                <flux:error name="location"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Beschreibung') }}</flux:label>
                <flux:textarea wire:model="description" rows="6" placeholder="{{ __('Beschreibe das Event...') }}"/>
                <flux:description>{{ __('Details über das Event') }}</flux:description>
                <flux:error name="description"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Link') }}</flux:label>
                <flux:input wire:model="link" type="url" placeholder="https://example.com"/>
                <flux:description>{{ __('Link zu weiteren Informationen') }}</flux:description>
                <flux:error name="link"/>
            </flux:field>
        </flux:fieldset>

        <!-- Form Actions -->
        <div class="flex items-center justify-between pt-8 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-4">
                <flux:button variant="ghost" type="button"
                             :href="route_with_country('meetups.edit', ['meetup' => $meetup])">
                    {{ __('Abbrechen') }}
                </flux:button>

                @if($event)
                    <flux:button variant="danger" type="button" wire:click="delete"
                                 wire:confirm="{{ __('Bist du sicher, dass du dieses Event löschen möchtest?') }}">
                        {{ __('Event löschen') }}
                    </flux:button>
                @endif
            </div>

            <div class="flex items-center gap-4">
                @if (session('status'))
                    <flux:text class="text-green-600 dark:text-green-400 font-medium">
                        {{ session('status') }}
                    </flux:text>
                @endif

                <flux:button variant="primary" type="submit">
                    {{ $event ? __('Event aktualisieren') : __('Event erstellen') }}
                </flux:button>
            </div>
        </div>
    </form>
</div>
