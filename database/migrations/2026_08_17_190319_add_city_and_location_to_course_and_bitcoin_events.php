<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives course and bitcoin events their own city and free-text location, taken from the
 * venue they currently hang off.
 *
 * This has to happen before the venue is torn down, not after. Every one of the 191 events
 * in production points at a venue, and for two thirds of them the venue is the ONLY record
 * of where they happen: `meetup_events` has had a free-text `location` since 2022, these two
 * tables never did, because the venue relation stood in for it.
 *
 * Dropping the venue without this step would turn "Bürgerhaus Neumarkt Oberpfalz,
 * Fischergasse 1" into "Neumarkt" — and only 16 of 96 venues have an OpenStreetMap place to
 * fall back on. The remaining 80 carry addresses somebody typed in by hand.
 *
 * Nothing here is guesswork: all 96 venues have a city, so the city column is exact.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['course_events', 'bitcoin_events'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                if (! Schema::hasColumn($table, 'city_id')) {
                    $blueprint->foreignId('city_id')
                        ->nullable()
                        ->after('id')
                        ->constrained()
                        // A city is not deleted lightly, and an event that loses one is
                        // still an event — it keeps its location text either way.
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn($table, 'location')) {
                    // Same shape as meetup_events.location, so all three event types
                    // describe their whereabouts identically from here on.
                    $blueprint->string('location')->nullable()->after('venue_id');
                }
            });

            $this->copyFromVenues($table);
        }
    }

    /**
     * Carry name, street and city over from the venue into the event itself.
     */
    private function copyFromVenues(string $table): void
    {
        if (! Schema::hasTable('venues') || ! Schema::hasColumn($table, 'venue_id')) {
            return;
        }

        DB::table('venues')->orderBy('id')->chunkById(100, function ($venues) use ($table): void {
            foreach ($venues as $venue) {
                DB::table($table)
                    ->where('venue_id', $venue->id)
                    // Never overwrite something already filled in — this migration may run
                    // after someone has corrected an event by hand.
                    ->whereNull('location')
                    ->update([
                        'city_id' => DB::raw('COALESCE(city_id, '.(int) $venue->city_id.')'),
                        'location' => $this->composeLocation($venue),
                    ]);
            }
        });
    }

    /**
     * "Name, Street" — unless the street merely repeats the name, which happens whenever
     * the venue was never a real address to begin with ("Wunschort im Rhein-Neckar Kreis").
     */
    private function composeLocation(object $venue): ?string
    {
        $name = trim((string) ($venue->name ?? ''));
        $street = trim((string) ($venue->street ?? ''));

        $parts = $street === '' || $street === $name
            ? [$name]
            : [$name, $street];

        $composed = trim(implode(', ', array_filter($parts)));

        return $composed === '' ? null : mb_substr($composed, 0, 255);
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                if (Schema::hasColumn($table, 'city_id')) {
                    $blueprint->dropConstrainedForeignId('city_id');
                }

                if (Schema::hasColumn($table, 'location')) {
                    $blueprint->dropColumn('location');
                }
            });
        }
    }
};
