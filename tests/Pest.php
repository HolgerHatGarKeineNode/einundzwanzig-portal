<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        config()->set('permission.testing', true);
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
    })
    ->in('Feature', '../resources/views');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        config()->set('database.connections.sqlite.database', database_path('testing.sqlite'));
        config()->set('permission.testing', true);
    })
    ->in('Browser');

/*
|--------------------------------------------------------------------------
| Test Impact Analysis
|--------------------------------------------------------------------------
|
| TIA faehrt nach einer Aenderung nur die betroffenen Tests wirklich aus und
| repliziert den Rest aus dem Graphen unter ~/.pest/tia/. Der dafuer noetige
| Coverage-Treiber kommt aus tests/tia-coverage-driver.php — dieser Aufruf
| hier ist zugleich der Ausloeser, den der Hook erkennt ("->tia(").
|
| "locally()" heisst in Pest 5 NICHT "erkennt eine CI automatisch": es
| schaltet TIA ab, sobald "--ci" am Aufruf steht, und sonst nie
| (Pest\Plugins\Environment::name() liefert LOCAL, bis das Flag faellt).
| Weitere Modifier sind bewusst NICHT gesetzt — Begruendung im Plan unter
| Phasen-Status P2:
|
|   filtered()  wuerde nicht betroffene Tests ganz aus dem Lauf werfen statt
|               sie als "replayed" zu melden. Es spart keinen Bootstrap (der
|               TiaTestCaseFilter greift erst an der fertig eingesammelten
|               Suite), es ist bei jedem Aufruf mit Pfadangabe automatisch
|               aus, und ohne Treffer endet der Lauf mit "No affected tests
|               found" und RC=0, ohne einen einzigen Test. Bei Bedarf pro
|               Lauf per "--filtered" zuschaltbar.
|   watch()     ist hier wirkungslos: TIA filtert seine Kandidatenliste durch
|               "git check-ignore" (Tia\ChangedFiles::filterIgnored), und
|               public/build ist per .gitignore ausgeschlossen. Ein Pattern,
|               das nie feuert, waere Deko.
|   baselined() haengt an einer CI-Baseline und gehoert damit zu P6.
|
| WARNUNG: Ein gruener "--tia"-Lauf ist ohne "--fresh" kein Beleg. Ein roter
| Test wird beim naechsten unveraenderten Lauf als "replayed" gemeldet und
| faerbt den Lauf gruen (Pests eigene Replay-Logik, in P1 belegt).
|
*/

pest()->tia()->locally();

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function actingAsUser(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    test()->actingAs($user);

    return $user;
}

function defaultCountrySegment(): string
{
    return (string) config('app.domain_country', 'de');
}
