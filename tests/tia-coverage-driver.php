<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Coverage-Treiber fuer Pest (TIA / --coverage)
|--------------------------------------------------------------------------
|
| Pests Test Impact Analysis (--tia) braucht Line-Coverage. Auf dieser
| Maschine sind pcov UND xdebug installiert, aber beide global
| abgeschaltet (/etc/php/conf.d/pcov.ini: "pcov.enabled = 0",
| /etc/php/conf.d/xdebug.ini: "xdebug.mode=off") — und /etc ist ohne
| sudo nicht beschreibbar.
|
| Beide Schalter sind PHP_INI_SYSTEM: ini_set('pcov.enabled', '1')
| liefert false, und xdebug kennt keine Laufzeit-Umschaltung
| (xdebug_set_mode() existiert nicht). Ein Coverage-Treiber laesst sich
| deshalb NUR beim Start des PHP-Prozesses aktivieren. PHP-CLI liest
| auch keine php.ini aus dem Arbeitsverzeichnis (nur /etc/php/php.ini
| plus /etc/php/conf.d) — es gibt also keine Datei im Repo, die PHP von
| sich aus einliest.
|
| Uebrig bleibt genau ein Weg, der ohne sudo und ohne Extra-Flag am
| Aufruf funktioniert: den Pest-Prozess einmal mit gesetztem Treiber neu
| starten. Genau das macht diese Datei. Sie wird ueber
| composer.json -> autoload-dev.files geladen, also aus
| vendor/autoload.php heraus und damit noch bevor Pests Kernel bootet.
|
| Pest selbst benutzt dasselbe Muster (Pest\Restarters\PcovRestarter
| startet fuer TIA mit "-d pcov.directory=<root>" neu) — dieser Hook
| ergaenzt nur das, was Pest nicht setzen kann: pcov.enabled.
|
| Wichtige Randbedingung, die hier mit erschlagen wird: Pests
| PcovRestarter baut sein Restart-Kommando aus PHP_BINARY + argv und
| uebernimmt KEINE "-d"-Flags des Elternprozesses. Ein von Hand
| gesetztes "php -d pcov.enabled=1 vendor/bin/pest --tia" geht dadurch
| still verloren; Umgebungsvariablen ueberleben den Neustart dagegen.
| Deshalb wird pcov.enabled hier ueber PHP_INI_SCAN_DIR gesetzt und
| nicht ueber "-d".
|
| Kosten: Der Neustart kostet einen zusaetzlichen PHP-Prozessstart
| (gemessen ~0,21 s, weil Composer-Autoload und Laravel-Bootstrap zweimal
| laufen). Deshalb wird er NUR ausgeloest, wenn dieser Lauf ueberhaupt
| Coverage braucht — sonst kostet dieser Hook nur ein paar
| String-Vergleiche.
|
| Warum pcov und nicht Xdebug — gemessen auf
| tests/Feature/SeoSiteNameTest.php, je 7 Laeufe, Median (Referenz: ohne
| Treiber, ohne --tia = 0,766 s):
|
|   pcov, ohne --tia .......... 0,797 s (1,04x)  -> im Leerlauf gratis
|   xdebug coverage, ohne --tia 0,939 s (1,23x)
|   pcov + --tia --fresh ...... 0,987 s (1,29x)
|   xdebug + --tia --fresh .... 2,112 s (2,76x)
|
| Gegenprobe auf tests/Feature/Meetups/ (60 Tests, Referenz 51,4 s):
| pcov + TIA 55,8 s (1,09x), xdebug + TIA 67,8 s (1,32x). TIA braucht nur
| Line-Coverage (Tia\Recorder::endTest ruft \pcov\collect(\pcov\inclusive)),
| also nichts, was pcov nicht kann.
|
*/

(static function (): void {
    /**
     * Liefert den PHP-Code einer Datei ohne Kommentare.
     *
     * Die Konfigurationserkennung unten sucht in tests/Pest.php nach den
     * Aufrufen "->tia(" und "->locally(". Ohne diesen Schritt wuerde jede
     * Erwaehnung im Fliesstext als Konfiguration zaehlen — auch eine
     * AUSKOMMENTIERTE Konfigurationszeile. Das kostet nicht nur den
     * Neustart (~0,21 s) fuer nichts; ein Kommentar, der "->locally("
     * erwaehnt, waehrend die Konfiguration in Wahrheit always() benutzt,
     * wuerde den Neustart unter "--ci" faelschlich unterdruecken und TIA
     * ohne Treiber laufen lassen. Pest normalisiert seine eigenen
     * Content-Hashes aus demselben Grund ueber token_get_all
     * (Tia\ContentHash::hashPhpContent).
     *
     * Faellt das Tokenisieren aus, wird der Rohtext zurueckgegeben. Das ist
     * die sichere Richtung nur fuer die "->tia("-Erkennung (lieber einmal zu
     * viel neu starten als TIA ohne Treiber) — fuer "->locally(" waere der
     * Rohtext gerade die UNSICHERE Richtung, siehe oben. Vertretbar, weil der
     * Zweig praktisch unerreichbar ist: token_get_all() liefert nur bei einer
     * komplett leeren Zeichenkette ein leeres Array (Syntaxfehler und fehlendes
     * PHP-Tag tokenisieren fehlertolerant weiter), und eine leere Datei
     * enthaelt kein "->tia(" — die Funktion steigt dann vorher aus.
     */
    $codeWithoutComments = static function (string $raw): string {
        $tokens = @token_get_all($raw);

        if ($tokens === []) {
            return $raw;
        }

        $code = '';

        foreach ($tokens as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }

                $code .= $token[1];

                continue;
            }

            $code .= $token;
        }

        return $code;
    };

    /**
     * Braucht dieser Lauf ueberhaupt Line-Coverage?
     *
     * Drei Faelle, in denen Pest einen Treiber verlangt:
     *  1. "--tia" bzw. PEST_TIA=1 am Aufruf (Pest\Plugins\Tia::isEnabledForRun)
     *  2. irgendein "--coverage..."-Report
     *  3. TIA ist in tests/Pest.php dauerhaft konfiguriert
     *     (z. B. pest()->tia()->locally()) — das sieht man am Aufruf NICHT,
     *     und tests/Pest.php wird erst nach diesem Hook geladen. Deshalb
     *     wird die Datei hier vorab auf den Konfigurationsaufruf geprueft.
     *     Faellt die Erkennung aus, laeuft der Test trotzdem — Pest meldet
     *     dann von sich aus "TIA skipped as it needs ext-pcov or Xdebug".
     *
     * @param  array<int, string>  $arguments
     */
    $coverageIsNeeded = static function (string $root, array $arguments) use ($codeWithoutComments): bool {
        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--coverage')) {
                return true;
            }
        }

        /*
         * Ab hier geht es nur noch um TIA. "--no-tia" ist Pests eigenes Flag
         * (Pest\Plugins\Tia::NO_OPTION) und schaltet ausschliesslich TIA ab —
         * NICHT den Treiber, den ein "--coverage"-Report braucht. Genau
         * "--coverage --no-tia" ist nach P2 der normale Weg zu einem
         * ungefilterten Coverage-Report.
         */
        foreach ($arguments as $argument) {
            if ($argument === '--no-tia') {
                return false;
            }
        }

        foreach ($arguments as $argument) {
            if ($argument === '--tia') {
                return true;
            }
        }

        if (filter_var((string) getenv('PEST_TIA'), FILTER_VALIDATE_BOOL)) {
            return true;
        }

        $configuration = $root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Pest.php';

        if (! is_file($configuration)) {
            return false;
        }

        $contents = @file_get_contents($configuration);

        if (! is_string($contents)) {
            return false;
        }

        $contents = $codeWithoutComments($contents);

        if (! str_contains($contents, '->tia(')) {
            return false;
        }

        /*
         * Dieselbe Rangfolge wie Pest\Plugins\Tia::isEnabledForRun(): ein
         * ausdrueckliches "--tia"/PEST_TIA schlaegt "--ci" (oben schon
         * behandelt), aber eine dauerhafte Konfiguration mit locally() wird
         * von "--ci" abgeschaltet. Ohne diese Zeile wuerde der Hook fuer
         * jeden "--ci"-Lauf umsonst neu starten (~0,21 s), obwohl TIA gar
         * nicht laeuft. Bei always() bleibt der Neustart richtig.
         */
        if (str_contains($contents, '->locally(') && in_array('--ci', $arguments, true)) {
            return false;
        }

        return true;
    };

    if (PHP_SAPI !== 'cli') {
        return;
    }

    if (getenv('PEST_COVERAGE_DRIVER_BOOTSTRAPPED') === '1') {
        return;
    }

    if (! extension_loaded('pcov')) {
        return;
    }

    $arguments = $_SERVER['argv'] ?? [];

    if ($arguments === [] || ! is_string($arguments[0])) {
        return;
    }

    if (basename($arguments[0]) !== 'pest') {
        return;
    }

    if (filter_var((string) ini_get('pcov.enabled'), FILTER_VALIDATE_BOOL)) {
        return;
    }

    $root = dirname(__DIR__);

    if (! $coverageIsNeeded($root, $arguments)) {
        return;
    }

    $scanDirectory = $root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'php.d';

    /*
     * Dieses Verzeichnis wird gleich zur PHP-Konfigurationsquelle fuer den
     * gesamten Kindprozessbaum. Eine zweite .ini darin koennte per
     * auto_prepend_file beliebigen Code vor jedem Skript ausfuehren — und
     * eine .ini liest sich im Review wie harmlose Konfiguration. Deshalb
     * wird genau eine erwartete Datei zugelassen und sonst abgebrochen.
     * PHP liest aus einem Scan-Verzeichnis ausschliesslich "*.ini".
     */
    $expected = [$scanDirectory.DIRECTORY_SEPARATOR.'pcov.ini'];
    $present = glob($scanDirectory.DIRECTORY_SEPARATOR.'*.ini');

    if ($present !== $expected) {
        fwrite(STDERR, sprintf(
            "[tia-coverage-driver] %s enthaelt nicht genau eine pcov.ini — Coverage-Treiber NICHT aktiviert.\n",
            $scanDirectory,
        ));

        return;
    }

    $environment = getenv();

    /*
     * PHP_INI_SCAN_DIR ist eine doppelpunktgetrennte Liste, in der ein
     * LEERES Element das eingebaute Default-Verzeichnis meint (gemessen:
     * unset -> 21 Dateien, ":" -> 42, "/tmp:" -> 21, "" -> 0). Ein rtrim
     * auf ":" wuerde also genau das Default-Verzeichnis wegwerfen. Die
     * bestehende Liste wird deshalb unveraendert uebernommen und nur
     * ergaenzt.
     */
    $existingScanDirectory = $environment['PHP_INI_SCAN_DIR'] ?? null;

    $environment['PHP_INI_SCAN_DIR'] = match (true) {
        // Nicht gesetzt: Default-Verzeichnis (leeres Element) plus unseres.
        ! is_string($existingScanDirectory) => ':'.$scanDirectory,
        // Ausdruecklich leer heisst "gar nichts scannen" — das bleibt so,
        // ergaenzt um unseres.
        $existingScanDirectory === '' => $scanDirectory,
        // Sonst: Liste 1:1 erhalten, inklusive leerer Elemente.
        default => $existingScanDirectory.':'.$scanDirectory,
    };

    $environment['PEST_COVERAGE_DRIVER_BOOTSTRAPPED'] = '1';

    $process = @proc_open(
        [PHP_BINARY, '-d', 'pcov.directory='.$root, ...array_values($arguments)],
        [STDIN, STDOUT, STDERR],
        $pipes,
        null,
        $environment,
    );

    if (! is_resource($process)) {
        return;
    }

    $exitCode = proc_close($process);

    exit($exitCode === -1 ? 1 : $exitCode);
})();
