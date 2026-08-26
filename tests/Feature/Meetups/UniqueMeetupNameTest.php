<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\User;
use App\Rules\UniqueMeetupName;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
    $this->creator = User::factory()->create(['nostr' => null]);
    $this->bestehend = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'created_by' => $this->creator->id,
        'name' => 'Einundzwanzig Mannheim',
    ]);
});

function nameRuleFails(string $name, ?int $ignore = null): bool
{
    return Validator::make(['name' => $name], ['name' => [new UniqueMeetupName($ignore)]])->fails();
}

it('rejects a name that differs only in case', function () {
    // Genau das Mannheimer Duplikat: fuer PostgreSQL zwei Werte, fuer Menschen einer.
    expect(nameRuleFails('EINUNDZWANZIG MANNHEIM'))->toBeTrue();
    expect(nameRuleFails('einundzwanzig mannheim'))->toBeTrue();
});

it('rejects a name that differs only in whitespace', function () {
    // Vergleicht den NORMALISIERTEN Wert — sonst liesse die Regel durch, was
    // NormalizesText anschliessend in die Kollision trimmt.
    expect(nameRuleFails('  Einundzwanzig   Mannheim '))->toBeTrue();
});

it('accepts a genuinely different name', function () {
    expect(nameRuleFails('Einundzwanzig Heidelberg'))->toBeFalse();
});

it('lets a meetup keep its own name', function () {
    expect(nameRuleFails('Einundzwanzig Mannheim', $this->bestehend->id))->toBeFalse();
    expect(nameRuleFails('EINUNDZWANZIG Mannheim', $this->bestehend->id))->toBeFalse();
});

it('names the colliding meetup in the message', function () {
    $validator = Validator::make(['name' => 'EINUNDZWANZIG MANNHEIM'], ['name' => [new UniqueMeetupName]]);

    expect($validator->errors()->first('name'))->toContain('Einundzwanzig Mannheim');
});

it('blocks the case collision on the api path', function () {
    $token = $this->creator->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/meetup', ['name' => 'EINUNDZWANZIG MANNHEIM', 'city_id' => $this->city->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');

    expect(Meetup::query()->count())->toBe(1);
});

it('lets the api update a meetup without changing its name', function () {
    $token = $this->creator->createToken('test')->plainTextToken;

    // Der Regressionsfall der ignore-id: ohne sie pruefte das Meetup gegen sich
    // selbst und jedes Speichern ohne Namensaenderung schluege fehl.
    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/meetup/'.$this->bestehend->id, [
            'name' => 'Einundzwanzig Mannheim',
            'intro' => 'Neuer Text',
        ])
        ->assertSuccessful();

    expect($this->bestehend->fresh()->intro)->toBe('Neuer Text');
});

it('has the unique index on lower(name) in the schema', function () {
    // Positivkontrolle: erst beweisen, dass der Index ueberhaupt greift, sonst
    // sagt ein ausbleibender Fehler unten nichts.
    DB::table('meetups')->insert([
        'name' => 'Einundzwanzig Testort', 'slug' => 'einundzwanzig-testort',
        'city_id' => $this->city->id, 'created_by' => $this->creator->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => DB::table('meetups')->insert([
        'name' => 'EINUNDZWANZIG TESTORT', 'slug' => 'einundzwanzig-testort-2',
        'city_id' => $this->city->id, 'created_by' => $this->creator->id,
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('names the colliding rows instead of failing with a raw sql error', function () {
    $migration = require base_path('database/migrations/2026_08_26_134145_add_case_insensitive_unique_to_meetup_names.php');

    // Index fallen lassen, damit die Kollision ueberhaupt entstehen kann.
    $migration->down();
    DB::table('meetups')->insert([
        'name' => 'EINUNDZWANZIG MANNHEIM', 'slug' => 'einundzwanzig-mannheim-2',
        'city_id' => $this->city->id, 'created_by' => $this->creator->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Forge migriert beim Deploy. Ein roher SQL-Fehler wuerde das Deploy kippen,
    // ohne zu sagen, welche zwei Meetups gemeint sind.
    try {
        $migration->up();
        $this->fail('Die Migration haette abbrechen muessen.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())
            ->toContain('Einundzwanzig Mannheim')
            ->toContain('EINUNDZWANZIG MANNHEIM')
            ->toContain('meetups:cleanup-duplicates');
    }
});
