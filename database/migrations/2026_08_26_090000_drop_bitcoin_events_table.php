<?php

use App\Actions\MergeUserAccounts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;

/**
 * Entfernt `bitcoin_events` samt der daran haengenden Medien.
 *
 * Die Tabelle hatte zuletzt keinen Leser mehr, der etwas ausliefert: kein Controller,
 * keine API-Resource, kein MCP-Tool, keine Route, kein Nostr-Publisher, kein ICS-Feed.
 * Was blieb, war eine Relation auf `City`, eine Staedte-Sperre in `places:cleanup` und
 * zwei Stellen, die den Tabellennamen nur als Zeichenkette fuehren
 * ({@see MergeUserAccounts}, `settings/link-identity`) — beide laufen durch
 * `Schema::hasTable()` und ueberleben diesen Drop wortlos.
 *
 * ## Warum die Medien hier MIT geloescht werden — anders als beim Venue-Drop
 *
 * `2026_08_17_190834_drop_venues_table` hat seine 41 Medienzeilen bewusst stehen lassen:
 * ein Veranstaltungsort trug Fotos, fuer die es nach dem Drop keinen neuen Eigentuemer
 * gab, und 13 MB Bilder zu vernichten war der teurere Fehler. Hier liegt es umgekehrt.
 * Die 99 Medienzeilen (Produktion, 2026-08-25) sind Logos von Veranstaltungen, die es
 * nach dieser Migration nicht mehr gibt — es gibt keinen Betrachter, keine Ansicht und
 * keinen Kandidaten fuer eine spaetere Zuordnung. Sie waeren nicht „aufgehoben", sondern
 * unauffindbar: `media` ist polymorph OHNE Fremdschluessel, also raeumt sie kein
 * Datenbank-Mechanismus je mit auf, und ein `model_type`, dessen Klasse nicht mehr
 * existiert, laesst sich nicht einmal mehr laden.
 *
 * Geloescht werden Zeile UND Datei. Der Pfad folgt dem
 * {@see DefaultPathGenerator}: das Verzeichnis
 * heisst wie die Medien-id und enthaelt Original, `conversions/` und `responsive-images/`.
 * Das Modell dafuer zu laden geht NICHT — `Media::model()` wuerde die geloeschte Klasse
 * aufloesen wollen. Deshalb roh ueber `DB` und `Storage`.
 *
 * Die Zahl wird ausgegeben, damit sie im Deploy-Protokoll steht statt in niemandes Kopf.
 */
return new class extends Migration
{
    private const MODEL_TYPE = 'App\Models\BitcoinEvent';

    public function up(): void
    {
        $this->purgeMedia();

        if (Schema::hasTable('bitcoin_events')) {
            Schema::drop('bitcoin_events');
        }
    }

    /**
     * Medienzeilen und die zugehoerigen Dateien entfernen und melden, wie viele es waren.
     */
    private function purgeMedia(): void
    {
        if (! Schema::hasTable('media')) {
            return;
        }

        $rows = DB::table('media')
            ->where('model_type', self::MODEL_TYPE)
            ->get(['id', 'disk', 'conversions_disk']);

        if ($rows->isEmpty()) {
            return;
        }

        $deletedFiles = 0;

        foreach ($rows as $row) {
            foreach (array_unique(array_filter([$row->disk, $row->conversions_disk])) as $disk) {
                try {
                    if (Storage::disk($disk)->deleteDirectory((string) $row->id)) {
                        $deletedFiles++;
                    }
                } catch (Throwable $e) {
                    // Eine fehlende Platte darf den Drop nicht aufhalten — aber sie darf
                    // auch nicht still bleiben, sonst gilt die Datei als geraeumt.
                    echo "  Warnung: Medien-Verzeichnis {$row->id} auf Disk '{$disk}' nicht loeschbar: {$e->getMessage()}\n";
                }
            }
        }

        $deletedRows = DB::table('media')->where('model_type', self::MODEL_TYPE)->delete();

        echo "  bitcoin_events: {$deletedRows} media-Zeilen geloescht, {$deletedFiles} Verzeichnisse von der Platte entfernt.\n";
    }

    /**
     * Stellt die Struktur wieder her, nicht die Daten.
     *
     * Genau wie beim Venue-Drop: ein Rollback landet in einer lauffaehigen Datenbank, die
     * Zeilen sind fort. `venue_id` fehlt hier absichtlich — die Spalte war schon vor
     * diesem Drop weg (`drop_venues_table`), und deren `down()` legt sie selbst wieder an,
     * wenn der Rollback so weit zurueckreicht.
     */
    public function down(): void
    {
        if (Schema::hasTable('bitcoin_events')) {
            return;
        }

        Schema::create('bitcoin_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('from');
            $table->dateTime('to');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('link')->nullable();
            $table->timestamps();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->boolean('show_worldwide')->default(false);
            $table->text('nostr_status')->nullable();
            $table->string('osm_type', 16)->nullable();
            $table->unsignedBigInteger('osm_id')->nullable();
            $table->string('osm_name')->nullable();
            $table->string('osm_address')->nullable();
            $table->decimal('osm_lat', 10, 7)->nullable();
            $table->decimal('osm_lon', 10, 7)->nullable();
            $table->string('location')->nullable();

            $table->index(['osm_type', 'osm_id']);
        });
    }
};
