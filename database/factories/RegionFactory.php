<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Region>
 */
class RegionFactory extends Factory
{
    protected $model = Region::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->state();

        return [
            'country_id' => Country::factory(),
            'code' => Str::lower($this->faker->unique()->lexify('??')),
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }

    /**
     * Der Slug folgt dem Namen, den der Aufrufer wirklich gesetzt hat.
     *
     * `definition()` leitet ihn aus dem Faker-Namen ab. Ueberschreibt ein Test nur den
     * NAMEN — was fuenf von ihnen tun, `['code' => 'nc', 'name' => 'North Carolina']` —,
     * blieb der Slug der des verworfenen Faker-Namens. Meist faellt das nicht auf; traf
     * `faker->state()` aber zufaellig „Indiana", kollidierte der Slug mit dem des
     * `indiana()`-States im selben Land und `regions.country_id/slug` schlug zu. Genau so
     * ist `ActiveRegionForGuardTest` im Vollauf vom 2026-08-25 einmal rot geworden:
     * ein Flake mit rund 1/50 Wahrscheinlichkeit, der aussieht wie ein Fehler im Code.
     *
     * Kein Test setzt `slug` selbst (geprueft ueber alle 30 Aufrufe), das Ableiten
     * uebergeht also keine Absicht. Der `indiana()`-State bleibt unberuehrt:
     * `Str::slug('Indiana')` ist derselbe Wert, den er ohnehin schreibt.
     *
     * Bleibt ein schmaler Rest: liefert Faker „Indiana" fuer eine Region OHNE
     * Namensueberschreibung, waehrend `indiana()` daneben steht, kollidieren beide immer
     * noch — `unique()` gilt pro Faker-Instanz und der State geht daran vorbei. Dieser
     * Fall ist nicht beobachtet und hier bewusst nicht mitbehandelt.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Region $region) {
            $region->slug = Str::slug($region->name);
        });
    }

    /**
     * Indiana, der Bundesstaat aus dem Auslöser-Issue.
     */
    public function indiana(): static
    {
        return $this->state(fn () => [
            'code' => 'in',
            'name' => 'Indiana',
            'slug' => 'indiana',
        ]);
    }
}
