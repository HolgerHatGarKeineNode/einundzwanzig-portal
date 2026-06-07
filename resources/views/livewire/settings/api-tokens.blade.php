<?php

use App\Attributes\SeoDataAttribute;
use App\Traits\SeoTrait;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;

new
#[SeoDataAttribute(key: 'settings_api_tokens')]
class extends Component {
    use SeoTrait;

    #[Validate('required|string|max:255')]
    public string $name = '';

    /**
     * The plain-text token, shown to the user exactly once after creation.
     */
    public ?string $plainTextToken = null;

    /**
     * Create a new personal access token for the authenticated user.
     */
    public function createToken(): void
    {
        $this->validate();

        $this->plainTextToken = Auth::user()
            ->createToken($this->name)
            ->plainTextToken;

        $this->reset('name');

        $this->dispatch('token-created');
    }

    /**
     * Revoke (delete) one of the authenticated user's tokens.
     */
    public function deleteToken(int $tokenId): void
    {
        Auth::user()->tokens()->whereKey($tokenId)->delete();

        $this->dispatch('token-deleted');
    }

    /**
     * Dismiss the one-time plain-text token display.
     */
    public function dismissPlainTextToken(): void
    {
        $this->plainTextToken = null;
    }

    public function with(): array
    {
        return [
            'tokens' => Auth::user()->tokens()->latest()->get(),
        ];
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('API Tokens')"
                       :subheading="__('Erstelle persönliche Zugriffstokens, um über die API auf dein Konto zuzugreifen.')">

        <div class="space-y-8">
            <flux:text>
                {{ __('Mit einem persönlichen Zugriffstoken kannst du deine Kurse und Kurs-Events programmatisch über die API verwalten (z. B. zum Synchronisieren aus einem externen System). Sende das Token als Bearer-Token im :header-Header.', ['header' => 'Authorization']) }}
            </flux:text>

            {{-- One-time token reveal --}}
            @if ($plainTextToken)
                <flux:callout variant="success" icon="key" x-data="{ copied: false }">
                    <flux:callout.heading>{{ __('Dein neues API Token') }}</flux:callout.heading>
                    <flux:callout.text>
                        <p class="mb-3">
                            {{ __('Kopiere dein Token jetzt. Aus Sicherheitsgründen wird es dir nur dieses eine Mal angezeigt.') }}
                        </p>
                        <div class="flex items-center gap-2">
                            <flux:input x-ref="token" readonly value="{{ $plainTextToken }}" class="font-mono" />
                            <flux:button type="button" icon="clipboard-document"
                                         x-on:click="navigator.clipboard.writeText($refs.token.value); copied = true; setTimeout(() => copied = false, 2000)">
                                <span x-show="!copied">{{ __('Kopieren') }}</span>
                                <span x-show="copied" x-cloak>{{ __('Kopiert!') }}</span>
                            </flux:button>
                        </div>
                    </flux:callout.text>
                    <x-slot name="actions">
                        <flux:button variant="ghost" size="sm" wire:click="dismissPlainTextToken">
                            {{ __('Verstanden') }}
                        </flux:button>
                    </x-slot>
                </flux:callout>
            @endif

            {{-- Create token form --}}
            <form wire:submit="createToken" class="space-y-4">
                <flux:input wire:model="name"
                            :label="__('Token-Name')"
                            :placeholder="__('z. B. Externer Kurs-Sync')"
                            :description="__('Ein aussagekräftiger Name hilft dir, das Token später wiederzuerkennen.')" />

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit" icon="plus">
                        {{ __('Token erstellen') }}
                    </flux:button>
                    <x-action-message on="token-created">
                        {{ __('Token erstellt.') }}
                    </x-action-message>
                </div>
            </form>

            <flux:separator />

            {{-- Existing tokens --}}
            <div>
                <flux:heading size="lg" class="mb-4">{{ __('Aktive Tokens') }}</flux:heading>

                @if ($tokens->isEmpty())
                    <flux:text class="text-zinc-500 dark:text-zinc-400">
                        {{ __('Du hast noch keine API Tokens erstellt.') }}
                    </flux:text>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Name') }}</flux:table.column>
                            <flux:table.column>{{ __('Zuletzt verwendet') }}</flux:table.column>
                            <flux:table.column>{{ __('Erstellt') }}</flux:table.column>
                            <flux:table.column />
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($tokens as $token)
                                <flux:table.row wire:key="token-{{ $token->id }}">
                                    <flux:table.cell variant="strong">{{ $token->name }}</flux:table.cell>
                                    <flux:table.cell>
                                        @if ($token->last_used_at)
                                            {{ $token->last_used_at->diffForHumans() }}
                                        @else
                                            <flux:badge size="sm" color="zinc">{{ __('Nie') }}</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $token->created_at->format('d.m.Y') }}</flux:table.cell>
                                    <flux:table.cell align="end">
                                        <flux:tooltip :content="__('Widerrufen')">
                                            <flux:button variant="danger" size="sm" icon="trash"
                                                         :aria-label="__('Token widerrufen')"
                                                         wire:click="deleteToken({{ $token->id }})"
                                                         wire:confirm="{{ __('Token „:name“ wirklich widerrufen? Anwendungen, die es nutzen, verlieren den Zugriff.', ['name' => $token->name]) }}" />
                                        </flux:tooltip>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </div>
        </div>
    </x-settings.layout>
</section>
