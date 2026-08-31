<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WebhookSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookSubscription>
 */
class WebhookSubscriptionFactory extends Factory
{
    protected $model = WebhookSubscription::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // A literal public IP, not a hostname: SsrfGuard::isPublicUrl() does a
            // live DNS lookup for a hostname, which would make every test relying
            // on this default depend on outbound DNS at test-run time.
            'url' => 'https://1.1.1.1/webhooks/'.fake()->unique()->uuid(),
            'secret' => bin2hex(random_bytes(32)),
            'resources' => ['meetup', 'meetup-event'],
            'approved_at' => now(),
            'active' => true,
            'consecutive_failures' => 0,
            'disabled_at' => null,
        ];
    }

    /**
     * Behind the approval gate, as a freshly self-service-created subscription is.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => ['approved_at' => null]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'consecutive_failures' => 10,
            'disabled_at' => now(),
        ]);
    }
}
