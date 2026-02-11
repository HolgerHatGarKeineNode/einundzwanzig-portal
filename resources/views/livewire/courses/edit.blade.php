<?php

use App\Attributes\SeoDataAttribute;
use App\Models\Course;
use App\Models\Lecturer;
use App\Traits\SeoTrait;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[SeoDataAttribute(key: 'courses_edit')]
class extends Component {
    use WithFileUploads;
    use SeoTrait;

    #[Validate('image|max:10240')] // 10MB Max
    public $logo;

    public Course $course;

    // Basic Information
    public string $name = '';
    public ?int $lecturer_id = null;
    public ?string $description = null;

    // System fields (read-only) - locked to prevent client-side tampering
    #[Locked]
    public ?int $created_by = null;

    #[Locked]
    public ?string $created_at = null;

    #[Locked]
    public ?string $updated_at = null;

    public function mount(): void
    {
        $this->course->load('media');

        // Basic Information
        $this->name = $this->course->name ?? '';
        $this->lecturer_id = $this->course->lecturer_id;
        $this->description = $this->course->description;

        // System fields
        $this->created_by = $this->course->created_by;
        $this->created_at = $this->course->created_at?->format('Y-m-d H:i:s');
        $this->updated_at = $this->course->updated_at?->format('Y-m-d H:i:s');
    }

    public function updateCourse(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'lecturer_id' => ['required', 'exists:lecturers,id'],
            'description' => ['nullable', 'string'],
        ]);

        $this->course->update($validated);

        if ($this->logo) {
            $this->course->clearMediaCollection('logo');
            $this->course
                ->addMedia($this->logo->getRealPath())
                ->usingName($this->course->name)
                ->toMediaCollection('logo');
            $this->logo = null;
            $this->course->load('media');
        }

        $this->dispatch('course-updated', name: $this->course->name);

        session()->flash('status', __('Kurs erfolgreich aktualisiert!'));
    }

    public function with(): array
    {
        return [
            'lecturers' => Lecturer::query()->orderBy('name')->get(),
        ];
    }
}; ?>

<div class="max-w-4xl mx-auto p-6">
    <flux:heading size="xl" class="mb-8">{{ __('Kurs bearbeiten') }}: {{ $course->name }}</flux:heading>

    <form wire:submit="updateCourse" class="space-y-10">

        <!-- Basic Information -->
        <flux:fieldset class="space-y-6">
            <flux:legend>{{ __('Grundlegende Informationen') }}</flux:legend>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <flux:file-upload wire:model="logo">
                    <!-- Custom logo uploader -->
                    <div class="
                            relative flex items-center justify-center size-20 rounded-full transition-colors cursor-pointer
                            border border-zinc-200 dark:border-white/10 hover:border-zinc-300 dark:hover:border-white/10
                            bg-zinc-100 hover:bg-zinc-200 dark:bg-white/10 hover:dark:bg-white/15 in-data-dragging:dark:bg-white/15
                        ">
                        <!-- Show the uploaded file if it exists -->
                        @if (!$logo && $course->getFirstMedia('logo'))
                            <img src="{{ $course->getFirstMediaUrl('logo') }}" alt="Logo"
                                 class="size-full object-cover rounded"/>
                        @elseif($logo)
                            <img src="{{ $logo?->temporaryUrl() }}" alt="Logo"
                                 class="size-full object-cover rounded"/>
                        @else
                            <!-- Show the default icon if no file is uploaded -->
                            <flux:icon name="academic-cap" variant="solid" class="text-zinc-500 dark:text-zinc-400"/>
                        @endif

                        <!-- Corner upload icon -->
                        <div class="absolute bottom-0 right-0 bg-white dark:bg-zinc-800 rounded">
                            <flux:icon name="arrow-up-circle" variant="solid" class="text-zinc-500 dark:text-zinc-400"/>
                        </div>
                    </div>
                </flux:file-upload>

                <flux:field>
                    <flux:label>{{ __('ID') }}</flux:label>
                    <flux:input value="{{ $course->id }}" disabled/>
                    <flux:description>{{ __('System-generierte ID (nur lesbar)') }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Name') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="name" required/>
                    <flux:description>{{ __('Der Anzeigename für diesen Kurs') }}</flux:description>
                    <flux:error name="name"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Dozent') }} <span class="text-red-500">*</span></flux:label>
                    <flux:select variant="listbox" searchable wire:model="lecturer_id"
                                 placeholder="{{ __('Dozent auswählen') }}">
                        <x-slot name="search">
                            <flux:select.search class="px-4" placeholder="{{ __('Suche passenden Dozenten...') }}"/>
                        </x-slot>
                        @foreach($lecturers as $lecturer)
                            <flux:select.option value="{{ $lecturer->id }}">{{ $lecturer->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:description>{{ __('Der Dozent, der diesen Kurs leitet') }}</flux:description>
                    <flux:error name="lecturer_id"/>
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Beschreibung') }}</flux:label>
                <flux:textarea wire:model="description" rows="6"/>
                <flux:description>{{ __('Ausführliche Beschreibung des Kurses') }}</flux:description>
                <flux:error name="description"/>
            </flux:field>
        </flux:fieldset>

        <!-- System Information -->
        <flux:fieldset class="space-y-6">
            <flux:legend>{{ __('Systeminformationen') }}</flux:legend>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <flux:field>
                    <flux:label>{{ __('Erstellt von') }}</flux:label>
                    <flux:input value="{{ $course->createdBy?->name ?? __('Unbekannt') }}" disabled/>
                    <flux:description>{{ __('Ersteller des Kurses') }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Erstellt am') }}</flux:label>
                    <flux:input value="{{ $created_at }}" disabled/>
                    <flux:description>{{ __('Wann dieser Kurs erstellt wurde') }}</flux:description>
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
                    {{ __('Kurs aktualisieren') }}
                </flux:button>
            </div>
        </div>
    </form>
</div>
