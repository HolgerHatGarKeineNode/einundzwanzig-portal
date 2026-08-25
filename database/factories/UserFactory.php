<?php

namespace Database\Factories;

use App\Models\User;
use Database\Factories\Helpers\NostrHelper;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->firstName().' '.fake()->lastName();

        return [
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'reputation' => fake()->numberBetween(0, 1000),
            'current_language' => fake()->randomElement(['de', 'en']),
            'timezone' => 'Europe/Berlin',
            'is_lecturer' => fake()->boolean(20),
            'nostr' => fake()->boolean(70) ? NostrHelper::randomNpub() : null,
            'lightning_address' => fake()->boolean(40) ? NostrHelper::randomLightningAddress($name) : null,
            'lnurl' => null,
            'node_id' => null,
            'paynym' => null,
            'lnbits' => null,
            'public_key' => null,
            'change' => null,
            'change_time' => null,
            'profile_photo_path' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function lecturer(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_lecturer' => true,
        ]);
    }

    /**
     * Nutzer ohne Referenten-Abzeichen.
     *
     * `definition()` wuerfelt `fake()->boolean(20)`, ein Test darf sich darauf nicht
     * verlassen. Das Abzeichen gatet seit dem Abbau der `is_lecturer`-Pruefung keine
     * Policy mehr — was es noch gatet (die beiden MCP-Werkzeuge, die API-Ausgabe),
     * braucht diesen Zustand deterministisch.
     */
    public function notLecturer(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_lecturer' => false,
        ]);
    }

    public function withNostr(): static
    {
        return $this->state(fn (array $attributes) => [
            'nostr' => NostrHelper::randomNpub(),
            'lightning_address' => NostrHelper::randomLightningAddress($attributes['name'] ?? null),
        ]);
    }
}
