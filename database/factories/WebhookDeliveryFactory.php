<?php

namespace Database\Factories;

use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    public function definition(): array
    {
        return [
            'subscription_id' => WebhookSubscription::factory(),
            'api_change_id' => null,
            'payload' => [
                'action' => 'created',
                'resource' => 'meetup',
                'id' => fake()->numberBetween(1, 10000),
                'sequence' => fake()->numberBetween(1, 10000),
                'occurred_at' => now()->toIso8601String(),
                'api_version' => '1',
                'data' => ['id' => 1],
                'links' => ['self' => null],
            ],
            'attempts' => 0,
            'last_response_code' => null,
            'delivered_at' => null,
            'failed_at' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'attempts' => 6,
            'last_response_code' => 500,
            'failed_at' => now(),
        ]);
    }
}
