<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #34, Nachtrag: NIP-52-Publishing ist Opt-in, nicht Opt-out.
 *
 * `nostr_coordinate` (siehe 2026_08_29_170000_...) gated nur, OB bereits publiziert
 * wurde — nicht, OB ueberhaupt publiziert werden DARF. Ohne dieses Feld wuerde ein
 * kuenftiger Cron-Eintrag fuer `nostr:publish-calendar` jedes bestehende und neue
 * Meetup automatisch veroeffentlichen, ohne dass die Betreiber zugestimmt haben.
 *
 * `nostr_publishing_enabled` steuert BEIDE Kalender-Kinds (31924 Meetup, 31923
 * MeetupEvent): ein MeetupEvent hat keine eigene Spalte, sondern erbt den Schalter
 * seines Meetups (siehe PublishCalendarEvents::handle()).
 *
 * Default `false`: kein bestehendes Meetup wird durch diese Migration aktiv.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetups', function (Blueprint $blueprint) {
            $blueprint->boolean('nostr_publishing_enabled')
                ->default(false)
                ->after('nostr_coordinate');
        });
    }

    public function down(): void
    {
        Schema::table('meetups', function (Blueprint $blueprint) {
            $blueprint->dropColumn('nostr_publishing_enabled');
        });
    }
};
