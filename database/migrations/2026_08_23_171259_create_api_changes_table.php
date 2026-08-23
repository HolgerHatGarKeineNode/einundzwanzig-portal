<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #29: das Aenderungs-Log der oeffentlichen API.
 *
 * Jede Zeile ist genau eine Aenderung an einer der sechs API-Ressourcen — angelegt,
 * geaendert oder geloescht. Die Tabelle ist der Unterbau fuer /api/changes (P2) und
 * fuer den spaeteren Broadcast (P4); sie steht aber allein fuer sich und funktioniert
 * auch dann, wenn nie ein WebSocket dazukommt.
 *
 * `id` ist zugleich der `sequence`-Cursor: monoton, luekenlos vergeben, und damit das
 * einzige, was ein Konsument nach einem Verbindungsabriss mitschicken muss. Ein
 * Zeitstempel taugt dafuer nicht — zwei Aenderungen in derselben Millisekunde sind der
 * Normalfall, und `occurred_at` ist damit ein Filter, kein Cursor.
 *
 * `payload` traegt IMMER das vollstaendige Objekt. Die 10-KB-Grenze von Reverb kuerzt
 * nur, was gesendet wird, niemals das, was hier steht — sonst waere der Resync-Weg
 * genauso luekenhaft wie der Push.
 *
 * `country_code` und `city_id` sind Vorbereitung fuer die Geo-Kanaele aus P7. Sie
 * werden nur gefuellt, soweit sie ohne eine zusaetzliche Query aus dem geschriebenen
 * Datensatz ableitbar sind; sonst bleiben sie null. Ein Schreibvorgang soll nicht
 * teurer werden, um eine Spalte zu fuellen, die heute niemand liest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_changes', function (Blueprint $table) {
            $table->bigIncrements('id');
            // Der Ressourcen-Name der API in Bindestrich-Schreibweise: `meetup`,
            // `meetup-event`, `city`, `course`, `course-event`, `lecturer`.
            $table->string('resource');
            $table->unsignedBigInteger('resource_id');
            // created | updated | deleted. Bewusst ein String und kein Enum: die Werte
            // gehen so, wie sie hier stehen, ueber die API nach draussen.
            $table->string('action');
            // ISO-3166-1 alpha-2, gross geschrieben wie in countries.code.
            $table->string('country_code', 2)->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->json('payload');
            $table->timestamp('occurred_at');

            // Der Resync-Weg: "alles nach Cursor X, optional nur Ressource Y".
            $table->index(['resource', 'id']);
            // Der Prune-Lauf und der Zeitfilter des Endpunkts.
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_changes');
    }
};
