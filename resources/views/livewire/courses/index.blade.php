<?php

use App\Attributes\SeoDataAttribute;
use App\Models\Course;
use App\Traits\SeoTrait;
use Livewire\Component;
use Livewire\WithPagination;

new
#[SeoDataAttribute(key: 'courses_index')]
class extends Component
{
    use SeoTrait;
    use WithPagination;

    public $country = 'de';

    public $search = '';

    public function mount(): void
    {
        $this->country = request()->route('country', config('app.domain_country'));
    }

    public function with(): array
    {
        return [
            'courses' => Course::with(['lecturer', 'createdBy'])
                ->withExists([
                    'courseEvents as has_future_events' => fn ($query) => $query->where('from', '>=', now()),
                ])
                ->when($this->search, fn ($query) => $query
                    ->whereLike('name', '%'.$this->search.'%')
                    ->orWhereLike('description', '%'.$this->search.'%'),
                )
                ->whereHas('courseEvents.city.country', fn ($query) => $query->matchingCode($this->country))
                ->orderByDesc('has_future_events')
                ->paginate(15),
        ];
    }
}; ?>

<div>
    <div class="flex items-center justify-between flex-col md:flex-row">
        <flux:heading size="xl">{{ __('Kurse') }}</flux:heading>
        <div class="flex items-center flex-col md:flex-row gap-4">
            <div>
                <flux:input
                    wire:model.live="search"
                    :placeholder="__('Suche nach Kursen...')"
                    clearable
                />
            </div>
            <flux:button variant="primary" icon="plus-circle" :href="route_with_country('courses.create')"
                         wire:navigate>{{ __('Neuer Kurs') }}</flux:button>
        </div>
    </div>

    <flux:table :paginate="$courses" class="mt-6">
        <flux:table.columns>
            <flux:table.column>
                {{ __('Name') }}
            </flux:table.column>
            <flux:table.column>
                {{ __('Dozent') }}
            </flux:table.column>
            <flux:table.column>{{ __('Nächster Termin') }}</flux:table.column>
            <flux:table.column>{{ __('Aktionen') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($courses as $course)
                <flux:table.row :key="$course->id">
                    <flux:table.cell variant="strong">
                        <flux:tooltip content="{{ $course->name }}">
                            <div class="flex items-center gap-3">
                                <flux:avatar
                                    :href="route('courses.landingpage', ['course' => $course, 'country' => $country])"
                                    src="{{ $course->getFirstMedia('logo') ? $course->getFirstMediaUrl('logo', 'thumb') : asset('android-chrome-512x512.png') }}"/>
                                <div>
                                    <a href="{{ route('courses.landingpage', ['course' => $course, 'country' => $country]) }}">
                                        <span>{{ Str::limit($course->name, 30) }}</span>
                                        @if($course->description)
                                            <div class="text-xs text-zinc-500">
                                                {{ Str::limit($course->description, 60) }}
                                            </div>
                                        @endif
                                    </a>
                                </div>
                            </div>
                        </flux:tooltip>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if($course->lecturer)
                            <div class="flex items-center gap-2">
                                <flux:avatar size="xs"
                                             src="{{ $course->lecturer->getFirstMedia('avatar') ? $course->lecturer->getFirstMediaUrl('avatar', 'thumb') : asset('img/einundzwanzig.png') }}"/>
                                <span>{{ $course->lecturer->name }}</span>
                            </div>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        @php
                            $nextEvent = $course->courseEvents()
                                ->where('from', '>=', now())
                                ->orderBy('from', 'asc')
                                ->first();
                        @endphp
                        @if($nextEvent)
                            <flux:badge color="green" size="sm">
                                {{ $nextEvent->from->asDateTime() }}
                            </flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex flex-col space-y-2">
                            {{--
                                Zwei Knoepfe, ZWEI verschiedene Fragen — und keine davon ist
                                `created_by === auth()->id()`.

                                Der Bearbeiten-Knopf gehoert an `CoursePolicy::update()`: dort
                                kommt ueber `ChecksCreatorOwnership` der Super-Admin hinzu, der
                                bisher das Recht hatte, aber keinen Knopf sah.

                                Der Termin-Knopf ist NICHT dieselbe Frage. Er legt einen
                                CourseEvent an diesem Kurs an, und genau dafuer gibt es seit P1
                                `CourseEventPolicy::createForCourse()`. Wer ihn auf `update`
                                legt, trifft heute zufaellig dasselbe Ergebnis und schreibt die
                                falsche Absicht fest: `create()` muss schrankenlos bleiben (die
                                REST-API ruft sie ohne Kurs auf), also beantwortet nur
                                `createForCourse()` die Frage „an DIESEN Kurs".

                                Die alte `:disabled`/`:href`-Doppelung faellt mit: sie stellte
                                dieselbe Bedingung drei Mal und war innerhalb des @if ohnehin
                                immer wahr — ein Knopf, der nie deaktiviert sein konnte.
                            --}}
                            @can('update', $course)
                                <div>
                                    <flux:button
                                        :href="route_with_country('courses.edit', ['course' => $course])"
                                        size="xs"
                                        variant="filled"
                                        icon="pencil">
                                        {{ __('Bearbeiten') }}
                                    </flux:button>
                                </div>
                            @endcan
                            @can('createForCourse', [App\Models\CourseEvent::class, $course])
                                <div>
                                    <flux:button
                                        :href="route_with_country('courses.events.create', ['course' => $course])"
                                        size="xs"
                                        variant="filled"
                                        icon="calendar">
                                        {{ __('Neues Event erstellen') }}
                                    </flux:button>
                                </div>
                            @endcan
                            @guest
                                <flux:link :href="route('login')">{{ __('Log in') }}</flux:link>
                            @endguest
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
