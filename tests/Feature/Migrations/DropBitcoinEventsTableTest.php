<?php

use App\Models\City;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Der Nachweis fuer P7: `bitcoin_events` ist fort — samt seiner Medien.
 *
 * Die zweite Haelfte ist die eigentliche Arbeit. `media` ist polymorph OHNE
 * Fremdschluessel: ein `DROP TABLE` laesst 99 Zeilen und ihre Dateien stehen, und weil
 * die Klasse hinter `model_type` dann nicht mehr existiert, kann sie niemand mehr laden,
 * geschweige denn zuordnen. Ein Test, der nur `Schema::hasTable()` prueft, haette den
 * teureren Teil des Auftrags nicht angefasst.
 */
const BITCOIN_EVENT_MODEL_TYPE = 'App\Models\BitcoinEvent';

function dropBitcoinEventsMigration(): object
{
    return require database_path('migrations/2026_08_26_090000_drop_bitcoin_events_table.php');
}

/**
 * Legt eine Medienzeile samt Datei an, so wie die Media Library sie ablegen wuerde:
 * Verzeichnis = id, darin Original und `conversions/`.
 */
function seedBitcoinEventMedia(int $id, string $modelType = BITCOIN_EVENT_MODEL_TYPE): void
{
    DB::table('media')->insert([
        'id' => $id,
        'model_type' => $modelType,
        'model_id' => $id,
        'uuid' => (string) Str::uuid(),
        'collection_name' => 'logo',
        'name' => 'logo',
        'file_name' => 'logo.png',
        'mime_type' => 'image/png',
        'disk' => 'public',
        'conversions_disk' => 'public',
        'size' => 1234,
        'manipulations' => '[]',
        'custom_properties' => '[]',
        'generated_conversions' => '[]',
        'responsive_images' => '[]',
        'order_column' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Storage::disk('public')->put("{$id}/logo.png", 'binaerer-inhalt');
    Storage::disk('public')->put("{$id}/conversions/logo-thumb.png", 'binaerer-inhalt');
}

it('has dropped the bitcoin_events table', function () {
    expect(Schema::hasTable('bitcoin_events'))->toBeFalse();
});

it('no longer ships a BitcoinEvent model or factory', function () {
    expect(class_exists('App\Models\BitcoinEvent'))->toBeFalse()
        ->and(class_exists('Database\Factories\BitcoinEventFactory'))->toBeFalse();
});

it('has no bitcoinEvents relation on City any more', function () {
    expect(method_exists(City::class, 'bitcoinEvents'))->toBeFalse();
});

it('removes the media rows and their files, and says how many', function () {
    Storage::fake('public');

    seedBitcoinEventMedia(9001);
    seedBitcoinEventMedia(9002);

    expect(Storage::disk('public')->exists('9001/logo.png'))->toBeTrue()
        ->and(Storage::disk('public')->exists('9002/conversions/logo-thumb.png'))->toBeTrue();

    ob_start();
    dropBitcoinEventsMigration()->up();
    $ausgabe = ob_get_clean();

    expect(DB::table('media')->where('model_type', BITCOIN_EVENT_MODEL_TYPE)->count())->toBe(0)
        ->and(Storage::disk('public')->exists('9001/logo.png'))->toBeFalse()
        ->and(Storage::disk('public')->exists('9001/conversions/logo-thumb.png'))->toBeFalse()
        ->and(Storage::disk('public')->exists('9002/logo.png'))->toBeFalse();

    // Die Zahl muss im Protokoll stehen, nicht nur im Ergebnis.
    expect($ausgabe)->toContain('2 media-Zeilen geloescht');
});

/**
 * Die Gegenprobe, ohne die der Test oben auch bei einem `DELETE FROM media` gruen waere.
 */
it('does not touch media belonging to anything else', function () {
    Storage::fake('public');

    seedBitcoinEventMedia(9003, 'App\Models\Course');

    ob_start();
    dropBitcoinEventsMigration()->up();
    ob_get_clean();

    expect(DB::table('media')->where('id', 9003)->count())->toBe(1)
        ->and(Storage::disk('public')->exists('9003/logo.png'))->toBeTrue();
});

/**
 * Rollback landet in einer LAUFFAEHIGEN Datenbank — mit leerer Tabelle. Die 86 Zeilen und
 * ihre Logos kommen nicht zurueck; das ist der Punkt der Aenderung, kein Backup.
 */
it('restores the table structure on rollback, but no rows', function () {
    $migration = dropBitcoinEventsMigration();

    $migration->down();

    expect(Schema::hasTable('bitcoin_events'))->toBeTrue()
        ->and(DB::table('bitcoin_events')->count())->toBe(0);

    foreach (['id', 'city_id', 'from', 'to', 'title', 'description', 'link', 'created_by',
        'show_worldwide', 'nostr_status', 'osm_type', 'osm_id', 'osm_name', 'osm_address',
        'osm_lat', 'osm_lon', 'location', 'created_at', 'updated_at'] as $column) {
        expect(Schema::hasColumn('bitcoin_events', $column))->toBeTrue("bitcoin_events.{$column} fehlt nach dem Rollback");
    }

    ob_start();
    $migration->up();
    ob_get_clean();

    expect(Schema::hasTable('bitcoin_events'))->toBeFalse();
});
