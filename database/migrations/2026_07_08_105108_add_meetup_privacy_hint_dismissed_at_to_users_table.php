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
        Schema::table('users', function (Blueprint $table) {
            // Zeitpunkt, an dem der Nutzer den Hinweis-Banner zu den neuen Meetup-
            // Sichtbarkeitseinstellungen dauerhaft weggeklickt hat (null = noch sichtbar).
            $table->timestamp('meetup_privacy_hint_dismissed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('meetup_privacy_hint_dismissed_at');
        });
    }
};
