<?php

use App\Http\Resources\LecturerResource;
use App\Models\Lecturer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Der Nachweis fuer den unumkehrbaren Schritt: die fuenf Lightning-Spalten auf `users`
 * sind fort, ihre Blind-Indizes ebenso — und `lecturers` ist unangetastet.
 *
 * Die zweite Haelfte ist keine Zugabe. `lecturers` traegt VIER GLEICHNAMIGE Spalten, die
 * ein oeffentlicher Vertrag sind ({@see LecturerResource} auf der
 * offenen Route `/api/lecturers`). Wer bei so einer Arbeit nach „Lightning-Feldern"
 * greppt statt nach `users.<spalte>`, maeht sie mit ab — das ist der wahrscheinlichste
 * Ausfuehrungsfehler dieser Phase, und deshalb steht er hier als Test.
 */
const RETIRED_USER_COLUMNS = ['lightning_address', 'lnurl', 'node_id', 'paynym', 'lnbits'];

const RETIRED_BLIND_INDEXES = ['lightning_address_index', 'lnurl_index', 'node_id_index', 'paynym_index'];

it('has dropped all five lightning columns from users', function () {
    foreach (RETIRED_USER_COLUMNS as $column) {
        expect(Schema::hasColumn('users', $column))->toBeFalse("users.{$column} steht noch");
    }
});

it('keeps the two user columns the lightning login hangs on', function () {
    expect(Schema::hasColumn('users', 'public_key'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'lightning_retired_at'))->toBeTrue();
});

it('keeps change and change_time, which carry the k1 challenge', function () {
    expect(Schema::hasColumn('users', 'change'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'change_time'))->toBeTrue();
});

it('leaves the four identically named lecturer columns completely alone', function () {
    foreach (['lightning_address', 'lnurl', 'node_id', 'paynym'] as $column) {
        expect(Schema::hasColumn('lecturers', $column))->toBeTrue("lecturers.{$column} wurde mitgerissen");
    }

    // Nicht nur vorhanden — beschreib- und auslesbar, ueber den echten Weg.
    $lecturer = Lecturer::factory()->create([
        'lightning_address' => 'satoshi@getalby.com',
        'lnurl' => 'LNURL1DP68GURN8GHJ7',
        'node_id' => '03864ef025fde8fb587d989186ce6a4a186895ee44a926bfc370e2c366597a3f8f',
        'paynym' => '+summerfrost1a',
    ]);

    expect($lecturer->fresh()->only(['lightning_address', 'lnurl', 'node_id', 'paynym']))
        ->toBe([
            'lightning_address' => 'satoshi@getalby.com',
            'lnurl' => 'LNURL1DP68GURN8GHJ7',
            'node_id' => '03864ef025fde8fb587d989186ce6a4a186895ee44a926bfc370e2c366597a3f8f',
            'paynym' => '+summerfrost1a',
        ]);
});

it('still delivers the lecturer lightning fields on the public route and in the resource', function () {
    $lecturer = Lecturer::factory()->create([
        'lightning_address' => 'satoshi@getalby.com',
        'lnurl' => 'LNURL1DP68GURN8GHJ7',
        'node_id' => '03864ef025fde8fb587d989186ce6a4a186895ee44a926bfc370e2c366597a3f8f',
        'paynym' => '+summerfrost1a',
    ]);

    // Die oeffentliche Detailroute antwortet ohne `data`-Huelle.
    $this->getJson('/api/lecturers/'.$lecturer->id)
        ->assertSuccessful()
        ->assertJsonPath('lightning_address', 'satoshi@getalby.com');

    // Und der Resource-Vertrag, der alle vier Felder verspricht.
    expect(LecturerResource::make($lecturer)->resolve())
        ->toMatchArray([
            'lightning_address' => 'satoshi@getalby.com',
            'lnurl' => 'LNURL1DP68GURN8GHJ7',
            'node_id' => '03864ef025fde8fb587d989186ce6a4a186895ee44a926bfc370e2c366597a3f8f',
            'paynym' => '+summerfrost1a',
        ]);
});

/**
 * Die Blind-Indizes sind KEINE Spalten (der Plan sagte das; `spatie/laravel-ciphersweet`
 * legt sie als Zeilen in `blind_indexes` ab). Ein reiner Spalten-Drop haette sie also
 * stehen lassen: suchbare Hashes ohne Spalte, die sie je wieder erzeugt.
 */
it('leaves no blind index rows behind for the retired fields', function () {
    User::factory()->count(3)->create();

    expect(DB::table('blind_indexes')->whereIn('name', RETIRED_BLIND_INDEXES)->count())->toBe(0);
});

it('keeps writing the two blind indexes the login needs', function () {
    $user = User::factory()->create(['public_key' => str_repeat('a', 66)]);

    $names = DB::table('blind_indexes')
        ->where('indexable_type', User::class)
        ->where('indexable_id', $user->id)
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    expect($names)->toBe(['email_index', 'public_key_index']);
});

it('finds a user through the public_key blind index, so the lightning login still works', function () {
    $publicKey = str_repeat('b', 66);
    $user = User::factory()->create(['public_key' => $publicKey]);

    expect(User::query()->whereBlind('public_key', 'public_key_index', $publicKey)->first()?->id)
        ->toBe($user->id);
});

it('knows nothing about the retired fields in configureCipherSweet any more', function () {
    $row = User::getCipherSweetEncryptedRow();

    $fields = array_values($row->listEncryptedFields());
    sort($fields);

    expect($fields)->toBe(['email', 'public_key']);

    foreach (RETIRED_USER_COLUMNS as $column) {
        expect($row->getBlindIndexObjectsForColumn($column))->toBe([], "Blind-Index fuer {$column} steht noch");
    }

    expect(array_keys($row->getBlindIndexObjectsForColumn('public_key')))->toBe(['public_key_index']);
});

it('does not offer the retired fields as fillable any more', function () {
    $fillable = (new User)->getFillable();

    foreach (RETIRED_USER_COLUMNS as $column) {
        expect($fillable)->not->toContain($column);
    }
});

/**
 * Rollback landet in einer LAUFFAEHIGEN Datenbank — mit leeren Spalten. Die Werte kommen
 * nicht zurueck; sie waren verschluesselt und sind mit der Spalte weg. Genau das ist der
 * Anspruch, den ein `down()` hier ueberhaupt einloesen kann, und er wird hier gemessen,
 * damit niemand ihn fuer ein Backup haelt.
 */
it('restores the column structure on rollback, but no rows', function () {
    $user = User::factory()->create();

    $file = 'migrations/2026_08_25_213000_drop_lightning_columns_from_users_table.php';
    $migration = require database_path($file);

    $migration->down();

    foreach (RETIRED_USER_COLUMNS as $column) {
        expect(Schema::hasColumn('users', $column))->toBeTrue("users.{$column} kam beim Rollback nicht zurueck");
        expect(DB::table('users')->where('id', $user->id)->value($column))->toBeNull();
    }

    $migration->up();

    foreach (RETIRED_USER_COLUMNS as $column) {
        expect(Schema::hasColumn('users', $column))->toBeFalse();
    }
});
