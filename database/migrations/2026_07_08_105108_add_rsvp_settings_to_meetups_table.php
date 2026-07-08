<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('meetups', function (Blueprint $table) {
            // Ob sich Besucher überhaupt für Termine dieses Meetups an-/abmelden können (RSVP).
            $table->boolean('rsvp_enabled')->default(true);
            // Ob die Teilnehmerliste/Zähler öffentlich sichtbar sind. Leader/Ersteller/
            // Super-Admin sehen sie unabhängig davon immer.
            $table->boolean('attendees_public')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meetups', function (Blueprint $table) {
            $table->dropColumn(['rsvp_enabled', 'attendees_public']);
        });
    }
};
