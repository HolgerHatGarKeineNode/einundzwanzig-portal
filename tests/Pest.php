<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Host Chromium for Pest Browser tests
|--------------------------------------------------------------------------
|
| phpunit.xml used to set PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH, but that env
| var does not exist anywhere in pestphp/pest-plugin-browser's bundled
| playwright-core (grepped coreBundle.js — zero references). It silently did
| nothing, and Playwright fell back to its own pinned "Chrome for Testing"
| build in ~/.cache/ms-playwright, downloaded on first run — exactly what
| the project rule ("Browser-Tests nutzen IMMER das Host-Chromium") forbids.
|
| Playwright resolves an executable by joining PLAYWRIGHT_BROWSERS_PATH with
| "{name}-{revision}/<platform-relative-path>" (see readDescriptors() /
| EXECUTABLE_PATHS in node_modules/playwright-core/lib/coreBundle.js) and
| only checks the path exists — no checksum. So we point
| PLAYWRIGHT_BROWSERS_PATH at a repo-local, gitignored directory and symlink
| both the "chromium" and "chromium-headless-shell" registry entries (Pest's
| default headless:true launch resolves the *headless-shell* variant, not
| plain chromium) to the actual host binary. The env vars are written to
| $_SERVER/$_ENV as well as via putenv() — Symfony\Process builds its default
| environment from $_SERVER/$_ENV, so putenv() alone never reaches the Node
| process (measured: userAgent stayed on the Playwright build). This runs once,
| before the first browser test boots the server.
|
| The revision comes from playwright-core's own browsers.json, not a
| hardcoded constant — it changes on Playwright upgrades.
*/
(function (): void {
    $browsersJsonPath = __DIR__.'/../node_modules/playwright-core/browsers.json';

    // Jeder Ausstieg meldet sich. Ohne diese Zeilen fällt die Verdrahtung still aus und
    // Playwright nimmt wieder seinen eigenen Build — genau der lautlose Fehlschlag, der
    // PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH wirkungslos in phpunit.xml stehen ließ.
    $ohneHostBrowser = function (string $grund): void {
        fwrite(STDERR, "\n[Pest] Host-Chromium NICHT verdrahtet: {$grund}. Browser-Tests laufen damit nicht gegen den System-Browser.\n");
    };

    if (! is_file($browsersJsonPath)) {
        $ohneHostBrowser('node_modules/playwright-core/browsers.json fehlt (npm install gelaufen?)');

        return;
    }

    $hostChromium = trim((string) shell_exec('command -v chromium || command -v chromium-browser || command -v google-chrome-stable || command -v google-chrome 2>/dev/null'));

    if ($hostChromium === '' || ! is_executable($hostChromium)) {
        $ohneHostBrowser('kein ausführbares System-Chromium gefunden');

        return;
    }

    $browsers = json_decode(file_get_contents($browsersJsonPath), true)['browsers'] ?? [];
    $revisions = [];
    foreach ($browsers as $browser) {
        if (in_array($browser['name'], ['chromium', 'chromium-headless-shell'], true)) {
            $revisions[$browser['name']] = $browser['revision'];
        }
    }

    if (! isset($revisions['chromium'], $revisions['chromium-headless-shell'])) {
        $ohneHostBrowser('browsers.json kennt chromium/chromium-headless-shell nicht mehr unter diesen Namen');

        return;
    }

    $registryDir = __DIR__.'/../.pest-chromium';

    $entries = [
        'chromium-'.$revisions['chromium'] => ['chrome-linux64', 'chrome'],
        'chromium_headless_shell-'.$revisions['chromium-headless-shell'] => ['chrome-headless-shell-linux64', 'chrome-headless-shell'],
    ];

    foreach ($entries as $dirName => $relativePath) {
        $targetDir = $registryDir.'/'.$dirName.'/'.$relativePath[0];
        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $executablePath = $targetDir.'/'.$relativePath[1];
        if (is_link($executablePath) || file_exists($executablePath)) {
            @unlink($executablePath);
        }
        symlink($hostChromium, $executablePath);

        $marker = $registryDir.'/'.$dirName.'/INSTALLATION_COMPLETE';
        if (! is_file($marker)) {
            touch($marker);
        }
    }

    // putenv() ALLEIN genügt nicht: Symfony\Process baut sein Default-Environment aus
    // $_SERVER/$_ENV, nicht aus getenv(). Ohne die beiden folgenden Zeilen legt der Block
    // die Symlinks zwar an, der Playwright-Node-Prozess sieht die Variable aber nie —
    // gemessen: navigator.userAgent meldete weiter 149 (Playwright-Build) statt 151 (Host).
    putenv('PLAYWRIGHT_BROWSERS_PATH='.$registryDir);
    putenv('PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1');
    $_ENV['PLAYWRIGHT_BROWSERS_PATH'] = $_SERVER['PLAYWRIGHT_BROWSERS_PATH'] = $registryDir;
    $_ENV['PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD'] = $_SERVER['PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD'] = '1';
})();

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
| OFFENER BEFUND — der naechste rote Lauf gehoert vollstaendig protokolliert.
| Am 2026-08-24 meldete auf Commit 478525c EIN "composer test"-Lauf
| "1 failed, 1144 passed". Der Name des roten Tests ist verloren: die Ausgabe
| lief durch "tail -6" — genau die Zeilen, die ihn genannt haetten. Fuenf
| weitere volle Laeufe auf demselben und spaeteren Staenden waren gruen, drei
| davon vom test-engineer; das widerlegt einen seltenen Flake nicht.
|
| Zwei ungepruefte Verdachtsrichtungen, beide mangels Reproduktion offen: der
| Unterschied "--fresh" plus volle Reihenfolge gegenueber einem gefilterten
| Lauf, und die Browser-/Playwright-Tests als klassische Flake-Kandidaten.
|
| WER HIER LANDET, WEIL SEIN LAUF ROT IST: leite die Ausgabe NICHT durch
| tail/head/grep, sondern vollstaendig in eine Datei, halte den Testnamen fest
| und fahre danach den gezielten Bisect (wiederholte Laeufe nur der
| Browser-Suite oder mit fixierter Reihenfolge). Erst dieser Name macht den
| Befund untersuchbar — ohne ihn ist die Jagd wieder ohne Ziel.
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
