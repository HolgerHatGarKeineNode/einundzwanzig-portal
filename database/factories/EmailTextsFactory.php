<?php

namespace Database\Factories;

use App\Models\EmailCampaign;
use App\Models\EmailTexts;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTexts>
 */
class EmailTextsFactory extends Factory
{
    protected $model = EmailTexts::class;

    public function definition(): array
    {
        return [
            'email_campaign_id' => EmailCampaign::factory(),
            'sender_md5' => md5(fake()->unique()->safeEmail()),
            'subject' => fake()->sentence(),
            'text' => fake()->paragraphs(3, true),
        ];
    }
}
