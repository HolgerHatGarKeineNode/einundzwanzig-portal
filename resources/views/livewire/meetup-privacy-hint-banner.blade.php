<?php

use App\Models\Meetup;
use Livewire\Component;

/**
 * Einmaliger Hinweis-Banner für Meetup-Leader zu den neuen Anmeldungs-/
 * Sichtbarkeits-Einstellungen. Erscheint global im authentifizierten Layout,
 * aber nur für Nutzer, die mindestens ein Meetup führen. Das Wegklicken wird
 * dauerhaft pro Nutzer gespeichert (users.meetup_privacy_hint_dismissed_at).
 */
new class extends Component
{
    public bool $show = false;

    public function mount(): void
    {
        $user = auth()->user();

        $this->show = $user !== null
            && $user->meetup_privacy_hint_dismissed_at === null
            && Meetup::query()->ledBy($user->id)->exists();
    }

    public function dismiss(): void
    {
        auth()->user()?->forceFill(['meetup_privacy_hint_dismissed_at' => now()])->save();

        $this->show = false;
    }
}; ?>

<div>
    @if($show)
        <flux:callout variant="secondary" icon="megaphone" class="mb-6">
            <flux:callout.heading>{{ __('Neu: Anmeldung & Sichtbarkeit pro Meetup') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Du kannst jetzt pro Meetup steuern, ob sich Besucher anmelden können und ob die Teilnehmerliste öffentlich sichtbar ist. Öffne dein Meetup zum Bearbeiten und findest die neue Sektion „Anmeldung & Sichtbarkeit“.') }}
            </flux:callout.text>
            <x-slot name="actions">
                <flux:button wire:click="dismiss" variant="primary" size="sm" icon="check">
                    {{ __('Verstanden') }}
                </flux:button>
            </x-slot>
        </flux:callout>
    @endif
</div>
