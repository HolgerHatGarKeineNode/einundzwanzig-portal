<?php

use App\Attributes\SeoDataAttribute;
use App\Enums\SelfHostedServiceType;
use App\Livewire\Forms\ServiceForm;
use App\Traits\SeoTrait;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new
#[SeoDataAttribute(key: 'services_create')]
class extends Component {
    use SeoTrait;

    public string $country = 'de';
    public ServiceForm $form;

    public function mount(): void
    {
        $this->country = request()->route('country', config('app.domain_country'));
    }

    public function save(): void
    {
        $service = $this->form->store();

        session()->flash('status', __('Service erfolgreich erstellt!'));

        redirect()->route('services.index', ['country' => $this->country]);
    }

    public function with(): array
    {
        return [
            'types' => collect(SelfHostedServiceType::cases())->map(fn($c) => [
                'value' => $c->value, 'label' => $c->label()
            ]),
        ];
    }
}; ?>

<div class="max-w-4xl mx-auto p-6">
    <flux:heading size="xl" class="mb-8">{{ __('Service anlegen') }}</flux:heading>

    <form wire:submit="save" class="space-y-10">

        <!-- Basic Information -->
        <flux:fieldset class="space-y-6">
            <flux:legend>{{ __('Grundlegende Informationen') }}</flux:legend>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('Name') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="form.name" placeholder="Name" required/>
                    <flux:description>{{ __('Der Name des Services') }}</flux:description>
                    <flux:error name="form.name"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Typ') }} <span class="text-red-500">*</span></flux:label>
                    <flux:select wire:model="form.type" placeholder="{{ __('Bitte wählen') }}" required>
                        <flux:select.option :value="null">—</flux:select.option>
                        @foreach($types as $t)
                            <flux:select.option value="{{ $t['value'] }}">{{ $t['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:description>{{ __('Art des Services') }}</flux:description>
                    <flux:error name="form.type"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Anonym einstellen') }}</flux:label>
                    <flux:switch wire:model="form.anonymous"/>
                    <flux:description>{{ __('Service ohne Autorenangabe einstellen') }}</flux:description>
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Beschreibung') }}</flux:label>
                <flux:textarea rows="4" wire:model="form.intro"/>
                <flux:description>{{ __('Kurze Beschreibung des Services') }}</flux:description>
                <flux:error name="form.intro"/>
            </flux:field>
        </flux:fieldset>

        <!-- URLs -->
        <flux:fieldset class="space-y-6">
            <flux:legend>{{ __('URLs & Erreichbarkeit') }}</flux:legend>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('URL (Clearnet)') }}</flux:label>
                    <flux:input wire:model="form.url_clearnet" type="url" placeholder="https://..."/>
                    <flux:description>{{ __('Normale Web-URL') }}</flux:description>
                    <flux:error name="form.url_clearnet"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('URL (Onion/Tor)') }}</flux:label>
                    <flux:input wire:model="form.url_onion" placeholder="http://...onion"/>
                    <flux:description>{{ __('Tor Hidden Service URL') }}</flux:description>
                    <flux:error name="form.url_onion"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('URL (I2P)') }}</flux:label>
                    <flux:input wire:model="form.url_i2p" placeholder="..."/>
                    <flux:description>{{ __('I2P Adresse') }}</flux:description>
                    <flux:error name="form.url_i2p"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('URL (pkdns)') }}</flux:label>
                    <flux:input wire:model="form.url_pkdns" placeholder="..."/>
                    <flux:description>{{ __('Pkarr DNS Adresse') }}</flux:description>
                    <flux:error name="form.url_pkdns"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('IP-Adresse') }}</flux:label>
                    <flux:input wire:model="form.ip" placeholder="192.168.1.1"/>
                    <flux:description>{{ __('IP Adresse') }}</flux:description>
                    <flux:error name="form.ip"/>
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Kontaktinformation') }}</flux:label>
                <flux:textarea rows="3" wire:model="form.contact" placeholder="{{ __('Signal: @username, SimpleX: https://..., Email: ...') }}"/>
                <flux:description>{{ __('Beliebige Kontaktinformationen (Signal, SimpleX, Email, etc.)') }}</flux:description>
                <flux:error name="form.contact"/>
            </flux:field>
        </flux:fieldset>

        <!-- Form Actions -->
        <div class="flex items-center justify-between pt-8 border-t border-gray-200 dark:border-gray-700">
            <flux:button class="cursor-pointer" variant="ghost" type="button" onclick="history.back()">
                {{ __('Abbrechen') }}
            </flux:button>

            <flux:button class="cursor-pointer" variant="primary" type="submit">
                {{ __('Service erstellen') }}
            </flux:button>
        </div>
    </form>
</div>
