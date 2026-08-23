<?php

namespace App\Models;

use App\Support\Broadcasting\ChangeRecorder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eine aufgezeichnete Aenderung an einer oeffentlichen API-Ressource (Issue #29).
 *
 * Geschrieben wird ausschliesslich vom {@see ChangeRecorder}; dieses Model ist der
 * Lesezugriff darauf. `id` ist zugleich der `sequence`-Cursor, den ein Konsument nach
 * einem Verbindungsabriss mitschickt.
 *
 * Die Zeile traegt IMMER das vollstaendige Payload. Was ueber einen WebSocket gehen
 * darf, ist eine andere Frage — die beantwortet {@see self::broadcastPayload()}.
 *
 * @property int $id
 * @property string $resource
 * @property int $resource_id
 * @property string $action
 * @property string|null $country_code
 * @property int|null $city_id
 * @property array<string, mixed> $payload
 * @property Carbon $occurred_at
 */
class ApiChange extends Model
{
    use HasFactory;

    /**
     * Die Tabelle hat bewusst keine created_at/updated_at-Spalten: eine Zeile im
     * Aenderungs-Log wird nie geaendert, und `occurred_at` ist der einzige Zeitpunkt,
     * der etwas bedeutet.
     */
    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'resource',
        'resource_id',
        'action',
        'country_code',
        'city_id',
        'payload',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'resource_id' => 'integer',
            'city_id' => 'integer',
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * Das Payload in der Gestalt, in der es ueber einen WebSocket gehen darf.
     *
     * Ab P4 liest der Broadcast genau diese Methode. In P1 existiert sie, damit die
     * 10-KB-Kuerzung getestet ist, bevor der erste Kanal steht — der Fallback, der erst
     * in Produktion das erste Mal laeuft, ist der, der dort nicht laeuft.
     *
     * @return array<string, mixed>
     */
    public function broadcastPayload(): array
    {
        return ChangeRecorder::broadcastPayload($this->payload);
    }
}
