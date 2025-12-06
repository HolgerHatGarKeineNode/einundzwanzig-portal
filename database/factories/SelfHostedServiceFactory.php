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
            'created_by' => User::factory(),
            'name' => $name,
            'slug' => str($name)->slug(),
            'intro' => $this->faker->optional()->paragraph(),
            'url_clearnet' => $this->faker->optional()->url(),
            'url_onion' => null,
            'url_i2p' => null,
            'url_pkdns' => null,
            'type' => $this->faker->randomElement(SelfHostedServiceType::cases())->value,
            'contact_url' => $this->faker->optional()->url(),
        ];
    }
}
