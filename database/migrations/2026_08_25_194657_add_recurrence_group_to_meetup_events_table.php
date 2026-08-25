<?php

use App\Http\Resources\MeetupEventResource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Die Identitaet einer Terminserie — ein UUID, das jedes Vorkommen mittraegt.
 *
 * Bewusst KEIN `parent_id`: der Anker einer Serie ist ein ganz normaler Termin und
 * loeschbar; mit einem Elternzeiger waere die Serie danach kopflos. Und bewusst keine
 * eigene `meetup_event_series`-Tabelle: {@see MeetupEventResource}
 * verspricht die fuenf `recurrence_*`-Felder PRO TERMIN, das ist bereits
 * veroeffentlichter Vertrag. Eine Serientabelle haette sie aus dem Termin gezogen.
 *
 * Nullable, weil der Einzeltermin der Normalfall ist und bleibt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetup_events', function (Blueprint $table) {
            $table->uuid('recurrence_group')->nullable()->after('recurrence_end_date');
            $table->index('recurrence_group');
        });
    }

    public function down(): void
    {
        Schema::table('meetup_events', function (Blueprint $table) {
            $table->dropIndex(['recurrence_group']);
            $table->dropColumn('recurrence_group');
        });
    }
};
