<?php

namespace App\Http\Requests\Api;

use App\Http\Resources\MeetupResource;
use App\Models\Meetup;
use Illuminate\Foundation\Http\FormRequest;

class StoreMeetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Meetup::class);
    }

    /**
     * `is_active` ist bewusst KEINE Eingabe.
     *
     * Das Feld ist ein Messwert, kein Wunsch: {@see Meetup::recalculateActivity()}
     * leitet es aus dem letzten und dem naechsten Termin ab und schreibt es per `forceFill`.
     * Ein setzbares `is_active` konnte diesen Wert nur ueberschreiben, bis der naechste
     * Observer-Lauf ihn wieder herstellte — ein stiller Rueckfall, den niemand sah.
     *
     * AUSGELIEFERT wird es weiterhin ({@see MeetupResource}). Das ist
     * oeffentlicher Vertrag und bleibt es: der Wechsel selbst wird ausdruecklich in den
     * Aenderungs-Feed gemeldet (Issue #29, {@see Meetup::recalculateActivity()}).
     * Lesen ja, schreiben nein.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'intro' => ['nullable', 'string'],
            'telegram_link' => ['nullable', 'url', 'max:255'],
            'webpage' => ['nullable', 'url', 'max:255'],
            'twitter_username' => ['nullable', 'string', 'max:255'],
            'matrix_group' => ['nullable', 'string', 'max:255'],
            'nostr' => ['nullable', 'string'],
            'simplex' => ['nullable', 'string'],
            'signal' => ['nullable', 'string', 'max:255'],
            'community' => ['nullable', 'string', 'max:255'],
            'visible_on_map' => ['boolean'],
            'rsvp_enabled' => ['boolean'],
            'attendees_public' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'city_id.exists' => __('Die angegebene Stadt existiert nicht.'),
        ];
    }
}
