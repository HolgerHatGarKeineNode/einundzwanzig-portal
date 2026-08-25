<?php

use App\Http\Controllers\LnurlAuthController;
use App\Http\Resources\LecturerResource;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Laesst die fuenf Lightning-Spalten auf `users` auslaufen — samt ihrer Blind-Indizes.
 *
 * ZWEITER von zwei Schritten. Der erste Commit hat den Code geraeumt: `lnbits` wird bei
 * keiner Registrierung mehr geschrieben, `NostrPlebController` selektiert die Spalten
 * nicht mehr, der Zusammenfuehrungs-Dialog bietet keine Lightning-Adresse mehr an. Erst
 * danach darf das Schema fallen — andersherum waere jede Neuanmeldung ueber LNURL UND
 * ueber Nostr an einer Spalte gescheitert, die es nicht mehr gibt.
 *
 * UNUMKEHRBAR. Die Werte sind CipherSweet-verschluesselt; `down()` stellt die Struktur
 * wieder her, nicht den Inhalt. Ein Rollback landet in einer funktionierenden Datenbank
 * mit leeren Spalten — das ist der ganze erreichbare Anspruch.
 *
 * WAS BLEIBT, und warum:
 *  - `users.public_key` und `users.lightning_retired_at`: an ihnen haengt der Login und
 *    seine Abschaltung ({@see LnurlAuthController}). Der
 *    Lightning-LOGIN laeuft weiter; hier fallen nur die Kontaktfelder daneben.
 *  - `users.change` / `users.change_time`: die k1-Challenge desselben Logins.
 *  - `lecturers.lightning_address` / `lnurl` / `node_id` / `paynym`: ein ANDERER,
 *    oeffentlicher Vertrag ({@see LecturerResource} auf der offenen
 *    Route `/api/lecturers`). Gleiche Spaltennamen, andere Tabelle, andere Bedeutung.
 *    Diese Migration nennt `users` in jedem einzelnen Aufruf ausdruecklich.
 *
 * KORREKTUR ZUM PLAN: die vier Blind-Indizes sind KEINE Spalten auf `users`.
 * `spatie/laravel-ciphersweet` legt sie als Zeilen in der polymorphen Tabelle
 * `blind_indexes` ab (`indexable_type`/`indexable_id`/`name`/`value`) — nachgeprueft an
 * `UsesCipherSweet::updateBlindIndexes()` und am Schema. Ein Spalten-Drop haette sie
 * also nicht mitgenommen: sie waeren als suchbare Hashes zurueckgeblieben, ohne Spalte,
 * die sie je wieder erzeugt. Deshalb werden sie hier eigens geloescht.
 */
return new class extends Migration
{
    /** Die Spalten auf `users` — NICHT die gleichnamigen auf `lecturers`. */
    private const COLUMNS = ['lightning_address', 'lnurl', 'node_id', 'paynym', 'lnbits'];

    /** Die zugehoerigen Blind-Index-Namen. `public_key_index` und `email_index` bleiben. */
    private const BLIND_INDEXES = [
        'lightning_address_index',
        'lnurl_index',
        'node_id_index',
        'paynym_index',
    ];

    public function up(): void
    {
        $this->dropBlindIndexRows();

        $vorhandene = array_values(array_filter(
            self::COLUMNS,
            fn (string $column): bool => Schema::hasColumn('users', $column),
        ));

        if ($vorhandene === []) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($vorhandene): void {
            $table->dropColumn($vorhandene);
        });
    }

    /**
     * Die Blind-Indizes sind abgeleitete Daten: suchbare Hashes der Werte, die gerade
     * verschwinden. Sie stehen zu lassen hiesse, Hashes zurueckgelassener Identitaeten zu
     * behalten, die kein Pfad je wieder erzeugt oder aufraeumt. Anders als bei verwaisten
     * Medien gibt es hier nichts zu retten — deshalb geloescht statt gezaehlt, aber mit
     * einer Meldung, damit die Zahl im Deploy-Protokoll steht.
     */
    private function dropBlindIndexRows(): void
    {
        if (! Schema::hasTable('blind_indexes')) {
            return;
        }

        $deleted = DB::table('blind_indexes')
            ->where('indexable_type', User::class)
            ->whereIn('name', self::BLIND_INDEXES)
            ->delete();

        if ($deleted > 0) {
            echo "  Removed {$deleted} blind index rows for the retired Lightning fields.\n";
        }
    }

    /**
     * Stellt die Struktur her, damit ein Rollback in einer lauffaehigen Datenbank landet.
     * Die Typen entsprechen dem Stand vor dem Drop (2023_01_18 / 2023_02_03 / 2023_03_1x):
     * vier `text`-Spalten und ein `json`. Die Blind-Index-Zeilen kommen NICHT zurueck —
     * es gibt keine Werte mehr, aus denen sie sich bilden liessen.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'lightning_address')) {
                $table->text('lightning_address')->nullable();
            }
            if (! Schema::hasColumn('users', 'lnurl')) {
                $table->text('lnurl')->nullable();
            }
            if (! Schema::hasColumn('users', 'node_id')) {
                $table->text('node_id')->nullable();
            }
            if (! Schema::hasColumn('users', 'paynym')) {
                $table->text('paynym')->nullable();
            }
            if (! Schema::hasColumn('users', 'lnbits')) {
                $table->json('lnbits')->nullable();
            }
        });
    }
};
