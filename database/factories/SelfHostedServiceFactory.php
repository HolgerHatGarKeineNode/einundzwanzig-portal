<?php

namespace Database\Factories;

use App\Enums\SelfHostedServiceType;
use App\Models\SelfHostedService;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SelfHostedService>
 */
class SelfHostedServiceFactory extends Factory
{
    protected $model = SelfHostedService::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'created_by' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1000, 99999),
            'intro' => $this->faker->paragraph(),
            'url_clearnet' => $this->faker->boolean(80) ? $this->faker->url() : null,
            'url_onion' => $this->faker->boolean(20) ? Str::random(56).'.onion' : null,
            'url_i2p' => null,
            'url_pkdns' => null,
            'type' => $this->faker->randomElement(SelfHostedServiceType::cases())->value,
            'contact' => $this->faker->boolean(60) ? $this->faker->email() : null,
            'ip' => $this->faker->boolean(50) ? $this->faker->ipv4() : null,
            'anon' => false,
        ];
    }

    public function anonymous(): static
    {
        return $this->state(fn () => [
            'anon' => true,
            'created_by' => null,
        ]);
    }
}
