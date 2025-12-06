<?php

use App\Attributes\SeoDataAttribute;
use App\Enums\SelfHostedServiceType;
use App\Models\SelfHostedService;
use App\Traits\SeoTrait;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new
#[SeoDataAttribute(key: 'services_create')]
class extends Component {
    use WithFileUploads;
    use SeoTrait;

    #[Validate('image|max:10240')] // 10MB
    public $logo;

    public string $name = '';
    public ?string $intro = null;
    public ?string $url_clearnet = null;
    public ?string $url_onion = null;
    public ?string $url_i2p = null;
    public ?string $url_pkdns = null;
    public ?string $type = null;
    public ?string $contact = null;
    public bool $anonymous = false;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => [
                'required', 'in:'.collect(SelfHostedServiceType::cases())->map(fn($c) => $c->value)->implode(',')
            ],
            'intro' => ['nullable', 'string'],
            'url_clearnet' => ['nullable', 'url', 'max:255'],
            'url_onion' => ['nullable', 'string', 'max:255'],
            'url_i2p' => ['nullable', 'string', 'max:255'],
            'url_pkdns' => ['nullable', 'string', 'max:255'],
            'contact' => ['nullable', 'string'],
            'anonymous' => ['boolean'],
        ];
    }

    protected function validateAtLeastOneUrl(): void
    {
        if (empty($this->url_clearnet) && empty($this->url_onion) && empty($this->url_i2p) && empty($this->url_pkdns)) {
            $this->addError('url_clearnet', __('Mindestens eine URL muss angegeben werden.'));
            throw new \Illuminate\Validation\ValidationException(
                validator([], [])
            );
        }
    }

    public function save(): void
    {
        $validated = $this->validate();

        $this->validateAtLeastOneUrl();

        /** @var SelfHostedService $service */
        $service = SelfHostedService::create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'intro' => $validated['intro'] ?? null,
            'url_clearnet' => $validated['url_clearnet'] ?? null,
            'url_onion' => $validated['url_onion'] ?? null,
            'url_i2p' => $validated['url_i2p'] ?? null,
            'url_pkdns' => $validated['url_pkdns'] ?? null,
            'contact' => $validated['contact'] ?? null,
            'created_by' => $this->anonymous ? null : auth()->id(),
        ]);

        if ($this->logo) {
            $service
                ->addMedia($this->logo->getRealPath())
                ->usingFileName($this->logo->getClientOriginalName())
                ->toMediaCollection('logo');
        }

        session()->flash('status', __('Service erfolgreich erstellt!'));

        redirect()->route('services.index', ['country' => request()->route('country')]);
    }

    public function with(): array
    {
        return [
            'types' => collect(SelfHostedServiceType::cases())->map(fn($c) => [
                'value' => $c->value, 'label' => ucfirst($c->value)
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

                <flux:file-upload wire:model="logo">
                    <div class="
                            relative flex items-center justify-center size-20 rounded transition-colors cursor-pointer
                            border border-zinc-200 dark:border-white/10 hover:border-zinc-300 dark:hover:border-white/10
                            bg-zinc-100 hover:bg-zinc-200 dark:bg-white/10 hover:dark:bg-white/15 in-data-dragging:dark:bg-white/15
                        ">
                        @if($logo)
                            <img src="{{ $logo?->temporaryUrl() }}" alt="Logo"
                                 class="size-full object-cover rounded"/>
                        @else
                            <flux:icon name="cube" variant="solid" class="text-zinc-500 dark:text-zinc-400"/>
                        @endif

                        <div class="absolute bottom-0 right-0 bg-white dark:bg-zinc-800 rounded">
                            <flux:icon name="arrow-up-circle" variant="solid" class="text-zinc-500 dark:text-zinc-400"/>
                        </div>
                    </div>
                </flux:file-upload>

                <flux:field>
                    <flux:label>{{ __('Name') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="name" placeholder="Name" required/>
                    <flux:description>{{ __('Der Name des Services') }}</flux:description>
                    <flux:error name="name"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Typ') }} <span class="text-red-500">*</span></flux:label>
                    <flux:select wire:model="type" placeholder="{{ __('Bitte wählen') }}" required>
                        <flux:select.option :value="null">—</flux:select.option>
                        @foreach($types as $t)
                            <flux:select.option value="{{ $t['value'] }}">{{ $t['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:description>{{ __('Art des Services') }}</flux:description>
                    <flux:error name="type"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Anonym einstellen') }}</flux:label>
                    <flux:switch wire:model="anonymous"/>
                    <flux:description>{{ __('Service ohne Autorenangabe einstellen') }}</flux:description>
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Beschreibung') }}</flux:label>
                <flux:textarea rows="4" wire:model="intro"/>
                <flux:description>{{ __('Kurze Beschreibung des Services') }}</flux:description>
                <flux:error name="intro"/>
            </flux:field>
        </flux:fieldset>

        <!-- URLs -->
        <flux:fieldset class="space-y-6">
            <flux:legend>{{ __('URLs & Erreichbarkeit') }}</flux:legend>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('URL (Clearnet)') }}</flux:label>
                    <flux:input wire:model="url_clearnet" type="url" placeholder="https://..."/>
                    <flux:description>{{ __('Normale Web-URL') }}</flux:description>
                    <flux:error name="url_clearnet"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('URL (Onion/Tor)') }}</flux:label>
                    <flux:input wire:model="url_onion" placeholder="http://...onion"/>
                    <flux:description>{{ __('Tor Hidden Service URL') }}</flux:description>
                    <flux:error name="url_onion"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('URL (I2P)') }}</flux:label>
                    <flux:input wire:model="url_i2p" placeholder="..."/>
                    <flux:description>{{ __('I2P Adresse') }}</flux:description>
                    <flux:error name="url_i2p"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('URL (pkdns)') }}</flux:label>
                    <flux:input wire:model="url_pkdns" placeholder="..."/>
                    <flux:description>{{ __('Pkarr DNS Adresse') }}</flux:description>
                    <flux:error name="url_pkdns"/>
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Kontaktinformation') }}</flux:label>
                <flux:textarea rows="3" wire:model="contact"
                               placeholder="{{ __('Signal: username, SimpleX: https://..., Email: ...') }}"/>
                <flux:description>{{ __('Beliebige Kontaktinformationen (Signal, SimpleX, Email, etc.)') }}</flux:description>
                <flux:error name="contact"/>
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
