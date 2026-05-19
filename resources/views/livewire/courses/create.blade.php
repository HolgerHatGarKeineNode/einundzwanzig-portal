<?php

use App\Attributes\SeoDataAttribute;
use App\Models\Course;
use App\Models\Lecturer;
use App\Traits\SeoTrait;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[SeoDataAttribute(key: 'courses_create')]
class extends Component {
    use WithFileUploads;
    use SeoTrait;

    #[Validate('image|mimes:jpeg,png,webp,avif|max:5120|dimensions:max_width=4000,max_height=4000')]
    public $logo;

    public string $name = '';
    public ?int $lecturer_id = null;
    public ?string $description = null;

    public function createCourse(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'lecturer_id' => ['required', 'exists:lecturers,id'],
            'description' => ['nullable', 'string'],
        ]);

        $course = Course::create($validated);

        if ($this->logo) {
            $course
                ->addMedia($this->logo->getRealPath())
                ->usingName($course->name)
                ->toMediaCollection('logo');
        }

        session()->flash('status', __('Kurs erfolgreich erstellt!'));

        $this->redirect(route_with_country('courses.edit', ['course' => $course]), navigate: true);
    }

    public function with(): array
    {
        return [
            'lecturers' => Lecturer::query()->orderBy('name')->get(),
        ];
    }
}; ?>

<div class="max-w-4xl mx-auto p-6">
    <flux:heading size="xl" class="mb-8">{{ __('Neuen Kurs erstellen') }}</flux:heading>

    <form wire:submit="createCourse" class="space-y-10">

        <!-- Basic Information -->
        <flux:fieldset class="space-y-6">
            <flux:legend>{{ __('Grundlegende Informationen') }}</flux:legend>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <flux:file-upload wire:model="logo">
                    <!-- Custom logo uploader -->
                    <div class="
                            relative flex items-center justify-center size-20 rounded transition-colors cursor-pointer
                            border border-zinc-200 dark:border-white/10 hover:border-zinc-300 dark:hover:border-white/10
                            bg-zinc-100 hover:bg-zinc-200 dark:bg-white/10 hover:dark:bg-white/15 in-data-dragging:dark:bg-white/15
                        ">
                        <!-- Show the uploaded file if it exists -->
                        @if($logo)
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

        <!-- Form Actions -->
        <div class="flex items-center justify-between pt-8 border-t border-gray-200 dark:border-gray-700">
            <flux:button class="cursor-pointer" variant="ghost" type="button" onclick="history.back()">
                {{ __('Abbrechen') }}
            </flux:button>

            <flux:button class="cursor-pointer" variant="primary" type="submit">
                {{ __('Kurs erstellen') }}
            </flux:button>
        </div>
    </form>
</div>
