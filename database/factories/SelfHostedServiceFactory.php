<?php

namespace Database\Factories;

use App\Enums\SelfHostedServiceType;
use App\Models\SelfHostedService;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

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
            'created_by' => $this->faker->optional()->numberBetween(1,9),
            'name' => $name,
            'slug' => str($name)->slug(),
            'intro' => $this->faker->optional()->paragraph(),
            'url_clearnet' => $this->faker->optional()->url(),
            'url_onion' => $this->faker->optional()->url(),
            'url_i2p' => $this->faker->optional()->url(),
            'url_pkdns' => $this->faker->optional()->url(),
            'type' => $this->faker->randomElement(SelfHostedServiceType::cases())->value,
            'contact' => $this->faker->optional()->url(),
        ];
    }
}
