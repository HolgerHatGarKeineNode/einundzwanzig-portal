<?php

use App\Attributes\SeoDataAttribute;
use App\Models\Lecturer;
use App\Traits\SeoTrait;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[SeoDataAttribute(key: 'lecturers_edit')]
class extends Component {
    use WithFileUploads;
    use SeoTrait;

    #[Validate('image|mimes:jpeg,png,webp,avif|max:5120|dimensions:max_width=4000,max_height=4000')]
    public $avatar;

    public Lecturer $lecturer;

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

    // System fields (read-only) - locked to prevent client-side tampering
    #[Locked]
    public ?int $created_by = null;

    #[Locked]
    public ?string $created_at = null;

    #[Locked]
    public ?string $updated_at = null;

    /**
     * Bearbeiten darf der Ersteller — oder ein Super-Admin (`LecturerPolicy::update()`).
     *
     * Die Inline-Bedingung, die hier stand, kannte den Super-Admin nicht: die Ausnahme
     * lag seit jeher in `ChecksCreatorOwnership::owns()`, nur fragte dieses Formular sie
     * nie. Zweiter Unterschied: `created_by === null` galt inline als Freigabe fuer jeden
     * Angemeldeten, die Policy antwortet darauf mit „nein" (ausser Super-Admin).
     */
    protected function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can('update', $this->lecturer), 403);
    }

    public function mount(): void
    {
        $this->authorizeAccess();

        $this->lecturer->load('media');

        $this->name = $this->lecturer->name ?? '';
        $this->subtitle = $this->lecturer->subtitle;
        $this->intro = $this->lecturer->intro;
        $this->description = $this->lecturer->description;
        $this->active = (bool) $this->lecturer->active;

        $this->website = $this->lecturer->website;
        $this->twitter_username = $this->lecturer->twitter_username;
        $this->nostr = $this->lecturer->nostr;
        $this->lightning_address = $this->lecturer->lightning_address;
        $this->lnurl = $this->lecturer->lnurl;
        $this->node_id = $this->lecturer->node_id;
        $this->paynym = $this->lecturer->paynym;

        $this->created_by = $this->lecturer->created_by;
        $this->created_at = $this->lecturer->created_at?->format('Y-m-d H:i:s');
        $this->updated_at = $this->lecturer->updated_at?->format('Y-m-d H:i:s');
    }

    public function updateLecturer(): void
    {
        $this->authorizeAccess();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('lecturers')->ignore($this->lecturer->id)],
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

        $this->lecturer->update($validated);

        if ($this->avatar) {
            $this->lecturer->clearMediaCollection('avatar');
            $this->lecturer
                ->addMedia($this->avatar->getRealPath())
                ->usingName($this->lecturer->name)
                ->toMediaCollection('avatar');
            $this->avatar = null;
            $this->lecturer->load('media');
        }

        $this->dispatch('lecturer-updated', name: $this->lecturer->name);

        session()->flash('status', __('Dozent erfolgreich aktualisiert!'));
    }
}; ?>

<div class="max-w-4xl mx-auto p-6">
    <flux:heading size="xl" class="mb-8">{{ __('Dozent bearbeiten') }}: {{ $lecturer->name }}</flux:heading>

    <form wire:submit="updateLecturer" class="space-y-10">

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
                        @if (!$avatar && $lecturer->getFirstMedia('avatar'))
                            <img src="{{ $lecturer->getFirstMediaUrl('avatar') }}" alt="Avatar"
                                 class="size-full object-cover rounded-full"/>
                        @elseif($avatar?->isPreviewable())
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
                    <flux:label>{{ __('ID') }}</flux:label>
                    <flux:input value="{{ $lecturer->id }}" disabled/>
                    <flux:description>{{ __('System-generierte ID (nur lesbar)') }}</flux:description>
                </flux:field>

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

        <!-- System Information -->
        <flux:fieldset class="space-y-6">
            <flux:legend>{{ __('Systeminformationen') }}</flux:legend>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <flux:field>
                    <flux:label>{{ __('Erstellt von') }}</flux:label>
                    <flux:input value="{{ $lecturer->createdBy?->name ?? __('Unbekannt') }}" disabled/>
                    <flux:description>{{ __('Ersteller des Dozenten') }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Erstellt am') }}</flux:label>
                    <flux:input value="{{ $created_at }}" disabled/>
                    <flux:description>{{ __('Wann dieser Dozent erstellt wurde') }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Aktualisiert am') }}</flux:label>
                    <flux:input value="{{ $updated_at }}" disabled/>
                    <flux:description>{{ __('Letzte Änderungszeit') }}</flux:description>
                </flux:field>
            </div>
        </flux:fieldset>

        <!-- Form Actions -->
        <div class="flex items-center justify-between pt-8 border-t border-gray-200 dark:border-gray-700">
            <flux:button class="cursor-pointer" variant="ghost" type="button" onclick="history.back()">
                {{ __('Abbrechen') }}
            </flux:button>

            <div class="flex items-center gap-4">
                @if (session('status'))
                    <flux:text class="text-green-600 dark:text-green-400 font-medium">
                        {{ session('status') }}
                    </flux:text>
                @endif

                <flux:button class="cursor-pointer" variant="primary" type="submit">
                    {{ __('Dozent aktualisieren') }}
                </flux:button>
            </div>
        </div>
    </form>
</div>
