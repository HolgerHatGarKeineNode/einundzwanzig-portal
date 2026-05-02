<?php

namespace Database\Factories;

use App\Models\EmailCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailCampaign>
 */
class EmailCampaignFactory extends Factory
{
    protected $model = EmailCampaign::class;

    public function definition(): array
    {
        return [
            'name' => 'Bitcoin Newsletter '.fake()->unique()->numberBetween(1, 9999),
            'list_file_name' => 'list_'.fake()->unique()->slug(2).'.csv',
            'subject_prompt' => 'Schreibe einen ansprechenden Betreff über Bitcoin und Sound Money.',
            'text_prompt' => 'Verfasse einen freundlichen Newsletter über die neuesten Bitcoin-Entwicklungen.',
        ];
    }
}
