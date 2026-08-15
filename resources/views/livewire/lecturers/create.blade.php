<?php

use App\Attributes\SeoDataAttribute;
use App\Models\Lecturer;
use App\Traits\SeoTrait;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[SeoDataAttribute(key: 'lecturers_create')]
class extends Component {
    use WithFileUploads;
    use SeoTrait;

    #[Validate('image|mimes:jpeg,png,webp,avif|max:5120|dimensions:max_width=4000,max_height=4000')]
    public $avatar;

    public string $name = '';
    public ?string $subtitle = null;
    public ?string $intro = null;
    public ?string $description = null;
    public bool $active = true;

    // Social & Payment Links
    public ?string $website = null;
    public ?string $twitter_username = null;
    public ?string $nostr = null;
    public ?string $lightning_address = null;
    public ?string $lnurl = null;
    public ?string $node_id = null;
    public ?string $paynym = null;

    public function createLecturer(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', 'unique:lecturers,name'],
            'subtitle' => ['nullable', 'string'],
            'intro' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'active' => ['boolean'],
            'website' => ['nullable', 'url', 'max:255'],
            'twitter_username' => ['nullable', 'string', 'max:255'],
            'nostr' => ['nullable', 'string', 'max:255'],
            'lightning_address' => ['nullable', 'string'],
            'lnurl' => ['nullable', 'string'],
            'node_id' => ['nullable', 'string', 'max:255'],
            'paynym' => ['nullable', 'string'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,webp,avif', 'max:5120', 'dimensions:max_width=4000,max_height=4000'],
        ]);

        $lecturer = Lecturer::create($validated);

        if ($this->avatar) {
            $lecturer
                ->addMedia($this->avatar->getRealPath())
                ->usingName($lecturer->name)
                ->toMediaCollection('avatar');
        }

        session()->flash('status', __('Dozent erfolgreich erstellt!'));

        $this->redirect(route_with_country('lecturers.edit', ['lecturer' => $lecturer]), navigate: true);
    }
}; ?>

<div class="max-w-4xl mx-auto p-6">
    <flux:heading size="xl" class="mb-8">{{ __('Neuen Dozenten erstellen') }}</flux:heading>

    <form wire:submit="createLecturer" class="space-y-10">

        <!-- Basic Information -->
        <flux:fieldset class="space-y-6">
            <flux:legend>{{ __('Grundlegende Informationen') }}</flux:legend>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <flux:file-upload wire:model="avatar">
                    <!-- Custom avatar uploader -->
                    <div class="
                            relative flex items-center justify-center size-20 rounded-full transition-colors cursor-pointer
                            border border-zinc-200 dark:border-white/10 hover:border-zinc-300 dark:hover:border-white/10
                            bg-zinc-100 hover:bg-zinc-200 dark:bg-white/10 hover:dark:bg-white/15 in-data-dragging:dark:bg-white/15
                        ">
                        @if($avatar?->isPreviewable())
                            <img src="{{ $avatar->temporaryUrl() }}" alt="Avatar"
                                 class="size-full object-cover rounded-full"/>
                        @else
                            <flux:icon name="user" variant="solid" class="text-zinc-500 dark:text-zinc-400"/>
                        @endif

                        <div class="absolute bottom-0 right-0 bg-white dark:bg-zinc-800 rounded-full">
                            <flux:icon name="arrow-up-circle" variant="solid" class="text-zinc-500 dark:text-zinc-400"/>
                        </div>
                    </div>

                    <flux:error name="avatar"/>
                </flux:file-upload>

                <flux:field>
                    <flux:label>{{ __('Name') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="name" required/>
                    <flux:description>{{ __('Vollständiger Name des Dozenten') }}</flux:description>
                    <flux:error name="name"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Untertitel') }}</flux:label>
                    <flux:input wire:model="subtitle"/>
                    <flux:description>{{ __('Kurze Berufsbezeichnung oder Rolle') }}</flux:description>
                    <flux:error name="subtitle"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Status') }}</flux:label>
                    <flux:switch wire:model="active"/>
                    <flux:description>{{ __('Ist dieser Dozent aktiv?') }}</flux:description>
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Einführung') }}</flux:label>
                <flux:textarea wire:model="intro" rows="3"/>
                <flux:description>{{ __('Kurze Vorstellung (wird auf Kurs-Seiten angezeigt)') }}</flux:description>
                <flux:error name="intro"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Beschreibung') }}</flux:label>
                <flux:textarea wire:model="description" rows="6"/>
                <flux:description>{{ __('Ausführliche Beschreibung und Biografie') }}</flux:description>
                <flux:error name="description"/>
            </flux:field>
        </flux:fieldset>

        <!-- Social Links -->
        <flux:fieldset class="space-y-6">
            <flux:legend>{{ __('Links & Soziale Medien') }}</flux:legend>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('Webseite') }}</flux:label>
                    <flux:input wire:model="website" type="url" placeholder="https://example.com"/>
                    <flux:description>{{ __('Persönliche Webseite oder Portfolio') }}</flux:description>
                    <flux:error name="website"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Twitter Benutzername') }}</flux:label>
                    <flux:input wire:model="twitter_username" placeholder="benutzername"/>
                    <flux:description>{{ __('Twitter-Handle ohne @ Symbol') }}</flux:description>
                    <flux:error name="twitter_username"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Nostr') }}</flux:label>
                    <flux:input wire:model="nostr" placeholder="npub..."/>
                    <flux:description>{{ __('Nostr öffentlicher Schlüssel') }}</flux:description>
                    <flux:error name="nostr"/>
                </flux:field>
            </div>
        </flux:fieldset>

        <!-- Payment Information -->
        <flux:fieldset class="space-y-6">
            <flux:legend>{{ __('Zahlungsinformationen') }}</flux:legend>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('Lightning Adresse') }}</flux:label>
                    <flux:input wire:model="lightning_address" placeholder="name@getalby.com"/>
                    <flux:description>{{ __('Lightning-Adresse für Zahlungen') }}</flux:description>
                    <flux:error name="lightning_address"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('LNURL') }}</flux:label>
                    <flux:input wire:model="lnurl"/>
                    <flux:description>{{ __('LNURL für Lightning-Zahlungen') }}</flux:description>
                    <flux:error name="lnurl"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Node ID') }}</flux:label>
                    <flux:input wire:model="node_id"/>
                    <flux:description>{{ __('Lightning Node ID') }}</flux:description>
                    <flux:error name="node_id"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('PayNym') }}</flux:label>
                    <flux:input wire:model="paynym"/>
                    <flux:description>{{ __('PayNym für Bitcoin-Zahlungen') }}</flux:description>
                    <flux:error name="paynym"/>
                </flux:field>
            </div>
        </flux:fieldset>

        <!-- Form Actions -->
        <div class="flex items-center justify-between pt-8 border-t border-gray-200 dark:border-gray-700">
            <flux:button class="cursor-pointer" variant="ghost" type="button" onclick="history.back()">
                {{ __('Abbrechen') }}
            </flux:button>

            <flux:button class="cursor-pointer" variant="primary" type="submit">
                {{ __('Dozenten erstellen') }}
            </flux:button>
        </div>
    </form>
</div>
