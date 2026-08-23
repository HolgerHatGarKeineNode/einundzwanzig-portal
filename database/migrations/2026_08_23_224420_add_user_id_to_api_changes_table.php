<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Der Akteur einer aufgezeichneten Aenderung (Issue #30).
 *
 * `api_changes` hielt bisher fest, WAS sich geaendert hat, aber nicht, WER es getan hat.
 * Solange nur der Ersteller schreiben durfte, war `created_by` der De-facto-Akteur — jede
 * Lockerung dieser Regel nimmt den letzten Zurechnungsanker weg, ohne einen neuen zu
 * liefern. Diese Spalte ist der neue.
 *
 * `nullable`, und zwar dauerhaft: Seeder, Importe und Konsolenbefehle schreiben ohne
 * angemeldeten Nutzer, und ein Schreibvorgang darf daran nicht scheitern. `null` heisst
 * hier "kein angemeldeter Nutzer", nicht "unbekannt".
 *
 * `nullOnDelete` statt `cascadeOnDelete`: die Loeschung eines Kontos soll den Akteur
 * entfernen, nicht die Historie der Aenderung. Anders herum als bei `cities.created_by`,
 * wo die Kaskade den Datensatz selbst mitnimmt.
 *
 * Die Spalte geht NICHT nach draussen: `ApiChangeResource` gibt ausschliesslich `payload`
 * zurueck, und `ChangeRecorder::record()` schreibt `user_id` bewusst nur in die Spalte,
 * nicht ins Envelope. Aus einer Zurechnungsspalte fuer den Betreiber wuerde sonst ein
 * oeffentlicher Autorenindex.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_changes', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('action')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('api_changes', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
