<?php

namespace App\Observers;

use App\Models\MeetupEvent;
use App\Support\Broadcasting\ChangeRecorder;
use Illuminate\Database\Eloquent\Model;

/**
 * Haengt das Aenderungs-Log (Issue #29) an die sechs API-Ressourcen.
 *
 * WARUM EIN OBSERVER UND KEIN TRAIT: Geschrieben wird auf vier Wegen — die
 * API-Controller unter `app/Http/Controllers/Api/`, die MCP-Tools unter
 * `app/Mcp/Tools/`, die Livewire-Volt-Views unter `resources/views/livewire/` und die
 * Artisan-Commands unter `app/Console/Commands/`. Ein Hook an einem dieser Wege haette
 * die anderen drei stillschweigend uebersprungen; nur das Model-Event sieht alle vier.
 * Zwischen Trait und Observer entscheidet die Hausordnung: Lebenszyklus-Reaktionen
 * liegen hier in `app/Observers/` und werden per `#[ObservedBy]` am Model registriert
 * ({@see MeetupEventObserver}, {@see MeetupEvent}). Die beiden Traits unter
 * `app/Models/Concerns/` sind etwas anderes: `HasOsmReference` liefert Accessoren,
 * `SetsCreatedBy` fuellt ein eigenes Feld des Models. Hier wird nichts am Model
 * gefuellt, sondern woanders etwas notiert — das ist ein Beobachter.
 *
 * Ein Observer statt sechs: die Ressourcen-Zuordnung steht ohnehin schon zentral in
 * {@see ChangeRecorder}, und sechs identische Klassen waeren sechs Orte, an denen eine
 * kuenftige Aenderung vergessen werden kann.
 *
 * `MeetupEvent` traegt diesen Observer ZUSAETZLICH zu {@see MeetupEventObserver}; die
 * beiden beruehren sich nicht.
 *
 * NICHT abgedeckt — bewusst, weil kein Model-Event feuert:
 *  - `Meetup::recalculateActivity()` endet auf `saveQuietly()`. Deshalb ruft die
 *    Methode den Recorder selbst auf.
 *  - Query-Builder-Updates, etwa `MergeUserAccounts`. Dort aendern sich nur interne
 *    `created_by`-Zeiger; im Plan als hingenommene Luecke festgehalten.
 */
class ApiChangeObserver
{
    public function created(Model $model): void
    {
        ChangeRecorder::record($model, 'created');
    }

    public function updated(Model $model): void
    {
        ChangeRecorder::record($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        ChangeRecorder::record($model, 'deleted');
    }
}
