<?php

use App\Attributes\SeoDataAttribute;
use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\User;
use App\Rules\ValidNpub;
use App\Support\NostrLogin;
use App\Traits\SeoTrait;
use Flux\Flux;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[SeoDataAttribute(key: 'meetups_edit')]
class extends Component
{
    use SeoTrait;
    use WithFileUploads;

    #[Validate('image|mimes:jpeg,png,webp,avif|max:5120|dimensions:max_width=4000,max_height=4000')]
    public $logo;

    public Meetup $meetup;

    // Basic Information
    public string $name = '';

    public ?int $city_id = null;

    public string $slug = '';

    public ?string $intro = null;

    // Links and Social Media
    public ?string $telegram_link = null;

    public ?string $webpage = null;

    public ?string $twitter_username = null;

    public ?string $matrix_group = null;

    public ?string $nostr = null;

    public ?string $nostr_status = null;

    public ?string $simplex = null;

    public ?string $signal = null;

    // Additional Information
    public ?string $community = null;

    public ?string $github_data = null;

    public bool $visible_on_map = false;

    // Anmeldung & Sichtbarkeit der Teilnehmer
    public bool $rsvp_enabled = true;

    public bool $attendees_public = true;

    // System fields (read-only) - locked to prevent client-side tampering
    #[Locked]
    public ?int $created_by = null;

    #[Locked]
    public ?string $created_at = null;

    #[Locked]
    public ?string $updated_at = null;

    // Leader management (Delegation): npub des einzusetzenden Leaders.
    public string $leaderNpub = '';

    // New City Modal
    public string $newCityName = '';

    public ?int $newCityCountryId = null;

    public ?float $newCityLatitude = null;

    public ?float $newCityLongitude = null;

    public function createCity(): void
    {
        $validated = $this->validate([
            'newCityName' => ['required', 'string', 'max:255', 'unique:cities,name'],
            'newCityCountryId' => ['required', 'exists:countries,id'],
            'newCityLatitude' => ['required', 'numeric'],
            'newCityLongitude' => ['required', 'numeric'],
        ]);

        $city = City::create([
            'name' => $validated['newCityName'],
            'country_id' => $validated['newCityCountryId'],
            'latitude' => $validated['newCityLatitude'],
            'longitude' => $validated['newCityLongitude'],
            'slug' => str($validated['newCityName'])->slug(),
            'created_by' => auth()->id(),
        ]);

        $this->city_id = $city->id;
        $this->reset(['newCityName', 'newCityCountryId', 'newCityLatitude', 'newCityLongitude']);

        Flux::modal('add-city')->close();
    }

    /**
     * Stammdaten bearbeiten dürfen der Ersteller, Super-Admins UND delegierte
     * Leader (meetup_user.is_leader). Einheitliche update-Ability für Portal-
     * Frontend, REST-API und MCP-Tools (MeetupPolicy::update).
     */
    protected function authorizeAccess(): void
    {
        if (auth()->guest() || auth()->user()->cannot('update', $this->meetup)) {
            abort(403);
        }
    }

    /**
     * Aktuelle Leader dieses Meetups (meetup_user.is_leader = true), Ersteller
     * zuerst. Treibt die Leader-Verwaltungsliste auf der Bearbeiten-Seite.
     */
    #[Computed]
    public function leaders()
    {
        return $this->meetup->leaders();
    }

    /**
     * Einen weiteren Leader per npub einsetzen. Nur Leader/Ersteller dürfen das
     * (manageLeaders). Existiert noch kein Account für den npub, wird er angelegt
     * (greift beim ersten Login). Idempotent: ein bereits gesetzter Leader bleibt.
     * Ein Meetup-Steward darf sich nicht selbst eintragen (appointLeader).
     */
    public function addLeader(): void
    {
        abort_unless(auth()->user()?->can('manageLeaders', $this->meetup), 403);

        $validated = $this->validate(['leaderNpub' => ['required', 'string', new ValidNpub]]);

        $target = NostrLogin::findOrCreateUser($validated['leaderNpub']);

        abort_unless(auth()->user()->can('appointLeader', [$this->meetup, $target]), 403);

        $this->meetup->promoteLeader($target);

        $this->leaderNpub = '';
        unset($this->leaders);
        session()->flash('leaderStatus', __('Leader eingesetzt.'));
    }

    /**
     * Einem Nutzer die Leader-Rolle entziehen (Demote; bleibt Mitglied). Der
     * Ersteller des Meetups ist geschützt und kann nie entzogen werden.
     */
    public function removeLeader(int $userId): void
    {
        abort_unless(auth()->user()?->can('manageLeaders', $this->meetup), 403);
        abort_if($userId === $this->meetup->created_by, 403, __('Der Ersteller des Meetups kann nicht entzogen werden.'));

        $this->meetup->demoteLeader(User::findOrFail($userId));

        unset($this->leaders);
        session()->flash('leaderStatus', __('Leader entzogen.'));
    }

    /**
     * Whitelist the keys allowed inside github_data and coerce types so a
     * tampered payload cannot smuggle arbitrary keys into the stored JSON.
     */
    protected function sanitizeGithubData(?string $raw): ?array
    {
        if (empty($raw)) {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return null;
        }

        $clean = [];
        if (array_key_exists('top', $decoded) && (is_string($decoded['top']) || is_numeric($decoded['top']))) {
            $clean['top'] = (string) $decoded['top'];
        }
        if (array_key_exists('left', $decoded) && (is_string($decoded['left']) || is_numeric($decoded['left']))) {
            $clean['left'] = (string) $decoded['left'];
        }
        if (array_key_exists('state', $decoded) && is_string($decoded['state'])) {
            $clean['state'] = mb_substr($decoded['state'], 0, 64);
        }

        return $clean === [] ? null : $clean;
    }

    public function mount(): void
    {
        $this->authorizeAccess();

        $this->meetup->load('media');

        // Basic Information
        $this->name = $this->meetup->name ?? '';
        $this->city_id = $this->meetup->city_id;
        $this->slug = $this->meetup->slug ?? '';
        $this->intro = $this->meetup->intro;

        // Links and Social Media
        $this->telegram_link = $this->meetup->telegram_link;
        $this->webpage = $this->meetup->webpage;
        $this->twitter_username = $this->meetup->twitter_username;
        $this->matrix_group = $this->meetup->matrix_group;
        $this->nostr = $this->meetup->nostr;
        $this->nostr_status = $this->meetup->nostr_status;
        $this->simplex = $this->meetup->simplex;
        $this->signal = $this->meetup->signal;

        // Additional Information
        $this->community = $this->meetup->community;
        $this->github_data = $this->meetup->github_data ? json_encode($this->meetup->github_data,
            JSON_PRETTY_PRINT) : null;
        $this->visible_on_map = (bool) $this->meetup->visible_on_map;
        $this->rsvp_enabled = (bool) $this->meetup->rsvp_enabled;
        $this->attendees_public = (bool) $this->meetup->attendees_public;

        // System fields
        $this->created_by = $this->meetup->created_by;
        $this->created_at = $this->meetup->created_at?->format('Y-m-d H:i:s');
        $this->updated_at = $this->meetup->updated_at?->format('Y-m-d H:i:s');
    }

    public function updateMeetup(): void
    {
        $this->authorizeAccess();

        $rateLimiterKey = Meetup::updateRateLimitKey(auth()->id());

        if (RateLimiter::tooManyAttempts($rateLimiterKey, 10)) {
            $this->addError('rateLimit',
                __('Zu viele Änderungen in kurzer Zeit. Bitte versuche es in einer Stunde erneut.'));

            return;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('meetups')->ignore($this->meetup->id)],
            'city_id' => ['nullable', 'exists:cities,id'],
            'intro' => ['nullable', 'string'],
            'telegram_link' => ['nullable', 'url', 'max:255'],
            'webpage' => ['nullable', 'url', 'max:255'],
            'twitter_username' => ['nullable', 'string', 'max:255'],
            'matrix_group' => ['nullable', 'string', 'max:255'],
            'nostr' => ['nullable', 'string', 'max:255'],
            'simplex' => ['nullable', 'string', 'max:255'],
            'signal' => ['nullable', 'string', 'max:255'],
            'community' => ['required', 'string', 'max:255'],
            'github_data' => ['nullable', 'json'],
            'rsvp_enabled' => ['boolean'],
            'attendees_public' => ['boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,webp,avif', 'max:5120', 'dimensions:max_width=4000,max_height=4000'],
        ]);

        RateLimiter::hit($rateLimiterKey, 3600);

        $validated['github_data'] = $this->sanitizeGithubData($validated['github_data'] ?? null);

        $this->meetup->update($validated);

        if ($this->logo) {
            $this->meetup->clearMediaCollection('logo');
            $this->meetup
                ->addMedia($this->logo->getRealPath())
                ->usingName($this->meetup->name)
                ->toMediaCollection('logo');
            $this->logo = null;
            $this->meetup->load('media');
        }

        $this->dispatch('meetup-updated', name: $this->meetup->name);

        session()->flash('status', __('Meetup erfolgreich aktualisiert!'));
    }

    public function with(): array
    {
        return [
            'cities' => City::query()
                ->with([
                    'country',
                ])
                ->orderBy('name')
                ->get(),
            'countries' => Country::query()->orderBy('countries.name')->get(),
        ];
    }
}; ?>

<div class="max-w-4xl mx-auto p-6">
    <flux:heading size="xl" class="mb-8">{{ __('Meetup bearbeiten') }}: {{ $meetup->name }}</flux:heading>

    <form wire:submit="updateMeetup" class="space-y-10">

        <!-- Basic Information -->
        <flux:fieldset class="space-y-6">
            <flux:legend>{{ __('Grundlegende Informationen') }}</flux:legend>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <flux:file-upload wire:model="logo">
                    <!-- Custom avatar uploader -->
                    <div class="
                            relative flex items-center justify-center size-20 rounded-full transition-colors cursor-pointer
                            border border-zinc-200 dark:border-white/10 hover:border-zinc-300 dark:hover:border-white/10
                            bg-zinc-100 hover:bg-zinc-200 dark:bg-white/10 hover:dark:bg-white/15 in-data-dragging:dark:bg-white/15
                        ">
                        <!-- Show the uploaded file if it exists -->
                        @if (!$logo && $meetup->getFirstMedia('logo'))
                            <img src="{{ $meetup->getFirstMediaUrl('logo') }}" alt="Logo"
                                 class="size-full object-cover rounded"/>
                        @elseif($logo?->isPreviewable())
                            <img src="{{ $logo->temporaryUrl() }}" alt="Logo"
                                 class="size-full object-cover rounded-full"/>
                        @else
                            <!-- Show the default icon if no file is uploaded -->
                            <flux:icon name="user" variant="solid" class="text-zinc-500 dark:text-zinc-400"/>
                        @endif

                        <!-- Corner upload icon -->
                        <div class="absolute bottom-0 right-0 bg-white dark:bg-zinc-800 rounded-full">
                            <flux:icon name="arrow-up-circle" variant="solid" class="text-zinc-500 dark:text-zinc-400"/>
                        </div>
                    </div>

                    <flux:error name="logo"/>
                </flux:file-upload>

                <flux:field>
                    <flux:label>{{ __('ID') }}</flux:label>
                    <flux:input value="{{ $meetup->id }}" disabled/>
                    <flux:description>{{ __('System-generierte ID (nur lesbar)') }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Name') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="name" required/>
                    <flux:description>{{ __('Der Anzeigename für dieses Meetup') }}</flux:description>
                    <flux:error name="name"/>
                </flux:field>

                <flux:field>
                    <div class="flex items-center justify-between mb-2">
                        <flux:label>{{ __('Stadt') }}</flux:label>
                        <flux:modal.trigger name="add-city">
                            <flux:button class="cursor-pointer" size="xs" variant="ghost" icon="plus">
                                {{ __('Stadt hinzufügen') }}
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                    <flux:select variant="listbox" searchable wire:model="city_id"
                                 placeholder="{{ __('Stadt auswählen') }}">
                        <x-slot name="search">
                            <flux:select.search class="px-4" placeholder="{{ __('Suche passende Stadt...') }}"/>
                        </x-slot>
                        @foreach($cities as $city)
                            <flux:select.option value="{{ $city->id }}">{{ $city->name }} ({{ $city->country->name }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:description>{{ __('Die nächstgrößte Stadt oder Ort') }}</flux:description>
                    <flux:error name="city_id"/>
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Einführung') }}</flux:label>
                <flux:textarea wire:model="intro" rows="4"/>
                <flux:description>{{ __('Kurze Beschreibung des Meetups') }}</flux:description>
                <flux:error name="intro"/>
            </flux:field>
        </flux:fieldset>

        <!-- Links and Social Media -->
        <flux:fieldset class="space-y-6">
            <flux:legend>{{ __('Links & Soziale Medien') }}</flux:legend>

            <!-- Primary Links -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('Webseite') }}</flux:label>
                    <flux:input wire:model="webpage" type="url" placeholder="https://example.com"/>
                    <flux:description>{{ __('Offizielle Webseite oder Landingpage') }}</flux:description>
                    <flux:error name="webpage"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Telegram Link') }}</flux:label>
                    <flux:input wire:model="telegram_link" type="url" placeholder="https://t.me/gruppenname"/>
                    <flux:description>{{ __('Link zur Telegram-Gruppe oder zum Kanal') }}</flux:description>
                    <flux:error name="telegram_link"/>
                </flux:field>
            </div>

            <!-- Social Media -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('Twitter Benutzername') }}</flux:label>
                    <flux:input wire:model="twitter_username" placeholder="benutzername"/>
                    <flux:description>{{ __('Twitter-Handle ohne @ Symbol') }}</flux:description>
                    <flux:error name="twitter_username"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Matrix Gruppe') }}</flux:label>
                    <flux:input wire:model="matrix_group" placeholder="#gruppe:matrix.org"/>
                    <flux:description>{{ __('Matrix-Raum Bezeichner oder Link') }}</flux:description>
                    <flux:error name="matrix_group"/>
                </flux:field>
            </div>

            <!-- Decentralized Platforms -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('Nostr') }}</flux:label>
                    <flux:input wire:model="nostr" placeholder="npub..."/>
                    <flux:description>{{ __('Nostr öffentlicher Schlüssel oder Bezeichner') }}</flux:description>
                    <flux:error name="nostr"/>
                </flux:field>
            </div>

            <!-- Messaging Apps -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('SimpleX') }}</flux:label>
                    <flux:input wire:model="simplex"/>
                    <flux:description>{{ __('SimpleX Chat Kontaktinformationen') }}</flux:description>
                    <flux:error name="simplex"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Signal') }}</flux:label>
                    <flux:input wire:model="signal"/>
                    <flux:description>{{ __('Signal Kontakt- oder Gruppeninformationen') }}</flux:description>
                    <flux:error name="signal"/>
                </flux:field>
            </div>
        </flux:fieldset>

        <!-- Additional Information -->
        <flux:fieldset class="space-y-6">
            <flux:legend>{{ __('Zusätzliche Informationen') }}</flux:legend>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('Gemeinschaft') }}</flux:label>
                    <flux:select wire:model="community">
                        <flux:select.option value="">{{ __('Keine') }}</flux:select.option>
                        <flux:select.option value="einundzwanzig">{{ __('EINUNDZWANZIG Community') }}</flux:select.option>
                        <flux:select.option value="bitcoin">{{ __('Allgemeine Bitcoin Community') }}</flux:select.option>
                    </flux:select>
                    <flux:description>{{ __('Gemeinschafts- oder Organisationsname') }}</flux:description>
                    <flux:error name="community"/>
                </flux:field>
            </div>
        </flux:fieldset>

        <!-- Anmeldung & Sichtbarkeit -->
        <flux:fieldset class="space-y-6" x-data="{ rsvp: $wire.entangle('rsvp_enabled') }">
            <flux:legend>{{ __('Anmeldung & Sichtbarkeit') }}</flux:legend>

            <div class="rounded-xl border border-zinc-200 p-4 space-y-5 dark:border-zinc-700">
                <flux:switch
                    wire:model.live="rsvp_enabled"
                    :label="__('Anmeldung (RSVP) aktivieren')"
                    :description="__('Besucher können sich für Termine an- oder abmelden („Ich komme“ / „Vielleicht“).')"/>

                <flux:separator variant="subtle"/>

                <div x-bind:class="rsvp ? '' : 'opacity-50 pointer-events-none'">
                    <flux:switch
                        wire:model="attendees_public"
                        x-bind:disabled="!rsvp"
                        :label="__('Teilnehmerliste öffentlich zeigen')"
                        :description="__('Aus: Zu-/Absagen und Zähler bleiben öffentlich verborgen. Du und weitere Leader seht sie weiterhin.')"/>
                </div>
            </div>
        </flux:fieldset>

        <!-- System Information -->
        <flux:fieldset class="space-y-6">
            <flux:legend>{{ __('Systeminformationen') }}</flux:legend>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <flux:field>
                    <flux:label>{{ __('Erstellt von') }}</flux:label>
                    <flux:input value="{{ $meetup->createdBy?->name ?? __('Unbekannt') }}" disabled/>
                    <flux:description>{{ __('Ersteller des Meetups') }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Erstellt am') }}</flux:label>
                    <flux:input value="{{ $created_at }}" disabled/>
                    <flux:description>{{ __('Wann dieses Meetup erstellt wurde') }}</flux:description>
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

                @error('rateLimit')
                    <flux:text class="text-red-600 dark:text-red-400 font-medium">
                        {{ $message }}
                    </flux:text>
                @enderror

                <flux:button class="cursor-pointer" variant="primary" type="submit">
                    {{ __('Meetup aktualisieren') }}
                </flux:button>
            </div>
        </div>
    </form>

    <!-- Leader-Verwaltung (Delegation) -->
    <flux:fieldset class="space-y-6 mt-10 pt-10 border-t border-gray-200 dark:border-gray-700">
        <flux:legend>{{ __('Leader verwalten') }}</flux:legend>

        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 space-y-2 dark:border-amber-500/40 dark:bg-amber-500/10">
            <div class="flex items-center gap-2 font-semibold text-amber-800 dark:text-amber-300">
                <flux:icon name="information-circle" class="size-5"/>
                {{ __('Was ist ein Leader?') }}
            </div>
            <flux:text>
                {{ __('Leader dürfen dieses Meetup bearbeiten und selbst weitere Leader einsetzen. Setze nur Personen ein, denen du vertraust.') }}
            </flux:text>
            <flux:text>
                {{ __('Frag die Person nach ihrem Nostr-Schlüssel (npub). Sie findet ihn in ihrer Nostr-App oder in ihrem Portal-Profil — er beginnt mit „npub1…“.') }}
            </flux:text>
        </div>

        <form wire:submit="addLeader" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <flux:field class="flex-1">
                <flux:label>{{ __('npub der Person') }}</flux:label>
                <flux:input wire:model="leaderNpub" placeholder="npub1…"/>
                <flux:error name="leaderNpub"/>
            </flux:field>
            <flux:button class="cursor-pointer" type="submit" variant="primary" icon="user-plus">
                {{ __('Als Leader einsetzen') }}
            </flux:button>
        </form>

        @if (session('leaderStatus'))
            <flux:text class="font-medium text-green-600 dark:text-green-400">{{ session('leaderStatus') }}</flux:text>
        @endif

        <div class="space-y-2">
            <flux:text class="font-medium">{{ __('Aktuelle Leader') }}</flux:text>
            @foreach ($this->leaders as $leader)
                <div class="flex items-center gap-3 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700"
                     wire:key="leader-{{ $leader->id }}">
                    <flux:avatar size="sm" circle :name="$leader->name" src="{{ $leader->profile_photo_url }}"/>
                    <div class="flex min-w-0 flex-1 flex-col">
                        <span class="truncate font-semibold">{{ $leader->name }}</span>
                        @if ($leader->nostr)
                            <span class="truncate font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ $leader->nostr }}</span>
                        @endif
                    </div>
                    @if ($leader->id === $meetup->created_by)
                        <flux:badge color="amber" icon="star">{{ __('Ersteller') }}</flux:badge>
                    @else
                        <flux:button class="cursor-pointer" size="sm" variant="ghost" icon="user-minus"
                                     wire:click="removeLeader({{ $leader->id }})"
                                     wire:confirm="{{ __(':name die Leader-Rolle entziehen? Die Person bleibt Mitglied, kann das Meetup aber nicht mehr bearbeiten.', ['name' => $leader->name]) }}">
                            {{ __('Entziehen') }}
                        </flux:button>
                    @endif
                </div>
            @endforeach
        </div>
    </flux:fieldset>

    <!-- Add City Modal -->
    <flux:modal name="add-city" variant="flyout" wire:key="add-city-modal">
        <form wire:submit="createCity" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Stadt hinzufügen') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Füge eine neue Stadt zur Datenbank hinzu.') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Stadtname') }} <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="newCityName" placeholder="{{ __('z.B. Berlin') }}" required/>
                <flux:error name="newCityName"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Land') }} <span class="text-red-500">*</span></flux:label>
                <flux:select variant="listbox" searchable wire:model="newCityCountryId"
                             placeholder="{{ __('Land auswählen') }}">
                    @foreach($countries as $country)
                        <flux:select.option value="{{ $country->id }}">
                            <div class="flex items-center space-x-2">
                                <img alt="{{ str($country->code)->lower() }}"
                                     src="{{ asset('vendor/blade-flags/country-'.str($country->code)->lower().'.svg') }}"
                                     width="24" height="12"/>
                                <span>{{ $country->name }}</span>
                            </div>
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="newCityCountryId"/>
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Breitengrad') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="newCityLatitude" type="number" step="0.000001" placeholder="52.520008"
                                required/>
                    <flux:error name="newCityLatitude"/>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Längengrad') }} <span class="text-red-500">*</span></flux:label>
                    <flux:input wire:model="newCityLongitude" type="number" step="0.000001" placeholder="13.404954"
                                required/>
                    <flux:error name="newCityLongitude"/>
                </flux:field>
            </div>

            <div class="flex gap-2">
                <flux:spacer/>

                <flux:modal.close>
                    <flux:button class="cursor-pointer" type="button"
                                 variant="ghost">{{ __('Abbrechen') }}</flux:button>
                </flux:modal.close>

                <flux:button class="cursor-pointer" type="submit"
                             variant="primary">{{ __('Stadt erstellen') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
