<?php

namespace App\Http\Resources;

use App\Models\ApiChange;
use App\Support\Broadcasting\ChangeRecorder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One recorded change, exactly as it was stored.
 *
 * @property-read ApiChange $resource
 */
class ApiChangeResource extends JsonResource
{
    /**
     * Gibt das gespeicherte Envelope 1:1 zurueck.
     *
     * KEINE zweite Gestalt, keine Umsortierung, kein nachtraeglich gefuelltes Feld:
     * das Payload wurde genau einmal gebaut ({@see ChangeRecorder::record()}), und
     * ein Konsument, der einen verpassten Eintrag hier nachholt, muss byte-gleich
     * dasselbe sehen wie der, der ihn ab P4 ueber den Kanal bekommen hat. Baute diese
     * Klasse das Envelope aus den Spalten neu zusammen, waeren es zwei Formate, die
     * sich nur so lange gleichen, wie jemand beide zugleich pflegt — und die
     * Abweichung faellt beim Konsumenten auf, nicht hier.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->payload;
    }
}
