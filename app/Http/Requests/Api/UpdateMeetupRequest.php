<?php

namespace App\Http\Requests\Api;

use App\Http\Resources\MeetupResource;
use App\Models\Meetup;
use App\Rules\UniqueMeetupName;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('meetup'));
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
     * `$meetupId` ist fuer die Aufrufer ohne Route da: das MCP-Tool baut diese
     * Request von Hand (`new UpdateMeetupRequest`), dort liefert `route('meetup')`
     * null. Ohne die id wuerde die Eindeutigkeitspruefung das Meetup gegen sich
     * selbst pruefen — ein Speichern ohne Namensaenderung schluege fehl.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(?int $meetupId = null): array
    {
        $meetupId ??= $this->route('meetup')?->getKey();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', new UniqueMeetupName($meetupId)],
            'city_id' => ['sometimes', 'required', 'integer', 'exists:cities,id'],
            'intro' => ['sometimes', 'nullable', 'string'],
            'telegram_link' => ['sometimes', 'nullable', 'url', 'max:255'],
            'webpage' => ['sometimes', 'nullable', 'url', 'max:255'],
            'twitter_username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'matrix_group' => ['sometimes', 'nullable', 'string', 'max:255'],
            'nostr' => ['sometimes', 'nullable', 'string'],
            'simplex' => ['sometimes', 'nullable', 'string'],
            'signal' => ['sometimes', 'nullable', 'string', 'max:255'],
            'community' => ['sometimes', 'nullable', 'string', 'max:255'],
            'visible_on_map' => ['sometimes', 'boolean'],
            'rsvp_enabled' => ['sometimes', 'boolean'],
            'attendees_public' => ['sometimes', 'boolean'],
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
