<?php

use App\Models\Concerns\NormalizesText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Schliesst die zweite Luecke im `unique` auf `meetups.name`.
 *
 * Die erste war Whitespace — 'Einundzwanzig Ulm ' stand jahrelang neben
 * 'Einundzwanzig Ulm', weil ein nachlaufendes Leerzeichen zwei verschiedene
 * Werte macht. Das faengt seit dem 26.08.2026 {@see NormalizesText}
 * beim Speichern ab.
 *
 * Die zweite ist die Gross-/Kleinschreibung: 'EINUNDZWANZIG Mannheim' und
 * 'Einundzwanzig Mannheim' sind fuer PostgreSQL zwei Werte, fuer jeden Menschen
 * einer. Genau so entstand das Mannheimer Duplikat. Trimmen allein haette es
 * nicht verhindert.
 *
 * Der Index liegt auf `lower(name)`, nicht auf `name`: die Schreibweise selbst
 * bleibt dem Meetup ueberlassen — 'EINUNDZWANZIG LEIPZIG ₿' ist gewollt und
 * soll so bleiben. Verboten ist nur, dass zwei Eintraege sich allein darin
 * unterscheiden.
 *
 * Das bestehende `unique` auf `name` bleibt daneben stehen. Es ist strenger
 * gefasst als noetig, aber es zu entfernen brauechte einen eigenen Grund.
 */
return new class extends Migration
{
    private const INDEX = 'meetups_lower_name_unique';

    public function up(): void
    {
        $this->assertNoCollisions();

        DB::statement(sprintf('CREATE UNIQUE INDEX %s ON meetups (LOWER(name))', self::INDEX));
    }

    public function down(): void
    {
        DB::statement(sprintf('DROP INDEX %s', self::INDEX));
    }

    /**
     * Vorher pruefen und mit Klartext abbrechen.
     *
     * Ohne diesen Schritt scheitert die Migration an einem SQL-Fehler, der die
     * kollidierenden Zeilen nicht nennt — und weil Forge beim Deploy migriert,
     * scheitert damit das Deploy. Wer dann in den Logs steht, soll lesen koennen,
     * welche zwei Meetups gemeint sind.
     */
    private function assertNoCollisions(): void
    {
        $kollisionen = DB::table('meetups')
            ->selectRaw('LOWER(name) as schluessel, COUNT(*) as anzahl')
            ->groupBy('schluessel')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('anzahl', 'schluessel');

        if ($kollisionen->isEmpty()) {
            return;
        }

        $details = $kollisionen->map(function (int $anzahl, string $schluessel): string {
            $namen = DB::table('meetups')
                ->whereRaw('LOWER(name) = ?', [$schluessel])
                ->pluck('name', 'id')
                ->map(fn (string $name, int $id): string => sprintf('#%d "%s"', $id, $name))
                ->implode(', ');

            return sprintf('  %dx %s', $anzahl, $namen);
        })->implode("\n");

        throw new RuntimeException(
            "Es gibt Meetup-Namen, die sich nur in der Gross-/Kleinschreibung unterscheiden.\n"
            ."Der Index kann erst danach angelegt werden:\n\n".$details."\n\n"
            .'Aufraeumen mit `php artisan meetups:cleanup-duplicates` oder von Hand, dann erneut migrieren.'
        );
    }
};
