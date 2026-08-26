<?php

declare(strict_types=1);

namespace App\Console\Commands\Database;

use App\Models\Concerns\NormalizesText;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\TextNormalizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Raeumt den Bestand nach denselben zwei Regeln auf, die {@see NormalizesText}
 * kuenftig beim Speichern anwendet. Der Trait deckt Neuzugaenge ab, dieser Lauf
 * den Altbestand — die Regeln teilen sie sich, damit beide dieselbe Form erzeugen.
 *
 * Welche Felder betroffen sind, steht nicht hier, sondern an den Modellen
 * (`$normalizedLabels` / `$normalizedProse`). Der Lauf liest sie ab, damit ein
 * spaeter ergaenztes Feld nicht an zwei Stellen nachgetragen werden muss.
 *
 * KEINE Model-Events: geschrieben wird per Query Builder. Der ApiChangeObserver
 * wuerde sonst gut 540 Aenderungsmeldungen in den Resync-Feed schreiben (Issue #29),
 * und zwar fuer Leerzeichen an den Raendern — fuer jeden Konsumenten, der seine
 * Strings trimmt, ein Unterschied ohne Unterschied. Der Feed ist fuer echte
 * Aenderungen da; ihn mit dieser Charge zu fuellen, macht ihn fuer 30 Tage
 * schwerer lesbar, ohne irgendwo eine Korrektur auszuloesen.
 *
 * Dry-Run ist der Default.
 */
#[Signature('db:normalize-text {--force : Wirklich schreiben statt nur zu zeigen} {--model= : Nur dieses Model, z. B. Meetup}')]
#[Description('Trimmt Rand-Leerzeichen im Bestand; Bezeichnungen zusaetzlich innen (Dry-Run ohne --force)')]
class NormalizeTextFields extends Command
{
    /**
     * Werte, die getrimmt in ein bestehendes unique laufen. Sie bleiben stehen —
     * das ist ein Duplikat und gehoert entschieden, nicht ueberschrieben.
     *
     * @var list<string>
     */
    private array $conflicts = [];

    /**
     * Die Modelle, die {@see NormalizesText} verwenden. Explizit statt per
     * Verzeichnis-Scan: welcher Bestand angefasst wird, ist eine Entscheidung
     * und soll nicht davon abhaengen, wo eine Datei liegt.
     *
     * `City` fehlt hier bewusst: fuer Staedte gibt es den Weg schon — die Migration
     * `2026_08_25_001255_normalise_city_names_and_merge_duplicates` hat den Bestand
     * bereinigt, und `db:audit-cities` haelt ihn im Blick. Zwei Mechanismen fuer
     * dieselbe Sache waeren genau der Fehler, den diese Bereinigung aufraeumt.
     *
     * @var list<class-string<Model>>
     */
    private const MODELS = [
        Meetup::class,
        MeetupEvent::class,
        Lecturer::class,
        Course::class,
    ];

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $nur = $this->option('model');

        $gesamt = 0;
        $zeilen = [];

        foreach (self::MODELS as $klasse) {
            if ($nur !== null && class_basename($klasse) !== $nur) {
                continue;
            }

            /** @var Model $muster */
            $muster = new $klasse;
            $tabelle = $muster->getTable();

            foreach ($this->fieldsOf($muster) as $feld => $collapseInner) {
                if (! Schema::hasColumn($tabelle, $feld)) {
                    continue;
                }

                $treffer = $this->fix($tabelle, $muster->getKeyName(), $feld, $collapseInner, $force);

                if ($treffer > 0) {
                    $gesamt += $treffer;
                    $zeilen[] = [
                        class_basename($klasse),
                        $feld,
                        $collapseInner ? 'Bezeichnung' : 'Freitext',
                        $treffer,
                    ];
                }
            }
        }

        if ($zeilen === []) {
            $this->info('Nichts zu tun — alle Felder sind sauber.');

            return Command::SUCCESS;
        }

        $this->table(
            ['Model', 'Feld', 'Regel', $force ? 'korrigiert' : 'wuerde korrigieren'],
            $zeilen,
        );

        $this->line('');
        $this->info(sprintf(
            '%s %d Feldwert(e). Bei Freitexten nur die Raender: Umbrueche IM Text bleiben, eine Leerzeile davor oder danach faellt mit.',
            $force ? 'Korrigiert:' : '[DRY-RUN] Betroffen:',
            $gesamt,
        ));

        if (! $force) {
            $this->line('Mit --force ausfuehren.');
        }

        if ($this->conflicts !== []) {
            $this->line('');
            $this->warn(sprintf('%d Wert(e) blieben stehen, weil sie getrimmt mit einem bestehenden kollidieren:', count($this->conflicts)));
            foreach ($this->conflicts as $conflict) {
                $this->line('  - '.$conflict);
            }
            $this->line('Das sind Duplikate. Sie gehoeren entschieden, nicht ueberschrieben.');
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<string, bool> Feldname => innere Mehrfach-Leerzeichen zusammenziehen?
     */
    private function fieldsOf(Model $model): array
    {
        $felder = [];

        foreach ($this->readList($model, 'normalizedLabels') as $feld) {
            $felder[$feld] = true;
        }

        foreach ($this->readList($model, 'normalizedProse') as $feld) {
            $felder[$feld] = false;
        }

        return $felder;
    }

    /**
     * @return list<string>
     */
    private function readList(Model $model, string $property): array
    {
        if (! property_exists($model, $property)) {
            return [];
        }

        $werte = (new \ReflectionProperty($model, $property))->getValue($model);

        return is_array($werte) ? array_values(array_filter($werte, 'is_string')) : [];
    }

    /**
     * Nur die betroffenen Zeilen holen und einzeln korrigieren.
     *
     * Bewusst kein SQL-seitiges TRIM() ueber die ganze Tabelle: die Regel fuer
     * Bezeichnungen zieht auch innere Mehrfach-Leerzeichen zusammen, und das
     * laesst sich zwischen PostgreSQL (Produktion) und SQLite (Tests) nicht
     * gleich formulieren. Die betroffene Menge ist klein genug — gemessen am
     * 26.08.2026 rund 540 Werte ueber alle Felder.
     */
    private function fix(string $tabelle, string $key, string $feld, bool $collapseInner, bool $force): int
    {
        $treffer = 0;

        DB::table($tabelle)
            ->whereNotNull($feld)
            ->where($feld, '<>', '')
            ->select([$key, $feld])
            ->orderBy($key)
            ->chunk(500, function ($zeilen) use ($tabelle, $key, $feld, $collapseInner, $force, &$treffer): void {
                foreach ($zeilen as $zeile) {
                    $alt = (string) $zeile->{$feld};
                    $neu = $collapseInner ? TextNormalizer::label($alt) : TextNormalizer::prose($alt);

                    if ($neu === $alt) {
                        continue;
                    }

                    $treffer++;

                    if (! $force) {
                        continue;
                    }

                    /*
                     * Ein getrimmter Name kann in ein bestehendes unique laufen —
                     * genau so entstand 'Einundzwanzig Ulm ' neben 'Einundzwanzig Ulm'.
                     * Diese Zeile darf den Lauf nicht abbrechen: die anderen 539
                     * Korrekturen sind unabhaengig richtig. Der Konflikt wird gesammelt
                     * und am Ende benannt, statt still zu verschwinden.
                     */
                    try {
                        DB::table($tabelle)->where($key, $zeile->{$key})->update([$feld => $neu]);
                    } catch (UniqueConstraintViolationException) {
                        $treffer--;
                        $this->conflicts[] = sprintf('%s #%s.%s: "%s" kollidiert getrimmt mit einem bestehenden Wert', $tabelle, $zeile->{$key}, $feld, $alt);
                    }
                }
            });

        return $treffer;
    }
}
