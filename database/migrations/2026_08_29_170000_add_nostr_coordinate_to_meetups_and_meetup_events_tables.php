<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #34 (NIP-52 cross-publishing): Meetups und MeetupEvents bekommen je eine
 * `nostr_coordinate`-Spalte fuer die parameterized-replaceable Nostr-Kalender-Events
 * (kind 31924 Calendar, kind 31923 Time-Based Calendar Event).
 *
 * Bewusst eine EIGENE Spalte statt der bestehenden `nostr_status`: Letztere gehoert dem
 * aelteren, ueber noscl versendeten kind:1-Textnote-Publisher (PublishUnpublishedItems)
 * und gated dessen Query per `whereNull('nostr_status')`. Eine gemeinsame Spalte fuer
 * beide Publisher wuerde sich gegenseitig blockieren: wer zuerst laeuft, setzt die Spalte
 * und der andere haelt den Datensatz faelschlich fuer bereits erledigt.
 *
 * `nostr_coordinate` speichert `<kind>:<pubkey>:<d-tag>` — die Adresse, unter der das
 * parameterized-replaceable Event auffindbar ist. Ein erneuter Publish-Lauf mit demselben
 * d-Tag ersetzt das Event beim Relay in place, statt ein Duplikat anzulegen.
 */
return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $tables = ['meetups', 'meetup_events'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->text('nostr_coordinate')
                    ->nullable()
                    ->after('nostr_status');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('nostr_coordinate');
            });
        }
    }
};
